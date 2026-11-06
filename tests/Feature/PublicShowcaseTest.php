<?php

use App\Enums\DealerStatus;
use App\Enums\PropertyStatus;
use App\Enums\Purpose;
use App\Models\AiKnowledgeDocument;
use App\Models\Package;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Services\Agents\KnowledgeBase;
use App\Services\InvestmentIndex\IndexEngine;
use App\Support\Contact;
use App\Support\Legal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/*
| The public showcase: no sample content in public, consistent official contact
| details, honest legal and package pages, and research wording on the Index.
*/

function showcaseDealer(DealerStatus $status = DealerStatus::Verified, bool $sample = false): User
{
    $user = User::factory()->dealer($status)->create();
    $user->dealerProfile->forceFill(['is_demo' => $sample, 'whatsapp_number' => '923001112233'])->save();

    return $user->fresh('dealerProfile');
}

function showcaseListing(User $dealer, array $attributes = []): Property
{
    $property = Property::factory()->forDealer($dealer)->create(['title' => $attributes['title'] ?? '10 Marla House '.Str::random(5)]);
    $property->forceFill(array_diff_key($attributes, ['title' => 1]))->save();

    return $property->fresh();
}

it('keeps sample listings off every public page, even when one is left published', function () {
    $dealer = showcaseDealer();
    $sample = showcaseListing($dealer, ['title' => 'Sample Showcase House', 'is_demo' => true]);
    $real = showcaseListing($dealer, ['title' => 'Real Showcase House']);

    expect(Property::published()->pluck('id')->all())->toBe([$real->id])->and($sample->isPublic())->toBeFalse();
    foreach (['/', '/properties', '/buy', '/lahore', '/map'] as $url) {
        $this->get($url)->assertOk()->assertDontSee('Sample Showcase House');
    }
    $this->get(route('properties.show', $sample))->assertNotFound();
    $this->get(route('properties.show', $real))->assertOk()->assertSee('Real Showcase House');
    Cache::flush();
    $this->get(route('sitemap'))->assertOk()->assertDontSee($sample->slug)->assertSee($real->slug);
});

it('hides a sample dealer from the directory and never shows it as verified', function () {
    $sample = showcaseDealer(sample: true);
    $real = showcaseDealer();

    expect($sample->dealerProfile->isVerified())->toBeFalse()->and($sample->isVerifiedDealer())->toBeFalse()
        ->and($sample->dealerProfile->verification_status)->toBe(DealerStatus::Verified)
        ->and($real->dealerProfile->isVerified())->toBeTrue();

    $this->get(route('dealers.index'))->assertOk()->assertDontSee($sample->dealerProfile->agency_name)->assertSee($real->dealerProfile->agency_name);
    $this->get(route('dealers.show', $sample->dealerProfile))->assertNotFound();
});

it('rejects placeholder text as a property description', function (string $text) {
    $dealer = showcaseDealer();

    $this->actingAs($dealer)->post(route('dealer.properties.store'), ['description' => $text])->assertSessionHasErrors('description');
})->with([
    'lorem ipsum' => [str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 2)],
    'placeholder wording' => ['Best house. This is placeholder text widely used in graphic design to preview layouts and typography.'],
]);

it('shows the official contact details, with the office phone and WhatsApp never swapped', function () {
    expect(Contact::phoneHref())->toBe('tel:+923002358853')
        ->and(Contact::whatsapp())->toBe('+923111749849')
        ->and(Contact::whatsappUrl('A & B?'))->toBe('https://wa.me/923111749849?text=A%20%26%20B%3F')
        ->and(Contact::emailHref())->toBe('mailto:support@meezanproperty.com')
        ->and(Contact::address())->toBe('Office 2, 3rd Floor, Bigcity Plaza, Gulberg-III, Lahore, Pakistan');

    foreach (['/', '/contact', '/about', '/properties'] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        expect($html)->toContain('href="tel:+923002358853"')->toContain('href="mailto:support@meezanproperty.com"')
            ->toContain('https://wa.me/923111749849?text=')->toContain('data-whatsapp-float')
            ->not->toContain('tel:+923111749849')->not->toContain('wa.me/923002358853');
    }
    $this->get('/contact')->assertSee('Bigcity Plaza, Gulberg-III')->assertSee('It is a person, not an automated assistant.');
});

