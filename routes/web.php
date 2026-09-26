<?php

use App\Http\Controllers\Account\BookingController as AccountBookingController;
use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Account\HotelReviewController;
use App\Http\Controllers\Account\SupportTicketController as AccountSupportTicketController;
use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\AdminSearchController;
use App\Http\Controllers\Admin\AgentActionController;
use App\Http\Controllers\Admin\AiContentController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BookingManagerController;
use App\Http\Controllers\Admin\BookingNoteController as AdminBookingNoteController;
use App\Http\Controllers\Admin\BookingPaymentController as AdminBookingPaymentController;
use App\Http\Controllers\Admin\BookingRescheduleController as AdminBookingRescheduleController;
use App\Http\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Admin\CityController as AdminCityController;
use App\Http\Controllers\Admin\CommunicationLogController as AdminCommunicationLogController;
use App\Http\Controllers\Admin\ContentTranslationController as AdminContentTranslationController;
use App\Http\Controllers\Admin\CountryController as AdminCountryController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CrmDashboardController as AdminCrmDashboardController;
use App\Http\Controllers\Admin\CurrencyController as AdminCurrencyController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DestinationController as AdminDestinationController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\EnquiryController as AdminEnquiryController;
use App\Http\Controllers\Admin\ExchangeRateController as AdminExchangeRateController;
use App\Http\Controllers\Admin\FailedJobController as AdminFailedJobController;
use App\Http\Controllers\Admin\FollowUpController as AdminFollowUpController;
use App\Http\Controllers\Admin\HomepageSectionController;
use App\Http\Controllers\Admin\Hotel\CatalogueController;
use App\Http\Controllers\Admin\Hotel\ChargeRuleController;
use App\Http\Controllers\Admin\Hotel\CustomFieldController;
use App\Http\Controllers\Admin\Hotel\HotelSettingsController;
use App\Http\Controllers\Admin\Hotel\RoomController;
use App\Http\Controllers\Admin\Hotel\UnitController;
use App\Http\Controllers\Admin\ImpersonationController as AdminImpersonationController;
use App\Http\Controllers\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Admin\KnowledgeAgentController;
use App\Http\Controllers\Admin\KnowledgeController;
use App\Http\Controllers\Admin\LanguageController as AdminLanguageController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\LeadSourceController as AdminLeadSourceController;
use App\Http\Controllers\Admin\LocationLookupController as AdminLocationLookupController;
use App\Http\Controllers\Admin\McpAccessController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\MessageController as AdminMessageController;
use App\Http\Controllers\Admin\ModuleController as AdminModuleController;
use App\Http\Controllers\Admin\NumberSeriesController as AdminNumberSeriesController;
use App\Http\Controllers\Admin\PackageManagerController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PayoutAccountController as AdminPayoutAccountController;
use App\Http\Controllers\Admin\PlaceController;
use App\Http\Controllers\Admin\PromotionalPopupController;
use App\Http\Controllers\Admin\QuotationController as AdminQuotationController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShareController as AdminShareController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\StateController as AdminStateController;
use App\Http\Controllers\Admin\SupportCategoryController as AdminSupportCategoryController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\SystemHealthController as AdminSystemHealthController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\Taxi\DriverController as AdminTaxiDriverController;
use App\Http\Controllers\Admin\Taxi\TaxiBookingController as AdminTaxiBookingController;
use App\Http\Controllers\Admin\Taxi\TaxiCompensationPlanController as AdminTaxiCompensationPlanController;
use App\Http\Controllers\Admin\Taxi\TaxiDashboardController as AdminTaxiDashboardController;
use App\Http\Controllers\Admin\Taxi\TaxiDispatchController as AdminTaxiDispatchController;
use App\Http\Controllers\Admin\Taxi\TaxiDriverEarningController as AdminTaxiDriverEarningController;
use App\Http\Controllers\Admin\Taxi\TaxiDriverPayoutController as AdminTaxiDriverPayoutController;
use App\Http\Controllers\Admin\Taxi\TaxiRateCardController as AdminTaxiRateCardController;
use App\Http\Controllers\Admin\Taxi\TaxiSettingsController as AdminTaxiSettingsController;
use App\Http\Controllers\Admin\Taxi\TaxiTrackingController as AdminTaxiTrackingController;
use App\Http\Controllers\Admin\Taxi\VehicleController as AdminTaxiVehicleController;
use App\Http\Controllers\Admin\Taxi\VehicleTypeController as AdminTaxiVehicleTypeController;
use App\Http\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Http\Controllers\Admin\TourAddonController as AdminTourAddonController;
use App\Http\Controllers\Admin\TourAvailabilityController as AdminTourAvailabilityController;
use App\Http\Controllers\Admin\TourCategoryController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VendorApplicationController as AdminVendorApplicationController;
use App\Http\Controllers\Admin\VendorDocumentController as AdminVendorDocumentController;
use App\Http\Controllers\Admin\VendorFinanceController as AdminVendorFinanceController;
use App\Http\Controllers\Admin\VendorPlanController as AdminVendorPlanController;
use App\Http\Controllers\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\ContentCopilotController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\Driver\Taxi\DriverDashboardController as DriverTaxiDashboardController;
use App\Http\Controllers\Driver\Taxi\DriverEarningController as DriverTaxiEarningController;
use App\Http\Controllers\Driver\Taxi\DriverLocationController as DriverTaxiLocationController;
use App\Http\Controllers\Driver\Taxi\DriverOfferController as DriverTaxiOfferController;
use App\Http\Controllers\Driver\Taxi\DriverProfileController as DriverTaxiProfileController;
use App\Http\Controllers\Driver\Taxi\DriverReviewController;
use App\Http\Controllers\Driver\Taxi\DriverTripController as DriverTaxiTripController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HotelBookingController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\McpServerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlaceController as PublicPlaceController;
use App\Http\Controllers\ProfileController; // <-- NEW IMPORT
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\PublicQuotationDecisionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SharedDocumentController;
use App\Http\Controllers\Taxi\TaxiCancellationPolicyController;
use App\Http\Controllers\Taxi\TaxiChangeController;
use App\Http\Controllers\Taxi\TaxiReviewController;
use App\Http\Controllers\TaxiEnquiryController;
use App\Http\Controllers\TaxiTrackingController;
use App\Http\Controllers\TourPackageController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\Vendor\BookingController as VendorBookingController;
use App\Http\Controllers\Vendor\CouponController as VendorCouponController;
use App\Http\Controllers\Vendor\FinanceController as VendorFinanceController;
use App\Http\Controllers\Vendor\Hotel\BookingController;
use App\Http\Controllers\Vendor\Hotel\DailyRateController;
use App\Http\Controllers\Vendor\Hotel\InventoryController;
use App\Http\Controllers\Vendor\Hotel\OperationsController;
use App\Http\Controllers\Vendor\Hotel\PropertyController;
use App\Http\Controllers\Vendor\Hotel\RatePlanController;
use App\Http\Controllers\Vendor\PayoutAccountController as VendorPayoutAccountController;
use App\Http\Controllers\Vendor\SupportTicketController as VendorSupportTicketController;
use App\Http\Controllers\Vendor\Taxi\TaxiBookingController as VendorTaxiBookingController;
use App\Http\Controllers\Vendor\Taxi\TaxiCompensationPlanController as VendorTaxiCompensationPlanController;
use App\Http\Controllers\Vendor\Taxi\TaxiDashboardController as VendorTaxiDashboardController;
use App\Http\Controllers\Vendor\Taxi\TaxiDispatchController as VendorTaxiDispatchController;
use App\Http\Controllers\Vendor\Taxi\TaxiDriverController as VendorTaxiDriverController;
use App\Http\Controllers\Vendor\Taxi\TaxiDriverEarningController as VendorTaxiDriverEarningController;
use App\Http\Controllers\Vendor\Taxi\TaxiDriverPayoutController as VendorTaxiDriverPayoutController;
use App\Http\Controllers\Vendor\Taxi\TaxiRateCardController as VendorTaxiRateCardController;
use App\Http\Controllers\Vendor\Taxi\TaxiTrackingController as VendorTaxiTrackingController;
use App\Http\Controllers\Vendor\Taxi\TaxiVehicleController as VendorTaxiVehicleController;
use App\Http\Controllers\Vendor\TourAddonController as VendorTourAddonController;
use App\Http\Controllers\Vendor\TourAvailabilityController as VendorTourAvailabilityController;
use App\Http\Controllers\Vendor\TourController as VendorTourController;
use App\Http\Controllers\Vendor\VendorApplicationController;
use App\Http\Controllers\Vendor\VendorDashboardController;
use App\Http\Controllers\Vendor\VendorDocumentController;
use App\Http\Controllers\Vendor\VendorDocumentDownloadController;
use App\Http\Controllers\Vendor\VendorProfileController;
use App\Http\Controllers\VendorStorefrontController;
use App\Models\City;
use App\Models\Destination;
use App\Models\Page;
use App\Models\Place;
use App\Models\Review;
use App\Models\TourPackage;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/sitemap.xml', function () {
    $packages = TourPackage::publiclyVisible()
        ->select('slug', 'updated_at')
        ->get();

    $cities = City::query()
        ->active()
        ->select('slug', 'updated_at')
        ->get();

    $destinations = Destination::query()
        ->active()
        ->select('slug', 'updated_at')
        ->get();

    $places = Place::query()
        ->active()
        ->select('slug', 'updated_at')
        ->get();

    $vendors = VendorProfile::where('is_active', true)
        ->whereNotNull('approved_at')
        ->where('storefront_enabled', true)
        ->whereNotNull('slug')
        ->select('slug', 'updated_at')
        ->get();
    $hasFaqPage = Page::query()
        ->active()
        ->where('slug', 'faq')
        ->exists();

    return response()
        ->view('sitemap', compact('packages', 'cities', 'destinations', 'places', 'vendors', 'hasFaqPage'))
        ->header('Content-Type', 'application/xml');
});

