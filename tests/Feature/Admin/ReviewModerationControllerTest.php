<?php

namespace Tests\Feature\Admin;

use App\Models\Review;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewModerationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_admin_can_filter_view_approve_unapprove_and_delete_reviews(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $package = TourPackage::factory()->create(['title' => 'Vrindavan Temple Tour']);
        $pendingReview = Review::create([
            'package_id' => $package->id,
            'reviewer_name' => 'Meera Visitor',
            'rating' => 5,
            'comment' => 'Wonderful darshan arrangements and service.',
            'is_approved' => false,
        ]);
        Review::create([
            'package_id' => $package->id,
            'reviewer_name' => 'Approved Visitor',
            'rating' => 3,
            'comment' => 'An already approved review for this tour.',
            'is_approved' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.reviews.index', [
            'search' => 'Meera',
            'package_id' => $package->id,
            'rating' => 5,
            'status' => 'pending',
        ], absolute: false))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reviews')
            ->has('reviews.data', 1)
            ->where('reviews.data.0.id', $pendingReview->id));

        $this->actingAs($admin)->get(route('admin.reviews.show', $pendingReview, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reviews/Show')
                ->where('review.id', $pendingReview->id));

        $this->actingAs($admin)->patch(route('admin.reviews.approve', $pendingReview, absolute: false))->assertRedirect();
        $this->assertTrue($pendingReview->refresh()->is_approved);

        $this->actingAs($admin)->patch(route('admin.reviews.reject', $pendingReview, absolute: false))->assertRedirect();
        $this->assertFalse($pendingReview->refresh()->is_approved);

        $this->actingAs($admin)->delete(route('admin.reviews.destroy', $pendingReview, absolute: false))
            ->assertRedirect(route('admin.reviews.index', absolute: false));
        $this->assertModelMissing($pendingReview);
    }
}
