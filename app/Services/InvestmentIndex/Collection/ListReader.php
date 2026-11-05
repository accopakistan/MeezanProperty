<?php

namespace App\Services\InvestmentIndex\Collection;

use DOMDocument;
use DOMXPath;

/**
 * Reads the rows of an official list into plain entries:
 * key, name, location, status_raw, date_raw, reference, url, raw.
 *
 *  inertia     the page data an Inertia site embeds as JSON in data-page;
 *              follows the paginator's pages up to max_pages
 *  html_table  a table on the page; columns are matched by header text
 *  cards       one page of "society-card" blocks: name, location line, status badges
 *  list_items  one page of list items whose text is "Name, Location"
 *
 * "complete" is true only when every page was read.
 */
class ListReader
{
    /** @return array{entries: list<array>, complete: bool, hash: string} */
    public function read(array $preset, Fetcher $fetcher): array
    {
        return match ($preset['driver']) {
            'inertia' => $this->inertia($preset, $fetcher),
            'html_table' => $this->htmlTable($preset, $fetcher),
            'cards' => $this->cards($preset, $fetcher),
            'list_items' => $this->listItems($preset, $fetcher),
            default => throw new CollectionError('format', 'Unknown collector driver '.$preset['driver'].'.'),
        };
    }

    protected function inertia(array $preset, Fetcher $fetcher): array
    {
        $entries = [];
        $hashes = [];
        $page = 1;
        $last = 1;
        $max = (int) config('investment_index.collection.max_pages');
        do {
            $url = $preset['url'].($page > 1 ? (str_contains($preset['url'], '?') ? '&' : '?').'page='.$page : '');
            $html = $fetcher->get($url);
            $hashes[] = hash('sha256', $html);
            if (! preg_match('/data-page="([^"]*)"/', $html, $m)) {
                throw new CollectionError('format', "No page data found at {$url}; the site may have changed.");
            }
            $data = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            $list = data_get($data, 'props.'.$preset['prop']);
            if ($list === null) {
                throw new CollectionError('format', "The page data at {$url} has no \"{$preset['prop']}\" list; the site may have changed.");
            }
            $rows = $preset['paginated'] ? ($list['data'] ?? null) : $list;
            if (! is_array($rows)) {
                throw new CollectionError('format', "The \"{$preset['prop']}\" list at {$url} is not in the expected form.");
            }
            if ($preset['paginated']) {
                $last = (int) ($list['last_page'] ?? 1);
            }
            foreach ($rows as $row) {
                $entries[] = $this->entry($row, $preset, $preset['url']);
            }
            $page++;
        } while ($preset['paginated'] && $page <= min($last, $max));

        return ['entries' => array_values(array_filter($entries)), 'complete' => ! $preset['paginated'] || $last <= $max, 'hash' => hash('sha256', implode('', $hashes))];
    }

