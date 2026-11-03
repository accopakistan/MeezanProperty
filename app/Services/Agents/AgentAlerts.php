<?php

namespace App\Services\Agents;

use App\Models\AiApproval;
use App\Models\AiTask;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AgentNeedsAttention;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tells administrators when the workforce needs a person. An alert problem
 * never affects the task itself; budget alerts are sent at most once a day.
 */
class AgentAlerts
{
    public static function approvalWaiting(AiApproval $approval, string $agentName): void
    {
        static::send('AI approval waiting', "{$agentName}: ".Str::limit($approval->description, 140), route('admin.ai.approvals.index'));
    }

    public static function ticketOpened(SupportTicket $ticket): void
    {
        static::send('Support ticket from the AI assistant', $ticket->reference.': '.Str::limit($ticket->subject, 120), route('admin.ai.work'));
    }

    public static function taskFailed(AiTask $task): void
    {
        static::send('AI task failed', ($task->agent?->name ?? 'Agent').': '.Str::limit($task->title, 120), route('admin.ai.tasks.show', $task));
    }

    public static function budgetReached(string $why): void
    {
        if (Cache::add('ai-budget-alert-'.md5($why).'-'.now()->toDateString(), true, now()->addDay())) {
            static::send('AI budget reached', $why.' Reports continue from data only.', route('admin.ai.usage'));
        }
    }

    public static function providerProblem(string $label, string $why): void
    {
        if (Cache::add('ai-provider-alert-'.md5($label.$why).'-'.now()->toDateString(), true, now()->addDay())) {
            static::send("{$label} needs attention", $why, route('admin.ai.providers'));
        }
    }

    protected static function send(string $title, string $body, string $url): void
    {
        try {
            Notification::send(User::activeAdmins(), new AgentNeedsAttention($title, OutputGuard::text($body), $url));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
