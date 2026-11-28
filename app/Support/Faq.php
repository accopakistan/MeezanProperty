<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Short question-and-answer blocks for city pages and area guides, written only
 * from Meezan Property's own records: live listing counts and asking prices, and
 * the authority status already shown on the area guides with its cited source.
 * Nothing here states market prices, approvals or advice that the data does not
 * contain. Each answer is plain text, so the visible answer and the FAQPage
 * structured data are identical.
 *
 * @phpstan-type Item array{question: string, answer: string, source?: ?string, sourceLabel?: ?string}
 */
class Faq
{
    /** Housing-scheme types whose approval status the authority lists cover. */
    public const SCHEME_TYPES = ['housing_scheme', 'cooperative_society', 'government_scheme', 'defence_scheme'];

    /**
     * @param  Collection<int|string, int>  $listedCounts  live listings per area id (most first)
     * @return list<Item>
     */
    public static function forCity(string $city, array $summary, Collection $listedCounts): array
    {
        $items = [];
        $today = now()->format('j F Y');

        // Listings on the site today.
        $live = Property::published()->where('city', $city)->where('is_demo', false);
        $sale = (clone $live)->where('purpose', 'sale')->count();
        $rent = (clone $live)->where('purpose', 'rent')->count();
        $total = $sale + $rent;
        $items[] = [
            'question' => "How many properties are listed in {$city} on Meezan Property?",
            'answer' => $total
                ? "On {$today}, {$total} checked ".Str::plural('listing', $total).' in '.$city.($total === 1 ? ' was' : ' were')." live on Meezan Property: {$sale} for sale and {$rent} for rent."
                    .' The count changes as dealers and owners add, sell or remove properties.'
                : "There are no live listings in {$city} on Meezan Property right now. You can save a search or ask the AI Property Finder to tell you when one appears.",
        ];

        // Where the listings are.
        $areas = Area::whereIn('id', $listedCounts->keys()->take(3))->get(['id', 'name'])->keyBy('id');
        $top = $listedCounts->take(3)->filter(fn ($n, $id) => isset($areas[$id]))->map(fn ($n, $id) => $areas[$id]->name.' ('.$n.')');
        if ($top->isNotEmpty()) {
            $items[] = [
                'question' => "Which areas of {$city} have the most listings on Meezan Property?",
                'answer' => 'By number of live listings: '.$top->implode(', ').'. This reflects what is listed on Meezan Property today, not the whole market.',
            ];
        }

        // Approval status of housing schemes, as recorded from the authority lists.
        $schemes = Area::active()->where('city', $city)->whereNull('parent_id')->whereIn('type', self::SCHEME_TYPES);
        $count = (clone $schemes)->count();
        if ($count) {
            $withStatus = (clone $schemes)->whereNotNull('approval_status')->whereNotIn('approval_status', ['unverified', 'not_applicable'])->count();
            $authorities = (clone $schemes)->whereNotNull('authority')->toBase()->selectRaw('authority, count(*) as total')->groupBy('authority')->orderByDesc('total')->pluck('authority');
            $authority = $authorities->take(3)->implode(', ');
            $mainAuthority = (clone $schemes)->whereNotNull('authority')->whereNotNull('approval_status')->whereNotIn('approval_status', ['unverified', 'not_applicable'])
                ->toBase()->selectRaw('authority, count(*) as total')->groupBy('authority')->orderByDesc('total')->value('authority');
            $source = (clone $schemes)->whereNotNull('verification_source_url')->toBase()->selectRaw('verification_source_url, count(*) as total')
                ->groupBy('verification_source_url')->orderByDesc('total')->value('verification_source_url');
            $checked = (clone $schemes)->whereNotNull('approval_status')->whereNotIn('approval_status', ['unverified', 'not_applicable'])->max('verified_on');

            // How many recorded statuses come from the authority's own website, and how many from a report of it.
            $bySource = (clone $schemes)->whereNotNull('approval_status')->whereNotIn('approval_status', ['unverified', 'not_applicable'])
                ->pluck('verification_source_url')->countBy(fn ($url) => self::sourceKind($url));
            $official = $bySource['official'] ?? 0;
            $secondary = ($bySource['secondary'] ?? 0) + ($bySource['none'] ?? 0);
            // Who reported the list, from the source note ("… (as reported by Zameen News, 1 Mar 2021)").
            $reportedBy = preg_match('/as reported by ([^)]+)\)/i', (string) (clone $schemes)->where('verification_source_url', $source)->value('approval_reference'), $rm) ? trim($rm[1]) : null;
            $sourceSentence = match (true) {
                $official && ! $secondary => ' All of these come from '.($mainAuthority ? $mainAuthority.'\'s' : 'the authority\'s').' own published lists (official source)',
                $secondary && ! $official => ' These come from a secondary source, a published report of '.($mainAuthority ? $mainAuthority.'\'s' : 'the authority\'s')
                    .' list'.($reportedBy ? ' ('.$reportedBy.')' : '').', not from the authority\'s own website, so treat them as unconfirmed',
                default => " {$official} come from the authority's own published lists (official source) and {$secondary} from secondary reports of them",
            };

            $items[] = [
                'question' => "How can I check whether a housing society in {$city} is approved?",
                'answer' => ($withStatus
                    ? "Meezan Property's area guides show a recorded approval status for {$withStatus} of the {$count} housing schemes listed in {$city}."
                        .$sourceSentence.($checked ? ', last checked on '.\Illuminate\Support\Carbon::parse($checked)->format('j F Y') : '').'.'
                        .' Each society\'s area guide shows its status and the exact source.'
                    : "Meezan Property lists {$count} housing schemes in {$city}".($authority ? ' under '.$authority : '').' but has not yet recorded their approval status.')
                    .' Approvals change, so confirm the current status with the development authority itself before you pay any money.',
                'source' => $source,
                'sourceLabel' => $source ? (self::sourceKind($source) === 'official' ? 'Official source' : self::secondaryLabel((clone $schemes)->where('verification_source_url', $source)->value('approval_reference'), $source, $mainAuthority)) : null,
            ];
        }

        $items[] = self::marla();

        return $items;
    }

