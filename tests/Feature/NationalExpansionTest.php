<?php

use App\Enums\DealerStatus;
use App\Enums\LeadStatus;
use App\Models\Area;
use App\Models\AreaAlias;
use App\Models\AuditLog;
use App\Models\BuyerRequirement;
use App\Models\City;
use App\Models\Property;
use App\Models\User;
use App\Services\Ai\LocationExtractor;
use App\Services\Ai\RulesRequirementParser;
use App\Services\Leads\LeadRouter;
use App\Services\Matching\PropertyMatcher;
use App\Support\LocationName;
use App\Support\LocationResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** A location in any launch city (the shared seeder only has Lahore). */
function place(string $city, string $name, array $attributes = []): Area
{
    return Area::create($attributes + ['city' => $city, 'name' => $name, 'slug' => Str::slug($name.' '.$city.' '.uniqid())]);
}

function dealerIn(string $homeCity, array $cities, array $areaIds): User
{
    $dealer = User::factory()->dealer(DealerStatus::Verified, $areaIds)->create();
    $dealer->dealerProfile->update(['city' => $homeCity]);
    $dealer->dealerProfile->cities()->sync(City::whereIn('name', $cities)->pluck('id'));

    return $dealer;
}

/* Cities and data model ------------------------------------------------- */

it('creates the seven launch cities and links locations to their city', function () {
    expect(City::orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Lahore', 'Karachi', 'Islamabad', 'Multan', 'Faisalabad', 'Quetta', 'Peshawar'])
        ->and(config('meezan.cities'))->toHaveCount(7)->not->toContain('Rawalpindi')
        ->and(area('Gulberg')->city_id)->toBe(City::where('name', 'Lahore')->value('id'))
        ->and(area('Gulberg')->normalized_name)->toBe('gulberg');

    $phase = place('Lahore', 'DHA Phase 5 Sector A', ['parent_id' => area('DHA Phase 5')->id]);
    expect($phase->depth)->toBe(1);
});

it('normalises spellings, roman numerals and sector codes', function (string $typed, string $stored) {
    expect(LocationName::normalize($typed))->toBe(LocationName::normalize($stored));
})->with([
    ['Gulshan e Iqbal', 'Gulshan-e-Iqbal'],
    ['DHA Ph V', 'DHA Phase 5'],
    ['F7', 'F-7'],
    ['E 11', 'E-11'],
    ['Gulberg III', 'Gulberg 3'],
    ['Defence Housing Authority Karachi', 'DHA Karachi'],
]);

it('keeps lettered DHA sectors distinct', function () {
    expect(LocationName::normalize('DHA Phase 1 Sector E'))->toBe('dha phase 1 sector e')
        ->and(LocationName::normalize('DHA Phase 3 Sector X'))->not->toBe(LocationName::normalize('DHA Phase 3 Sector 10'));
});

/* Import ---------------------------------------------------------------- */

function locationsCsv(array $rows): string
{
    $header = ['key', 'city', 'province', 'name', 'official_name', 'aliases', 'type', 'parent_key', 'authority', 'approval_status', 'approval_reference',
        'source_ids', 'source_url', 'research_date', 'confidence', 'latitude', 'longitude', 'coordinate_precision', 'coordinate_source', 'needs_review', 'popular', 'active', 'notes'];
    $path = tempnam(sys_get_temp_dir(), 'loc').'.csv';
    $out = fopen($path, 'w');
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, array_map(fn ($c) => $row[$c] ?? match ($c) {
            'approval_status' => 'unverified', 'type' => 'neighbourhood', 'active' => '1', 'needs_review' => '0', 'popular' => '0', 'research_date' => '2026-10-30', default => '',
        }, $header));
    }
    fclose($out);

    return $path;
}

