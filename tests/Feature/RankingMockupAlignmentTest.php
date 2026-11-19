<?php

// Investment Rankings hero search and dashboard pieces added to match the owner's mockups (19 Nov 2026).

it('sends the Invest tab to the category leaderboard with its filters', function () {
    $this->get('/search?mode=investment&tab=invest&investment_city=lahore&category=shopping-malls&risk=low&development=completed&legal=approved&q=mall')
        ->assertRedirect(route('rankings.city-category', ['city' => 'lahore', 'category' => 'shopping-malls', 'q' => 'mall', 'development' => 'completed', 'risk' => 'low', 'legal' => 'approved']));

    $this->get('/search?mode=investment&tab=invest&investment_city=pakistan&category=business-towers')
        ->assertRedirect(route('rankings.category', ['category' => 'business-towers']));
});

it('sends the Invest tab without a type to the city or the Index home', function () {
    $this->get('/search?mode=investment&tab=invest&investment_city=karachi')->assertRedirect(route('rankings.city', 'karachi'));
    $this->get('/search?mode=investment&tab=invest&investment_city=pakistan')->assertRedirect(route('rankings.index'));
});

it('sends the Compare tab to that category comparison', function () {
    $this->get('/search?mode=investment&tab=compare&category=shopping-malls')->assertRedirect(route('rankings.category-compare', 'shopping-malls'));
    $this->get('/search?mode=investment&tab=compare')->assertRedirect(route('rankings.category-compare', 'housing-societies'));
});

it('refuses filter values outside the lists', function () {
    $this->get('/search?mode=investment&tab=invest&category=castles')->assertSessionHasErrors('category');
    $this->get('/search?mode=investment&tab=invest&risk=none')->assertSessionHasErrors('risk');
});

it('shows the five-tab investment search and context tabs on ranking pages', function () {
    $html = $this->get(route('rankings.city-category', ['lahore', 'shopping-malls']))->assertOk()->getContent();

    foreach (['Buy', 'Rent', 'Invest', 'Compare', 'AI Search'] as $tab) {
        expect($html)->toContain('data-inv-tab="'.match ($tab) { 'AI Search' => 'ai', default => strtolower($tab) }.'"');
    }
    // Commercial page: the commercial tab set; types the Index does not rank yet are shown, not linked.
    expect($html)->toContain('Office Spaces')->toContain('Mixed Use')->toContain('aria-disabled="true"')
        ->not->toContain('Residential Societies</a>');

    $city = $this->get(route('rankings.city', 'lahore'))->assertOk()->getContent();
    expect($city)->toContain('Residential Societies')->toContain('Commercial Projects')->toContain('data-investment-search');
});