    /**
     * @param  array{total: int, sale: int, rent: int, metrics: list<array{label: string, sample: int, median: ?int, low: ?int, high: ?int}>}  $insights
     * @return list<Item>
     */
    public static function forArea(Area $area, array $insights): array
    {
        $items = [];
        $place = $area->name.', '.$area->city;

        if (! in_array($area->approval_status, ['not_applicable'], true) && in_array($area->type, self::SCHEME_TYPES, true)) {
            $status = $area->approval_status && $area->approval_status !== 'unverified';
            $kind = self::sourceKind($area->verification_source_url);
            $whose = $area->authority ? $area->authority.'\'s' : 'the authority\'s';
            $read = $area->verified_on ? ' (read on '.$area->verified_on->format('j F Y').')' : '';
            $items[] = [
                'question' => "Is {$area->name} approved?",
                'answer' => ($status
                    ? match ($kind) {
                        'official' => $whose.' own published list (official source) records '.$area->name.' as "'.$area->approvalLabel().'"'.$read.'.',
                        'secondary' => 'A secondary source, a published report of '.$whose.' list rather than the authority\'s own website, records '.$area->name.' as "'.$area->approvalLabel().'"'.$read.'. Meezan Property has not confirmed this with '.($area->authority ?: 'the authority').'.',
                        default => 'Meezan Property has recorded "'.$area->approvalLabel().'" for '.$area->name.$read.' but holds no source link for it, so treat it as unverified.',
                    }
                        .($area->approval_reference ? ' Source note: '.$area->approval_reference.'.' : '')
                    : 'Meezan Property has not verified the approval status of '.$area->name.($area->authority ? ' with '.$area->authority : '').'.')
                    .' Meezan Property does not certify any scheme; confirm the current status with the development authority itself before you buy.',
                'source' => $area->verification_source_url,
                'sourceLabel' => $area->verification_source_url ? ($kind === 'official' ? 'Official source' : self::secondaryLabel($area->approval_reference, $area->verification_source_url, $area->authority)) : null,
            ];
        }

        $items[] = [
            'question' => "How many properties are for sale or rent in {$area->name}?",
            'answer' => $insights['total']
                ? 'On '.now()->format('j F Y').', '.$insights['total'].' checked '.Str::plural('listing', $insights['total']).' in '.$place.($insights['total'] === 1 ? ' was' : ' were')." live on Meezan Property: {$insights['sale']} for sale and {$insights['rent']} for rent."
                : "There are no live listings in {$place} on Meezan Property right now.",
        ];

        $priced = collect($insights['metrics'] ?? [])->filter(fn ($m) => $m['median'] !== null);
        if ($priced->isNotEmpty()) {
            $items[] = [
                'question' => "What are asking prices in {$area->name}?",
                'answer' => $priced->map(fn ($m) => $m['label'].': median '.Format::price($m['median']).' (range '.Format::price($m['low']).' to '.Format::price($m['high']).', '.$m['sample'].' listings)')->implode('. ')
                    .'. These are asking prices on current Meezan Property listings, not sale prices or valuations.',
            ];
        }

        return $items;
    }