it('imports the dataset idempotently without touching existing names or coordinates', function () {
    area('Gulberg')->update(['latitude' => 31.51, 'longitude' => 74.34]);
    $csv = locationsCsv([
        ['key' => 'lahore--gulberg', 'city' => 'Lahore', 'name' => 'Gulberg', 'type' => 'neighbourhood', 'approval_status' => 'not_applicable', 'latitude' => '31.99', 'longitude' => '74.99'],
        ['key' => 'karachi--dha-karachi', 'city' => 'Karachi', 'name' => 'DHA Karachi', 'type' => 'defence_scheme', 'aliases' => 'DHA Defence|Defence Housing Authority Karachi', 'approval_status' => 'not_applicable'],
        ['key' => 'karachi--dha-phase-6-karachi', 'city' => 'Karachi', 'name' => 'DHA Phase 6 Karachi', 'type' => 'phase', 'parent_key' => 'karachi--dha-karachi', 'approval_status' => 'not_applicable'],
        ['key' => 'quetta--x-town', 'city' => 'Quetta', 'name' => 'X Town', 'type' => 'housing_scheme', 'approval_status' => 'declared_illegal', 'approval_reference' => 'QDA letter No. 745', 'needs_review' => '1'],
        ['key' => 'rawalpindi--bahria-town-phase-8', 'city' => 'Rawalpindi', 'name' => 'Bahria Town Phase 8', 'type' => 'phase'],
        ['key' => 'multan--bad', 'city' => 'Multan', 'name' => 'Bad', 'type' => 'castle'],
    ]);

    $this->artisan('meezan:import-locations', ['--file' => $csv])->assertSuccessful();

    $gulberg = area('Gulberg');
    $phase = Area::where('research_key', 'karachi--dha-phase-6-karachi')->firstOrFail();
    expect($gulberg->latitude)->toBe(31.51)
        ->and($gulberg->research_key)->toBe('lahore--gulberg')
        ->and($phase->parent->name)->toBe('DHA Karachi')
        ->and($phase->slug)->toBe('dha-phase-6-karachi')
        ->and(Area::where('city', 'Rawalpindi')->exists())->toBeFalse()
        ->and(Area::where('name', 'Bad')->exists())->toBeFalse()
        ->and(Area::where('name', 'X Town')->first()->approval_status)->toBe('declared_illegal')
        // "Defence Housing Authority Karachi" normalises to the name itself, so only one alias is stored.
        ->and(AreaAlias::pluck('alias')->all())->toBe(['DHA Defence'])
        ->and(AuditLog::where('action', 'locations.imported')->exists())->toBeTrue();

    $count = Area::count();
    $this->artisan('meezan:import-locations', ['--file' => $csv])->assertSuccessful();
    expect(Area::count())->toBe($count)->and(AreaAlias::count())->toBe(1);
});

it('validates without writing on a dry run', function () {
    $csv = locationsCsv([['key' => 'peshawar--hayatabad', 'city' => 'Peshawar', 'name' => 'Hayatabad', 'type' => 'government_scheme', 'approval_status' => 'not_applicable']]);

    $this->artisan('meezan:import-locations', ['--file' => $csv, '--dry-run' => true])->assertSuccessful();

    expect(Area::where('city', 'Peshawar')->exists())->toBeFalse();
});

/* City pages, homepage, SEO --------------------------------------------- */

it('shows a city landing page with real counts, canonical URL and structured data', function () {
    $clifton = place('Karachi', 'Clifton', ['is_popular' => true, 'type' => 'neighbourhood']);
    Property::factory()->create(['city' => 'Karachi', 'area_id' => $clifton->id, 'title' => 'Sea View Apartment Clifton']);
    Property::factory()->create(['title' => 'Lahore Only House']);

    $this->get('/karachi')
        ->assertOk()
        ->assertSee('Property in Karachi')
        ->assertSee('Sea View Apartment Clifton')
        ->assertDontSee('Lahore Only House')
        ->assertSee('<link rel="canonical" href="'.route('cities.show', 'karachi').'">', false)
        ->assertSee('"@type":"City"', false)
        ->assertSee('"@type":"BreadcrumbList"', false);

    $this->get('/quetta')->assertOk()->assertSee('No published listings in Quetta yet');
    $this->get('/rawalpindi')->assertNotFound();
});

it('lists the seven cities on the homepage without invented numbers', function () {
    Property::factory()->count(2)->create();

    $this->get('/')->assertOk()
        ->assertSee('Explore Properties Across Pakistan')
        ->assertSee(route('cities.show', 'peshawar'))
        ->assertSee('2 listings')
        ->assertSee('New on Meezan');
});

it('adds city pages to the sitemap', function () {
    $this->get('/sitemap.xml')->assertOk()
        ->assertSee(route('cities.show', 'lahore'))
        ->assertSee(route('cities.show', 'quetta'));
});

