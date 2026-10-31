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
use App\Http\Controllers\ListingReportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\SavedPropertyController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
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

Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/map', [PropertyController::class, 'map'])->name('properties.map');
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

Route::get('/areas', [AreaGuideController::class, 'index'])->name('areas.index');
Route::get('/areas/{area:slug}', [AreaGuideController::class, 'show'])->name('areas.show');

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

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/locations/suggest', [LocationController::class, 'suggest'])->middleware('throttle:120,1')->name('locations.suggest');
Route::get('/locations/options', [LocationController::class, 'options'])->middleware('throttle:120,1')->name('locations.options');
Route::get('/locations/{area}/children', [LocationController::class, 'children'])->middleware('throttle:120,1')->whereNumber('area')->name('locations.children');

// City landing pages: /lahore, /karachi … (slugs fixed in config, so the route stays cacheable).
Route::get('/{city}', CityController::class)
    ->whereIn('city', array_column(config('meezan.city_details'), 'slug'))
    ->name('cities.show');

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:120,1')->name('webhooks.whatsapp');

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
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
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
    Route::get('/subscription', Dealer\SubscriptionController::class)->name('subscription');
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

    Route::get('/conversations', [Admin\ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [Admin\ConversationController::class, 'show'])->name('conversations.show');
    Route::patch('/conversations/{conversation}/mode', [Admin\ConversationController::class, 'updateMode'])->name('conversations.mode');
    Route::post('/conversations/{conversation}/resolve', [Admin\ConversationController::class, 'resolve'])->name('conversations.resolve');
    Route::post('/conversations/{conversation}/reveal', [Admin\ConversationController::class, 'revealContact'])->name('conversations.reveal');

    Route::get('/reports', [Admin\ListingReportController::class, 'index'])->name('reports.index');
    Route::patch('/reports/{report}', [Admin\ListingReportController::class, 'resolve'])->name('reports.resolve');

    Route::resource('projects', Admin\ProjectController::class)->except(['show', 'destroy']);

    Route::get('/areas', [Admin\AreaController::class, 'index'])->name('areas.index');
    Route::post('/areas', [Admin\AreaController::class, 'store'])->name('areas.store');
    Route::patch('/areas/{area}', [Admin\AreaController::class, 'update'])->name('areas.update');
    Route::get('/areas/{area}/edit', [Admin\AreaController::class, 'edit'])->name('areas.edit');
    Route::post('/areas/{area}/merge', [Admin\AreaController::class, 'merge'])->name('areas.merge');

    Route::get('/subscriptions', [Admin\BillingController::class, 'subscriptions'])->name('subscriptions');
    Route::get('/payments', [Admin\BillingController::class, 'payments'])->name('payments');
    Route::get('/analytics', Admin\AnalyticsController::class)->name('analytics');
    Route::get('/settings', Admin\SettingsController::class)->name('settings');
    Route::get('/audit-logs', Admin\AuditLogController::class)->name('audit-logs');
});
