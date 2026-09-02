<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Enquiry;
use App\Models\State;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_receive_a_server_side_math_security_question(): void
    {
        $tourPackage = $this->createTourPackage();

        $response = $this->get("/packages/{$tourPackage->slug}");

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Show')
            ->has('securityQuestion'));
        $response->assertSessionHas('enquiry_math_answer');
    }

    public function test_public_visitor_can_submit_a_tour_plan_enquiry(): void
    {
        $tourPackage = $this->createTourPackage();

        $response = $this->withSession($this->mathChallengeSession())
            ->post('/enquiries', $this->tourPlanPayload(['tour_package_id' => $tourPackage->id]));

        $response->assertRedirect();
        $response->assertSessionHas('flash', 'Thank you. Your enquiry has been received.');
        $this->assertDatabaseHas('enquiries', [
            'enquiry_type' => 'tour_plan',
            'status' => 'new',
            'tour_package_id' => $tourPackage->id,
            'full_name' => 'Radha Sharma',
            'phone' => '9876543210',
            'pickup_drop' => 'Delhi Airport to Vrindavan',
        ]);
    }

    public function test_public_visitor_can_submit_a_quick_enquiry(): void
    {
        $response = $this->post('/enquiries', [
            'enquiry_type' => 'quick',
            'full_name' => 'Mohan Das',
            'phone' => '+91 98765 43210',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('enquiries', [
            'enquiry_type' => 'quick',
            'status' => 'new',
            'full_name' => 'Mohan Das',
            'phone' => '+91 98765 43210',
        ]);
    }

    public function test_departure_date_cannot_be_before_arrival_date(): void
    {
        $payload = $this->tourPlanPayload([
            'arrival_date' => now()->addDays(10)->toDateString(),
            'departure_date' => now()->addDays(9)->toDateString(),
        ]);

        $response = $this->withSession($this->mathChallengeSession())
            ->post('/enquiries', $payload);

        $response->assertSessionHasErrors('departure_date');
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_tour_plan_rejects_an_incorrect_math_security_answer(): void
    {
        $response = $this->withSession($this->mathChallengeSession())
            ->post('/enquiries', $this->tourPlanPayload(['security_answer' => 11]));

        $response->assertSessionHasErrors('security_answer');
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_admin_can_view_saved_enquiries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Enquiry::factory()->create(['full_name' => 'Radha Sharma']);

        $response = $this->actingAs($admin)->get('/admin/enquiries');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Enquiries')
            ->has('enquiries.data', 1)
            ->where('enquiries.data.0.full_name', 'Radha Sharma'));
    }

    public function test_guest_cannot_view_admin_enquiries(): void
    {
        $response = $this->get('/admin/enquiries');

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_package_checkout_page_is_not_available(): void
    {
        $tourPackage = $this->createTourPackage();

        $response = $this->get("/packages/{$tourPackage->slug}/checkout");

        $response->assertNotFound();
    }

    private function tourPlanPayload(array $overrides = []): array
    {
        return array_merge([
            'enquiry_type' => 'tour_plan',
            'full_name' => 'Radha Sharma',
            'phone' => '9876543210',
            'email' => 'radha@example.com',
            'pickup_drop' => 'Delhi Airport to Vrindavan',
            'hotel_category' => 'standard',
            'adults' => 2,
            'children' => 1,
            'arrival_date' => now()->addWeek()->toDateString(),
            'departure_date' => now()->addWeek()->addDays(2)->toDateString(),
            'message' => 'Need a senior-friendly itinerary.',
            'security_answer' => 12,
        ], $overrides);
    }

    private function mathChallengeSession(): array
    {
        return [
            'enquiry_math_answer' => 12,
            'enquiry_math_question' => '5 + 7 = ?',
        ];
    }

    private function createTourPackage(): TourPackage
    {
        $state = State::create(['name' => 'Uttar Pradesh', 'slug' => 'uttar-pradesh']);
        $city = City::create([
            'state_id' => $state->id,
            'name' => 'Vrindavan',
            'slug' => 'vrindavan',
        ]);

        return TourPackage::create([
            'title' => 'Vrindavan Darshan',
            'slug' => 'vrindavan-darshan',
            'city_id' => $city->id,
            'duration_days' => 2,
            'duration_nights' => 1,
            'price' => 5000,
            'is_active' => true,
        ]);
    }
}
