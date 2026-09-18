<?php

use App\Http\Controllers\Account\BookingController as AccountBookingController;
use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Account\SupportTicketController as AccountSupportTicketController;
use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\AdminSearchController;
use App\Http\Controllers\Admin\AiContentController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BookingManagerController;
use App\Http\Controllers\Admin\BookingNoteController as AdminBookingNoteController;
use App\Http\Controllers\Admin\BookingPaymentController as AdminBookingPaymentController;
use App\Http\Controllers\Admin\BookingRescheduleController as AdminBookingRescheduleController;
use App\Http\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Admin\CommunicationLogController as AdminCommunicationLogController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CrmDashboardController as AdminCrmDashboardController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DestinationController as AdminDestinationController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Http\Controllers\Admin\EnquiryController as AdminEnquiryController;
use App\Http\Controllers\Admin\FailedJobController as AdminFailedJobController;
use App\Http\Controllers\Admin\FollowUpController as AdminFollowUpController;
use App\Http\Controllers\Admin\HomepageSectionController;
use App\Http\Controllers\Admin\ImpersonationController as AdminImpersonationController;
use App\Http\Controllers\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\LeadSourceController as AdminLeadSourceController;
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
use App\Http\Controllers\Admin\SupportCategoryController as AdminSupportCategoryController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\SystemHealthController as AdminSystemHealthController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\Taxi\DriverController as AdminTaxiDriverController;
use App\Http\Controllers\Admin\Taxi\TaxiBookingController as AdminTaxiBookingController;
use App\Http\Controllers\Admin\Taxi\TaxiDashboardController as AdminTaxiDashboardController;
use App\Http\Controllers\Admin\Taxi\TaxiSettingsController as AdminTaxiSettingsController;
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
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlaceController as PublicPlaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\PublicQuotationDecisionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SharedDocumentController;
use App\Http\Controllers\TaxiEnquiryController;
use App\Http\Controllers\TourPackageController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\Vendor\BookingController as VendorBookingController;
use App\Http\Controllers\Vendor\CouponController as VendorCouponController; // <-- NEW IMPORT
use App\Http\Controllers\Vendor\FinanceController as VendorFinanceController;
use App\Http\Controllers\Vendor\PayoutAccountController as VendorPayoutAccountController;
use App\Http\Controllers\Vendor\SupportTicketController as VendorSupportTicketController;
use App\Http\Controllers\Vendor\Taxi\TaxiBookingController as VendorTaxiBookingController;
use App\Http\Controllers\Vendor\Taxi\TaxiDashboardController as VendorTaxiDashboardController;
use App\Http\Controllers\Vendor\Taxi\TaxiDriverController as VendorTaxiDriverController;
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
use App\Models\Destination;
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

    $destinations = Destination::query()
        ->select('slug', 'updated_at')
        ->get();

    $places = Place::query()
        ->select('slug', 'updated_at')
        ->get();

    $vendors = VendorProfile::where('is_active', true)
        ->whereNotNull('approved_at')
        ->where('storefront_enabled', true)
        ->whereNotNull('slug')
        ->select('slug', 'updated_at')
        ->get();

    return response()
        ->view('sitemap', compact('packages', 'destinations', 'places', 'vendors'))
        ->header('Content-Type', 'application/xml');
});

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', GlobalSearchController::class)
    ->middleware('throttle:60,1')
    ->name('search');

Route::get('/packages', [TourPackageController::class, 'index'])->name('packages.index')->middleware('module:tours');
Route::get('/packages/{package:slug}', [TourPackageController::class, 'show'])->name('packages.show')->middleware('module:tours');
Route::post('/packages/{package:slug}/reviews', [ReviewController::class, 'storePublic'])
    ->middleware(['throttle:3,10', 'module:tours'])
    ->name('packages.reviews.store');

Route::get('/packages/{package:slug}/book', [PublicBookingController::class, 'create'])->name('booking.form')->middleware('module:tours');
Route::post('/booking/estimate', [PublicBookingController::class, 'estimate'])->name('booking.estimate')->middleware('module:tours');
Route::post('/bookings', [PublicBookingController::class, 'store'])->name('booking.store')->middleware('module:tours');