    /**
     * Official authority websites whose domain is not under .gov.pk. Add a host only after
     * confirming it is the authority's own site (qda.gob.pk: Quetta Development Authority).
     */
    public const OFFICIAL_HOSTS = ['qda.gob.pk'];

    /**
     * Whether a source link is the authority's own website ("official"), anything else such as
     * a news report or portal article ("secondary"), or missing ("none").
     */
    public static function sourceKind(?string $url): string
    {
        $host = $url ? strtolower((string) parse_url($url, PHP_URL_HOST)) : '';
        if ($host === '') {
            return 'none';
        }
        $host = preg_replace('/^www\./', '', $host);

        return str_ends_with($host, '.gov.pk') || in_array($host, self::OFFICIAL_HOSTS, true) ? 'official' : 'secondary';
    }

    /**
     * Link label for a secondary approval source, naming the report when the source note says
     * who reported it ("… (as reported by Zameen News, 1 Mar 2021)"), e.g.
     * "Secondary source: Zameen News report, 1 Mar 2021 (not confirmation from LDA)".
     */
    public static function secondaryLabel(?string $reference, ?string $url, ?string $authority): string
    {
        $who = preg_match('/as reported by ([^)]+)\)/i', (string) $reference, $m)
            ? trim($m[1])
            : preg_replace('/^www\./', '', (string) parse_url((string) $url, PHP_URL_HOST));
        [$name, $date] = array_pad(array_map('trim', explode(',', $who, 2)), 2, null);

        return 'Secondary source: '.($name ?: 'published').' report'.($date ? ', '.$date : '')
            .' (not confirmation from '.($authority ?: 'the authority').')';
    }

    /** The site's own unit convention, stated once. */
    public static function marla(): array
    {
        $marla = (int) config('meezan.marla_sqft', 225);

        return [
            'question' => 'How big is a Marla and a Kanal on Meezan Property?',
            'answer' => "Meezan Property converts 1 Marla to {$marla} square feet and 1 Kanal to 20 Marla (".number_format(20 * $marla).' square feet), the convention used by most Lahore housing schemes.'
                .' Other places and some land records use a different Marla size, so check the size stated in the allotment or transfer documents.',
        ];
    }

    /** FAQPage structured data for exactly the questions shown on the page. */
    public static function schema(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($i) => [
                '@type' => 'Question',
                'name' => $i['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['answer']],
            ], $items),
        ];
    }
}
