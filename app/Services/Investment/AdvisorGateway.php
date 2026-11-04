<?php

namespace App\Services\Investment;

use App\Models\User;
use App\Services\Agents\CentralAiOrchestrator;
use Throwable;

/**
 * The AI Investment Advisor panel goes through the Central AI Brain: each
 * question is a task for the Investment Analyst agent, which answers with the
 * investment.advisor tool (permissions, budgets, audit and grounded model
 * wording all apply). If the agent is disabled or the task fails, the panel
 * still answers, directly from the same data, and says so.
 */
class AdvisorGateway
{
    public function __construct(protected CentralAiOrchestrator $brain, protected InvestmentAdvisor $advisor) {}

    /**
     * @return array{question: string, city: ?string, segment: string, intent: string, size: ?string, answer: list<string>, items: list<array>, sources: list<string>, missing: list<string>, mode: string, demo: bool, methodology: ?int, published_at: ?string, task: ?string}
     */
    public function ask(string $question, ?string $city, ?string $segment, ?string $focus, ?User $user, string $source = 'website'): array
    {
        try {
            $task = $this->brain->submit($question, 'investment-analyst', $user, $source, array_filter(['city' => $city, 'segment' => $segment, 'focus' => $focus]), intent: 'advisor');
        } catch (Throwable $e) {
            report($e);
            $task = null;
        }
        $r = $task?->result;
        if (! $task || ! in_array($task->status, ['completed', 'awaiting_approval'], true) || ! $r || ! collect($r['sections'] ?? [])->firstWhere('heading', 'Question')) {
            return $this->direct($question, $city, $segment, $focus, $task?->error);
        }

        $sections = collect($r['sections'])->keyBy('heading');
        $asked = json_decode($sections['Question']['items'][0] ?? '{}', true) ?: [];
        $table = $sections['Entries']['table'] ?? ['rows' => []];
        $demo = in_array(config('meezan.investment.demo_notice'), $r['limitations'] ?? [], true);
        $answer = $r['narrative'] ?? null
            ? array_values(array_filter([$demo ? config('meezan.investment.demo_notice') : null, $r['narrative']]))
            : $r['summary'];

        return [
            'question' => $question,
            'city' => $asked['city'] ?? $city,
            'segment' => $asked['segment'] ?? $segment ?? 'societies',
            'intent' => $asked['intent'] ?? 'best',
            'size' => $asked['size'] ?? null,
            'answer' => $answer,
            'items' => collect($table['rows'])->map(fn ($row) => array_combine(['name', 'location', 'group_label', 'score', 'status_label', 'confidence_label', 'yield', 'demo', 'key', 'image'], $row)
                + ['is_demo' => $row[7] === 'yes'])->all(),
            'sources' => $sections['Sources']['items'] ?? [],
            'missing' => $r['missing'] ?? [],
            'mode' => ($task->mode === 'llm' ? 'llm' : 'rules').'-agent',
            'demo' => $demo,
            'methodology' => preg_match('/v(\d+)/', (string) ($r['period'] ?? ''), $m) ? (int) $m[1] : null,
            'published_at' => preg_match('/published (\S+)/', (string) ($r['period'] ?? ''), $m) ? $m[1] : null,
            'task' => $task->uuid,
        ];
    }

    protected function direct(string $question, ?string $city, ?string $segment, ?string $focus, ?string $why): array
    {
        $a = $this->advisor->answer($question, $city, $segment, $focus);
        $a['mode'] = 'rules-direct';
        $a['task'] = null;
        $a['missing'][] = 'The AI agent was not available'.($why ? ' ('.$why.')' : '').'; answered directly from the published data.';

        return $a;
    }
}
