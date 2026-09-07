<?php

use App\Http\Controllers\Admin\AiContentController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BookingManagerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DestinationController as AdminDestinationController;
use App\Http\Controllers\Admin\EnquiryController as AdminEnquiryController;
use App\Http\Controllers\Admin\PackageManagerController;
use App\Http\Controllers\Admin\PlaceController;
use App\Http\Controllers\Admin\PromotionalPopupController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\TourCategoryController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PlaceController as PublicPlaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TourPackageController;
use App\Http\Controllers\Admin\EditorUploadController;
use App\Models\Review;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', GlobalSearchController::class)
    ->middleware('throttle:60,1')
    ->name('search');

Route::get('/packages', [TourPackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{package:slug}', [TourPackageController::class, 'show'])->name('packages.show');
Route::post('/packages/{package:slug}/reviews', [ReviewController::class, 'storePublic'])
    ->middleware('throttle:3,10')
    ->name('packages.reviews.store');

Route::get('/about', fn () => Inertia::render('Static/About'))->name('about');
Route::get('/our-team', fn () => Inertia::render('Static/Team'))->name('team');
Route::get('/contact', [EnquiryController::class, 'create'])->name('contact');
Route::post('/enquiries', [EnquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('enquiries.store');
Route::get('/faq', fn () => Inertia::render('Static/Faq'))->name('faq');
Route::get('/destinations', [DestinationController::class, 'index'])->name('destinations');
Route::get('/destinations/{destination:slug}', [DestinationController::class, 'show'])->name('destinations.show');
Route::get('/places/{place:slug}', [PublicPlaceController::class, 'show'])->name('places.show');
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
    Route::post('/bookings', [BookingController::class, 'store'])->name('booking.store');
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirmPayment'])->name('booking.confirm');
    Route::get('/my-bookings', [BookingController::class, 'history'])->name('booking.history');

    Route::get('/bookings/{booking}/invoice', [InvoiceController::class, 'show'])->name('booking.invoice');
    Route::get('/bookings/{booking}/invoice/download', [InvoiceController::class, 'download'])->name('booking.invoice.download');

    Route::post('/bookings/{booking}/review', [ReviewController::class, 'store'])->name('review.store');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post(
        '/editor/upload-image',
        [EditorUploadController::class, 'store']
    )->name('editor.upload-image');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/enquiries', [AdminEnquiryController::class, 'index'])->name('enquiries.index');
    Route::delete('/enquiries/{enquiry}', [\App\Http\Controllers\Admin\EnquiryController::class, 'destroy'])->name('enquiries.destroy');


    Route::get('/packages', [PackageManagerController::class, 'index'])->name('packages.index');
    Route::get('/packages/create', [PackageManagerController::class, 'create'])->name('packages.create');
    Route::post('/packages', [PackageManagerController::class, 'store'])->name('packages.store');
    Route::get('/packages/{package}/edit', [PackageManagerController::class, 'edit'])->name('packages.edit');
    Route::put('/packages/{package}', [PackageManagerController::class, 'update'])->name('packages.update');
    Route::delete('/packages/{package}', [PackageManagerController::class, 'destroy'])->name('packages.destroy');
    Route::post('/packages/ai-draft-itinerary', [AiContentController::class, 'draftItinerary'])->name('packages.ai-draft');

    Route::resource('destinations', AdminDestinationController::class)->except('show');
    Route::resource('places', PlaceController::class)->except('show');
    Route::resource('tags', TagController::class)->except('show');
    Route::resource('tour-categories', TourCategoryController::class)->except('show');

    Route::get('/banners', [BannerController::class, 'index'])->name('banners.index');
    Route::post('/banners', [BannerController::class, 'store'])->name('banners.store');
    Route::delete('/banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');
    Route::patch('/banners/{banner}/order', [BannerController::class, 'updateOrder']);


    Route::get('/promotional-popup', [PromotionalPopupController::class, 'index'])->name('promotional-popup.index');
    Route::post('/promotional-popup', [PromotionalPopupController::class, 'store'])->name('promotional-popup.store');

    Route::get('/bookings', [BookingManagerController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [BookingManagerController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingManagerController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}/edit', [BookingManagerController::class, 'edit'])->name('bookings.edit');
    Route::put('/bookings/{booking}', [BookingManagerController::class, 'update'])->name('bookings.update');
    Route::delete('/bookings/{booking}', [BookingManagerController::class, 'destroy'])->name('bookings.destroy');
    Route::patch('/bookings/{booking}/status', [BookingManagerController::class, 'updateStatus'])->name('bookings.status');

    Route::get('/reviews', [ReviewModerationController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/{review}', [ReviewModerationController::class, 'show'])->name('reviews.show');
    Route::patch('/reviews/{review}/approve', [ReviewModerationController::class, 'approve'])->name('reviews.approve');
    Route::patch('/reviews/{review}/reject', [ReviewModerationController::class, 'reject'])->name('reviews.reject');
    Route::delete('/reviews/{review}', [ReviewModerationController::class, 'destroy'])->name('reviews.destroy');
});

require __DIR__.'/auth.php'; // Breeze/Fortify-style login, register, password reset routes go here
