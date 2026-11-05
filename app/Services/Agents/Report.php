<?php

namespace App\Services\Agents;

/**
 * The structured result every agent returns: findings from real data, the
 * tools (data sources) used, what was missing and the limitations, kept
 * apart from recommendations and from any model-written narrative.
 */
class Report
{
    public array $summary = [];

    public array $sections = [];

    public array $recommendations = [];

    public array $missing = [];

    public array $sources = [];

    public ?string $period = null;

    public ?string $narrative = null;

    public array $limitations = [];

    /** Send the text exactly as written: no model wording (links and references must survive). */
    public bool $verbatim = false;

    /** Hand the customer's chat to staff, with this reason. */
    public ?string $handoff = null;

    public function __construct(public string $title) {}

    public function finding(string $line): static
    {
        $this->summary[] = $line;

        return $this;
    }

    /** @param  list<string>  $items */
    public function section(string $heading, array $items = [], ?array $table = null): static
    {
        $this->sections[] = array_filter(['heading' => $heading, 'items' => $items ?: null, 'table' => $table], fn ($v) => $v !== null);

        return $this;
    }

    /** @param  list<string>  $columns  @param  list<list<mixed>>  $rows */
    public function table(string $heading, array $columns, array $rows, string $empty = 'No records.'): static
    {
        return $rows ? $this->section($heading, [], ['columns' => $columns, 'rows' => $rows]) : $this->section($heading, [$empty]);
    }

    public function recommend(string $line): static
    {
        $this->recommendations[] = $line;

        return $this;
    }

    public function missing(string $line): static
    {
        $this->missing[] = $line;

        return $this;
    }

    public function limitation(string $line): static
    {
        $this->limitations[] = $line;

        return $this;
    }

    public function source(string $tool): static
    {
        $this->sources[] = $tool;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'period' => $this->period,
            'summary' => $this->summary,
            'sections' => $this->sections,
            'recommendations' => $this->recommendations,
            'missing' => array_values(array_unique($this->missing)),
            'limitations' => array_values(array_unique($this->limitations)),
            'data_sources' => array_values(array_unique($this->sources)),
            'narrative' => $this->narrative,
            'generated_at' => now()->toIso8601String(),
        ] + ($this->handoff ? ['handoff' => $this->handoff] : []);
    }

    /** For a customer: the same text, without internal tool failures. */
    public static function customerPlain(array $r, int $max = 1000): string
    {
        $r['missing'] = array_values(array_filter($r['missing'] ?? [], fn ($m) => ! preg_match('/^[a-z_]+\.[a-z_]+:/', (string) $m)));

        return self::plain($r, $max);
    }

    /** Plain text (WhatsApp / chat). */
    public static function plain(array $r, int $max = 1200): string
    {
        $lines = array_merge($r['summary'] ?? [], array_map(fn ($m) => 'Not available: '.$m, array_slice($r['missing'] ?? [], 0, 2)));
        $text = trim(($r['narrative'] ?? '') ?: implode("\n", $lines));

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }
}