it('shows authority status with its source and a disclaimer on an area guide', function () {
    $scheme = place('Islamabad', 'Test Enclave', ['type' => 'housing_scheme', 'authority' => 'CDA', 'approval_status' => 'noc_issued',
        'approval_reference' => 'CDA: layout plan approved, NOC issued', 'verification_source_url' => 'https://www.cda.gov.pk/public/housing-schemes', 'verified_on' => '2026-10-30']);

    $this->get(route('areas.show', $scheme))->assertOk()
        ->assertSee('NOC issued')
        ->assertSee('https://www.cda.gov.pk/public/housing-schemes')
        ->assertSee('Meezan Property does not certify any scheme');
});

/* Search ---------------------------------------------------------------- */

it('searches a whole city from typed text and keeps cities apart', function () {
    $clifton = place('Karachi', 'Clifton');
    Property::factory()->create(['city' => 'Karachi', 'area_id' => $clifton->id, 'title' => 'Karachi Flat']);
    Property::factory()->create(['title' => 'Lahore House']);

    $this->get('/properties?location=Karachi')->assertSee('Karachi Flat')->assertDontSee('Lahore House');
    $this->get('/properties?location=Clifton+Karachi')->assertSee('Karachi Flat')->assertSee('in Clifton, Karachi');
});

it('offers the other cities when a typed name exists in several', function () {
    $fsd = place('Faisalabad', 'Gulberg Faisalabad');
    AreaAlias::create(['area_id' => $fsd->id, 'alias' => 'Gulberg']);
    place('Quetta', 'Garden Town');

    // An exact name wins and the alias match is offered as an alternative.
    expect(LocationResolver::find('Gulberg')->pluck('city')->all())->toBe(['Lahore', 'Faisalabad']);

    $this->get('/properties?location=Garden+Town')
        ->assertSee('matches more than one place')
        ->assertSee('Garden Town, Quetta')
        ->assertSee('Garden Town, Lahore');
});

it('includes phases and blocks when searching a society', function () {
    $dha = place('Lahore', 'DHA Lahore', ['type' => 'defence_scheme']);
    area('DHA Phase 6')->update(['parent_id' => $dha->id]);
    Property::factory()->create(['area_id' => area('DHA Phase 6')->id, 'title' => 'Phase Six Villa']);

    $this->get('/properties?area='.$dha->slug)->assertSee('Phase Six Villa');
});

it('suggests locations as you type without sending the whole list', function () {
    place('Karachi', 'Gulshan-e-Iqbal', ['is_popular' => true]);

    $this->getJson(route('locations.suggest', ['q' => 'gulshan e']))
        ->assertOk()
        ->assertJsonPath('areas.0.name', 'Gulshan-e-Iqbal')
        ->assertJsonPath('areas.0.city', 'Karachi');

    $this->getJson(route('locations.suggest', ['q' => 'gul', 'city' => 'Karachi']))
        ->assertJsonMissing(['name' => 'Gulberg']);

    // 300 more locations: the search page still only embeds a bounded list.
    foreach (range(1, 300) as $i) {
        place('Quetta', "Bulk Scheme {$i}");
    }
    $this->get('/properties')->assertOk()->assertDontSee('Bulk Scheme 250');
});

/* Listing form ---------------------------------------------------------- */

it('saves the deepest consistent location from the dependent selects', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $sector = place('Lahore', 'DHA Phase 6 Sector A', ['parent_id' => area('DHA Phase 6')->id]);
    $other = place('Lahore', 'DHA Phase 5 Sector B', ['parent_id' => area('DHA Phase 5')->id]);

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['area_id' => null, 'area_path' => [area('DHA Phase 6')->id, $sector->id]]))->assertRedirect();
    expect(Property::latest('id')->first()->area_id)->toBe($sector->id);

    // A lower select left over from another branch is ignored.
    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['title' => 'Second listing in DHA', 'area_id' => null, 'area_path' => [area('DHA Phase 6')->id, $other->id]]))->assertRedirect();
    expect(Property::latest('id')->first()->area_id)->toBe(area('DHA Phase 6')->id);
});

it('rejects a location from another city on the listing form', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    $clifton = place('Karachi', 'Clifton');

    $this->actingAs($dealer)->post(route('dealer.properties.store'), listingPayload(['city' => 'Lahore', 'area_id' => $clifton->id]))
        ->assertSessionHasErrors('area_id');
});

