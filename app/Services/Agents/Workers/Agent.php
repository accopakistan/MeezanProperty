<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/**
 * Base for the 16 agents. An agent declares its intents (workflow name =>
 * trigger words, English / Roman Urdu / Urdu) and implements each workflow
 * with authorised tools only. Agents never touch models directly.
 */
abstract class Agent
{
    /** @return array<string, list<string>> */
    abstract public static function intents(): array;

    abstract public function handle(AgentContext $ctx, string $intent): Report;

    public static function intentFor(string $request): string
    {
        $text = mb_strtolower($request);
        $best = array_key_first(static::intents());
        $bestScore = 0;
        foreach (static::intents() as $intent => $words) {
            $score = collect($words)->sum(fn ($w) => str_contains($text, mb_strtolower($w)) ? 1 + substr_count($w, ' ') : 0);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$intent, $score];
            }
        }

        return $best;
    }

    /** How well a request matches this agent (used by the router). */
    public static function score(string $request): int
    {
        $text = mb_strtolower($request);

        return (int) collect(static::intents())->flatten()->sum(fn ($w) => str_contains($text, mb_strtolower($w)) ? 1 + substr_count($w, ' ') : 0);
    }

    /** The customer wrote in Urdu or Roman Urdu. Replies then use Roman Urdu. */
    protected function urdu(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text) || (bool) preg_match('/\b(kaise|kese|kya|hai|hain|karna|karein|mujhe|chahiye|kahan|kyun|nahi|masla|paise|banna)\b/i', $text);
    }

    /** A customer answer from an approved public article, or the fallback. */
    protected function publicAnswer(AgentContext $ctx, Report $r, string $query, string $fallback): void
    {
        $kb = $this->need($ctx, $r, 'knowledge.search', ['query' => $query, 'limit' => 1]);
        $doc = $kb['documents'][0] ?? null;
        $r->finding($doc && $doc['score'] >= 2 ? trim(preg_replace('/^Roman Urdu:.*$/mi', '', $doc['excerpt'])) : $fallback);
    }

    /**
     * Pass a customer's problem to staff. The agent looks nothing up and decides
     * nothing: it opens a ticket and says so.
     */
    protected function ticketForCustomer(AgentContext $ctx, Report $r, string $category, string $priority, string $subject): ?string
    {
        $t = $ctx->tool('support.create_ticket', [
            'subject' => mb_substr($subject, 0, 200), 'summary' => mb_substr($ctx->task->request, 0, 2000), 'category' => $category, 'priority' => $priority,
        ]);
        $r->source('support.create_ticket');

        return $t['ok'] ? $t['data']['ticket'] : null;
    }

    /** City named in the request, if any. */
    protected function city(string $request): ?string
    {
        foreach (config('meezan.cities') as $city) {
            if (str_contains(mb_strtolower($request), mb_strtolower($city))) {
                return $city;
            }
        }

        return null;
    }

    protected function days(string $request, int $default = 30): int
    {
        $t = mb_strtolower($request);

        return match (true) {
            str_contains($t, 'today') || str_contains($t, 'daily') => 1,
            str_contains($t, 'week') => 7,
            str_contains($t, 'quarter') => 90,
            str_contains($t, 'year') => 365,
            default => $default,
        };
    }

    /**
     * Ask for a human decision on an approval-required action, using the
     * structured input given with the task. Nothing changes until approved.
     */
    protected function propose(AgentContext $ctx, string $tool, string $title): Report
    {
        $r = new Report($title);
        $a = $ctx->tool($tool, $ctx->task->input ?? []);
        $r->source($tool);
        $a['ok']
            ? $r->finding('Approval requested (#'.$a['data']['approval_id'].'). Nothing has been changed; an administrator decides in the Approval Center.')
            : $r->missing('Not requested: '.$a['error']);

        return $r;
    }

    /** Record a failed tool call as missing data on the report. */
    protected function need(AgentContext $ctx, Report $report, string $tool, array $input = []): mixed
    {
        $r = $ctx->tool($tool, $input);
        $report->source($tool);
        if (! $r['ok']) {
            $report->missing($tool.': '.$r['error']);

            return null;
        }

        return $r['data'];
    }
}
