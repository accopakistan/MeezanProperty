<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AiFinderController;
use App\Http\Controllers\AreaGuideController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\DealerRegistrationController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\Dealer;
use App\Http\Controllers\DealerDirectoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\InvestmentEvidenceController;
use App\Http\Controllers\InvestmentIndexController;
use App\Http\Controllers\InvestmentRankingsController;
use App\Http\Controllers\ListingReportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LocationListingController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\SavedPropertyController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Support\Cities;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/buy', [CategoryController::class, 'buy'])->name('properties.buy');
Route::get('/rent', [CategoryController::class, 'rent'])->name('properties.rent');
Route::get('/plots', [CategoryController::class, 'plots'])->name('properties.plots');
Route::get('/commercial', [CategoryController::class, 'commercial'])->name('properties.commercial');

// The shared search bar (Buy | Rent | Plots | Commercial) submits here; it redirects to the results.
Route::get('/search', SearchController::class)->name('search');
Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
// Investment intelligence (published ranking snapshots only).
Route::get('/investment', [InvestmentController::class, 'index'])->name('investment.index');
Route::get('/investment/compare', [InvestmentController::class, 'compare'])->name('investment.compare');
Route::get('/investment/methodology', [InvestmentController::class, 'methodology'])->name('investment.methodology');
Route::get('/investment/rental-yield-calculator', [InvestmentController::class, 'yieldCalculator'])->name('investment.yield');
Route::get('/investment/{entityType}/{entityId}', [InvestmentController::class, 'show'])->whereIn('entityType', ['city', 'area', 'project'])->whereNumber('entityId')->name('investment.show');

// Investment Rankings. The Meezan Investment Index: five Pakistan-wide leaderboards, the Overall Index,
// project profiles and the methodology. The MPIS dashboards keep their addresses beside it: all of
// Pakistan (/pakistan), cities, groups, the five development categories and compare, verified data only.
Route::prefix('investment-rankings')->name('rankings.')->group(function () {
    $cities = array_merge(['pakistan'], array_map([Cities::class, 'slug'], Cities::names()));
    $groups = array_keys(config('meezan.investment.groups'));
    Route::controller(InvestmentIndexController::class)->group(function () {
        Route::get('/', 'home')->name('index');
        Route::get('/overall', 'overall')->name('overall');
        Route::get('/methodology', 'methodology')->name('methodology');
        Route::get('/projects/{project}', 'project')->name('project');
        Route::get('/{category}', 'category')->whereIn('category', array_keys(config('investment_index.categories')))->name('category');
        Route::get('/{category}/compare', 'compare')->whereIn('category', array_keys(config('investment_index.categories')))->name('category-compare');
        Route::get('/{city}/{category}', 'cityCategory')->whereIn('city', array_map([Cities::class, 'slug'], Cities::names()))->whereIn('category', array_keys(config('investment_index.categories')))->name('city-category');
    });
    Route::controller(InvestmentRankingsController::class)->group(function () use ($cities, $groups) {
        Route::get('/advisor', 'advisor')->middleware('throttle:30,1')->name('advisor');
        Route::get('/{city}', 'city')->whereIn('city', $cities)->name('city');
        Route::get('/{city}/{group}', 'group')->whereIn('city', $cities)->whereIn('group', $groups)->name('group');
        Route::get('/{city}/{group}/{segment}', 'segment')->whereIn('city', $cities)->whereIn('group', $groups)->name('segment');
        Route::get('/{city}/{group}/{segment}/compare', 'compare')->whereIn('city', $cities)->whereIn('group', $groups)->name('compare');
    });
});

Route::get('/map', [MapController::class, 'page'])->name('properties.map');
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/map/data', [MapController::class, 'data'])->name('properties.map.data');
    Route::get('/map/listings/{property}', [MapController::class, 'preview'])->name('properties.map.preview');
    Route::get('/map/boundaries', [MapController::class, 'boundaries'])->name('map.boundaries');
    Route::get('/map/boundaries/{boundary}', [MapController::class, 'boundary'])->whereNumber('boundary')->name('map.boundaries.show');
});
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
Route::get('/properties/{property}/nearby', [PropertyController::class, 'nearby'])->middleware('throttle:30,1')->name('properties.nearby');
Route::post('/properties/{property}/inquiries', [InquiryController::class, 'store'])->middleware('throttle:inquiries')->name('inquiries.store');
Route::post('/properties/{property}/save', [SavedPropertyController::class, 'toggle'])->name('properties.save');
Route::post('/properties/{property}/report', [ListingReportController::class, 'store'])->middleware('throttle:inquiries')->name('properties.report');