it('loads one city at a time into the listing form', function () {
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    place('Karachi', 'Clifton');

    $this->actingAs($dealer)->get(route('dealer.properties.create'))->assertOk()->assertSee('Gulberg')->assertDontSee('Clifton');
    $this->getJson(route('locations.options', ['city' => 'Karachi']))->assertOk()->assertJsonPath('0.name', 'Clifton');
    $this->getJson(route('locations.children', area('DHA Phase 6')))->assertOk()->assertJsonPath('options', []);
});

/* AI parser ------------------------------------------------------------- */

it('recognises the city and its areas in a requirement', function () {
    $clifton = place('Karachi', 'Clifton');

    $criteria = app(RulesRequirementParser::class)->parse('2 bed flat on rent in Clifton Karachi under 1 lakh');

    expect($criteria->city)->toBe('Karachi')->and($criteria->areaIds)->toBe([$clifton->id]);
});

it('asks which city when a place exists in several', function () {
    place('Islamabad', 'Gulberg Islamabad', ['type' => 'housing_scheme']);

    $place = app(LocationExtractor::class)->extract('house in Gulberg');

    expect($place['clarify']['options'])->toContain('Gulberg, Lahore', 'Gulberg Islamabad, Islamabad')
        ->and($place['areaIds'])->toBe([]);

    expect(app(LocationExtractor::class)->extract('house in Gulberg Lahore')['areaIds'])->toBe([area('Gulberg')->id]);
});

it('does not map an uncovered city onto a launch city', function () {
    $place = app(LocationExtractor::class)->extract('plot in Rawalpindi near Saddar');

    expect($place['unsupported'])->toBe('Rawalpindi')->and($place['city'])->toBeNull();

    $this->postJson(route('ai-finder.match'), ['message' => 'house in Pindi'])
        ->assertOk()
        ->assertJsonPath('reply', fn ($reply) => str_contains($reply, 'does not cover Rawalpindi'));
});

it('reads a city name inside a location name as part of that location', function () {
    $road = place('Lahore', 'Multan Road', ['type' => 'corridor']);

    expect(app(LocationExtractor::class)->extract('house on Multan Road')['city'])->toBe('Lahore')
        ->and(app(LocationExtractor::class)->extract('house on Multan Road')['areaIds'])->toBe([$road->id]);
});

it('never matches listings from another city', function () {
    $clifton = place('Karachi', 'Clifton');
    Property::factory()->create(['city' => 'Karachi', 'area_id' => $clifton->id, 'purpose' => 'sale', 'title' => 'Karachi Home']);
    Property::factory()->create(['purpose' => 'sale', 'title' => 'Lahore Home']);

    $result = app(PropertyMatcher::class)->match(app(RulesRequirementParser::class)->parse('house to buy in Karachi'));

    expect($result->matches->pluck('property.title')->all())->toBe(['Karachi Home']);
});

/* Lead routing and dealer coverage ------------------------------------ */

it('routes leads only to dealers who serve the city', function () {
    $clifton = place('Karachi', 'Clifton');
    $karachiOnly = dealerIn('Karachi', ['Karachi'], [$clifton->id]);
    $lahoreDealer = dealerIn('Lahore', ['Lahore'], [area('Gulberg')->id]);
    $both = dealerIn('Karachi', ['Karachi', 'Lahore'], [$clifton->id, area('Gulberg')->id]);

    $requirement = new BuyerRequirement(['purpose' => 'sale', 'city' => 'Lahore', 'area_ids' => [area('Gulberg')->id]]);
    $requirement->user_id = User::factory()->create()->id;
    $requirement->save();

    $ids = app(LeadRouter::class)->eligibleDealers($requirement)->pluck('dealer.id')->all();

    expect($ids)->toContain($lahoreDealer->id, $both->id)->not->toContain($karachiOnly->id);
});

it('matches a dealer covering a society to a request for one of its phases', function () {
    $dha = place('Lahore', 'DHA Lahore', ['type' => 'defence_scheme']);
    area('DHA Phase 6')->update(['parent_id' => $dha->id]);
    $dealer = dealerIn('Lahore', ['Lahore'], [$dha->id]);

    $requirement = new BuyerRequirement(['purpose' => 'sale', 'city' => 'Lahore', 'area_ids' => [area('DHA Phase 6')->id]]);
    $requirement->user_id = User::factory()->create()->id;
    $requirement->save();

    $lead = app(LeadRouter::class)->routeRequirement($requirement, true);

    expect($lead->status)->toBe(LeadStatus::Assigned)
        ->and($lead->activeAssignments()->sole()->dealer_id)->toBe($dealer->id);
});