    protected function htmlTable(array $preset, Fetcher $fetcher): array
    {
        foreach (['url', 'columns', 'list_type', 'authority'] as $required) {
            if (empty($preset[$required])) {
                throw new CollectionError('format', "This source's table collector is missing \"{$required}\".");
            }
        }
        $html = $fetcher->get($preset['url']);
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $tables = (new DOMXPath($dom))->query('//table');
        $table = $tables->item((int) ($preset['table'] ?? 0)) ?? throw new CollectionError('format', 'The expected table was not found at '.$preset['url'].'.');

        $rows = iterator_to_array((new DOMXPath($dom))->query('.//tr', $table));
        $header = array_map(fn ($c) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $c->textContent))), iterator_to_array(array_shift($rows)?->childNodes ?? []));
        $header = array_values(array_filter($header, fn ($h) => $h !== ''));
        $index = [];
        foreach ($preset['columns'] as $field => $title) {
            $i = array_search(mb_strtolower($title), $header, true);
            if ($i === false) {
                throw new CollectionError('format', "Column \"{$title}\" was not found in the table at {$preset['url']}; the site may have changed.");
            }
            $index[$field] = $i;
        }

        $entries = [];
        foreach ($rows as $tr) {
            $cells = array_values(array_filter(iterator_to_array($tr->childNodes), fn ($n) => in_array($n->nodeName, ['td', 'th'], true)));
            $values = array_map(fn ($c) => trim(preg_replace('/\s+/u', ' ', $c->textContent)), $cells);
            $row = [];
            foreach ($index as $field => $i) {
                $row[$field] = $values[$i] ?? null;
            }
            $row['key'] ??= hash('sha256', mb_strtolower(($row['name'] ?? '').'|'.($row['location'] ?? '')));
            $entries[] = $this->entry($row, ['fields' => array_combine(array_keys($row), array_keys($row))] + $preset, $preset['url']);
        }

        return ['entries' => array_values(array_filter($entries)), 'complete' => true, 'hash' => hash('sha256', $html)];
    }

    protected function cards(array $preset, Fetcher $fetcher): array
    {
        $html = $fetcher->get($preset['url']);
        $blocks = array_slice(preg_split('/society-card/', $html), 1);
        if ($blocks === []) {
            throw new CollectionError('format', "No scheme cards were found at {$preset['url']}; the site may have changed.");
        }
        $text = fn (string $s) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $entries = [];
        foreach ($blocks as $block) {
            if (! preg_match('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/s', $block, $a)) {
                continue;
            }
            preg_match('/<p[^>]*>(.*?)<\/p>/s', $block, $p);
            preg_match_all('/class="badge[^"]*"[^>]*>(.*?)<\/span>/s', $block, $b);
            $row = ['key' => basename(parse_url($a[1], PHP_URL_PATH) ?: $text($a[2])), 'name' => $text($a[2]), 'location' => isset($p[1]) ? str_replace(' | ', ', ', $text($p[1])) : null,
                'status' => implode(' / ', array_map($text, $b[1]))];
            $entries[] = $this->entry($row, $preset, $preset['url']);
        }

        return ['entries' => array_values(array_filter($entries)), 'complete' => true, 'hash' => hash('sha256', $html)];
    }

    protected function listItems(array $preset, Fetcher $fetcher): array
    {
        $html = $fetcher->get($preset['url']);
        if (! preg_match_all('/<li[^>]*list-group-item[^>]*data-name="[^"]*"[^>]*>(.*?)<\/li>/s', $html, $m)) {
            throw new CollectionError('format', "No list items were found at {$preset['url']}; the site may have changed.");
        }
        $entries = [];
        foreach ($m[1] as $item) {
            $line = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($item), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $comma = mb_strrpos($line, ',');
            $row = ['name' => $comma ? trim(mb_substr($line, 0, $comma)) : $line, 'location' => $comma ? trim(mb_substr($line, $comma + 1)) : null, 'key' => hash('sha256', mb_strtolower($line))];
            $entries[] = $this->entry($row, $preset, $preset['url']);
        }

        return ['entries' => array_values(array_filter($entries)), 'complete' => true, 'hash' => hash('sha256', $html)];
    }

    /** One plain entry, or null for rows without a name or outside the "active" filter. */
    protected function entry(array $row, array $preset, string $listUrl): ?array
    {
        foreach ($preset['active'] ?? [] as $field => $value) {
            if (mb_strtolower(trim((string) ($row[$field] ?? ''))) !== mb_strtolower($value)) {
                return null;
            }
        }
        $f = $preset['fields'];
        $get = fn (string $k) => isset($f[$k]) ? (is_scalar($row[$f[$k]] ?? null) ? trim((string) $row[$f[$k]]) : null) : null;
        $name = SchemeNames::clean((string) $get('name'));
        if ($name === '') {
            return null;
        }
        $key = (string) ($get('key') ?? hash('sha256', mb_strtolower($name)));

        return [
            'key' => mb_substr($key, 0, 120), 'name' => mb_substr($name, 0, 250), 'location' => ($l = $get('location')) ? mb_substr(SchemeNames::clean($l), 0, 500) : null,
            'status_raw' => $get('status'), 'date_raw' => $get('date'), 'reference' => $get('reference'),
            // A stable link to this row of the official list.
            'url' => $listUrl.'#'.rawurlencode($key),
            'raw' => $row,
        ];
    }
}