Route::get('/compare', [CompareController::class, 'index'])->name('compare');
Route::post('/compare/clear', [CompareController::class, 'clear'])->name('compare.clear');
Route::post('/compare/{property}', [CompareController::class, 'toggle'])->name('compare.toggle');

Route::get('/agents', [DealerDirectoryController::class, 'index'])->name('dealers.index');
Route::get('/agents/{dealer:slug}', [DealerDirectoryController::class, 'show'])->name('dealers.show');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
// Owners and managers submit documented evidence for the Meezan Investment Index (staff-checked).
Route::middleware('auth')->prefix('projects/{project}/evidence')->name('investment-index.evidence.')->controller(InvestmentEvidenceController::class)->group(function () {
    Route::get('/', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:10,60')->name('store');
});

Route::get('/areas', [AreaGuideController::class, 'index'])->name('areas.index');
Route::get('/areas/{area:slug}', [AreaGuideController::class, 'show'])->name('areas.show');
Route::get('/areas/{area:slug}/{category}', [LocationListingController::class, 'area'])
    ->whereIn('category', array_keys(config('meezan.seo.categories')))
    ->name('areas.category');

Route::get('/sell', [SellController::class, 'index'])->name('sell');

Route::get('/ai-property-finder', [AiFinderController::class, 'show'])->name('ai-finder');
Route::post('/ai-property-finder', [AiFinderController::class, 'match'])->middleware('throttle:ai-finder')->name('ai-finder.match');
Route::post('/ai-property-finder/reset', [AiFinderController::class, 'reset'])->middleware('throttle:ai-finder')->name('ai-finder.reset');
Route::post('/ai-property-finder/save', [AiFinderController::class, 'save'])->middleware(['auth', 'role:buyer', 'throttle:ai-finder'])->name('ai-finder.save');
Route::post('/ai-property-finder/contact', [AiFinderController::class, 'contact'])->middleware(['auth', 'role:buyer', 'throttle:ai-finder'])->name('ai-finder.contact');

Route::get('/about', [PageController::class, 'about'])->name('pages.about');
Route::get('/how-it-works', [PageController::class, 'howItWorks'])->name('pages.how-it-works');
Route::get('/contact', [PageController::class, 'contact'])->name('pages.contact');
Route::post('/contact', [PageController::class, 'sendContact'])->middleware('throttle:inquiries')->name('pages.contact.send');
Route::get('/terms', [PageController::class, 'terms'])->name('pages.terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('pages.privacy');
Route::get('/disclaimer', [PageController::class, 'disclaimer'])->name('pages.disclaimer');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemaps/{name}.xml', [SitemapController::class, 'show'])->where('name', 'pages|locations|properties-[1-9][0-9]{0,3}')->name('sitemap.show');

Route::get('/locations/suggest', [LocationController::class, 'suggest'])->middleware('throttle:120,1')->name('locations.suggest');
Route::get('/locations/options', [LocationController::class, 'options'])->middleware('throttle:120,1')->name('locations.options');
Route::get('/locations/{area}/children', [LocationController::class, 'children'])->middleware('throttle:120,1')->whereNumber('area')->name('locations.children');

// City landing pages: /lahore, /karachi … (slugs fixed in config, so the route stays cacheable).
Route::get('/{city}', CityController::class)
    ->whereIn('city', array_column(config('meezan.city_details'), 'slug'))
    ->name('cities.show');
// City category landing pages: /lahore/houses-for-sale … (both segments fixed in config).
Route::get('/{city}/{category}', [LocationListingController::class, 'city'])
    ->whereIn('city', array_column(config('meezan.city_details'), 'slug'))
    ->whereIn('category', array_keys(config('meezan.seo.categories')))
    ->name('cities.category');

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:120,1')->name('webhooks.whatsapp');
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)->whereAlphaNumeric('gateway')->middleware('throttle:120,1')->name('webhooks.payments');
Route::get('/pricing', PricingController::class)->name('pricing');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:registrations')->name('register.store');

    Route::get('/dealer/register', [DealerRegistrationController::class, 'create'])->name('dealer.register');
    Route::post('/dealer/register', [DealerRegistrationController::class, 'store'])->middleware('throttle:registrations')->name('dealer.register.store');
    Route::redirect('/dealer/login', '/login')->name('dealer.login');

    Route::get('/sell/owner', [SellController::class, 'createOwner'])->name('sell.owner');
    Route::post('/sell/owner', [SellController::class, 'storeOwner'])->middleware('throttle:registrations')->name('sell.owner.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

// In-app notification centre (all roles).
Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('/read-all', [NotificationController::class, 'readAll'])->name('read-all');
    Route::get('/{id}', [NotificationController::class, 'open'])->whereUuid('id')->name('open');
});