it('registers a dealer serving several cities', function () {
    $clifton = place('Karachi', 'Clifton');

    $this->post(route('dealer.register.store'), [
        'name' => 'Multi City', 'email' => 'multi@example.test', 'phone' => '03001234567',
        'password' => 'Str0ng!Passw0rd#', 'password_confirmation' => 'Str0ng!Passw0rd#',
        'business_type' => 'agency', 'agency_name' => 'Two Cities Estate', 'city' => 'Lahore',
        'cities' => ['Karachi'], 'areas' => [area('Gulberg')->id, $clifton->id], 'terms' => '1',
    ])->assertRedirect(route('dealer.dashboard'));

    $profile = User::where('email', 'multi@example.test')->firstOrFail()->dealerProfile;
    expect($profile->cities->pluck('name')->sort()->values()->all())->toBe(['Karachi', 'Lahore'])
        ->and($profile->areas)->toHaveCount(2);
});

it('rejects dealer areas outside the cities they serve', function () {
    $clifton = place('Karachi', 'Clifton');

    $this->post(route('dealer.register.store'), [
        'name' => 'One City', 'email' => 'one@example.test', 'phone' => '03001234567',
        'password' => 'Str0ng!Passw0rd#', 'password_confirmation' => 'Str0ng!Passw0rd#',
        'business_type' => 'agency', 'agency_name' => 'Lahore Only Estate', 'city' => 'Lahore',
        'areas' => [$clifton->id], 'terms' => '1',
    ])->assertSessionHasErrors('areas.0');
});

it('gives existing dealers only their own city when coverage was introduced', function () {
    expect(DB::table('city_dealer_profile')->count())->toBe(0); // no dealers existed at migration time in tests
    $dealer = User::factory()->dealer(DealerStatus::Verified)->create();
    expect($dealer->dealerProfile->servedCityNames())->toBe(['Lahore']);
});

/* Admin location management ------------------------------------------ */

it('lets admins edit location metadata and aliases with an audit trail', function () {
    $admin = User::factory()->admin()->create();
    $gulberg = area('Gulberg');

    $this->actingAs($admin)->get(route('admin.areas.edit', $gulberg))->assertOk()->assertSee('Merge a duplicate');
    $this->actingAs($admin)->patch(route('admin.areas.update', $gulberg), [
        'full_edit' => '1', 'name' => 'Gulberg', 'city' => 'Lahore', 'type' => 'neighbourhood', 'approval_status' => 'not_applicable',
        'authority' => 'LDA', 'aliases' => "Gulberg Lahore\nGulburg", 'is_active' => '1',
    ])->assertSessionHasNoErrors();

    expect($gulberg->fresh()->authority)->toBe('LDA')
        ->and($gulberg->aliases()->pluck('alias')->sort()->values()->all())->toBe(['Gulberg Lahore', 'Gulburg'])
        ->and(AuditLog::where('action', 'area.updated')->exists())->toBeTrue();

    $this->actingAs($admin)->get(route('admin.areas.index', ['q' => 'gulburg']))->assertOk()->assertSee('value="Gulberg"', false);
});

it('merges a duplicate location and keeps old links working', function () {
    $admin = User::factory()->admin()->create();
    $duplicate = place('Lahore', 'Gulberg Lahore Duplicate');
    $listing = Property::factory()->create(['area_id' => $duplicate->id]);
    $dealer = dealerIn('Lahore', ['Lahore'], [$duplicate->id]);

    $this->actingAs($admin)->post(route('admin.areas.merge', $duplicate), ['into_id' => area('Gulberg')->id, 'confirm' => '1'])
        ->assertRedirect(route('admin.areas.edit', area('Gulberg')));

    expect($listing->fresh()->area_id)->toBe(area('Gulberg')->id)
        ->and($dealer->dealerProfile->areas()->pluck('areas.id')->all())->toBe([area('Gulberg')->id])
        ->and($duplicate->fresh()->is_active)->toBeFalse()
        ->and(area('Gulberg')->aliases()->where('alias', 'Gulberg Lahore Duplicate')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'area.merged')->exists())->toBeTrue();

    $this->get(route('areas.show', $duplicate))->assertRedirect(route('areas.show', area('Gulberg')));
});