it('does not claim the automated WhatsApp assistant is live', function () {
    $this->get(route('ai-finder'))->assertOk()->assertDontSee('Get property suggestions directly on WhatsApp')
        ->assertDontSee('On website &amp; WhatsApp', false)->assertSee('The automated WhatsApp assistant is not live yet.');
});

it('accepts real phone numbers and rejects letters or incomplete mobiles', function (string $phone, bool $valid) {
    expect(Validator::make(['phone' => $phone], ['phone' => [new PhoneNumber]])->passes())->toBe($valid);
})->with([
    ['0300 1234567', true], ['+92 300 1234567', true], ['923001234567', true], ['042-35761234', true], ['+44 20 7946 0958', true],
    ['abc', false], ['0300 12345', false], ['+92 300 12345', false], ['----------', false], ['0300-12a4567', false],
]);

it('validates the contact form on the server', function () {
    $this->post(route('pages.contact.send'), ['name' => 'A', 'email' => 'not-an-email', 'phone' => 'abc', 'message' => 'short'])
        ->assertSessionHasErrors(['email', 'phone', 'message']);
    $this->post(route('pages.contact.send'), ['name' => 'A', 'email' => 'a@example.com', 'phone' => '03001234567', 'message' => 'A question about a listing.', 'website' => 'spam'])
        ->assertSessionHasErrors('website');
});

