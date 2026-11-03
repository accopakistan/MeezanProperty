<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** An approved knowledge-base document, optionally limited to certain agents. */
class AiKnowledgeDocument extends Model
{
    public const CATEGORIES = [
        'company' => 'Company information',
        'product' => 'Product documentation',
        'property_policy' => 'Property policies',
        'dealer_policy' => 'Dealer policies',
        'subscriptions' => 'Subscription packages',
        'support_faq' => 'Support FAQs',
        'investment' => 'Investment methodology',
        'marketing' => 'Marketing guidelines',
        'brand' => 'Brand guidelines',
        'technical' => 'Technical documentation',
        'procedures' => 'Business procedures',
        'competitor_research' => 'Competitor research notes',
    ];

    /** What the public Customer Support agent may quote; other categories are internal. */
    public const PUBLIC_CATEGORIES = ['company', 'product', 'property_policy', 'dealer_policy', 'subscriptions', 'support_faq', 'investment'];

    protected $fillable = ['category', 'title', 'body', 'source', 'agents'];

    protected function casts(): array
    {
        return ['agents' => 'array', 'approved_at' => 'datetime'];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function visibleTo(string $agentSlug): bool
    {
        return $this->agents === null || $this->agents === [] || in_array($agentSlug, $this->agents, true);
    }
}