it('refuses to merge locations from different cities', function () {
    $admin = User::factory()->admin()->create();
    $clifton = place('Karachi', 'Clifton');

    $this->actingAs($admin)->post(route('admin.areas.merge', $clifton), ['into_id' => area('Gulberg')->id, 'confirm' => '1'])
        ->assertSessionHasErrors('into_id');
    expect($clifton->fresh()->is_active)->toBeTrue();
});

/* Geocoding ------------------------------------------------------------- */

it('rejects geocoder hits that do not name the place or repeat the parent point', function () {
    City::where('name', 'Lahore')->update(['latitude' => 31.5204, 'longitude' => 74.3587]);
    $sector = place('Lahore', 'DHA Phase 6 Sector Zed', ['parent_id' => area('DHA Phase 6')->id]);
    area('DHA Phase 6')->update(['latitude' => 31.47, 'longitude' => 74.45]);
    Area::whereKeyNot($sector->id)->whereNull('latitude')->update(['latitude' => 31.5, 'longitude' => 74.3]);
    $other = place('Lahore', 'Valencia Phase Q');

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::sequence()
            ->push([['lat' => '31.47', 'lon' => '74.45', 'name' => 'DHA Phase 6', 'display_name' => 'DHA Phase 6 Sector Zed, Lahore']]) // parent's point
            ->push([['lat' => '31.40', 'lon' => '74.20', 'name' => 'Johar Town', 'display_name' => 'Johar Town, Lahore']]) // wrong place
            ->whenEmpty(Http::response([])),
    ]);

    $this->artisan('meezan:geocode-areas')->assertSuccessful();

    expect($sector->fresh()->latitude)->toBeNull()
        ->and($other->fresh()->latitude)->toBeNull()
        ->and($other->fresh()->coordinates_source)->toBe('osm_no_match');
});

it('writes geocoded centres back into the dataset CSV', function () {
    $csv = locationsCsv([['key' => 'lahore--gulberg', 'city' => 'Lahore', 'name' => 'Gulberg', 'approval_status' => 'not_applicable']]);
    area('Gulberg')->update(['research_key' => 'lahore--gulberg', 'latitude' => 31.5101, 'longitude' => 74.3422, 'coordinates_source' => 'openstreetmap']);

    $this->artisan('meezan:export-locations', ['--file' => $csv])->assertSuccessful();

    $rows = array_map('str_getcsv', file($csv));
    $row = array_combine($rows[0], $rows[1]);
    expect($row['latitude'])->toBe('31.5101000')->and($row['coordinate_source'])->toBe('openstreetmap')->and($row['name'])->toBe('Gulberg');
});

it('never gives two locations the same geocoded point', function () {
    City::where('name', 'Lahore')->update(['latitude' => 31.5204, 'longitude' => 74.3587]);
    Area::query()->update(['latitude' => 31.5, 'longitude' => 74.3]);
    $society = place('Lahore', 'Agrics Town');
    $phase = place('Lahore', 'Agrics Town Phase 2');
    $point = [['lat' => '31.44157', 'lon' => '74.2244467', 'name' => 'Agrics Town', 'display_name' => 'Agrics Town, Lahore']];
    Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()->push($point)->push($point)]);

    $this->artisan('meezan:geocode-areas')->assertSuccessful();

    expect($society->fresh()->latitude)->toBe(31.44157)->and($phase->fresh()->latitude)->toBeNull();

    // Points shared before this check existed are cleared from all but the earliest record.
    $phase->update(['latitude' => 31.44157, 'longitude' => 74.2244467, 'coordinates_source' => 'openstreetmap']);
    $society->update(['coordinates_source' => 'openstreetmap']);
    $this->artisan('meezan:geocode-areas', ['--fix-shared' => true])->assertSuccessful();
    expect($society->fresh()->latitude)->toBe(31.44157)->and($phase->fresh()->latitude)->toBeNull();
});