// Phase 11 public vendor storefront (before the CMS catch-all).
Route::get('/vendors/{vendor:slug}', [VendorStorefrontController::class, 'show'])->name('vendors.show');
Route::get('/bookings/confirmation/{booking}', [PublicBookingController::class, 'confirmation'])->name('booking.confirmation')->middleware('signed');
Route::post('/bookings/{booking}/pay', [PublicBookingController::class, 'pay'])->name('booking.pay')->middleware('signed');

Route::get('/about', fn () => Inertia::render('Static/About'))->name('about');
Route::get('/our-team', fn () => Inertia::render('Static/Team'))->name('team');
Route::get('/contact', [EnquiryController::class, 'create'])->name('contact');
Route::post('/enquiries', [EnquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('enquiries.store');
Route::get('/faq', fn () => Inertia::render('Static/Faq'))->name('faq');
Route::get('/destinations', [DestinationController::class, 'index'])->name('destinations')->middleware('module:tours');
Route::get('/destinations/{destination:slug}', [DestinationController::class, 'show'])->name('destinations.show')->middleware('module:tours');
Route::get('/places/{place:slug}', [PublicPlaceController::class, 'show'])->name('places.show')->middleware('module:tours');
Route::get('/gallery', fn () => Inertia::render('Static/Gallery'))->name('gallery');
Route::get('/blog', fn () => Inertia::render('Blog/Index'))->name('blog');
Route::get('/privacy', fn () => Inertia::render('Static/Privacy'))->name('privacy');
Route::get('/terms', fn () => Inertia::render('Static/Terms'))->name('terms');
Route::get('/spiritual-wisdom', fn () => Inertia::render('Static/SpiritualWisdom'))->name('wisdom');
Route::get('/testimonials', function () {
    return Inertia::render('Static/Testimonials', [
        'testimonials' => Review::where('is_approved', true)
            ->with('user:id,name', 'package:id,title')
            ->latest()
            ->take(24)
            ->get(['id', 'user_id', 'package_id', 'reviewer_name', 'rating', 'comment', 'created_at']),
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
    Route::get('/', [AccountDashboardController::class, 'index'])->name('dashboard');
    Route::get('/bookings', [AccountBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [AccountBookingController::class, 'show'])->name('bookings.show');
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

    // Vendor bookings are read-only: vendors view only bookings
    // historically assigned to their own vendor profile.
    Route::get('/bookings', [VendorBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [VendorBookingController::class, 'create'])->name('bookings.create')->middleware('module:tours');
    Route::post('/bookings', [VendorBookingController::class, 'store'])->name('bookings.store')->middleware('module:tours');
    Route::get('/bookings/{booking}', [VendorBookingController::class, 'show'])->name('bookings.show');

    // Phase 12A.1 vendor taxi fleet (own resources only, taxi module on).
    Route::middleware('module:taxi')->prefix('taxi')->name('taxi.')->group(function () {
        Route::get('/dashboard', [VendorTaxiDashboardController::class, 'index'])->name('dashboard');
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

        Route::get('/bookings', [VendorTaxiBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [VendorTaxiBookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings', [VendorTaxiBookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{taxiBooking}', [VendorTaxiBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{taxiBooking}/assign', [VendorTaxiBookingController::class, 'assign'])->name('bookings.assign');
        Route::post('/bookings/{taxiBooking}/unassign', [VendorTaxiBookingController::class, 'unassign'])->name('bookings.unassign');
        Route::patch('/bookings/{taxiBooking}/status', [VendorTaxiBookingController::class, 'status'])->name('bookings.status');
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

Route::middleware(['auth', 'admin', 'staff.permissions'])->prefix('admin')->name('admin.')->group(function () {
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

    Route::get('/banners', [BannerController::class, 'index'])->name('banners.index');
    Route::post('/banners', [BannerController::class, 'store'])->name('banners.store');
    Route::delete('/banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');
    Route::patch('/banners/{banner}/order', [BannerController::class, 'updateOrder']);

    Route::get('/promotional-popup', [PromotionalPopupController::class, 'index'])->name('promotional-popup.index');
    Route::post('/promotional-popup', [PromotionalPopupController::class, 'store'])->name('promotional-popup.store');

    Route::get('/bookings', [BookingManagerController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [BookingManagerController::class, 'create'])->name('bookings.create');
    // Reservation Desk (structured offline flow) — before {booking}.
    Route::get('/bookings/desk', [BookingManagerController::class, 'desk'])->name('bookings.desk');
    Route::post('/bookings', [BookingManagerController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [BookingManagerController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/status', [BookingManagerController::class, 'updateStatus'])->name('bookings.status');
    Route::patch('/bookings/{booking}/cancellation-requests/{cancellation}/approve', [BookingManagerController::class, 'approveCancellation'])->name('bookings.cancellation.approve');
    Route::patch('/bookings/{booking}/cancellation-requests/{cancellation}/reject', [BookingManagerController::class, 'rejectCancellation'])->name('bookings.cancellation.reject');

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
    Route::post('/bookings/{booking}/refunds', [AdminRefundController::class, 'store'])->name('bookings.refunds.store');

    // Phase 11.5B: manual payment collection (append-only), reschedules
    // (history-preserving date changes) and operational notes.
    Route::post('/bookings/{booking}/payments', [AdminBookingPaymentController::class, 'store'])->name('bookings.payments.store');
    Route::patch('/bookings/{booking}/payment-due-date', [AdminBookingPaymentController::class, 'updateDueDate'])->name('bookings.payments.due-date');
    Route::post('/bookings/{booking}/payment-reminder', [AdminBookingPaymentController::class, 'remind'])->name('bookings.payments.remind');
    Route::get('/bookings/{booking}/payments/{payment}/receipt', [AdminBookingPaymentController::class, 'receipt'])->name('bookings.payments.receipt');
    Route::post('/bookings/{booking}/reschedule', [AdminBookingRescheduleController::class, 'store'])->name('bookings.reschedule.store');
    Route::post('/bookings/{booking}/notes', [AdminBookingNoteController::class, 'store'])->name('bookings.notes.store');

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
    Route::post('/bookings/{booking}/share-invoice', [AdminShareController::class, 'invoice'])->name('bookings.share-invoice');
    Route::post('/bookings/{booking}/payments/{payment}/share-receipt', [AdminShareController::class, 'receipt'])->name('bookings.share-receipt');

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

    // Phase 12A.1 taxi module (blocked with 404 while the taxi module is
    // disabled; staff permissions enforced per route via the map).
    Route::middleware('module:taxi')->prefix('taxi')->name('taxi.')->group(function () {
        Route::get('/dashboard', [AdminTaxiDashboardController::class, 'index'])->name('dashboard');

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

        Route::get('/bookings', [AdminTaxiBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [AdminTaxiBookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings', [AdminTaxiBookingController::class, 'store'])->name('bookings.store');
        Route::post('/bookings/convert', [AdminTaxiBookingController::class, 'convert'])->name('bookings.convert');
        Route::get('/bookings/{taxiBooking}', [AdminTaxiBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{taxiBooking}/assign', [AdminTaxiBookingController::class, 'assign'])->name('bookings.assign');
        Route::post('/bookings/{taxiBooking}/unassign', [AdminTaxiBookingController::class, 'unassign'])->name('bookings.unassign');
        Route::patch('/bookings/{taxiBooking}/status', [AdminTaxiBookingController::class, 'status'])->name('bookings.status');
        Route::post('/bookings/{taxiBooking}/payments', [AdminTaxiBookingController::class, 'storePayment'])->name('bookings.payments.store');

        Route::get('/settings', [AdminTaxiSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [AdminTaxiSettingsController::class, 'update'])->name('settings.update');
    });
});

// Phase 12A.1 public taxi request (creates a CRM lead, never a direct
// booking). Hidden with 404 while the taxi module is disabled.
Route::get('/taxi', [TaxiEnquiryController::class, 'show'])->middleware('module:taxi')->name('taxi.enquiry');
Route::post('/taxi/enquiry', [TaxiEnquiryController::class, 'store'])->middleware('module:taxi')->name('taxi.enquiry.store');

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