it('labels draft legal pages as not final, keeps them out of search, and serves a disclaimer', function () {
    expect(Legal::approved('terms'))->toBeFalse();

    foreach (['pages.terms', 'pages.privacy', 'pages.disclaimer'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('Not yet final.')->assertSee('noindex, follow', false)->assertDontSee('Draft for review');
    }
    $this->get(route('pages.disclaimer'))->assertSee(config('meezan.investment.disclaimer'))->assertSee('does not mean a project is illegal');
    Cache::flush();
    $this->get(route('sitemap'))->assertDontSee(route('pages.terms'))->assertDontSee(route('pages.disclaimer'));
});

it('does not ask people to accept terms that are not approved', function () {
    $this->get(route('register'))->assertOk()->assertDontSee('I agree to the')->assertSee('I confirm my details are accurate');
    $this->get(route('dealer.register'))->assertOk()->assertDontSee('I agree to the');
    $this->post(route('register'), ['name' => 'Buyer', 'email' => 'b@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
        ->assertSessionHasErrors('terms');
});

it('keeps draft packages off public pages and out of checkout', function () {
    $draft = new Package(['name' => 'Gold (draft)', 'slug' => 'draft-gold', 'price' => 7500, 'currency' => 'PKR', 'billing_interval' => 'month', 'is_active' => true, 'is_demo' => true]);
    $draft->save();
    $dealer = showcaseDealer();

    $this->get(route('pricing'))->assertOk()->assertDontSee('Gold (draft)')->assertSee('Dealer packages are being finalised')->assertSee('noindex, follow', false);
    $this->actingAs($dealer)->get(route('dealer.billing.checkout', $draft))->assertNotFound();
    $this->actingAs($dealer)->post(route('dealer.billing.choose', $draft))->assertNotFound();
    expect(Package::count())->toBe(1);

    $final = new Package(['name' => 'Starter', 'slug' => 'starter', 'price' => 2500, 'currency' => 'PKR', 'billing_interval' => 'month', 'is_active' => true, 'is_demo' => false]);
    $final->save();
    $this->get(route('pricing'))->assertOk()->assertSee('Starter')->assertDontSee('Gold (draft)');
});

it('uses research wording on the Investment Index while nothing is ranked', function () {
    $this->get(route('rankings.index'))->assertOk()->assertSee('Meezan Investment Index — Research, Verification &amp; Investment Intelligence', false)
        ->assertSee('Under research')->assertDontSee('Top Housing Societies')->assertDontSee('View city rankings');
    $this->get(route('rankings.category', 'housing-societies'))->assertOk()->assertSee('Housing Societies in Pakistan')->assertDontSee('Top Housing Societies in Pakistan');
    $this->get(route('investment.index'))->assertOk()->assertSee('Investment Insights in Pakistan')->assertDontSee('Best Overall Investment');
    $this->get(route('rankings.city', 'lahore'))->assertOk()->assertDontSee('Transparent Rankings')->assertSee('Transparent Research');
    $this->get(route('investment.methodology'))->assertOk()->assertSee('Market insight score (MPIS) methodology')->assertSee(route('rankings.methodology'), false);
});

it('lists the five Index category pages in the sitemap and drops an empty pricing page', function () {
    Cache::flush();
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();

    foreach (array_keys(config('investment_index.categories')) as $category) {
        expect($xml)->toContain(route('rankings.category', $category));
    }
    expect($xml)->not->toContain(route('pricing'));
});

it('allows dealer registration in robots.txt', function () {
    expect(file_get_contents(public_path('robots.txt')))->toContain("Allow: /dealer/register\nDisallow: /dealer/");
});

it('never sorts sale prices and monthly rents on one scale', function () {
    $dealer = showcaseDealer();
    $rent = showcaseListing($dealer, ['title' => 'Cheap Rental', 'purpose' => Purpose::Rent, 'price' => 50_000]);
    $sale = showcaseListing($dealer, ['title' => 'Big Sale', 'price' => 90_000_000]);

    $html = $this->get(route('properties.index', ['sort' => 'price_asc']))->assertOk()->getContent();
    expect(strpos($html, 'Big Sale'))->toBeLessThan(strpos($html, 'Cheap Rental'));
    expect($rent->purpose)->toBe(Purpose::Rent)->and($sale->purpose)->toBe(Purpose::Sale);
});

it('echoes an unmatched search term and sends a page past the end back to the last page', function () {
    $dealer = showcaseDealer();
    showcaseListing($dealer);

    $this->get(route('properties.index', ['q' => 'zzzzqq']))->assertOk()->assertSee('matching “zzzzqq”', false);
    $this->get(route('properties.index', ['page' => 99]))->assertRedirect();
});

it('shows "Not assessed" instead of a risk level for a project under research', function () {
    $project = new Project(['name' => 'Research Town', 'city' => 'Lahore', 'category' => 'residential_society', 'status' => 'unverified', 'description' => 'A test project.']);
    $project->forceFill(['is_published' => true, 'is_demo' => false])->save();
    app(IndexEngine::class)->run('test');

    $this->get(route('rankings.project', $project))->assertOk()->assertSee('Not assessed')->assertDontSee('Low risk');
});

it('gives the assistant knowledge of the Index, contact details and packages', function () {
    $search = fn (string $q) => collect(app(KnowledgeBase::class)->search($q, 'customer-support', null, 3, AiKnowledgeDocument::PUBLIC_CATEGORIES)['documents'])->pluck('title');

    expect($search('How does the Meezan Investment Index work?'))->toContain('How the Meezan Investment Index works')
        ->and($search('What is your phone number and office address'))->toContain('About Meezan Property and how to contact us');
    expect(AiKnowledgeDocument::where('title', 'About Meezan Property and how to contact us')->value('body'))->toContain('+92 300 2358853')->toContain('+92 311 1749849')
        ->and(AiKnowledgeDocument::where('title', 'Dealer subscriptions and packages')->value('body'))->toContain('not published yet');
});

it('keeps Gemini as the default provider', function () {
    // The test environment blanks provider settings, so the defaults are read from the files themselves.
    expect(file_get_contents(config_path('meezan.php')))->toContain("'provider' => env('MEEZAN_AI_PROVIDER', 'gemini')")
        ->and(file_get_contents(base_path('.env.example')))->toContain('MEEZAN_AI_PROVIDER=gemini');
});

it('prints valid structured data, with no Blade code inside it', function (string $url) {
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $this->get($url)->assertOk()->getContent(), $m);

    expect($m[1])->not->toBeEmpty();
    foreach ($m[1] as $json) {
        expect(json_decode(trim($json), true))->toBeArray()->and($json)->not->toContain('__context');
    }
})->with(['/', '/lahore', '/buy', '/contact']);

it('redirects the www address to the main domain', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://meezanproperty.com']);

    $this->get('https://www.meezanproperty.com/contact')->assertStatus(301)->assertRedirect('https://meezanproperty.com/contact');
    Request::setTrustedHosts([]);
});

it('leaves admins able to see archived sample listings', function () {
    $dealer = showcaseDealer();
    $sample = showcaseListing($dealer, ['title' => 'Archived Sample', 'is_demo' => true, 'status' => PropertyStatus::Archived]);

    $this->actingAs(User::factory()->admin()->create())->get(route('properties.show', $sample))->assertOk()->assertSee('Archived Sample');
});
