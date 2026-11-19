<?php

use App\Support\PriceIndexTrend;

// Advertised price trend for the ranking dashboards (owner's decision, 19 Nov 2026).

function trendFacts(string $size, string $price, ?string $y1, ?string $y2): array
{
    $src = ['source' => 'Zameen.com price index — Test Town', 'url' => 'https://example.test/index', 'basis' => 'Advertised (asking) price index, not verified sales'];

    return array_values(array_filter([
        ['label' => "Advertised price ($size plot)", 'value' => $price] + $src,
        $y1 !== null ? ['label' => "Price change, 1 year ($size plot)", 'value' => $y1] + $src : null,
        $y2 !== null ? ['label' => "Price change, 2 years ($size plot)", 'value' => $y2] + $src : null,
    ]));
}

it('works back from the published price and its changes', function () {
    $trend = PriceIndexTrend::forSize(trendFacts('10 marla', 'PKR 2.73 Crore (Sep 2026)', '+5%', '+17%'), '10 marla');

    expect(array_column($trend['points'], 'label'))->toBe(['Sep 2024', 'Sep 2025', 'Sep 2026'])
        ->and(array_column($trend['points'], 'amount'))->toBe([(int) round(27_300_000 / 1.17), (int) round(27_300_000 / 1.05), 27_300_000])
        ->and(array_column($trend['points'], 'derived'))->toBe([true, true, false])
        ->and($trend['source'])->toBe('Zameen.com price index — Test Town');
});

it('handles Lakh prices, falls, and a missing two-year change', function () {
    $trend = PriceIndexTrend::forSize(trendFacts('5 marla', 'PKR 85 Lakh (Aug 2026)', '-15%', null), '5 marla');

    expect(array_column($trend['points'], 'label'))->toBe(['Aug 2025', 'Aug 2026'])
        ->and($trend['points'][0]['amount'])->toBe(10_000_000);
});

it('draws nothing without a dated price and at least one change', function () {
    expect(PriceIndexTrend::forSize(trendFacts('1 kanal', 'PKR 4 Crore', '+5%', '+9%'), '1 kanal'))->toBeNull()
        ->and(PriceIndexTrend::forSize(trendFacts('1 kanal', 'PKR 4 Crore (Sep 2026)', null, null), '1 kanal'))->toBeNull()
        ->and(PriceIndexTrend::forFacts(null))->toBe([])
        ->and(PriceIndexTrend::forFacts(trendFacts('10 marla', 'PKR 2 Crore (Sep 2026)', '+4%', '+9%')))->toHaveKeys(['10 marla']);
});

it('formats prices like the tables', function () {
    expect(PriceIndexTrend::format(27_300_000))->toBe('Rs 2.73 Cr')->and(PriceIndexTrend::format(20_000_000))->toBe('Rs 2 Cr')
        ->and(PriceIndexTrend::format(8_500_000))->toBe('Rs 85 Lakh');
});