Route::get('/', [HomeController::class, 'index'])->name('home');
// Phase 13A: persistent customer language selection (guest-safe,
// session + first-party cookie, module-independent).
Route::post('/locale', [LocaleController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('locale.store');
// Phase 13B: persistent visitor DISPLAY currency (guest-safe,
// session + first-party cookie, module-independent).
Route::post('/currency', [CurrencyController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('currency.store');
Route::get('/search', GlobalSearchController::class)
    ->middleware('throttle:60,1')
    ->name('search');

// Phase 13C shared unified search & discovery foundation. Geography is
// shared and module-independent; Hotel/Tour/Taxi slices gate inside
// their services (plus route middleware where the module owns the
// route). Legacy /search above stays for backwards compatibility.
Route::get('/discover/locations', [DiscoveryController::class, 'locations'])
    ->middleware('throttle:60,1')
    ->name('discover.locations');
Route::get('/discover/global', [DiscoveryController::class, 'global'])
    ->middleware('throttle:60,1')
    ->name('discover.global');
Route::get('/discover/location', [DiscoveryController::class, 'location'])
    ->middleware('throttle:60,1')
    ->name('discover.location');
Route::get('/search/hotels', [DiscoveryController::class, 'hotels'])
    ->middleware('throttle:60,1')
    ->name('search.hotels');
Route::get('/search/tours', [DiscoveryController::class, 'tours'])
    ->middleware('throttle:60,1')
    ->name('search.tours');
Route::get('/discover/taxi', [DiscoveryController::class, 'taxi'])
    ->middleware('throttle:60,1')
    ->name('discover.taxi');

Route::get('/packages', [TourPackageController::class, 'index'])->name('packages.index')->middleware('module:tours');
Route::get('/packages/{package:slug}', [TourPackageController::class, 'show'])->name('packages.show')->middleware('module:tours');

// Phase 12B.1 public hotels (published properties only, no availability yet).
Route::get('/hotels', [HotelController::class, 'index'])->name('hotels.index')->middleware('module:hotels');
Route::get('/hotels/{property:slug}', [HotelController::class, 'show'])->name('hotels.show')->middleware('module:hotels');
// Phase 12B.3 public availability (inventory only, no pricing yet).
Route::get('/hotels/{property:slug}/availability', [HotelController::class, 'availability'])->name('hotels.availability')->middleware(['module:hotels', 'throttle:60,1']);
// Phase 12B.4 public rates (pricing only, no booking yet).
Route::get('/hotels/{property:slug}/rates', [HotelController::class, 'rates'])->name('hotels.rates')->middleware(['module:hotels', 'throttle:60,1']);
Route::get('/hotels/{slug}/book', [HotelBookingController::class, 'create'])->name('hotel-booking.create')->middleware(['module:hotels', 'auth']);
Route::post('/hotels/{slug}/book', [HotelBookingController::class, 'store'])->name('hotel-booking.store')->middleware(['module:hotels', 'throttle:10,1']);
Route::get('/hotel-bookings/{booking}/confirmation', [App\Http\Controllers\Account\HotelBookingController::class, 'confirmation'])->name('hotel-booking.confirmation')->middleware(['module:hotels', 'auth']);
Route::post('/packages/{package:slug}/reviews', [ReviewController::class, 'storePublic'])
    ->middleware(['throttle:3,10', 'module:tours'])
    ->name('packages.reviews.store');

Route::get('/packages/{package:slug}/book', [PublicBookingController::class, 'create'])->name('booking.form')->middleware('module:tours');
Route::post('/booking/estimate', [PublicBookingController::class, 'estimate'])->name('booking.estimate')->middleware('module:tours');
Route::post('/bookings', [PublicBookingController::class, 'store'])->name('booking.store')->middleware('module:tours');

// Phase 11 public vendor storefront (before the CMS catch-all).
Route::get('/vendors/{vendor:slug}', [VendorStorefrontController::class, 'show'])->name('vendors.show');
Route::get('/bookings/confirmation/{booking}', [PublicBookingController::class, 'confirmation'])
    ->name('booking.confirmation')
    ->middleware(['module:tours', 'signed', 'tracking.privacy']);
Route::post('/bookings/{booking}/pay', [PublicBookingController::class, 'pay'])
    ->name('booking.pay')
    ->middleware(['module:tours', 'signed', 'tracking.privacy']);

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/our-team', fn () => Inertia::render('Static/Team'))->name('team');
Route::get('/contact', [EnquiryController::class, 'create'])->name('contact');
Route::post('/enquiries', [EnquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('enquiries.store');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/destinations', [DestinationController::class, 'index'])->name('destinations');
Route::get('/destinations/{destination:slug}', [DestinationController::class, 'show'])->name('destinations.show');
Route::get('/cities/{city:slug}', [CityController::class, 'show'])->name('cities.show');
Route::get('/places', [PublicPlaceController::class, 'index'])->name('places');
Route::get('/places/{place:slug}', [PublicPlaceController::class, 'show'])->name('places.show');
Route::get('/gallery', fn () => Inertia::render('Static/Gallery'))->name('gallery');
Route::get('/blog', fn () => Inertia::render('Blog/Index'))->name('blog');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::redirect('/spiritual-wisdom', '/destinations')->name('wisdom');
Route::get('/testimonials', function () {
    return Inertia::render('Static/Testimonials', [
        'testimonials' => Review::where('is_approved', true)
            ->with('user:id,name', 'package:id,title')
            ->latest()
            ->take(24)
            ->get(['id', 'user_id', 'package_id', 'reviewer_name', 'rating', 'comment', 'created_at'])
            ->map(static fn (Review $review): array => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'reviewer_name' => $review->reviewer_name ?: $review->user?->name,
                'package_title' => $review->package?->title,
                'published_at' => $review->created_at?->toDateString(),
            ])
            ->values()
            ->all(),
    ]);
})->name('testimonials');

Route::middleware('auth')->group(function () {
    Route::get('/my-bookings', fn () => redirect()->route('account.bookings.index'))->name('booking.history');

    Route::get('/bookings/{booking}/invoice', [InvoiceController::class, 'show'])->name('booking.invoice');
    Route::get('/bookings/{booking}/invoice/download', [InvoiceController::class, 'download'])->name('booking.invoice.download');

    Route::post('/bookings/{booking}/review', [ReviewController::class, 'store'])->name('review.store');

    // Phase 9 notification center (own notifications only).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
});

// Customer account area (public theme, never under /admin).
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::prefix('taxi')->name('taxi.')->middleware(['module:taxi', 'tracking.privacy'])->group(function () {
        Route::get('/bookings', [TaxiChangeController::class, 'index'])->name('bookings.index');

        // Phase 12A.11: authenticated booking changes and manual refunds.
        Route::get('/changes/{booking}', [TaxiChangeController::class, 'show'])->name('changes.show')->middleware('throttle:30,1');
        Route::get('/changes/{booking}/quote', [TaxiChangeController::class, 'quote'])->name('changes.quote')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/cancel', [TaxiChangeController::class, 'cancel'])->name('changes.cancel')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/reschedule-quote', [TaxiChangeController::class, 'rescheduleQuote'])->name('changes.reschedule-quote')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/reschedule', [TaxiChangeController::class, 'reschedule'])->name('changes.reschedule')->middleware('throttle:30,1');

        // Phase 12A.12: booking-backed taxi reviews (authenticated ownership only).
        Route::get('/reviews', [TaxiReviewController::class, 'index'])->name('reviews.index');
        Route::get('/reviews/create/{booking}', [TaxiReviewController::class, 'create'])->name('reviews.create');
        Route::post('/reviews/{booking}', [TaxiReviewController::class, 'store'])->name('reviews.store')->middleware('throttle:10,1');
        Route::get('/reviews/{review}', [TaxiReviewController::class, 'show'])->name('reviews.show');
    });

    Route::get('/', [AccountDashboardController::class, 'index'])->name('dashboard');
    Route::get('/bookings', [AccountBookingController::class, 'index'])->name('bookings.index')->middleware('tracking.privacy');
    Route::get('/bookings/{booking}', [AccountBookingController::class, 'show'])->name('bookings.show')->middleware('tracking.privacy');
    Route::get('/hotel-bookings', [App\Http\Controllers\Account\HotelBookingController::class, 'index'])->name('hotel-bookings.index')->middleware('module:hotels');
    Route::get('/hotel-bookings/{booking}', [App\Http\Controllers\Account\HotelBookingController::class, 'show'])->name('hotel-bookings.show')->middleware('module:hotels');
    Route::get('/hotel-bookings/{booking}/review', [HotelReviewController::class, 'show'])->name('hotel-reviews.show')->middleware('module:hotels');
    Route::post('/hotel-bookings/{booking}/review', [HotelReviewController::class, 'store'])->name('hotel-reviews.store')->middleware(['module:hotels', 'throttle:10,1']);
    Route::put('/hotel-bookings/{booking}/review', [HotelReviewController::class, 'update'])->name('hotel-reviews.update')->middleware(['module:hotels', 'throttle:10,1']);
    Route::get('/hotel-bookings/{booking}/cancellation-quote', [App\Http\Controllers\Account\HotelBookingController::class, 'cancellationQuote'])->name('hotel-bookings.cancellation-quote')->middleware('module:hotels');
    Route::post('/hotel-bookings/{booking}/cancel', [App\Http\Controllers\Account\HotelBookingController::class, 'cancel'])->name('hotel-bookings.cancel')->middleware('module:hotels');
    Route::post('/hotel-bookings/{booking}/reschedule-quote', [App\Http\Controllers\Account\HotelBookingController::class, 'rescheduleQuote'])->name('hotel-bookings.reschedule-quote')->middleware('module:hotels');
    Route::post('/hotel-bookings/{booking}/reschedule', [App\Http\Controllers\Account\HotelBookingController::class, 'reschedule'])->name('hotel-bookings.reschedule')->middleware('module:hotels');
    Route::post('/bookings/{booking}/cancellation-requests', [AccountBookingController::class, 'requestCancellation'])->name('cancellation-requests.store');

    // Phase 11.5C customer support portal (own tickets only).
    Route::get('/support', [AccountSupportTicketController::class, 'index'])->name('support.index');
    Route::get('/support/create', [AccountSupportTicketController::class, 'create'])->name('support.create');
    Route::post('/support', [AccountSupportTicketController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket}', [AccountSupportTicketController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/replies', [AccountSupportTicketController::class, 'reply'])->name('support.replies.store');
    Route::get('/support/{ticket}/attachments/{attachment}', [AccountSupportTicketController::class, 'download'])->name('support.attachments.download');
});

// Breeze profile screens reused for customers (exact route names the
// bundled Profile partials already call).
Route::middleware('auth')->group(function () {
    Route::get('/account/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/account/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/account/profile', [ProfileController::class, 'destroy'])->name('profile.destroy')->middleware('block.impersonated.sensitive');
});

// Vendor application (customer → vendor)
Route::middleware('auth')->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/apply', [VendorApplicationController::class, 'create'])->name('application.create');
    Route::post('/apply', [VendorApplicationController::class, 'store'])->name('application.store');
    Route::get('/application', [VendorApplicationController::class, 'show'])->name('application.show');
    Route::get('/application/edit', [VendorApplicationController::class, 'edit'])->name('application.edit');
    Route::patch('/application', [VendorApplicationController::class, 'update'])->name('application.update');

    Route::post('/documents', [VendorDocumentController::class, 'store'])->name('documents.store');
    Route::delete('/documents/{vendorDocument}', [VendorDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{vendorDocument}/download', VendorDocumentDownloadController::class)->name('documents.download');
});

// Vendor dashboard/profile (requires vendor role)
Route::middleware(['auth', 'vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::post('/ai/content', [ContentCopilotController::class, 'generate'])->name('ai.content')->middleware('throttle:10,1');
    Route::get('/', [VendorDashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [VendorProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [VendorProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [VendorProfileController::class, 'update'])->name('profile.update');

    Route::get('/tours', [VendorTourController::class, 'index'])->name('tours.index')->middleware('module:tours');
    Route::get('/tours/create', [VendorTourController::class, 'create'])->name('tours.create')->middleware('module:tours');
    Route::post('/tours', [VendorTourController::class, 'store'])->name('tours.store')->middleware('module:tours');
    Route::get('/tours/{tour}/edit', [VendorTourController::class, 'edit'])->name('tours.edit')->middleware('module:tours');
    Route::put('/tours/{tour}', [VendorTourController::class, 'update'])->name('tours.update')->middleware('module:tours');
    Route::delete('/tours/{tour}', [VendorTourController::class, 'destroy'])->name('tours.destroy')->middleware('module:tours');
    Route::post('/tours/{tour}/submit', [VendorTourController::class, 'submit'])->name('tours.submit')->middleware('module:tours');

    // Phase 10: own-tour extras, availability and coupons (approved-vendor
    // access is enough; payout KYC stays a separate gate).
    Route::get('/tours/{tour}/addons', [VendorTourAddonController::class, 'index'])->name('tours.addons.index')->middleware('module:tours');
    Route::post('/tours/{tour}/addons', [VendorTourAddonController::class, 'store'])->name('tours.addons.store')->middleware('module:tours');
    Route::put('/tours/{tour}/addons/{addon}', [VendorTourAddonController::class, 'update'])->name('tours.addons.update')->middleware('module:tours');
    Route::patch('/tours/{tour}/addons/{addon}/toggle', [VendorTourAddonController::class, 'toggle'])->name('tours.addons.toggle')->middleware('module:tours');
    Route::delete('/tours/{tour}/addons/{addon}', [VendorTourAddonController::class, 'destroy'])->name('tours.addons.destroy')->middleware('module:tours');

    Route::get('/tours/{tour}/availability', [VendorTourAvailabilityController::class, 'show'])->name('tours.availability.show')->middleware('module:tours');
    Route::put('/tours/{tour}/availability', [VendorTourAvailabilityController::class, 'update'])->name('tours.availability.update')->middleware('module:tours');
    Route::post('/tours/{tour}/blackouts', [VendorTourAvailabilityController::class, 'store'])->name('tours.blackouts.store')->middleware('module:tours');
    Route::delete('/tours/{tour}/blackouts/{blackout}', [VendorTourAvailabilityController::class, 'destroy'])->name('tours.blackouts.destroy')->middleware('module:tours');

    Route::get('/coupons', [VendorCouponController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create', [VendorCouponController::class, 'create'])->name('coupons.create');
    Route::post('/coupons', [VendorCouponController::class, 'store'])->name('coupons.store');
    Route::get('/coupons/{coupon}/edit', [VendorCouponController::class, 'edit'])->name('coupons.edit');
    Route::put('/coupons/{coupon}', [VendorCouponController::class, 'update'])->name('coupons.update');
    Route::patch('/coupons/{coupon}/toggle', [VendorCouponController::class, 'toggle'])->name('coupons.toggle');
    Route::delete('/coupons/{coupon}', [VendorCouponController::class, 'destroy'])->name('coupons.destroy');

    // Phase 12B.1 vendor hotel properties (own properties only).
    Route::middleware('module:hotels')->prefix('hotel')->name('hotel.')->group(function () {
        Route::get('/operations', [OperationsController::class, 'index'])->name('operations');
        Route::get('/reviews', [App\Http\Controllers\Vendor\Hotel\ReviewController::class, 'index'])->name('reviews.index');
        Route::get('/reviews/{review}', [App\Http\Controllers\Vendor\Hotel\ReviewController::class, 'show'])->name('reviews.show');
        Route::put('/reviews/{review}/reply', [App\Http\Controllers\Vendor\Hotel\ReviewController::class, 'reply'])->name('reviews.reply')->middleware('throttle:10,1');
        Route::patch('/operations/bookings/{booking}/status', [OperationsController::class, 'status'])->name('operations.status');
        Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
        Route::get('/properties/create', [PropertyController::class, 'create'])->name('properties.create');
        Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
        Route::get('/properties/{property:id}', [PropertyController::class, 'show'])->name('properties.show');
        Route::get('/properties/{property:id}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
        Route::put('/properties/{property:id}', [PropertyController::class, 'update'])->name('properties.update');
        Route::delete('/properties/{property:id}', [PropertyController::class, 'destroy'])->name('properties.destroy');
        Route::post('/properties/{property:id}/submit', [PropertyController::class, 'submit'])->name('properties.submit');
        Route::post('/properties/{property:id}/images', [PropertyController::class, 'storeImage'])->name('properties.images.store');
        Route::delete('/properties/{property:id}/images/{image}', [PropertyController::class, 'destroyImage'])->name('properties.images.destroy');
        Route::patch('/properties/{property:id}/images/{image}/primary', [PropertyController::class, 'primaryImage'])->name('properties.images.primary');
        Route::get('/cities', [PropertyController::class, 'cities'])->name('cities.index');
        Route::get('/destinations', [PropertyController::class, 'destinations'])->name('destinations.index');

        Route::get('/properties/{property:id}/room-types', [App\Http\Controllers\Vendor\Hotel\RoomController::class, 'index'])->name('room-types.index');
        Route::get('/properties/{property:id}/room-types/create', [App\Http\Controllers\Vendor\Hotel\RoomController::class, 'create'])->name('room-types.create');
        Route::post('/properties/{property:id}/room-types', [App\Http\Controllers\Vendor\Hotel\RoomController::class, 'store'])->name('room-types.store');
        Route::get('/room-types/{roomType}', [App\Http\Controllers\Vendor\Hotel\RoomController::class, 'show'])->name('room-types.show');
        Route::get('/room-types/{roomType}/edit', [App\Http\Controllers\Vendor\Hotel\RoomController::class, 'edit'])->name('room-types.edit');
        Route::put('/room-types/{roomType}', [App\Http\Controllers\Vendor\Hotel\RoomController::class, 'update'])->name('room-types.update');
        Route::delete('/room-types/{roomType}', [App\Http\Controllers\Vendor\Hotel\RoomController::class, 'destroy'])->name('room-types.destroy');
        Route::post('/room-types/{roomType}/images', [App\Http\Controllers\Vendor\Hotel\RoomController::class, 'storeImage'])->name('room-types.images.store');
        Route::delete('/room-types/{roomType}/images/{image}', [App\Http\Controllers\Vendor\Hotel\RoomController::class, 'destroyImage'])->name('room-types.images.destroy');
        Route::patch('/room-types/{roomType}/images/{image}/primary', [App\Http\Controllers\Vendor\Hotel\RoomController::class, 'primaryImage'])->name('room-types.images.primary');

        Route::get('/properties/{property:id}/units', [App\Http\Controllers\Vendor\Hotel\UnitController::class, 'index'])->name('room-units.index');
        Route::post('/properties/{property:id}/units', [App\Http\Controllers\Vendor\Hotel\UnitController::class, 'store'])->name('room-units.store');
        Route::put('/units/{unit}', [App\Http\Controllers\Vendor\Hotel\UnitController::class, 'update'])->name('room-units.update');
        Route::delete('/units/{unit}', [App\Http\Controllers\Vendor\Hotel\UnitController::class, 'destroy'])->name('room-units.destroy');

        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::post('/inventory/bulk', [InventoryController::class, 'bulk'])->name('inventory.bulk');
        Route::delete('/inventory', [InventoryController::class, 'clear'])->name('inventory.clear');

        Route::get('/rate-plans', [RatePlanController::class, 'index'])->name('rate-plans.index');
        Route::post('/rate-plans', [RatePlanController::class, 'store'])->name('rate-plans.store');
        Route::put('/rate-plans/{plan}', [RatePlanController::class, 'update'])->name('rate-plans.update');
        Route::patch('/rate-plans/{plan}/toggle', [RatePlanController::class, 'toggle'])->name('rate-plans.toggle');
        Route::post('/seasons', [RatePlanController::class, 'storeSeason'])->name('seasons.store');
        Route::put('/seasons/{season}', [RatePlanController::class, 'updateSeason'])->name('seasons.update');
        Route::patch('/seasons/{season}/toggle', [RatePlanController::class, 'toggleSeason'])->name('seasons.toggle');
        Route::delete('/seasons/{season}', [RatePlanController::class, 'destroySeason'])->name('seasons.destroy');
        Route::get('/daily-rates', [DailyRateController::class, 'index'])->name('daily-rates.index');
        Route::post('/daily-rates', [DailyRateController::class, 'store'])->name('daily-rates.store');
        Route::post('/daily-rates/bulk', [DailyRateController::class, 'bulk'])->name('daily-rates.bulk');
        Route::delete('/daily-rates', [DailyRateController::class, 'clear'])->name('daily-rates.clear');
        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::patch('/bookings/{booking}/status', [BookingController::class, 'status'])->name('bookings.status');
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{booking}/reschedule', [BookingController::class, 'reschedule'])->name('bookings.reschedule');
        Route::post('/bookings/{booking}/reschedule-quote', [BookingController::class, 'rescheduleQuote'])->name('bookings.reschedule-quote');
    });

    // Vendor bookings are read-only: vendors view only bookings
    // historically assigned to their own vendor profile.
    Route::get('/bookings', [VendorBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [VendorBookingController::class, 'create'])->name('bookings.create')->middleware('module:tours');
    Route::post('/bookings', [VendorBookingController::class, 'store'])->name('bookings.store')->middleware('module:tours');
    Route::get('/bookings/{booking}', [VendorBookingController::class, 'show'])->name('bookings.show');

    // Phase 12A.1 vendor taxi fleet (own resources only, taxi module on).
    Route::middleware('module:taxi')->prefix('taxi')->name('taxi.')->group(function () {
        Route::get('/dashboard', [VendorTaxiDashboardController::class, 'index'])->name('dashboard');
        Route::get('/pricing', [VendorTaxiRateCardController::class, 'index'])->name('pricing.index');
        Route::get('/pricing/create', [VendorTaxiRateCardController::class, 'create'])->name('pricing.create');
        Route::post('/pricing', [VendorTaxiRateCardController::class, 'store'])->name('pricing.store');
        Route::get('/pricing/{rateCard}/edit', [VendorTaxiRateCardController::class, 'edit'])->name('pricing.edit');
        Route::put('/pricing/{rateCard}', [VendorTaxiRateCardController::class, 'update'])->name('pricing.update');
        Route::patch('/pricing/{rateCard}/toggle', [VendorTaxiRateCardController::class, 'toggle'])->name('pricing.toggle');
        Route::delete('/pricing/{rateCard}', [VendorTaxiRateCardController::class, 'destroy'])->name('pricing.destroy');
        Route::get('/vehicles', [VendorTaxiVehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/vehicles/create', [VendorTaxiVehicleController::class, 'create'])->name('vehicles.create');
        Route::post('/vehicles', [VendorTaxiVehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/vehicles/{vehicle}', [VendorTaxiVehicleController::class, 'show'])->name('vehicles.show');
        Route::get('/vehicles/{vehicle}/edit', [VendorTaxiVehicleController::class, 'edit'])->name('vehicles.edit');
        Route::put('/vehicles/{vehicle}', [VendorTaxiVehicleController::class, 'update'])->name('vehicles.update');
        Route::post('/vehicles/{vehicle}/documents', [VendorTaxiVehicleController::class, 'storeDocument'])->name('vehicles.documents.store');
        Route::post('/vehicles/{vehicle}/unavailable', [VendorTaxiVehicleController::class, 'storeUnavailable'])->name('vehicles.unavailable.store');

        Route::get('/drivers', [VendorTaxiDriverController::class, 'index'])->name('drivers.index');
        Route::get('/drivers/create', [VendorTaxiDriverController::class, 'create'])->name('drivers.create');
        Route::post('/drivers', [VendorTaxiDriverController::class, 'store'])->name('drivers.store');
        Route::get('/drivers/{driver}', [VendorTaxiDriverController::class, 'show'])->name('drivers.show');
        Route::get('/drivers/{driver}/edit', [VendorTaxiDriverController::class, 'edit'])->name('drivers.edit');
        Route::put('/drivers/{driver}', [VendorTaxiDriverController::class, 'update'])->name('drivers.update');
        Route::post('/drivers/{driver}/documents', [VendorTaxiDriverController::class, 'storeDocument'])->name('drivers.documents.store');
        Route::post('/drivers/{driver}/availability', [VendorTaxiDriverController::class, 'storeAvailability'])->name('drivers.availability.store');
        Route::delete('/drivers/availability/{availability}', [VendorTaxiDriverController::class, 'destroyAvailability'])->name('drivers.availability.destroy');

        Route::get('/dispatch', [VendorTaxiDispatchController::class, 'index'])->name('dispatch.index');
        Route::get('/dispatch/{taxiBooking}/eligible', [VendorTaxiDispatchController::class, 'eligible'])->name('dispatch.eligible');
        Route::post('/dispatch/{taxiBooking}/notes', [VendorTaxiDispatchController::class, 'storeNote'])->name('dispatch.notes.store');
        Route::get('/dispatch/{taxiBooking}/notes', [VendorTaxiDispatchController::class, 'notes'])->name('dispatch.notes.index');
        Route::get('/dispatch/{taxiBooking}/recommendations', [VendorTaxiDispatchController::class, 'recommendations'])->name('dispatch.recommendations');

        Route::get('/tracking', [VendorTaxiTrackingController::class, 'index'])->name('tracking.index');
        Route::get('/tracking/route', [VendorTaxiTrackingController::class, 'route'])->name('tracking.route');

        Route::get('/bookings', [VendorTaxiBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [VendorTaxiBookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings/quote', [VendorTaxiBookingController::class, 'quote'])->name('bookings.quote');
        Route::post('/bookings', [VendorTaxiBookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{taxiBooking}', [VendorTaxiBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{taxiBooking}/assign', [VendorTaxiBookingController::class, 'assign'])->name('bookings.assign');
        Route::post('/bookings/{taxiBooking}/unassign', [VendorTaxiBookingController::class, 'unassign'])->name('bookings.unassign');
        Route::post('/bookings/{taxiBooking}/auto-dispatch/start', [VendorTaxiBookingController::class, 'startAutoDispatch'])->name('bookings.auto-dispatch.start');
        Route::post('/bookings/{taxiBooking}/auto-dispatch/stop', [VendorTaxiBookingController::class, 'stopAutoDispatch'])->name('bookings.auto-dispatch.stop');
        Route::post('/bookings/{taxiBooking}/tracking', [VendorTaxiBookingController::class, 'generateTrackingLink'])->name('bookings.tracking.store');
        Route::delete('/bookings/{taxiBooking}/tracking', [VendorTaxiBookingController::class, 'revokeTrackingLink'])->name('bookings.tracking.destroy');
        Route::patch('/bookings/{taxiBooking}/status', [VendorTaxiBookingController::class, 'status'])->name('bookings.status');

        // Phase 12A.11: authenticated booking changes and manual refunds.
        Route::get('/changes/{booking}', [TaxiChangeController::class, 'show'])->name('changes.show')->middleware('throttle:30,1');
        Route::get('/changes/{booking}/quote', [TaxiChangeController::class, 'quote'])->name('changes.quote')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/cancel', [TaxiChangeController::class, 'cancel'])->name('changes.cancel')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/reschedule-quote', [TaxiChangeController::class, 'rescheduleQuote'])->name('changes.reschedule-quote')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/reschedule', [TaxiChangeController::class, 'reschedule'])->name('changes.reschedule')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/refunds', [TaxiChangeController::class, 'refund'])->name('changes.refunds.store');
        Route::post('/changes/{booking}/refunds/{refund}/process', [TaxiChangeController::class, 'processRefund'])->name('changes.refunds.process');
        Route::get('/cancellation-policies', [TaxiCancellationPolicyController::class, 'index'])->name('cancellation-policies.index');
        Route::post('/cancellation-policies', [TaxiCancellationPolicyController::class, 'store'])->name('cancellation-policies.store');
        Route::put('/cancellation-policies/{policy}', [TaxiCancellationPolicyController::class, 'update'])->name('cancellation-policies.update');
        Route::patch('/cancellation-policies/{policy}/toggle', [TaxiCancellationPolicyController::class, 'toggle'])->name('cancellation-policies.toggle');
        Route::get('/reviews', [TaxiReviewController::class, 'index'])->name('reviews.index');
        Route::get('/reviews/{review}', [TaxiReviewController::class, 'show'])->name('reviews.show');
        Route::post('/reviews/{review}/reply', [TaxiReviewController::class, 'reply'])->name('reviews.reply');
        Route::post('/reviews/{review}/flag', [TaxiReviewController::class, 'flag'])->name('reviews.flag');
        Route::get('/earnings', [VendorTaxiDriverEarningController::class, 'index'])->name('earnings.index');
        Route::get('/earnings/{earning}', [VendorTaxiDriverEarningController::class, 'show'])->name('earnings.show');
        Route::patch('/earnings/{earning}/payable', [VendorTaxiDriverEarningController::class, 'markPayable'])->name('earnings.payable');
        Route::post('/earnings/{earning}/adjustments', [VendorTaxiDriverEarningController::class, 'storeAdjustment'])->name('earnings.adjustments.store');

        Route::get('/plans', [VendorTaxiCompensationPlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [VendorTaxiCompensationPlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [VendorTaxiCompensationPlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [VendorTaxiCompensationPlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan}', [VendorTaxiCompensationPlanController::class, 'update'])->name('plans.update');
        Route::patch('/plans/{plan}/toggle', [VendorTaxiCompensationPlanController::class, 'toggle'])->name('plans.toggle');
        Route::delete('/plans/{plan}', [VendorTaxiCompensationPlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('/payouts', [VendorTaxiDriverPayoutController::class, 'index'])->name('payouts.index');
        Route::get('/payouts/create', [VendorTaxiDriverPayoutController::class, 'create'])->name('payouts.create');
        Route::post('/payouts', [VendorTaxiDriverPayoutController::class, 'store'])->name('payouts.store');
        Route::get('/payouts/{payout}', [VendorTaxiDriverPayoutController::class, 'show'])->name('payouts.show');
        Route::patch('/payouts/{payout}/mark-paid', [VendorTaxiDriverPayoutController::class, 'markPaid'])->name('payouts.mark-paid');
        Route::patch('/payouts/{payout}/cancel', [VendorTaxiDriverPayoutController::class, 'cancel'])->name('payouts.cancel');
    });

    // Phase 11.5C vendor support portal (own tickets only).
    Route::get('/support', [VendorSupportTicketController::class, 'index'])->name('support.index');
    Route::get('/support/create', [VendorSupportTicketController::class, 'create'])->name('support.create');
    Route::post('/support', [VendorSupportTicketController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket}', [VendorSupportTicketController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/replies', [VendorSupportTicketController::class, 'reply'])->name('support.replies.store');
    Route::get('/support/{ticket}/attachments/{attachment}', [VendorSupportTicketController::class, 'download'])->name('support.attachments.download');

    // Vendor finance: earnings/ledger/withdrawals. Money movement is
    // blocked while impersonating — an impersonated vendor may view but
    // never request or cancel withdrawals.
    Route::get('/finance', [VendorFinanceController::class, 'index'])->name('finance.index');
    Route::post('/withdrawals', [VendorFinanceController::class, 'store'])
        ->middleware('block.impersonated.sensitive')
        ->name('withdrawals.store');
    Route::patch('/withdrawals/{withdrawal}/cancel', [VendorFinanceController::class, 'cancel'])
        ->middleware('block.impersonated.sensitive')
        ->name('withdrawals.cancel');

    // Payout destination: view masked status freely, but add/replace is
    // blocked while impersonating.
    Route::post('/payout-account', [VendorPayoutAccountController::class, 'store'])
        ->middleware('block.impersonated.sensitive')
        ->name('payout-account.store');
});

// Impersonation stop (available while impersonating)
Route::middleware('auth')->post('/impersonation/stop', [ImpersonationController::class, 'destroy'])->name('impersonation.stop');

// Phase 12A.4 driver portal (own assigned trips only). Access requires a
// linked Driver record (drivers.user_id) via the driver middleware; every
// trip endpoint additionally validates an open assignment and 404s
// otherwise. Hidden with 404 while the taxi module is disabled.
Route::middleware(['auth', 'driver'])->prefix('driver')->name('driver.')->group(function () {
    Route::middleware('module:taxi')->prefix('taxi')->name('taxi.')->group(function () {
        Route::get('/offers', [DriverTaxiOfferController::class, 'index'])->name('offers.index');
        Route::post('/offers/{offer}/accept', [DriverTaxiOfferController::class, 'accept'])->name('offers.accept');
        Route::post('/offers/{offer}/reject', [DriverTaxiOfferController::class, 'reject'])->name('offers.reject');
        Route::get('/offers/pending-count', [DriverTaxiOfferController::class, 'pendingCount'])->name('offers.pending-count');
        Route::get('/dashboard', [DriverTaxiDashboardController::class, 'index'])->name('dashboard');
        Route::get('/trips', [DriverTaxiTripController::class, 'index'])->name('trips.index');
        Route::get('/trips/{taxiBooking}', [DriverTaxiTripController::class, 'show'])->name('trips.show');
        Route::patch('/trips/{taxiBooking}/status', [DriverTaxiTripController::class, 'status'])->name('trips.status');
        Route::post('/trips/{taxiBooking}/acknowledge', [DriverTaxiTripController::class, 'acknowledge'])->name('trips.acknowledge');
        Route::post('/trips/{taxiBooking}/notes', [DriverTaxiTripController::class, 'storeNote'])->name('trips.notes.store');
        Route::get('/trips/{taxiBooking}/notes', [DriverTaxiTripController::class, 'notes'])->name('trips.notes.index');
        Route::get('/trips/{taxiBooking}/route', [DriverTaxiTripController::class, 'routeMap'])->name('trips.route');
        Route::get('/profile', [DriverTaxiProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [DriverTaxiProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/availability', [DriverTaxiProfileController::class, 'availability'])->name('profile.availability');
        Route::get('/earnings', [DriverTaxiEarningController::class, 'index'])->name('earnings.index');
        Route::get('/earnings/{earning}', [DriverTaxiEarningController::class, 'show'])->name('earnings.show');
        Route::get('/reviews', [DriverReviewController::class, 'index'])->name('reviews.index');
        Route::post('/location', [DriverTaxiLocationController::class, 'store'])->name('location.store');
        Route::get('/location/status', [DriverTaxiLocationController::class, 'status'])->name('location.status');
    });
});

Route::middleware(['auth', 'admin'])->post('/admin/ai/content', [ContentCopilotController::class, 'generate'])
    ->name('admin.ai.content')->middleware('throttle:10,1');

Route::match(['GET', 'POST', 'DELETE', 'OPTIONS'], '/mcp', McpServerController::class)
    ->name('mcp.server')->middleware(['throttle:60,1', 'mcp.auth']);

Route::middleware(['auth', 'admin', 'staff.permissions'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/mcp-access', [McpAccessController::class, 'index'])->name('mcp.index');
    Route::post('/mcp-access/toggle', [McpAccessController::class, 'toggle'])->name('mcp.toggle');
    Route::post('/mcp-access/tokens', [McpAccessController::class, 'store'])->name('mcp.tokens.store');
    Route::delete('/mcp-access/tokens/{token}', [McpAccessController::class, 'revoke'])->name('mcp.tokens.revoke');
    Route::get('/ai-assistant', [KnowledgeAgentController::class, 'index'])->name('ai-assistant.index');
    Route::get('/ai-assistant/actions', [AgentActionController::class, 'index'])->name('ai-assistant.actions.index');
    Route::post('/ai-assistant/message', [KnowledgeAgentController::class, 'message'])->name('ai-assistant.message')->middleware('throttle:10,1');
    Route::get('/ai-assistant/conversations/{conversation}', [KnowledgeAgentController::class, 'show'])->name('ai-assistant.conversations.show');
    Route::patch('/ai-assistant/conversations/{conversation}', [KnowledgeAgentController::class, 'rename'])->name('ai-assistant.conversations.rename');
    Route::delete('/ai-assistant/conversations/{conversation}', [KnowledgeAgentController::class, 'destroy'])->name('ai-assistant.conversations.destroy');
    Route::post('/ai-assistant/actions/{proposal}/confirm', [AgentActionController::class, 'confirm'])->name('ai-assistant.actions.confirm')->middleware('throttle:12,1');
    Route::post('/ai-assistant/actions/{proposal}/reject', [AgentActionController::class, 'reject'])->name('ai-assistant.actions.reject')->middleware('throttle:12,1');
    Route::get('/ai-assistant/knowledge', [KnowledgeController::class, 'index'])->name('ai-assistant.knowledge.index');
    Route::post('/ai-assistant/knowledge', [KnowledgeController::class, 'store'])->name('ai-assistant.knowledge.store');
    Route::put('/ai-assistant/knowledge/{document}', [KnowledgeController::class, 'update'])->name('ai-assistant.knowledge.update');
    Route::post('/ai-assistant/knowledge/{document}/reindex', [KnowledgeController::class, 'reindex'])->name('ai-assistant.knowledge.reindex');
    Route::delete('/ai-assistant/knowledge/{document}', [KnowledgeController::class, 'destroy'])->name('ai-assistant.knowledge.destroy');
    Route::post('/ai-assistant/knowledge/import', [KnowledgeController::class, 'import'])->name('ai-assistant.knowledge.import');
    Route::post(
        '/editor/upload-image',
        [EditorUploadController::class, 'store']
    )->name('editor.upload-image');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/enquiries', [AdminEnquiryController::class, 'index'])->name('enquiries.index');
    Route::delete('/enquiries/{enquiry}', [AdminEnquiryController::class, 'destroy'])->name('enquiries.destroy');

    // Phase 11.5A: tours-module routes are unavailable (404) when the
    // tours module is disabled. Shared platform routes stay untouched.
    Route::middleware('module:tours')->group(function () {
        Route::get('/packages', [PackageManagerController::class, 'index'])->name('packages.index');
        Route::get('/packages/create', [PackageManagerController::class, 'create'])->name('packages.create');
        Route::post('/packages', [PackageManagerController::class, 'store'])->name('packages.store');
        Route::get('/packages/{package}', [PackageManagerController::class, 'show'])->name('packages.show');
        Route::get('/packages/{package}/edit', [PackageManagerController::class, 'edit'])->name('packages.edit');
        Route::put('/packages/{package}', [PackageManagerController::class, 'update'])->name('packages.update');
        Route::delete('/packages/{package}', [PackageManagerController::class, 'destroy'])->name('packages.destroy');
        Route::post('/packages/ai-draft-itinerary', [AiContentController::class, 'draftItinerary'])->name('packages.ai-draft');
        Route::post('/packages/{package}/approve', [PackageManagerController::class, 'approve'])->name('packages.approve');
        Route::post('/packages/{package}/request-changes', [PackageManagerController::class, 'requestChanges'])->name('packages.requestChanges');
        Route::post('/packages/{package}/reject', [PackageManagerController::class, 'reject'])->name('packages.reject');
    });

    // Phase 10: coupons, add-ons, availability (admin manages any tour).
    Route::get('/coupons', [AdminCouponController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create', [AdminCouponController::class, 'create'])->name('coupons.create');
    Route::post('/coupons', [AdminCouponController::class, 'store'])->name('coupons.store');
    Route::get('/coupons/{coupon}/edit', [AdminCouponController::class, 'edit'])->name('coupons.edit');
    Route::put('/coupons/{coupon}', [AdminCouponController::class, 'update'])->name('coupons.update');
    Route::patch('/coupons/{coupon}/toggle', [AdminCouponController::class, 'toggle'])->name('coupons.toggle');
    Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy'])->name('coupons.destroy');

    Route::middleware('module:tours')->group(function () {
        Route::get('/packages/{package}/addons', [AdminTourAddonController::class, 'index'])->name('packages.addons.index');
        Route::post('/packages/{package}/addons', [AdminTourAddonController::class, 'store'])->name('packages.addons.store');
        Route::put('/packages/{package}/addons/{addon}', [AdminTourAddonController::class, 'update'])->name('packages.addons.update');
        Route::patch('/packages/{package}/addons/{addon}/toggle', [AdminTourAddonController::class, 'toggle'])->name('packages.addons.toggle');
        Route::delete('/packages/{package}/addons/{addon}', [AdminTourAddonController::class, 'destroy'])->name('packages.addons.destroy');

        Route::get('/packages/{package}/availability', [AdminTourAvailabilityController::class, 'show'])->name('packages.availability.show');
        Route::put('/packages/{package}/availability', [AdminTourAvailabilityController::class, 'update'])->name('packages.availability.update');
        Route::post('/packages/{package}/blackouts', [AdminTourAvailabilityController::class, 'store'])->name('packages.blackouts.store');
        Route::delete('/packages/{package}/blackouts/{blackout}', [AdminTourAvailabilityController::class, 'destroy'])->name('packages.blackouts.destroy');
    });

    Route::resource('destinations', AdminDestinationController::class)->except('show')->middleware('module:tours');
    Route::resource('places', PlaceController::class)->except('show')->middleware('module:tours');
    Route::resource('tags', TagController::class)->except('show')->middleware('module:tours');
    Route::resource('tour-categories', TourCategoryController::class)->except('show')->middleware('module:tours');

    // Phase 12B.4.1 shared geography (Countries/States/Cities). Platform
    // data — intentionally NOT module-gated so Hotels/Tours/discovery
    // keep their geography even when a single module is disabled.
    Route::resource('countries', AdminCountryController::class)->except('show');
    Route::resource('states', AdminStateController::class)->except('show');
    Route::resource('cities', AdminCityController::class)->except('show');

    // Bounded dependent selects for Property/Tour admin + vendor forms.
    Route::prefix('locations/lookup')->name('locations.lookup.')->group(function () {
        Route::get('/states', [AdminLocationLookupController::class, 'states'])->name('states');
        Route::get('/cities', [AdminLocationLookupController::class, 'cities'])->name('cities');
        Route::get('/destinations', [AdminLocationLookupController::class, 'destinations'])->name('destinations');
    });

    Route::get('/banners', [BannerController::class, 'index'])->name('banners.index');
    Route::post('/banners', [BannerController::class, 'store'])->name('banners.store');
    Route::delete('/banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');
    Route::patch('/banners/{banner}/order', [BannerController::class, 'updateOrder']);

    Route::get('/promotional-popup', [PromotionalPopupController::class, 'index'])->name('promotional-popup.index');
    Route::post('/promotional-popup', [PromotionalPopupController::class, 'store'])->name('promotional-popup.store');

    Route::get('/tour/bookings', [BookingManagerController::class, 'index'])->name('bookings.index');
    Route::get('/tour/bookings/create', [BookingManagerController::class, 'create'])->name('bookings.create');
    // Reservation Desk (structured offline flow) — before {booking}.
    Route::get('/tour/bookings/desk', [BookingManagerController::class, 'desk'])->name('bookings.desk');
    Route::post('/tour/bookings', [BookingManagerController::class, 'store'])->name('bookings.store');
    Route::get('/tour/bookings/{booking}', [BookingManagerController::class, 'show'])->name('bookings.show');
    Route::patch('/tour/bookings/{booking}/status', [BookingManagerController::class, 'updateStatus'])->name('bookings.status');
    Route::patch('/tour/bookings/{booking}/cancellation-requests/{cancellation}/approve', [BookingManagerController::class, 'approveCancellation'])->name('bookings.cancellation.approve');
    Route::patch('/tour/bookings/{booking}/cancellation-requests/{cancellation}/reject', [BookingManagerController::class, 'rejectCancellation'])->name('bookings.cancellation.reject');

    // Marketplace withdrawals (manual settlement, no gateway). No GET
    // route mutates state; review transitions are guarded by
    // WithdrawalStatus and idempotent in VendorLedgerService.
    Route::get('/withdrawals', [AdminWithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::get('/withdrawals/{withdrawal}', [AdminWithdrawalController::class, 'show'])->name('withdrawals.show');
    Route::patch('/withdrawals/{withdrawal}/approve', [AdminWithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::patch('/withdrawals/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject'])->name('withdrawals.reject');
    Route::patch('/withdrawals/{withdrawal}/mark-paid', [AdminWithdrawalController::class, 'markPaid'])->name('withdrawals.mark-paid');

    // Payout destinations (masked only — no reveal action by design).
    Route::get('/payout-accounts', [AdminPayoutAccountController::class, 'index'])->name('payout-accounts.index');
    Route::get('/payout-accounts/{payoutAccount}', [AdminPayoutAccountController::class, 'show'])->name('payout-accounts.show');
    Route::patch('/payout-accounts/{payoutAccount}/verify', [AdminPayoutAccountController::class, 'verify'])->name('payout-accounts.verify');
    Route::patch('/payout-accounts/{payoutAccount}/reject', [AdminPayoutAccountController::class, 'reject'])->name('payout-accounts.reject');

    // Manual accounting refunds (no money moves electronically).
    Route::post('/tour/bookings/{booking}/refunds', [AdminRefundController::class, 'store'])->name('bookings.refunds.store');

    // Phase 11.5B: manual payment collection (append-only), reschedules
    // (history-preserving date changes) and operational notes.
    Route::post('/tour/bookings/{booking}/payments', [AdminBookingPaymentController::class, 'store'])->name('bookings.payments.store');
    Route::patch('/tour/bookings/{booking}/payment-due-date', [AdminBookingPaymentController::class, 'updateDueDate'])->name('bookings.payments.due-date');
    Route::post('/tour/bookings/{booking}/payment-reminder', [AdminBookingPaymentController::class, 'remind'])->name('bookings.payments.remind');
    Route::get('/tour/bookings/{booking}/payments/{payment}/receipt', [AdminBookingPaymentController::class, 'receipt'])->name('bookings.payments.receipt');
    Route::post('/tour/bookings/{booking}/reschedule', [AdminBookingRescheduleController::class, 'store'])->name('bookings.reschedule.store');
    Route::post('/tour/bookings/{booking}/notes', [AdminBookingNoteController::class, 'store'])->name('bookings.notes.store');

    // Per-vendor finance detail + manual adjustments (append-only).
    Route::get('/vendor-finances/{vendorProfile}', [AdminVendorFinanceController::class, 'show'])->name('vendor-finances.show');
    Route::post('/vendor-finances/{vendorProfile}/adjustments', [AdminVendorFinanceController::class, 'storeAdjustment'])->name('vendor-finances.adjustments.store');

    Route::get('/reviews', [ReviewModerationController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/{review}', [ReviewModerationController::class, 'show'])->name('reviews.show');
    Route::patch('/reviews/{review}/approve', [ReviewModerationController::class, 'approve'])->name('reviews.approve');
    Route::patch('/reviews/{review}/reject', [ReviewModerationController::class, 'reject'])->name('reviews.reject');
    Route::delete('/reviews/{review}', [ReviewModerationController::class, 'destroy'])->name('reviews.destroy');

    // admin settings routes
    Route::get(
        '/settings',
        [SettingController::class, 'index']
    )->name('settings.index');

    Route::post(
        '/settings/basic',
        [SettingController::class, 'updateBasic']
    )->name('settings.basic.update');

    Route::post(
        '/settings/logo',
        [SettingController::class, 'updateLogo']
    )->name('settings.logo.update');

    Route::post(
        '/settings/contact',
        [SettingController::class, 'updateContact']
    )->name('settings.contact.update');

    Route::post(
        '/settings/social',
        [SettingController::class, 'updateSocial']
    )->name('settings.social.update');

    Route::post(
        '/settings/seo',
        [SettingController::class, 'updateSeo']
    )->name('settings.seo.update');

    Route::post(
        '/settings/marketplace',
        [SettingController::class, 'updateMarketplace']
    )->name('settings.marketplace.update');

    Route::post(
        '/settings/operations',
        [SettingController::class, 'updateOperations']
    )->name('settings.operations.update');

    Route::post(
        '/settings/ai',
        [SettingController::class, 'updateAi']
    )->name('settings.ai.update');

    // Phase 13A shared localization (module-independent platform
    // infrastructure — never gated behind Hotels/Tours/Taxi).
    Route::get('/languages', [AdminLanguageController::class, 'index'])->name('languages.index');
    Route::get('/languages/create', [AdminLanguageController::class, 'create'])->name('languages.create');
    Route::post('/languages', [AdminLanguageController::class, 'store'])->name('languages.store');
    Route::get('/languages/{language}/edit', [AdminLanguageController::class, 'edit'])->name('languages.edit');
    Route::put('/languages/{language}', [AdminLanguageController::class, 'update'])->name('languages.update');
    Route::patch('/languages/{language}/default', [AdminLanguageController::class, 'setDefault'])->name('languages.default');
    Route::patch('/languages/{language}/toggle', [AdminLanguageController::class, 'toggle'])->name('languages.toggle');
    Route::delete('/languages/{language}', [AdminLanguageController::class, 'destroy'])->name('languages.destroy');
    Route::post('/translations', [AdminContentTranslationController::class, 'store'])->name('translations.store');

    // Phase 13B shared multi-currency (module-independent platform
    // infrastructure — never gated behind Hotels/Tours/Taxi).
    Route::get('/currencies', [AdminCurrencyController::class, 'index'])->name('currencies.index');
    Route::get('/currencies/create', [AdminCurrencyController::class, 'create'])->name('currencies.create');
    Route::post('/currencies', [AdminCurrencyController::class, 'store'])->name('currencies.store');
    Route::get('/currencies/{currency}/edit', [AdminCurrencyController::class, 'edit'])->name('currencies.edit');
    Route::put('/currencies/{currency}', [AdminCurrencyController::class, 'update'])->name('currencies.update');
    Route::patch('/currencies/{currency}/default', [AdminCurrencyController::class, 'setDefault'])->name('currencies.default');
    Route::patch('/currencies/{currency}/toggle', [AdminCurrencyController::class, 'toggle'])->name('currencies.toggle');
    Route::delete('/currencies/{currency}', [AdminCurrencyController::class, 'destroy'])->name('currencies.destroy');
    Route::post('/exchange-rates', [AdminExchangeRateController::class, 'store'])->name('exchange-rates.store');
    Route::delete('/exchange-rates/{exchangeRate}', [AdminExchangeRateController::class, 'destroy'])->name('exchange-rates.destroy');
    Route::post('/exchange-rates/settings', [AdminExchangeRateController::class, 'updateSettings'])->name('exchange-rates.settings');

    Route::resource('pages', AdminPageController::class)->except(['show']);
    Route::resource('menus', MenuController::class)->except(['show']);

    Route::prefix('menus/{menu}')->name('menus.')->group(function () {
        Route::get('items', [MenuItemController::class, 'index'])->name('items.index');
        Route::get('items/create', [MenuItemController::class, 'create'])->name('items.create');
        Route::put('items/reorder', [MenuItemController::class, 'reorder'])->name('items.reorder');
        Route::post('items/pages', [MenuItemController::class, 'addPages'])->name('items.pages');
        Route::post('items', [MenuItemController::class, 'store'])->name('items.store');
        Route::get('items/{menuItem}/edit', [MenuItemController::class, 'edit'])->name('items.edit');
        Route::put('items/{menuItem}', [MenuItemController::class, 'update'])->name('items.update');
        Route::delete('items/{menuItem}', [MenuItemController::class, 'destroy'])->name('items.destroy');
    });

    Route::prefix('homepage-sections')->name('homepage-sections.')->group(function () {
        Route::get('/', [HomepageSectionController::class, 'index'])->name('index');
        Route::put('/reorder', [HomepageSectionController::class, 'reorder'])->name('reorder');
        Route::put('/merchandising/reorder', [HomepageSectionController::class, 'reorderMerchandising'])->name('merchandising.reorder');
        Route::get('/{homepageSection}/items/search', [HomepageSectionController::class, 'searchItems'])->name('items.search');
        Route::put('/{homepageSection}', [HomepageSectionController::class, 'update'])->name('update');
    });

    Route::get('/vendor-applications', [AdminVendorApplicationController::class, 'index'])->name('vendor-applications.index');
    Route::get('/vendor-applications/{vendorApplication}', [AdminVendorApplicationController::class, 'show'])->name('vendor-applications.show');
    Route::post('/vendor-applications/{vendorApplication}/approve', [AdminVendorApplicationController::class, 'approve'])->name('vendor-applications.approve');
    Route::post('/vendor-applications/{vendorApplication}/reject', [AdminVendorApplicationController::class, 'reject'])->name('vendor-applications.reject');
    Route::post('/vendor-applications/{vendorApplication}/request-resubmission', [AdminVendorApplicationController::class, 'requestResubmission'])->name('vendor-applications.resubmission');
    Route::post('/vendor-applications/{vendorApplication}/verify-kyc', [AdminVendorApplicationController::class, 'verifyKyc'])->name('vendor-applications.verify-kyc');

    Route::get('/vendor-documents/{vendorDocument}/download', [AdminVendorDocumentController::class, 'download'])->name('vendor-documents.download');
    Route::post('/vendor-documents/{vendorDocument}/verify', [AdminVendorDocumentController::class, 'verify'])->name('vendor-documents.verify');
    Route::post('/vendor-documents/{vendorDocument}/reject', [AdminVendorDocumentController::class, 'reject'])->name('vendor-documents.reject');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');

    Route::post('/users/{user}/impersonate', [AdminImpersonationController::class, 'store'])->name('users.impersonate');

    // Phase 11.5A platform core: global search, staff, roles, modules,
    // number series. Server-side permission enforcement comes from the
    // staff.permissions middleware map + explicit can: gates below.
    Route::get('/search', [AdminSearchController::class, 'search'])
        ->middleware('throttle:60,1')
        ->name('search');
    Route::get('/select-options', [AdminSearchController::class, 'selectOptions'])
        ->middleware('throttle:120,1')
        ->name('select-options');

    Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
    Route::get('/staff/create', [AdminStaffController::class, 'create'])->name('staff.create');
    Route::post('/staff', [AdminStaffController::class, 'store'])->name('staff.store');
    Route::get('/staff/{staff}', [AdminStaffController::class, 'show'])->name('staff.show');
    Route::get('/staff/{staff}/edit', [AdminStaffController::class, 'edit'])->name('staff.edit');
    Route::put('/staff/{staff}', [AdminStaffController::class, 'update'])->name('staff.update');

    Route::get('/roles', [AdminRoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [AdminRoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [AdminRoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [AdminRoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [AdminRoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [AdminRoleController::class, 'destroy'])->name('roles.destroy');

    Route::get('/modules', [AdminModuleController::class, 'index'])->name('modules.index');
    Route::patch('/modules/{module}', [AdminModuleController::class, 'update'])->name('modules.update');

    Route::get('/number-series', [AdminNumberSeriesController::class, 'index'])->name('number-series.index');
    Route::put('/number-series/{entity}', [AdminNumberSeriesController::class, 'update'])->name('number-series.update');

    // Phase 11.5D platform operations: audit log, system health,
    // failed jobs, reports. Permissions enforced per route below.
    Route::get('/activity-logs', [AdminActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/system', [AdminSystemHealthController::class, 'index'])->name('system.index');
    Route::get('/system/failed-jobs', [AdminFailedJobController::class, 'index'])->name('system.failed-jobs.index');
    Route::post('/system/failed-jobs/{uuid}/retry', [AdminFailedJobController::class, 'retry'])->name('system.failed-jobs.retry');
    Route::delete('/system/failed-jobs/{uuid}', [AdminFailedJobController::class, 'destroy'])->name('system.failed-jobs.destroy');
    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [AdminReportController::class, 'export'])->name('reports.export');

    // Phase 11.5C support desk + communications.
    Route::get('/support', [AdminSupportTicketController::class, 'index'])->name('support.index');
    Route::get('/support/{ticket}', [AdminSupportTicketController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/replies', [AdminSupportTicketController::class, 'reply'])->name('support.replies.store');
    Route::post('/support/{ticket}/notes', [AdminSupportTicketController::class, 'note'])->name('support.notes.store');
    Route::post('/support/{ticket}/assign', [AdminSupportTicketController::class, 'assign'])->name('support.assign');
    Route::patch('/support/{ticket}/status', [AdminSupportTicketController::class, 'status'])->name('support.status');
    Route::get('/support/{ticket}/attachments/{attachment}', [AdminSupportTicketController::class, 'download'])->name('support.attachments.download');

    Route::get('/support-categories', [AdminSupportCategoryController::class, 'index'])->name('support-categories.index');
    Route::post('/support-categories', [AdminSupportCategoryController::class, 'store'])->name('support-categories.store');
    Route::put('/support-categories/{supportCategory}', [AdminSupportCategoryController::class, 'update'])->name('support-categories.update');
    Route::delete('/support-categories/{supportCategory}', [AdminSupportCategoryController::class, 'destroy'])->name('support-categories.destroy');

    Route::get('/messages/create', [AdminMessageController::class, 'create'])->name('messages.create');
    Route::post('/messages', [AdminMessageController::class, 'store'])->name('messages.store');

    Route::get('/templates', [AdminTemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [AdminTemplateController::class, 'store'])->name('templates.store');
    Route::put('/templates/{template}', [AdminTemplateController::class, 'update'])->name('templates.update');

    Route::get('/communication-logs', [AdminCommunicationLogController::class, 'index'])->name('communication-logs.index');

    Route::get('/campaigns', [AdminCampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [AdminCampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [AdminCampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [AdminCampaignController::class, 'show'])->name('campaigns.show');
    Route::get('/campaigns/{campaign}/edit', [AdminCampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('/campaigns/{campaign}', [AdminCampaignController::class, 'update'])->name('campaigns.update');
    Route::post('/campaigns/{campaign}/send', [AdminCampaignController::class, 'send'])->name('campaigns.send');
    Route::post('/campaigns/{campaign}/schedule', [AdminCampaignController::class, 'schedule'])->name('campaigns.schedule');
    Route::post('/campaigns/{campaign}/cancel', [AdminCampaignController::class, 'cancel'])->name('campaigns.cancel');

    Route::post('/users/{user}/invite', [AdminInvitationController::class, 'store'])->name('users.invite');

    Route::post('/quotations/{quotation}/share', [AdminShareController::class, 'quotation'])->name('quotations.share');
    Route::post('/tour/bookings/{booking}/share-invoice', [AdminShareController::class, 'invoice'])->name('bookings.share-invoice');
    Route::post('/tour/bookings/{booking}/payments/{payment}/share-receipt', [AdminShareController::class, 'receipt'])->name('bookings.share-receipt');

    // Legacy Tour Booking URLs remain routable for saved links and external
    // integrations. Named application routes above generate the canonical
    // /admin/tour/bookings paths.
    Route::prefix('bookings')->name('legacy.bookings.')->group(function () {
        Route::get('/', [BookingManagerController::class, 'index'])->name('index');
        Route::get('/create', [BookingManagerController::class, 'create'])->name('create');
        Route::get('/desk', [BookingManagerController::class, 'desk'])->name('desk');
        Route::post('/', [BookingManagerController::class, 'store'])->name('store');
        Route::get('/{booking}', [BookingManagerController::class, 'show'])->name('show');
        Route::patch('/{booking}/status', [BookingManagerController::class, 'updateStatus'])->name('status');
        Route::patch('/{booking}/cancellation-requests/{cancellation}/approve', [BookingManagerController::class, 'approveCancellation'])->name('cancellation.approve');
        Route::patch('/{booking}/cancellation-requests/{cancellation}/reject', [BookingManagerController::class, 'rejectCancellation'])->name('cancellation.reject');
        Route::post('/{booking}/refunds', [AdminRefundController::class, 'store'])->name('refunds.store');
        Route::post('/{booking}/payments', [AdminBookingPaymentController::class, 'store'])->name('payments.store');
        Route::patch('/{booking}/payment-due-date', [AdminBookingPaymentController::class, 'updateDueDate'])->name('payments.due-date');
        Route::post('/{booking}/payment-reminder', [AdminBookingPaymentController::class, 'remind'])->name('payments.remind');
        Route::get('/{booking}/payments/{payment}/receipt', [AdminBookingPaymentController::class, 'receipt'])->name('payments.receipt');
        Route::post('/{booking}/reschedule', [AdminBookingRescheduleController::class, 'store'])->name('reschedule.store');
        Route::post('/{booking}/notes', [AdminBookingNoteController::class, 'store'])->name('notes.store');
        Route::post('/{booking}/share-invoice', [AdminShareController::class, 'invoice'])->name('share-invoice');
        Route::post('/{booking}/payments/{payment}/share-receipt', [AdminShareController::class, 'receipt'])->name('share-receipt');
    });

    // Phase 11.5B CRM: leads, follow-ups, quotations, customers.
    Route::get('/crm', [AdminCrmDashboardController::class, 'index'])->name('crm.index');

    Route::get('/lead-sources', [AdminLeadSourceController::class, 'index'])->name('lead-sources.index');
    Route::post('/lead-sources', [AdminLeadSourceController::class, 'store'])->name('lead-sources.store');
    Route::put('/lead-sources/{leadSource}', [AdminLeadSourceController::class, 'update'])->name('lead-sources.update');
    Route::delete('/lead-sources/{leadSource}', [AdminLeadSourceController::class, 'destroy'])->name('lead-sources.destroy');

    Route::get('/leads', [AdminLeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/create', [AdminLeadController::class, 'create'])->name('leads.create');
    Route::post('/leads', [AdminLeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/{lead}', [AdminLeadController::class, 'show'])->name('leads.show');
    Route::get('/leads/{lead}/edit', [AdminLeadController::class, 'edit'])->name('leads.edit');
    Route::put('/leads/{lead}', [AdminLeadController::class, 'update'])->name('leads.update');
    Route::post('/leads/{lead}/assign', [AdminLeadController::class, 'assign'])->name('leads.assign');
    Route::patch('/leads/{lead}/status', [AdminLeadController::class, 'status'])->name('leads.status');
    Route::post('/leads/{lead}/notes', [AdminLeadController::class, 'note'])->name('leads.notes.store');
    Route::post('/leads/{lead}/convert', [AdminLeadController::class, 'convert'])->name('leads.convert');
    Route::post('/leads/{lead}/follow-ups', [AdminLeadController::class, 'storeFollowUp'])->name('leads.follow-ups.store');

    Route::get('/follow-ups', [AdminFollowUpController::class, 'index'])->name('follow-ups.index');
    Route::patch('/follow-ups/{followUp}/complete', [AdminFollowUpController::class, 'complete'])->name('follow-ups.complete');
    Route::patch('/follow-ups/{followUp}/cancel', [AdminFollowUpController::class, 'cancel'])->name('follow-ups.cancel');

    Route::get('/quotations', [AdminQuotationController::class, 'index'])->name('quotations.index');
    Route::get('/quotations/create', [AdminQuotationController::class, 'create'])->name('quotations.create');
    Route::post('/quotations', [AdminQuotationController::class, 'store'])->name('quotations.store');
    Route::get('/quotations/{quotation}', [AdminQuotationController::class, 'show'])->name('quotations.show');
    Route::get('/quotations/{quotation}/edit', [AdminQuotationController::class, 'edit'])->name('quotations.edit');
    Route::put('/quotations/{quotation}', [AdminQuotationController::class, 'update'])->name('quotations.update');
    Route::post('/quotations/{quotation}/revisions', [AdminQuotationController::class, 'revise'])->name('quotations.revisions.store');
    Route::post('/quotations/{quotation}/send', [AdminQuotationController::class, 'send'])->name('quotations.send');
    Route::post('/quotations/{quotation}/accept', [AdminQuotationController::class, 'accept'])->name('quotations.accept');
    Route::post('/quotations/{quotation}/reject', [AdminQuotationController::class, 'reject'])->name('quotations.reject');
    Route::post('/quotations/{quotation}/expire', [AdminQuotationController::class, 'expire'])->name('quotations.expire');
    Route::get('/quotations/{quotation}/convert', [AdminQuotationController::class, 'convertForm'])->name('quotations.convert.form');
    Route::post('/quotations/{quotation}/convert', [AdminQuotationController::class, 'convert'])->name('quotations.convert');

    Route::post('/customers', [AdminCustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/search', [AdminCustomerController::class, 'search'])->name('customers.search');

    // Phase 11 vendor plans (manual assignment, no billing yet).
    Route::get('/vendor-plans', [AdminVendorPlanController::class, 'index'])->name('vendor-plans.index');
    Route::get('/vendor-plans/create', [AdminVendorPlanController::class, 'create'])->name('vendor-plans.create');
    Route::post('/vendor-plans', [AdminVendorPlanController::class, 'store'])->name('vendor-plans.store');
    Route::get('/vendor-plans/{vendorPlan}/edit', [AdminVendorPlanController::class, 'edit'])->name('vendor-plans.edit');
    Route::put('/vendor-plans/{vendorPlan}', [AdminVendorPlanController::class, 'update'])->name('vendor-plans.update');
    Route::patch('/vendor-plans/{vendorPlan}/toggle', [AdminVendorPlanController::class, 'toggle'])->name('vendor-plans.toggle');
    Route::delete('/vendor-plans/{vendorPlan}', [AdminVendorPlanController::class, 'destroy'])->name('vendor-plans.destroy');
    Route::post('/vendor-profiles/{vendorProfile}/plan', [AdminVendorPlanController::class, 'assign'])->name('vendor-profiles.plan.assign');

    // Phase 12B.1 hotel module (blocked with 404 while the hotels module
    // is disabled; staff permissions enforced per route via the map).
    Route::middleware('module:hotels')->prefix('hotel')->name('hotel.')->group(function () {
        Route::get('/operations', [App\Http\Controllers\Admin\Hotel\OperationsController::class, 'index'])->name('operations');
        Route::get('/reviews', [App\Http\Controllers\Admin\Hotel\ReviewController::class, 'index'])->name('reviews.index');
        Route::get('/reviews/{review}', [App\Http\Controllers\Admin\Hotel\ReviewController::class, 'show'])->name('reviews.show');
        Route::patch('/reviews/{review}/moderate', [App\Http\Controllers\Admin\Hotel\ReviewController::class, 'moderate'])->name('reviews.moderate');
        Route::post('/reviews/{review}/reply', [App\Http\Controllers\Admin\Hotel\ReviewController::class, 'reply'])->name('reviews.reply');
        Route::put('/reviews/{review}/reply', [App\Http\Controllers\Admin\Hotel\ReviewController::class, 'updateReply'])->name('reviews.reply.update');
        Route::delete('/reviews/{review}/reply', [App\Http\Controllers\Admin\Hotel\ReviewController::class, 'destroyReply'])->name('reviews.reply.destroy');
        Route::patch('/operations/bookings/{booking}/status', [App\Http\Controllers\Admin\Hotel\OperationsController::class, 'status'])->name('operations.status');
        Route::get('/properties', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'index'])->name('properties.index');
        Route::get('/properties/create', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'create'])->name('properties.create');
        Route::post('/properties', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'store'])->name('properties.store');
        Route::get('/properties/{property:id}', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'show'])->name('properties.show');
        Route::get('/properties/{property:id}/edit', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'edit'])->name('properties.edit');
        Route::put('/properties/{property:id}', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'update'])->name('properties.update');
        Route::delete('/properties/{property:id}', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'destroy'])->name('properties.destroy');
        Route::post('/properties/{property:id}/publish', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'publish'])->name('properties.publish');
        Route::post('/properties/{property:id}/reject', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'reject'])->name('properties.reject');
        Route::post('/properties/{property:id}/deactivate', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'deactivate'])->name('properties.deactivate');
        Route::post('/properties/{property:id}/images', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'storeImage'])->name('properties.images.store');
        Route::delete('/properties/{property:id}/images/{image}', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'destroyImage'])->name('properties.images.destroy');
        Route::patch('/properties/{property:id}/images/{image}/primary', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'primaryImage'])->name('properties.images.primary');
        Route::get('/cities', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'cities'])->name('cities.index');
        Route::get('/destinations', [App\Http\Controllers\Admin\Hotel\PropertyController::class, 'destinations'])->name('destinations.index');

        Route::get('/property-types', [CatalogueController::class, 'types'])->name('property-types.index');
        Route::post('/property-types', [CatalogueController::class, 'storeType'])->name('property-types.store');
        Route::put('/property-types/{type}', [CatalogueController::class, 'updateType'])->name('property-types.update');
        Route::patch('/property-types/{type}/toggle', [CatalogueController::class, 'toggleType'])->name('property-types.toggle');

        Route::get('/amenities', [CatalogueController::class, 'amenities'])->name('amenities.index');
        Route::post('/amenities', [CatalogueController::class, 'storeAmenity'])->name('amenities.store');
        Route::put('/amenities/{amenity}', [CatalogueController::class, 'updateAmenity'])->name('amenities.update');
        Route::patch('/amenities/{amenity}/toggle', [CatalogueController::class, 'toggleAmenity'])->name('amenities.toggle');

        Route::get('/settings', [HotelSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [HotelSettingsController::class, 'update'])->name('settings.update');

        Route::get('/properties/{property:id}/room-types', [RoomController::class, 'index'])->name('room-types.index');
        Route::get('/properties/{property:id}/room-types/create', [RoomController::class, 'create'])->name('room-types.create');
        Route::post('/properties/{property:id}/room-types', [RoomController::class, 'store'])->name('room-types.store');
        Route::get('/room-types/{roomType}', [RoomController::class, 'show'])->name('room-types.show');
        Route::get('/room-types/{roomType}/edit', [RoomController::class, 'edit'])->name('room-types.edit');
        Route::put('/room-types/{roomType}', [RoomController::class, 'update'])->name('room-types.update');
        Route::delete('/room-types/{roomType}', [RoomController::class, 'destroy'])->name('room-types.destroy');
        Route::post('/room-types/{roomType}/images', [RoomController::class, 'storeImage'])->name('room-types.images.store');
        Route::delete('/room-types/{roomType}/images/{image}', [RoomController::class, 'destroyImage'])->name('room-types.images.destroy');
        Route::patch('/room-types/{roomType}/images/{image}/primary', [RoomController::class, 'primaryImage'])->name('room-types.images.primary');

        Route::get('/properties/{property:id}/units', [UnitController::class, 'index'])->name('room-units.index');
        Route::post('/properties/{property:id}/units', [UnitController::class, 'store'])->name('room-units.store');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->name('room-units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('room-units.destroy');

        Route::get('/bed-types', [CatalogueController::class, 'bedTypes'])->name('bed-types.index');
        Route::post('/bed-types', [CatalogueController::class, 'storeBedType'])->name('bed-types.store');
        Route::put('/bed-types/{bedType}', [CatalogueController::class, 'updateBedType'])->name('bed-types.update');
        Route::patch('/bed-types/{bedType}/toggle', [CatalogueController::class, 'toggleBedType'])->name('bed-types.toggle');

        Route::get('/custom-fields', [CustomFieldController::class, 'index'])->name('custom-fields.index');
        Route::post('/custom-fields', [CustomFieldController::class, 'store'])->name('custom-fields.store');
        Route::put('/custom-fields/{definition}', [CustomFieldController::class, 'update'])->name('custom-fields.update');
        Route::patch('/custom-fields/{definition}/toggle', [CustomFieldController::class, 'toggle'])->name('custom-fields.toggle');

        Route::get('/inventory', [App\Http\Controllers\Admin\Hotel\InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory', [App\Http\Controllers\Admin\Hotel\InventoryController::class, 'store'])->name('inventory.store');
        Route::post('/inventory/bulk', [App\Http\Controllers\Admin\Hotel\InventoryController::class, 'bulk'])->name('inventory.bulk');
        Route::delete('/inventory', [App\Http\Controllers\Admin\Hotel\InventoryController::class, 'clear'])->name('inventory.clear');

        Route::get('/rate-plans', [App\Http\Controllers\Admin\Hotel\RatePlanController::class, 'index'])->name('rate-plans.index');
        Route::post('/rate-plans', [App\Http\Controllers\Admin\Hotel\RatePlanController::class, 'store'])->name('rate-plans.store');
        Route::put('/rate-plans/{plan}', [App\Http\Controllers\Admin\Hotel\RatePlanController::class, 'update'])->name('rate-plans.update');
        Route::patch('/rate-plans/{plan}/toggle', [App\Http\Controllers\Admin\Hotel\RatePlanController::class, 'toggle'])->name('rate-plans.toggle');
        Route::post('/seasons', [App\Http\Controllers\Admin\Hotel\RatePlanController::class, 'storeSeason'])->name('seasons.store');
        Route::put('/seasons/{season}', [App\Http\Controllers\Admin\Hotel\RatePlanController::class, 'updateSeason'])->name('seasons.update');
        Route::patch('/seasons/{season}/toggle', [App\Http\Controllers\Admin\Hotel\RatePlanController::class, 'toggleSeason'])->name('seasons.toggle');
        Route::delete('/seasons/{season}', [App\Http\Controllers\Admin\Hotel\RatePlanController::class, 'destroySeason'])->name('seasons.destroy');
        Route::get('/daily-rates', [App\Http\Controllers\Admin\Hotel\DailyRateController::class, 'index'])->name('daily-rates.index');
        Route::post('/daily-rates', [App\Http\Controllers\Admin\Hotel\DailyRateController::class, 'store'])->name('daily-rates.store');
        Route::post('/daily-rates/bulk', [App\Http\Controllers\Admin\Hotel\DailyRateController::class, 'bulk'])->name('daily-rates.bulk');
        Route::delete('/daily-rates', [App\Http\Controllers\Admin\Hotel\DailyRateController::class, 'clear'])->name('daily-rates.clear');
        Route::get('/bookings', [App\Http\Controllers\Admin\Hotel\BookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [App\Http\Controllers\Admin\Hotel\BookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings', [App\Http\Controllers\Admin\Hotel\BookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{booking}', [App\Http\Controllers\Admin\Hotel\BookingController::class, 'show'])->name('bookings.show');
        Route::patch('/bookings/{booking}/status', [App\Http\Controllers\Admin\Hotel\BookingController::class, 'status'])->name('bookings.status');
        Route::post('/bookings/{booking}/cancel', [App\Http\Controllers\Admin\Hotel\BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{booking}/refunds', [App\Http\Controllers\Admin\Hotel\BookingController::class, 'refund'])->name('bookings.refunds.store');
        Route::post('/bookings/{booking}/reschedule', [App\Http\Controllers\Admin\Hotel\BookingController::class, 'reschedule'])->name('bookings.reschedule');
        Route::post('/bookings/{booking}/reschedule-quote', [App\Http\Controllers\Admin\Hotel\BookingController::class, 'rescheduleQuote'])->name('bookings.reschedule-quote');
        Route::get('/charges', [ChargeRuleController::class, 'index'])->name('charges.index');
        Route::post('/charges', [ChargeRuleController::class, 'store'])->name('charges.store');
        Route::put('/charges/{rule}', [ChargeRuleController::class, 'update'])->name('charges.update');
        Route::patch('/charges/{rule}/toggle', [ChargeRuleController::class, 'toggle'])->name('charges.toggle');
    });

    // Phase 12A.1 taxi module (blocked with 404 while the taxi module is
    // disabled; staff permissions enforced per route via the map).
    Route::middleware('module:taxi')->prefix('taxi')->name('taxi.')->group(function () {
        Route::get('/dashboard', [AdminTaxiDashboardController::class, 'index'])->name('dashboard');

        Route::get('/pricing', [AdminTaxiRateCardController::class, 'index'])->name('pricing.index');
        Route::get('/pricing/create', [AdminTaxiRateCardController::class, 'create'])->name('pricing.create');
        Route::post('/pricing', [AdminTaxiRateCardController::class, 'store'])->name('pricing.store');
        Route::get('/pricing/{rateCard}/edit', [AdminTaxiRateCardController::class, 'edit'])->name('pricing.edit');
        Route::put('/pricing/{rateCard}', [AdminTaxiRateCardController::class, 'update'])->name('pricing.update');
        Route::patch('/pricing/{rateCard}/toggle', [AdminTaxiRateCardController::class, 'toggle'])->name('pricing.toggle');
        Route::delete('/pricing/{rateCard}', [AdminTaxiRateCardController::class, 'destroy'])->name('pricing.destroy');

        Route::get('/vehicle-types', [AdminTaxiVehicleTypeController::class, 'index'])->name('vehicle-types.index');
        Route::post('/vehicle-types', [AdminTaxiVehicleTypeController::class, 'store'])->name('vehicle-types.store');
        Route::put('/vehicle-types/{vehicleType}', [AdminTaxiVehicleTypeController::class, 'update'])->name('vehicle-types.update');
        Route::delete('/vehicle-types/{vehicleType}', [AdminTaxiVehicleTypeController::class, 'destroy'])->name('vehicle-types.destroy');

        Route::get('/vehicles', [AdminTaxiVehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/vehicles/create', [AdminTaxiVehicleController::class, 'create'])->name('vehicles.create');
        Route::post('/vehicles', [AdminTaxiVehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/vehicles/{vehicle}', [AdminTaxiVehicleController::class, 'show'])->name('vehicles.show');
        Route::get('/vehicles/{vehicle}/edit', [AdminTaxiVehicleController::class, 'edit'])->name('vehicles.edit');
        Route::put('/vehicles/{vehicle}', [AdminTaxiVehicleController::class, 'update'])->name('vehicles.update');
        Route::post('/vehicles/{vehicle}/documents', [AdminTaxiVehicleController::class, 'storeDocument'])->name('vehicles.documents.store');
        Route::post('/vehicles/documents/{document}/verify', [AdminTaxiVehicleController::class, 'verifyDocument'])->name('vehicles.documents.verify');
        Route::post('/vehicles/{vehicle}/unavailable', [AdminTaxiVehicleController::class, 'storeUnavailable'])->name('vehicles.unavailable.store');
        Route::delete('/vehicles/unavailable/{period}', [AdminTaxiVehicleController::class, 'destroyUnavailable'])->name('vehicles.unavailable.destroy');

        Route::get('/drivers', [AdminTaxiDriverController::class, 'index'])->name('drivers.index');
        Route::get('/drivers/create', [AdminTaxiDriverController::class, 'create'])->name('drivers.create');
        Route::post('/drivers', [AdminTaxiDriverController::class, 'store'])->name('drivers.store');
        Route::get('/drivers/{driver}', [AdminTaxiDriverController::class, 'show'])->name('drivers.show');
        Route::get('/drivers/{driver}/edit', [AdminTaxiDriverController::class, 'edit'])->name('drivers.edit');
        Route::put('/drivers/{driver}', [AdminTaxiDriverController::class, 'update'])->name('drivers.update');
        Route::post('/drivers/{driver}/documents', [AdminTaxiDriverController::class, 'storeDocument'])->name('drivers.documents.store');
        Route::post('/drivers/documents/{document}/verify', [AdminTaxiDriverController::class, 'verifyDocument'])->name('drivers.documents.verify');
        Route::post('/drivers/{driver}/availability', [AdminTaxiDriverController::class, 'storeAvailability'])->name('drivers.availability.store');
        Route::delete('/drivers/availability/{availability}', [AdminTaxiDriverController::class, 'destroyAvailability'])->name('drivers.availability.destroy');

        Route::get('/dispatch', [AdminTaxiDispatchController::class, 'index'])->name('dispatch.index');
        Route::get('/dispatch/{taxiBooking}/eligible', [AdminTaxiDispatchController::class, 'eligible'])->name('dispatch.eligible');
        Route::post('/dispatch/{taxiBooking}/notes', [AdminTaxiDispatchController::class, 'storeNote'])->name('dispatch.notes.store');
        Route::get('/dispatch/{taxiBooking}/notes', [AdminTaxiDispatchController::class, 'notes'])->name('dispatch.notes.index');
        Route::get('/dispatch/{taxiBooking}/recommendations', [AdminTaxiDispatchController::class, 'recommendations'])->name('dispatch.recommendations');

        Route::get('/tracking', [AdminTaxiTrackingController::class, 'index'])->name('tracking.index');
        Route::get('/tracking/route', [AdminTaxiTrackingController::class, 'route'])->name('tracking.route');

        Route::get('/bookings', [AdminTaxiBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [AdminTaxiBookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings/quote', [AdminTaxiBookingController::class, 'quote'])->name('bookings.quote');
        Route::post('/bookings', [AdminTaxiBookingController::class, 'store'])->name('bookings.store');
        Route::post('/bookings/convert', [AdminTaxiBookingController::class, 'convert'])->name('bookings.convert');
        Route::get('/bookings/{taxiBooking}', [AdminTaxiBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{taxiBooking}/assign', [AdminTaxiBookingController::class, 'assign'])->name('bookings.assign');
        Route::post('/bookings/{taxiBooking}/unassign', [AdminTaxiBookingController::class, 'unassign'])->name('bookings.unassign');
        Route::post('/bookings/{taxiBooking}/auto-dispatch/start', [AdminTaxiBookingController::class, 'startAutoDispatch'])->name('bookings.auto-dispatch.start');
        Route::post('/bookings/{taxiBooking}/auto-dispatch/stop', [AdminTaxiBookingController::class, 'stopAutoDispatch'])->name('bookings.auto-dispatch.stop');
        Route::post('/bookings/{taxiBooking}/tracking', [AdminTaxiBookingController::class, 'generateTrackingLink'])->name('bookings.tracking.store');
        Route::delete('/bookings/{taxiBooking}/tracking', [AdminTaxiBookingController::class, 'revokeTrackingLink'])->name('bookings.tracking.destroy');
        Route::patch('/bookings/{taxiBooking}/status', [AdminTaxiBookingController::class, 'status'])->name('bookings.status');
        Route::post('/bookings/{taxiBooking}/payments', [AdminTaxiBookingController::class, 'storePayment'])->name('bookings.payments.store');

        // Phase 12A.11: authenticated booking changes and manual refunds.
        Route::get('/changes/{booking}', [TaxiChangeController::class, 'show'])->name('changes.show')->middleware('throttle:30,1');
        Route::get('/changes/{booking}/quote', [TaxiChangeController::class, 'quote'])->name('changes.quote')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/cancel', [TaxiChangeController::class, 'cancel'])->name('changes.cancel')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/reschedule-quote', [TaxiChangeController::class, 'rescheduleQuote'])->name('changes.reschedule-quote')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/reschedule', [TaxiChangeController::class, 'reschedule'])->name('changes.reschedule')->middleware('throttle:30,1');
        Route::post('/changes/{booking}/refunds', [TaxiChangeController::class, 'refund'])->name('changes.refunds.store');
        Route::post('/changes/{booking}/refunds/{refund}/process', [TaxiChangeController::class, 'processRefund'])->name('changes.refunds.process');
        Route::get('/cancellation-policies', [TaxiCancellationPolicyController::class, 'index'])->name('cancellation-policies.index');
        Route::post('/cancellation-policies', [TaxiCancellationPolicyController::class, 'store'])->name('cancellation-policies.store');
        Route::put('/cancellation-policies/{policy}', [TaxiCancellationPolicyController::class, 'update'])->name('cancellation-policies.update');
        Route::patch('/cancellation-policies/{policy}/toggle', [TaxiCancellationPolicyController::class, 'toggle'])->name('cancellation-policies.toggle');
        Route::put('/cancellation-settings', [TaxiCancellationPolicyController::class, 'settings'])->name('cancellation-settings.update');
        Route::get('/reviews', [TaxiReviewController::class, 'index'])->name('reviews.index');
        Route::get('/reviews/{review}', [TaxiReviewController::class, 'show'])->name('reviews.show');
        Route::post('/reviews/{review}/moderate', [TaxiReviewController::class, 'moderate'])->name('reviews.moderate');
        Route::post('/reviews/{review}/reply', [TaxiReviewController::class, 'reply'])->name('reviews.reply');
        Route::post('/reviews/{review}/flag', [TaxiReviewController::class, 'flag'])->name('reviews.flag');
        Route::get('/earnings', [AdminTaxiDriverEarningController::class, 'index'])->name('earnings.index');
        Route::get('/earnings/{earning}', [AdminTaxiDriverEarningController::class, 'show'])->name('earnings.show');
        Route::patch('/earnings/{earning}/payable', [AdminTaxiDriverEarningController::class, 'markPayable'])->name('earnings.payable');
        Route::patch('/earnings/{earning}/void', [AdminTaxiDriverEarningController::class, 'void'])->name('earnings.void');
        Route::post('/earnings/{earning}/adjustments', [AdminTaxiDriverEarningController::class, 'storeAdjustment'])->name('earnings.adjustments.store');

        Route::get('/plans', [AdminTaxiCompensationPlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [AdminTaxiCompensationPlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [AdminTaxiCompensationPlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [AdminTaxiCompensationPlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan}', [AdminTaxiCompensationPlanController::class, 'update'])->name('plans.update');
        Route::patch('/plans/{plan}/toggle', [AdminTaxiCompensationPlanController::class, 'toggle'])->name('plans.toggle');
        Route::delete('/plans/{plan}', [AdminTaxiCompensationPlanController::class, 'destroy'])->name('plans.destroy');

        Route::get('/payouts', [AdminTaxiDriverPayoutController::class, 'index'])->name('payouts.index');
        Route::get('/payouts/create', [AdminTaxiDriverPayoutController::class, 'create'])->name('payouts.create');
        Route::post('/payouts', [AdminTaxiDriverPayoutController::class, 'store'])->name('payouts.store');
        Route::get('/payouts/{payout}', [AdminTaxiDriverPayoutController::class, 'show'])->name('payouts.show');
        Route::patch('/payouts/{payout}/mark-paid', [AdminTaxiDriverPayoutController::class, 'markPaid'])->name('payouts.mark-paid');
        Route::patch('/payouts/{payout}/cancel', [AdminTaxiDriverPayoutController::class, 'cancel'])->name('payouts.cancel');

        Route::get('/settings', [AdminTaxiSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [AdminTaxiSettingsController::class, 'update'])->name('settings.update');
    });
});

// Phase 12A.1 public taxi request (creates a CRM lead, never a direct
// booking). Hidden with 404 while the taxi module is disabled.
Route::get('/taxi', [TaxiEnquiryController::class, 'show'])->middleware('module:taxi')->name('taxi.enquiry');
Route::post('/taxi/enquiry', [TaxiEnquiryController::class, 'store'])->middleware('module:taxi')->name('taxi.enquiry.store');
Route::post('/taxi/quote', [TaxiEnquiryController::class, 'quote'])
    ->middleware(['module:taxi', 'throttle:30,1'])
    ->name('taxi.quote');
Route::post('/taxi/book', [TaxiEnquiryController::class, 'book'])
    ->middleware(['module:taxi', 'throttle:10,1'])
    ->name('taxi.book');
Route::get('/taxi/confirmation/{taxiBooking}', [TaxiEnquiryController::class, 'confirmation'])
    ->middleware(['module:taxi', 'signed', 'tracking.privacy'])
    ->name('taxi.confirmation');

// Phase 12A.9 public customer tracking (token-authenticated, no login).
// Token shape enforced; privacy headers + throttled refresh included.
Route::get('/taxi/track/{token}', [TaxiTrackingController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{20,128}')
    ->middleware(['module:taxi', 'tracking.privacy', 'throttle:60,1'])
    ->name('taxi.track.public');
Route::get('/taxi/track/{token}/status', [TaxiTrackingController::class, 'status'])
    ->where('token', '[A-Za-z0-9]{20,128}')
    ->middleware(['module:taxi', 'tracking.privacy', 'throttle:30,1'])
    ->name('taxi.track.status');

// Phase 11.5B: read-only public quotation view behind an unguessable
// token (no login). Phase 11.5C adds signed accept/reject decisions.
// Placed before the CMS catch-all.
Route::get('/q/{token}', [PublicQuotationController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('quotations.public');
Route::post('/q/{token}/accept', [PublicQuotationDecisionController::class, 'accept'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('signed')
    ->name('quotations.public.accept');
Route::post('/q/{token}/reject', [PublicQuotationDecisionController::class, 'reject'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('signed')
    ->name('quotations.public.reject');

// Staff-minted secure document links (temporary signed URLs, no login).
Route::get('/share/invoice/{booking}', [SharedDocumentController::class, 'invoice'])
    ->middleware('signed')
    ->name('share.invoice');
Route::get('/share/receipt/{payment}', [SharedDocumentController::class, 'receipt'])
    ->middleware('signed')
    ->name('share.receipt');

// Marketing unsubscribe (signed, no login) and account invitations.
Route::get('/unsubscribe/{user}', [UnsubscribeController::class, 'show'])
    ->middleware('signed')
    ->name('unsubscribe.show');
Route::post('/unsubscribe/{user}', [UnsubscribeController::class, 'store'])
    ->middleware('signed')
    ->name('unsubscribe.store');
Route::get('/invitation/{token}', [InvitationAcceptController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('guest')
    ->name('invitation.accept');
Route::post('/invitation/{token}', [InvitationAcceptController::class, 'store'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('guest')
    ->name('invitation.store');

require __DIR__.'/auth.php'; // Breeze/Fortify-style login, register, password reset routes go here

/*
 * -------------------------------------------------------------------------
 * PUBLIC CMS PAGE RENDERING
 * -------------------------------------------------------------------------
 * This catch‑all route must be placed **after** all other public routes so
 * that it does not shadow static routes (about, contact, etc.) or admin
 * routes. It resolves a single‑segment slug to a CMS page.
 */
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-\_]+')
    ->name('page.show');