it('flags names that normalise to the same text in one city instead of merging them', function () {
    $csv = locationsCsv([
        ['key' => 'quetta--al-zia-h-s', 'city' => 'Quetta', 'name' => 'Al Zia H/S', 'type' => 'housing_scheme', 'approval_status' => 'under_process'],
        ['key' => 'quetta--al-zia-housing-scheme', 'city' => 'Quetta', 'name' => 'Al Zia Housing Scheme', 'type' => 'housing_scheme', 'approval_status' => 'declared_illegal'],
        ['key' => 'islamabad--bahria-town-islamabad-phase-7-e', 'city' => 'Islamabad', 'name' => 'Bahria Town Islamabad Phase 7-E', 'type' => 'housing_scheme'],
        ['key' => 'islamabad--bahria-town-islamabad-phase-7', 'city' => 'Islamabad', 'name' => 'Bahria Town Islamabad Phase 7', 'type' => 'housing_scheme'],
    ]);

    $this->artisan('meezan:import-locations', ['--file' => $csv])->assertSuccessful();

    $zia = Area::where('city', 'Quetta')->get();
    expect($zia)->toHaveCount(2)
        ->and($zia->every->needs_review)->toBeTrue()
        ->and($zia->firstWhere('name', 'Al Zia H/S')->notes)->toContain('"Al Zia Housing Scheme" (declared_illegal)')
        // "7-E" is a suffix, so Phase 7 and Phase 7-E stay distinct.
        ->and(Area::where('city', 'Islamabad')->where('needs_review', true)->exists())->toBeFalse();

    $this->artisan('meezan:import-locations', ['--file' => $csv])->expectsOutputToContain('Log written')->assertSuccessful();
    expect(Area::where('city', 'Quetta')->count())->toBe(2);
});

it('recognises place names made of common words without reading them as amenities', function () {
    $criteria = app(RulesRequirementParser::class)->parse('house in Model Town with a lawn');
    expect($criteria->areaIds)->toBe([area('Model Town')->id])->and($criteria->amenities)->toBe(['lawn']);

    place('Lahore', 'Garden Town Test', ['type' => 'neighbourhood']);
    expect(app(RulesRequirementParser::class)->parse('house in Garden Town')->amenities)->toBe([]);
});

it('only accepts OpenStreetMap objects of a fitting kind', function () {
    City::where('name', 'Karachi')->update(['latitude' => 24.86, 'longitude' => 67.01]);
    Area::query()->update(['latitude' => 31.5, 'longitude' => 74.3]);
    $colony = place('Karachi', 'Zamzama');
    $road = place('Karachi', 'Tariq Road', ['type' => 'corridor']);
    Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()
        ->push([['lat' => '24.87', 'lon' => '67.05', 'name' => 'Tariq Road', 'display_name' => 'Tariq Road, Karachi', 'addresstype' => 'road', 'type' => 'primary']])
        ->push([['lat' => '24.86', 'lon' => '67.03', 'name' => 'Zamzama Shop', 'display_name' => 'Zamzama Shop, Karachi', 'addresstype' => 'shop', 'type' => 'clothes']])]);

    $this->artisan('meezan:geocode-areas')->assertSuccessful();

    expect($road->fresh()->latitude)->toBe(24.87)->and($colony->fresh()->latitude)->toBeNull();

    // Points stored earlier with the wrong kind are cleared by --fix-shared.
    $colony->update(['latitude' => 24.86, 'longitude' => 67.03, 'coordinates_source' => 'openstreetmap', 'coordinate_precision' => 'historic']);
    $this->artisan('meezan:geocode-areas', ['--fix-shared' => true])->assertSuccessful();
    expect($colony->fresh()->latitude)->toBeNull()->and($road->fresh()->latitude)->toBe(24.87);
});

it('uses the first geocoder candidate that passes the rules', function () {
    City::where('name', 'Karachi')->update(['latitude' => 24.86, 'longitude' => 67.01]);
    Area::query()->update(['latitude' => 31.5, 'longitude' => 74.3]);
    $area = place('Karachi', 'Zamzama');
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
        ['lat' => '24.90', 'lon' => '67.30', 'name' => 'Zamzama Store', 'display_name' => 'Zamzama Store, Karachi', 'addresstype' => 'shop', 'type' => 'clothes'],
        ['lat' => '24.82', 'lon' => '67.03', 'name' => 'Zamzama', 'display_name' => 'Zamzama, Clifton, Karachi', 'addresstype' => 'neighbourhood', 'type' => 'neighbourhood'],
    ])]);

    $this->artisan('meezan:geocode-areas')->assertSuccessful();

    expect($area->fresh()->only('latitude', 'longitude', 'coordinate_precision'))->toBe(['latitude' => 24.82, 'longitude' => 67.03, 'coordinate_precision' => 'neighbourhood']);
});
