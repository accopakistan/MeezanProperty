<?php

namespace App\Services\Agents\Workers;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Report;

/**
 * Customer Support: answers from approved knowledge only (English, Urdu or
 * Roman Urdu questions), helps with search, and escalates complaints and
 * unanswered account issues to staff as tickets. Never reveals private data.
 */
class CustomerSupportAgent extends Agent
{
    protected const ESCALATE = '/\b(complain|complaint|fraud|scam|cheat|hacked|stolen|refund|legal action|harass|shikayat|dhoka|fraud hua|paise wapas)\b/i';

    /** Urdu-script terms for the help articles, so a question in Urdu finds the same answer. */
    protected const URDU_TERMS = ['ڈیلر' => ' dealer ', 'رجسٹریشن' => ' registration ', 'رجسٹر' => ' register ', 'پاس ورڈ' => ' password ', 'پیکج' => ' package ', 'سبسکرپشن' => ' subscription ', 'لسٹنگ' => ' listing ', 'پراپرٹی' => ' property ', 'اکاؤنٹ' => ' account ', 'تصدیق' => ' verification ', 'سرمایہ کاری' => ' investment '];

    public static function intents(): array
    {
        return [
            'faq' => ['how can i', 'how do i', 'how to', 'register', 'sign up', 'account', 'password', 'login', 'list my', 'listing process', 'subscription', 'package', 'verification', 'kaise', 'kese', 'kya', 'help', 'support', 'faq'],
            'find' => ['find', 'looking for', 'search', 'chahiye', 'dhoond'],
            'escalate' => ['complaint', 'fraud', 'scam', 'refund', 'shikayat', 'not working', 'problem'],
        ];
    }

    public function handle(AgentContext $ctx, string $intent): Report
    {
        $text = $ctx->task->request;
        if (preg_match(self::ESCALATE, $text)) {
            return $this->escalate($ctx, 'complaint', 'This needs a member of staff.');
        }

        return $intent === 'find' ? $this->find($ctx) : $this->faq($ctx);
    }

    protected function faq(AgentContext $ctx): Report
    {
        $r = new Report('Support answer');
        $kb = $this->need($ctx, $r, 'knowledge.search', ['query' => mb_substr(strtr($ctx->task->request, self::URDU_TERMS), 0, 300), 'limit' => 2]);
        $doc = $kb['documents'][0] ?? null;
        if (! $doc || $doc['score'] < 2) {
            $r->missing('No approved help article answers this question.');
            if ($ctx->user) {
                return $this->escalate($ctx, 'other', 'I could not find an approved answer.', $r);
            }
            $r->finding('I could not find an approved answer to that. Please use the contact page or ask to speak to our team.');

            return $r;
        }

        $r->finding($this->answerText($doc['excerpt'], $this->romanUrdu($ctx->task->request)));
        $r->section('Source', [$doc['title'].' ('.$doc['source'].')']);

        return $r;
    }

    protected function find(AgentContext $ctx): Report
    {
        $r = new Report('Property search help');
        $data = $this->need($ctx, $r, 'properties.search', array_filter(['city' => $this->city($ctx->task->request), 'limit' => 5]));
        if ($data) {
            $data['count']
                ? $r->table('Matching live listings', ['Title', 'City', 'Area', 'Price', 'Link'], collect($data['listings'])->map(fn ($l) => [$l['title'], $l['city'], $l['area'], $l['price'], $l['url']])->all())
                : $r->finding('No live listings match yet. Try the AI Property Finder to save your requirement and get alerts.');
            $r->finding('For a guided search with budgets and areas, use the AI Property Finder: '.route('ai-finder'));
        }

        return $r;
    }

    protected function escalate(AgentContext $ctx, string $category, string $why, ?Report $r = null): Report
    {
        $r ??= new Report('Escalated to staff');
        $t = $ctx->tool('support.create_ticket', [
            'subject' => mb_substr('Support: '.$ctx->task->request, 0, 200), 'summary' => mb_substr($ctx->task->request, 0, 2000),
            'category' => $category, 'priority' => $category === 'complaint' ? 'high' : 'normal',
        ]);
        $r->source('support.create_ticket');
        $t['ok']
            ? $r->finding("{$why} I have passed it to our support team (reference {$t['data']['ticket']}); a member of staff will follow up.")
            : $r->finding("{$why} Please contact us through the contact page: ".route('pages.contact'));

        return $r;
    }

    protected function romanUrdu(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text) || (bool) preg_match('/\b(kaise|kese|kya|hai|hain|karna|karein|mujhe|chahiye|kahan|kyun|bataen|batayen)\b/i', $text);
    }

    /** Approved documents may carry a "Roman Urdu:" paragraph; use it when the question is in Urdu or Roman Urdu. */
    protected function answerText(string $excerpt, bool $romanUrdu): string
    {
        if ($romanUrdu && preg_match('/^Roman Urdu:\s*(.+)$/mi', $excerpt, $m)) {
            return trim($m[1]);
        }

        return trim(preg_replace('/^Roman Urdu:.*$/mi', '', $excerpt));
    }
}