// Email confirmation (Laravel's signed-link flow).
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Buyer account
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:buyer'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', Account\DashboardController::class)->name('dashboard');
    Route::get('/saved-properties', [SavedPropertyController::class, 'index'])->name('saved');
    Route::resource('requirements', Account\RequirementController::class)->except('show')
        ->middlewareFor(['store', 'update'], 'verified.required');
    Route::get('/requirements/{requirement}/matches', [Account\RequirementController::class, 'matches'])->name('requirements.matches');
    Route::get('/inquiries', Account\InquiryController::class)->name('inquiries');
    Route::get('/profile', [Account\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [Account\ProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings', [Account\ProfileController::class, 'settings'])->name('settings');
    Route::put('/settings/notifications', [Account\ProfileController::class, 'updateNotifications'])->name('notifications.update');
    Route::get('/saved-searches', [Account\SavedSearchController::class, 'index'])->name('saved-searches.index');
    Route::post('/saved-searches', [Account\SavedSearchController::class, 'store'])->name('saved-searches.store');
    Route::patch('/saved-searches/{savedSearch}', [Account\SavedSearchController::class, 'update'])->name('saved-searches.update');
    Route::delete('/saved-searches/{savedSearch}', [Account\SavedSearchController::class, 'destroy'])->name('saved-searches.destroy');
    Route::get('/saved-searches/{savedSearch}/open', [Account\SavedSearchController::class, 'open'])->name('saved-searches.open');
    Route::get('/alerts', [Account\SavedSearchController::class, 'alerts'])->name('alerts');
    Route::post('/alerts/seen', [Account\SavedSearchController::class, 'markAllSeen'])->name('alerts.seen');
    Route::put('/settings/password', [Account\ProfileController::class, 'updatePassword'])->name('password.update');
    Route::post('/settings/whatsapp-link', [Account\ProfileController::class, 'whatsappLinkCode'])->middleware('throttle:5,1')->name('whatsapp.link');
});

/*
|--------------------------------------------------------------------------
| Dealer portal
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:dealer'])->prefix('dealer')->name('dealer.')->group(function () {
    Route::get('/dashboard', Dealer\DashboardController::class)->name('dashboard');
    Route::resource('properties', Dealer\PropertyController::class)->except('show')
        ->middlewareFor(['store', 'update'], 'verified.required');
    Route::post('/properties/{property}/submit', [Dealer\PropertyController::class, 'submit'])->middleware('verified.required')->name('properties.submit');
    Route::post('/properties/{property}/renew', [Dealer\PropertyController::class, 'renew'])->middleware('verified.required')->name('properties.renew');
    Route::get('/verification', [Dealer\VerificationController::class, 'index'])->name('verification');
    Route::post('/verification/submit', [Dealer\VerificationController::class, 'submit'])->middleware('throttle:10,1')->name('verification.submit');
    Route::post('/verification/documents', [Dealer\VerificationController::class, 'store'])->middleware('throttle:10,1')->name('documents.store');
    Route::get('/verification/documents/{document}', [Dealer\VerificationController::class, 'show'])->name('documents.show');
    Route::delete('/verification/documents/{document}', [Dealer\VerificationController::class, 'destroy'])->name('documents.destroy');
    Route::get('/location-search', Dealer\LocationSearchController::class)->middleware('throttle:location-search')->name('location-search');
    Route::get('/leads', [Dealer\LeadController::class, 'index'])->name('leads');
    Route::get('/performance', [Dealer\PerformanceController::class, 'index'])->name('performance');
    Route::get('/performance/export', [Dealer\PerformanceController::class, 'export'])->name('performance.export');
    Route::patch('/inquiries/{inquiry}', [Dealer\LeadController::class, 'updateInquiry'])->name('inquiries.update');
    Route::patch('/leads/{assignment}', [Dealer\LeadController::class, 'updateAssignment'])->name('assignments.update');
    Route::get('/profile', [Dealer\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [Dealer\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [Dealer\ProfileController::class, 'updatePassword'])->name('password.update');
    Route::post('/properties/{property}/feature', [Dealer\PropertyController::class, 'feature'])->name('properties.feature');

    // Lead CRM: every route works on the dealer's own active assignment (LeadAssignmentPolicy).
    Route::get('/crm', [Dealer\CrmController::class, 'dashboard'])->name('crm');
    Route::prefix('crm')->name('crm.')->group(function () {
        Route::get('/pipeline', [Dealer\CrmController::class, 'pipeline'])->name('pipeline');
        Route::get('/leads', [Dealer\CrmController::class, 'index'])->name('leads');
        Route::get('/agenda', [Dealer\CrmController::class, 'agenda'])->name('agenda');
        Route::get('/leads/{assignment}', [Dealer\CrmController::class, 'show'])->name('leads.show');
        Route::middleware('throttle:120,1')->group(function () {
            Route::patch('/leads/{assignment}/stage', [Dealer\CrmController::class, 'updateStage'])->name('leads.stage');
            Route::patch('/leads/{assignment}/priority', [Dealer\CrmController::class, 'updatePriority'])->name('leads.priority');
            Route::post('/leads/{assignment}/notes', [Dealer\CrmController::class, 'storeNote'])->name('notes.store');
            Route::patch('/leads/{assignment}/notes/{note}', [Dealer\CrmController::class, 'updateNote'])->whereNumber('note')->name('notes.update');
            Route::post('/leads/{assignment}/follow-ups', [Dealer\CrmController::class, 'storeFollowUp'])->name('follow-ups.store');
            Route::patch('/leads/{assignment}/follow-ups/{followUp}', [Dealer\CrmController::class, 'finishFollowUp'])->whereNumber('followUp')->name('follow-ups.update');
            Route::post('/leads/{assignment}/appointments', [Dealer\CrmController::class, 'storeAppointment'])->name('appointments.store');
            Route::patch('/leads/{assignment}/appointments/{appointment}', [Dealer\CrmController::class, 'updateAppointment'])->whereNumber('appointment')->name('appointments.update');
            Route::post('/leads/{assignment}/recommendations', [Dealer\CrmController::class, 'storeRecommendation'])->name('recommendations.store');
            Route::patch('/leads/{assignment}/recommendations/{recommendation}', [Dealer\CrmController::class, 'updateRecommendation'])->whereNumber('recommendation')->name('recommendations.update');
        });
    });

    Route::get('/subscription', [Dealer\SubscriptionController::class, 'index'])->name('subscription');
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/packages', [Dealer\SubscriptionController::class, 'packages'])->name('packages');
        Route::get('/packages/{package:slug}', [Dealer\SubscriptionController::class, 'checkout'])->name('checkout');
        Route::post('/packages/{package:slug}', [Dealer\SubscriptionController::class, 'choose'])->middleware('throttle:10,1')->name('choose');
        Route::post('/subscription/cancel', [Dealer\SubscriptionController::class, 'cancel'])->name('cancel');
        Route::post('/subscription/resume', [Dealer\SubscriptionController::class, 'resume'])->name('resume');
        Route::get('/invoices/{invoice}', [Dealer\BillingController::class, 'invoice'])->whereNumber('invoice')->name('invoices.show');
        Route::post('/invoices/{invoice}/pay', [Dealer\BillingController::class, 'pay'])->whereNumber('invoice')->middleware('throttle:10,1')->name('invoices.pay');
        Route::get('/payments/{payment}', [Dealer\BillingController::class, 'payment'])->whereNumber('payment')->name('payments.show');
        Route::post('/payments/{payment}/transfer', [Dealer\BillingController::class, 'transfer'])->whereNumber('payment')->middleware('throttle:10,1')->name('payments.transfer');
        Route::get('/payments/{payment}/proof', [Dealer\BillingController::class, 'proof'])->whereNumber('payment')->name('payments.proof');
        Route::get('/test-checkout/{payment}', [Dealer\BillingController::class, 'mock'])->whereNumber('payment')->name('mock');
        Route::post('/test-checkout/{payment}', [Dealer\BillingController::class, 'mockComplete'])->whereNumber('payment')->middleware('throttle:20,1')->name('mock.complete');
    });
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/status', [Admin\UserController::class, 'updateStatus'])->name('users.status');

    Route::get('/dealers', [Admin\DealerController::class, 'index'])->name('dealers.index');
    Route::get('/dealers/{dealer}', [Admin\DealerController::class, 'show'])->name('dealers.show');
    Route::patch('/dealers/{dealer}/verification', [Admin\DealerController::class, 'updateVerification'])->name('dealers.verification');
    Route::patch('/dealers/{dealer}/capacity', [Admin\DealerController::class, 'updateCapacity'])->name('dealers.capacity');
    Route::get('/dealer-documents/{document}', [Admin\DealerDocumentController::class, 'show'])->name('dealers.documents.show');
    Route::patch('/dealer-documents/{document}/review', [Admin\DealerController::class, 'reviewDocument'])->name('dealers.documents.review');
    Route::patch('/dealers/{dealer}/note', [Admin\DealerController::class, 'updateNote'])->name('dealers.note');

    Route::get('/properties', [Admin\PropertyController::class, 'index'])->name('properties.index');
    Route::get('/properties-duplicates', [Admin\PropertyController::class, 'duplicates'])->name('properties.duplicates');
    Route::get('/properties/{property}', [Admin\PropertyController::class, 'show'])->withTrashed()->name('properties.show');
    Route::patch('/properties/{property}/moderate', [Admin\PropertyController::class, 'moderate'])->name('properties.moderate');
    Route::patch('/properties/{property}/feature', [Admin\PropertyController::class, 'feature'])->name('properties.feature');
    Route::patch('/properties/{property}/review', [Admin\PropertyController::class, 'review'])->name('properties.review');
    Route::patch('/property-images/{image}/moderate', [Admin\PropertyController::class, 'moderateImage'])->name('properties.images.moderate');
    Route::get('/trust', Admin\TrustDashboardController::class)->name('trust');

    Route::get('/leads', [Admin\LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/{lead}', [Admin\LeadController::class, 'show'])->name('leads.show');
    Route::post('/leads/{lead}/reassign', [Admin\LeadController::class, 'reassign'])->name('leads.reassign');
    Route::patch('/leads/{lead}/status', [Admin\LeadController::class, 'updateStatus'])->name('leads.status');
    Route::patch('/leads/{lead}/validity', [Admin\LeadController::class, 'updateValidity'])->name('leads.validity');
    Route::post('/leads/{lead}/assignments', [Admin\LeadController::class, 'addDealer'])->name('leads.assignments.store');
    Route::delete('/lead-assignments/{assignment}', [Admin\LeadController::class, 'removeAssignment'])->name('leads.assignments.destroy');
    Route::get('/crm', Admin\CrmOversightController::class)->name('crm');

    Route::get('/conversations', [Admin\ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [Admin\ConversationController::class, 'show'])->name('conversations.show');
    Route::patch('/conversations/{conversation}/mode', [Admin\ConversationController::class, 'updateMode'])->name('conversations.mode');
    Route::post('/conversations/{conversation}/resolve', [Admin\ConversationController::class, 'resolve'])->name('conversations.resolve');
    Route::post('/conversations/{conversation}/reveal', [Admin\ConversationController::class, 'revealContact'])->name('conversations.reveal');
    Route::post('/conversations/{conversation}/reply', [Admin\ConversationController::class, 'reply'])->middleware('throttle:30,1')->name('conversations.reply');
    Route::post('/conversations/{conversation}/handoff', [Admin\ConversationController::class, 'handoff'])->name('conversations.handoff');
    Route::patch('/conversations/{conversation}/note', [Admin\ConversationController::class, 'note'])->name('conversations.note');

    Route::get('/whatsapp', [Admin\WhatsAppController::class, 'index'])->name('whatsapp');
    Route::get('/whatsapp/messages', [Admin\WhatsAppController::class, 'messages'])->name('whatsapp.messages');
    Route::patch('/whatsapp/settings', [Admin\WhatsAppController::class, 'updateSettings'])->name('whatsapp.settings');
    Route::post('/whatsapp/connection-test', [Admin\WhatsAppController::class, 'testConnection'])->middleware('throttle:10,1')->name('whatsapp.test');
    Route::post('/whatsapp/test-send', [Admin\WhatsAppController::class, 'testSend'])->middleware('throttle:5,1')->name('whatsapp.test-send');
    Route::post('/whatsapp/messages/{message}/retry', [Admin\WhatsAppController::class, 'retry'])->name('whatsapp.retry');
    Route::post('/whatsapp/simulate', [Admin\WhatsAppController::class, 'simulate'])->middleware('throttle:60,1')->name('whatsapp.simulate');

    Route::get('/reports', [Admin\ListingReportController::class, 'index'])->name('reports.index');
    Route::patch('/reports/{report}', [Admin\ListingReportController::class, 'resolve'])->name('reports.resolve');

    Route::get('/projects/import', [Admin\ProjectController::class, 'importForm'])->name('projects.import');
    Route::post('/projects/import', [Admin\ProjectController::class, 'import'])->middleware('throttle:10,1')->name('projects.import.store');
    Route::get('/projects/template.csv', [Admin\ProjectController::class, 'template'])->name('projects.template');
    Route::resource('projects', Admin\ProjectController::class)->except(['show', 'destroy']);

    Route::get('/areas', [Admin\AreaController::class, 'index'])->name('areas.index');
    Route::post('/areas', [Admin\AreaController::class, 'store'])->name('areas.store');
    Route::patch('/areas/{area}', [Admin\AreaController::class, 'update'])->name('areas.update');
    Route::get('/areas/{area}/edit', [Admin\AreaController::class, 'edit'])->name('areas.edit');
    Route::post('/areas/{area}/merge', [Admin\AreaController::class, 'merge'])->name('areas.merge');

    Route::get('/boundaries', [Admin\BoundaryController::class, 'index'])->name('boundaries.index');
    Route::get('/boundaries/create', [Admin\BoundaryController::class, 'create'])->name('boundaries.create');
    Route::post('/boundaries', [Admin\BoundaryController::class, 'store'])->name('boundaries.store');
    Route::get('/boundaries/{boundary}/edit', [Admin\BoundaryController::class, 'edit'])->name('boundaries.edit');
    Route::patch('/boundaries/{boundary}', [Admin\BoundaryController::class, 'update'])->name('boundaries.update');
    Route::patch('/boundaries/{boundary}/status', [Admin\BoundaryController::class, 'transition'])->name('boundaries.transition');
    Route::post('/boundaries/{boundary}/duplicate', [Admin\BoundaryController::class, 'duplicate'])->name('boundaries.duplicate');

    // Market Intelligence & Data Centre
    Route::prefix('market')->name('market.')->group(function () {
        Route::get('/', Admin\MarketController::class)->name('index');
        Route::resource('sources', Admin\MarketSourceController::class)->except(['show', 'destroy']);
        Route::get('/categories', [Admin\InvestmentAdminController::class, 'categories'])->name('categories');
        Route::get('/demo', [Admin\InvestmentDemoController::class, 'index'])->name('demo');
        Route::post('/demo/run', [Admin\InvestmentDemoController::class, 'run'])->middleware('throttle:5,1')->name('demo.run');
        Route::post('/demo/mode', [Admin\InvestmentDemoController::class, 'mode'])->name('demo.mode');
        Route::post('/demo/remove', [Admin\InvestmentDemoController::class, 'remove'])->name('demo.remove');
        Route::get('/methodologies', [Admin\InvestmentAdminController::class, 'methodologies'])->name('methodologies.index');
        Route::post('/methodologies', [Admin\InvestmentAdminController::class, 'createMethodology'])->name('methodologies.store');
        Route::get('/methodologies/{methodology}/edit', [Admin\InvestmentAdminController::class, 'editMethodology'])->name('methodologies.edit');
        Route::patch('/methodologies/{methodology}', [Admin\InvestmentAdminController::class, 'updateMethodology'])->name('methodologies.update');
        Route::post('/methodologies/{methodology}/activate', [Admin\InvestmentAdminController::class, 'activateMethodology'])->name('methodologies.activate');
        Route::get('/calculations', [Admin\InvestmentAdminController::class, 'calculations'])->name('calculations.index');
        Route::post('/calculations', [Admin\InvestmentAdminController::class, 'runCalculation'])->middleware('throttle:10,1')->name('calculations.store');
        Route::get('/calculations/{calculation}', [Admin\InvestmentAdminController::class, 'showCalculation'])->name('calculations.show');
        Route::post('/calculations/{calculation}/publish', [Admin\InvestmentAdminController::class, 'publish'])->name('calculations.publish');

        Route::whereIn('type', ['observations', 'indicators'])->group(function () {
            Route::get('/records/{type}', [Admin\MarketRecordController::class, 'index'])->name('records.index');
            Route::get('/records/{type}/create', [Admin\MarketRecordController::class, 'create'])->name('records.create');
            Route::post('/records/{type}', [Admin\MarketRecordController::class, 'store'])->name('records.store');
            Route::get('/records/{type}/import', [Admin\MarketRecordController::class, 'importForm'])->name('records.import');
            Route::post('/records/{type}/import', [Admin\MarketRecordController::class, 'import'])->middleware('throttle:10,1')->name('records.import.store');
            Route::get('/records/{type}/template.csv', [Admin\MarketRecordController::class, 'template'])->name('records.template');
            Route::get('/records/{type}/{id}/edit', [Admin\MarketRecordController::class, 'edit'])->whereNumber('id')->name('records.edit');
            Route::patch('/records/{type}/{id}', [Admin\MarketRecordController::class, 'update'])->whereNumber('id')->name('records.update');
            Route::patch('/records/{type}/{id}/status', [Admin\MarketRecordController::class, 'transition'])->whereNumber('id')->name('records.transition');
        });
    });

    // Meezan Investment Index: sources, collection, official registry, exceptions, owner evidence review
    Route::prefix('investment-index')->name('investment-index.')->controller(Admin\InvestmentIndexController::class)->group(function () {
        Route::get('/', 'overview')->name('overview');
        Route::get('/sources', 'sources')->name('sources');
        Route::post('/sources/setup', 'setupSources')->name('sources.setup');
        Route::patch('/sources/{source}/access', 'authorizeSource')->name('sources.authorize');
        Route::post('/sources/{source}/collect', 'collect')->middleware('throttle:6,1')->name('sources.collect');
        Route::get('/registry', 'registry')->name('registry');
        Route::post('/registry/{entry}/match', 'match')->name('registry.match');
        Route::get('/exceptions', 'exceptions')->name('exceptions');
        Route::post('/exceptions/{exception}/resolve', 'resolve')->name('exceptions.resolve');
        Route::get('/submissions', 'submissions')->name('submissions');
        Route::get('/submissions/{submission}', 'submission')->name('submissions.show');
        Route::get('/submissions/{submission}/files/{index}', 'file')->whereNumber('index')->name('submissions.file');
        Route::post('/submissions/{submission}/review', 'review')->name('submissions.review');
        Route::get('/methodology', 'methodology')->name('methodology');
        Route::post('/methodology', 'createDraft')->name('methodology.draft');
        Route::patch('/methodology/{methodology}', 'updateDraft')->name('methodology.update');
        Route::post('/methodology/{methodology}/activate', 'activate')->name('methodology.activate');
    });

    Route::prefix('investment-index')->name('investment-index.')->controller(Admin\InvestmentCoverageController::class)->group(function () {
        Route::get('/coverage', 'coverage')->name('coverage');
        Route::get('/verification', 'verification')->name('verification');
        Route::post('/verification/legal', 'storeLegal')->name('legal.store');
        Route::post('/verification/import', 'import')->middleware('throttle:10,1')->name('import');
        Route::post('/verification/source/{source}', 'reviewSource')->whereNumber('source')->middleware('throttle:10,1')->name('review-source');
        Route::post('/verification/{kind}/{id}', 'review')->whereIn('kind', ['legal', 'evidence'])->whereNumber('id')->name('review');
        Route::get('/validation', 'validation')->name('validation');
        Route::get('/research', 'research')->name('research');
        Route::post('/research/{project}/request', 'requestResearch')->whereNumber('project')->middleware('throttle:60,1')->name('research.request');
        Route::patch('/research/provisional', 'provisionalSwitch')->name('research.provisional');
        Route::post('/validation/alias', 'storeAlias')->name('alias.store');
        Route::get('/evidence-gaps', 'evidenceGaps')->name('evidence-gaps');
    });

    // AI Command Center (Central AI Brain and agent workforce)
    Route::prefix('ai')->name('ai.')->controller(Admin\AiCommandCenterController::class)->group(function () {
        Route::get('/', 'overview')->name('overview');
        Route::get('/agents', 'agents')->name('agents.index');
        Route::get('/agents/{agent}', 'agent')->name('agents.show');
        Route::patch('/agents/{agent}', 'updateAgent')->name('agents.update');
        Route::get('/tasks', 'tasks')->name('tasks.index');
        Route::post('/tasks', 'runTask')->middleware('throttle:20,1')->name('tasks.store');
        Route::get('/tasks/{task}', 'task')->name('tasks.show');
        Route::get('/tasks/{task}/export', 'exportTask')->name('tasks.export');
        Route::post('/tasks/{task}/retry', 'retryTask')->middleware('throttle:20,1')->name('tasks.retry');
        Route::post('/tasks/{task}/cancel', 'cancelTask')->name('tasks.cancel');
        Route::get('/approvals', 'approvals')->name('approvals.index');
        Route::post('/approvals/{approval}', 'decide')->name('approvals.decide');
        Route::get('/usage', 'usage')->name('usage');
        Route::put('/usage/pricing', 'updatePricing')->name('usage.pricing');
        Route::get('/knowledge', 'knowledge')->name('knowledge');
        Route::post('/knowledge', 'storeDocument')->name('knowledge.store');
        Route::patch('/knowledge/{document}', 'documentStatus')->name('knowledge.status');
        Route::patch('/memory/{memory}', 'memoryDecision')->name('memory.decide');
        Route::post('/memory', 'storeMemory')->name('memory.store');
        Route::get('/work', 'work')->name('work');
        Route::patch('/tickets/{ticket}', 'updateTicket')->name('tickets.update');
        Route::patch('/staff-tasks/{staffTask}', 'updateStaffTask')->name('staff-tasks.update');
        Route::patch('/drafts/{draft}', 'updateDraft')->name('drafts.update');
        Route::get('/tools', 'tools')->name('tools');
        Route::get('/logs', 'logs')->name('logs');
        Route::get('/providers', 'providers')->name('providers');
        Route::patch('/providers/{provider}', 'updateProvider')->whereIn('provider', array_keys(config('meezan.agents.adapters')))->name('providers.update');
        Route::post('/providers/{provider}/test', 'testProvider')->whereIn('provider', array_keys(config('meezan.agents.adapters')))->middleware('throttle:10,1')->name('providers.test');
        Route::patch('/controls', 'updateControls')->name('controls');
        Route::get('/connectors', 'connectors')->name('connectors');
        Route::post('/connectors/{key}/check', 'checkConnector')->middleware('throttle:10,1')->where('key', '[a-z_]+')->name('connectors.check');
    });

    Route::get('/billing', [Admin\BillingController::class, 'dashboard'])->name('billing');
    Route::resource('packages', Admin\PackageController::class)->except(['show', 'destroy']);
    Route::patch('/packages/{package}/toggle', [Admin\PackageController::class, 'toggle'])->name('packages.toggle');
    Route::get('/subscriptions', [Admin\BillingController::class, 'subscriptions'])->name('subscriptions');
    Route::get('/subscriptions/{subscription}', [Admin\BillingController::class, 'subscription'])->name('subscriptions.show');
    Route::patch('/subscriptions/{subscription}', [Admin\BillingController::class, 'updateSubscription'])->name('subscriptions.update');
    Route::get('/payments', [Admin\BillingController::class, 'payments'])->name('payments');
    Route::get('/payments/{payment}', [Admin\BillingController::class, 'payment'])->name('payments.show');
    Route::patch('/payments/{payment}/review', [Admin\BillingController::class, 'reviewPayment'])->name('payments.review');
    Route::get('/payments/{payment}/proof', [Admin\BillingController::class, 'proof'])->name('payments.proof');
    Route::get('/invoices/{invoice}', [Admin\BillingController::class, 'invoice'])->name('invoices.show');
    Route::post('/invoices/{invoice}/refunds', [Admin\BillingController::class, 'refund'])->name('invoices.refund');
    Route::post('/dealers/{dealer}/billing-adjustments', [Admin\BillingController::class, 'adjustment'])->name('billing.adjustments');
    Route::get('/analytics', Admin\AnalyticsController::class)->name('analytics');
    Route::get('/settings', Admin\SettingsController::class)->name('settings');
    Route::get('/audit-logs', Admin\AuditLogController::class)->name('audit-logs');
});
