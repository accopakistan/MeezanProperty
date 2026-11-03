<?php

namespace App\Services\Agents;

use App\Models\AiKnowledgeDocument;

/**
 * Retrieval from approved knowledge-base documents an agent may read.
 * Simple keyword scoring (title words count double): no external vector
 * service is needed. Results carry their source so answers can cite it;
 * document text is treated as data, never as instructions.
 */
class KnowledgeBase
{
    protected const STOP = ['the', 'a', 'an', 'is', 'are', 'how', 'can', 'i', 'do', 'to', 'of', 'for', 'and', 'or', 'in', 'on', 'my', 'me', 'what', 'kya', 'kaise', 'kese', 'hai', 'ka', 'ki', 'ke', 'mein', 'main', 'as', 'be', 'with'];

    /** @return array{found: int, documents: list<array{id: int, title: string, category: string, source: string, excerpt: string, score: int}>} */
    public function search(string $query, string $agentSlug, ?string $category = null, int $limit = 3, ?array $onlyCategories = null): array
    {
        $terms = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)))
            ->filter(fn ($t) => mb_strlen($t) > 2 && ! in_array($t, self::STOP, true))->unique()->values();
        if ($terms->isEmpty()) {
            return ['found' => 0, 'documents' => []];
        }

        $docs = AiKnowledgeDocument::approved()->when($category, fn ($q) => $q->where('category', $category))->when($onlyCategories, fn ($q) => $q->whereIn('category', $onlyCategories))->get()
            ->filter(fn (AiKnowledgeDocument $d) => $d->visibleTo($agentSlug));

        $scored = $docs->map(function (AiKnowledgeDocument $d) use ($terms) {
            $title = mb_strtolower($d->title);
            $body = mb_strtolower($d->body);
            $score = $terms->sum(fn ($t) => 2 * substr_count($title, $t) + min(5, substr_count($body, $t)));

            return ['doc' => $d, 'score' => $score];
        })->filter(fn ($r) => $r['score'] > 0)->sortByDesc('score')->take($limit);

        return ['found' => $scored->count(), 'documents' => $scored->map(fn ($r) => [
            'id' => $r['doc']->id, 'title' => $r['doc']->title, 'category' => $r['doc']->category, 'source' => $r['doc']->source,
            'excerpt' => mb_substr($r['doc']->body, 0, 1200), 'score' => $r['score'],
        ])->values()->all()];
    }
}
