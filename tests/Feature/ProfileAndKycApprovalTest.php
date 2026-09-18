<?php

namespace Tests\Feature;

use App\Enums\VendorVerificationStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorDocument;
use App\Models\VendorProfile;
use App\Models\VendorVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProfileAndKycApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Storage::fake('vendor_kyc');
    }

    protected function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function vendor(): User
    {
        $user = User::factory()->create(['role' => 'vendor']);
        VendorProfile::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        return $user;
    }

    // 1. authenticated Customer can load /account/profile
    public function test_customer_can_load_profile(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->get('/account/profile')->assertOk();
        $this->actingAs($customer)->get(route('profile.edit'))->assertOk();
    }

    // 2. profile page does not depend on unavailable frontend route() helper
    public function test_profile_page_does_not_use_route_helper(): void
    {
        $updateForm = file_get_contents(resource_path('js/Pages/Profile/Partials/UpdateProfileInformationForm.vue'));
        $this->assertStringContainsString("appUrl('/account/profile')", $updateForm);
        $this->assertStringNotContainsString("route('profile.update')", $updateForm);

        $passwordForm = file_get_contents(resource_path('js/Pages/Profile/Partials/UpdatePasswordForm.vue'));
        $this->assertStringContainsString("appUrl('/password')", $passwordForm);
        $this->assertStringNotContainsString("route('password.update')", $passwordForm);

        $deleteForm = file_get_contents(resource_path('js/Pages/Profile/Partials/DeleteUserForm.vue'));
        $this->assertStringContainsString("appUrl('/account/profile')", $deleteForm);
        $this->assertStringNotContainsString("route('profile.destroy')", $deleteForm);

        $editPage = file_get_contents(resource_path('js/Pages/Profile/Edit.vue'));
        $this->assertStringContainsString('AppLayout', $editPage);
        $this->assertStringNotContainsString('AuthenticatedLayout', $editPage);
        $this->assertStringNotContainsString('route(', $editPage);
    }

    // 3. profile update works under /code
    public function test_profile_update_works_under_code(): void
    {
        // Under /code deployment, frontend uses appUrl('/account/profile') which prepends /code.
        // Verify the Vue file uses appUrl helper so the request will be base-path safe.
        $updateForm = file_get_contents(resource_path('js/Pages/Profile/Partials/UpdateProfileInformationForm.vue'));
        $this->assertStringContainsString("appUrl('/account/profile')", $updateForm);
        $this->assertStringContainsString("appUrl('/password')", file_get_contents(resource_path('js/Pages/Profile/Partials/UpdatePasswordForm.vue')));

        // Normal update still works (base path handled by appUrl in browser, server route is /account/profile)
        $customer = $this->customer();
        $this->actingAs($customer)->patch('/account/profile', ['name' => 'New Name', 'email' => 'new@example.com', 'phone' => '999'])->assertRedirect('/account/profile');
        $customer->refresh();
        $this->assertEquals('New Name', $customer->name);
    }

    // 4. password update route works under /code
    public function test_password_update_works(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertSessionHasNoErrors();
    }

    // 5. invalid profile data returns validation correctly
    public function test_invalid_profile_data_returns_validation(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->patch('/account/profile', ['name' => '', 'email' => 'not-an-email'])->assertSessionHasErrors(['name', 'email']);
    }

    // 6. profile change does not modify historical Booking customer snapshot
    public function test_profile_change_does_not_modify_booking_snapshot(): void
    {
        $customer = $this->customer(['name' => 'Old Name', 'phone' => '111']);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'customer_name' => 'Old Name', 'customer_phone' => '111']);
        $this->actingAs($customer)->patch('/account/profile', ['name' => 'New Name', 'email' => $customer->email, 'phone' => '222']);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'customer_name' => 'Old Name', 'customer_phone' => '111']);
        $customer->refresh();
        $this->assertEquals('New Name', $customer->name);
    }

    // 7. customer cannot update role through profile request
    public function test_customer_cannot_update_role(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->patch('/account/profile', ['name' => 'Hacked', 'email' => $customer->email, 'role' => 'admin']);
        $customer->refresh();
        $this->assertEquals('customer', $customer->role);
        $this->assertEquals('Hacked', $customer->name);
    }

    // 8. impersonated Customer can view profile
    public function test_impersonated_customer_can_view_profile(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $customer));
        $this->get('/account/profile')->assertOk();
    }

    // 9. sensitive operations remain blocked while impersonating
    public function test_impersonation_blocks_password_and_delete(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $customer));
        $this->put('/password', ['current_password' => 'password', 'password' => 'new', 'password_confirmation' => 'new'])->assertForbidden();
        $this->delete('/account/profile', ['password' => 'password'])->assertForbidden();
    }

    // 10-13. Admin can approve with various incomplete KYC states
    public function test_admin_can_approve_with_kyc_not_started(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::NotStarted->value]);
        $this->actingAs($admin)->post(route('admin.vendor-applications.approve', $app))->assertRedirect();
        $this->assertEquals('approved', $app->refresh()->status->value);
    }

    public function test_admin_can_approve_with_pending_documents(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => 'pending']);
        $this->actingAs($admin)->post(route('admin.vendor-applications.approve', $app))->assertRedirect();
        $this->assertEquals('approved', $app->refresh()->status->value);
    }

    public function test_admin_can_approve_with_missing_required_documents(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending', 'entity_type' => 'individual', 'country_code' => 'IN']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => 'pending', 'country_code' => 'IN', 'entity_type' => 'individual']);
        // No documents at all
        $this->actingAs($admin)->post(route('admin.vendor-applications.approve', $app))->assertRedirect();
        $this->assertEquals('approved', $app->refresh()->status->value);
    }

    public function test_admin_can_approve_with_needs_resubmission(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::NeedsResubmission->value]);
        $this->actingAs($admin)->post(route('admin.vendor-applications.approve', $app))->assertRedirect();
        $this->assertEquals('approved', $app->refresh()->status->value);
    }

    // 14-17. approval effects
    public function test_approval_assigns_vendor_role_and_creates_profile_but_not_verified(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => 'pending']);
        $this->actingAs($admin)->post(route('admin.vendor-applications.approve', $app))->assertRedirect();
        $customer->refresh();
        $ver->refresh();
        $this->assertEquals('vendor', $customer->role);
        $this->assertDatabaseHas('vendor_profiles', ['user_id' => $customer->id]);
        $this->assertNotEquals('verified', $ver->status->value);
        $this->assertEquals('pending', $ver->status->value);
    }

    // 18. approved Vendor can access /vendor
    public function test_approved_vendor_can_access_vendor_dashboard(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => 'pending']);
        $this->actingAs($admin)->post(route('admin.vendor-applications.approve', $app));
        $customer->refresh();
        $this->actingAs($customer)->get(route('vendor.dashboard'))->assertOk();
    }

    // 19. approved-but-unverified can continue KYC
    public function test_approved_but_unverified_can_continue_kyc(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => 'pending']);
        $this->actingAs($admin)->post(route('admin.vendor-applications.approve', $app));
        $customer->refresh();
        // Now vendor, try to upload
        $file = UploadedFile::fake()->create('pan.pdf', 100, 'application/pdf');
        $this->actingAs($customer)->post(route('vendor.documents.store'), ['document_type' => 'pan', 'document_file' => $file])->assertRedirect();
        $this->assertDatabaseHas('vendor_documents', ['vendor_verification_id' => $ver->id, 'document_type' => 'pan']);
    }

    // 20. non-Admin cannot invoke approval
    public function test_non_admin_cannot_approve(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => 'pending']);
        $this->actingAs($customer)->post(route('admin.vendor-applications.approve', $app))->assertForbidden();
        $otherCustomer = $this->customer();
        $this->actingAs($otherCustomer)->post(route('admin.vendor-applications.approve', $app))->assertForbidden();
    }

    // 21. Customer cannot self-approve
    public function test_customer_cannot_self_approve(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => 'pending']);
        $this->actingAs($customer)->post(route('admin.vendor-applications.approve', $app))->assertForbidden();
        $this->assertEquals('pending', $app->refresh()->status->value);
    }

    // 22. Vendor cannot self-mark KYC verified
    public function test_vendor_cannot_self_verify_kyc(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => 'pending']);
        $this->actingAs($customer)->post(route('admin.vendor-applications.verify-kyc', $app))->assertForbidden();
    }

    // 23. Admin can later verify KYC independently
    public function test_admin_can_later_verify_kyc(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending', 'entity_type' => 'individual', 'country_code' => 'IN']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => 'pending', 'country_code' => 'IN', 'entity_type' => 'individual']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'pan', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'masked_aadhaar', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'cancelled_cheque', 'status' => 'verified']);
        // Approve first (with incomplete check, but now we have complete)
        $this->actingAs($admin)->post(route('admin.vendor-applications.approve', $app))->assertRedirect();
        // Now verify KYC
        $this->actingAs($admin)->post(route('admin.vendor-applications.verify-kyc', $app))->assertRedirect();
        $this->assertEquals('verified', $ver->refresh()->status->value);
    }

    // 24. existing fully-verified approval still works
    public function test_fully_verified_approval_still_works(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending', 'entity_type' => 'individual', 'country_code' => 'IN']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::Verified->value, 'verified_at' => now(), 'country_code' => 'IN', 'entity_type' => 'individual']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'pan', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'masked_aadhaar', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'cancelled_cheque', 'status' => 'verified']);
        $this->actingAs($admin)->post(route('admin.vendor-applications.approve', $app))->assertRedirect();
        $this->assertEquals('approved', $app->refresh()->status->value);
        $this->assertEquals('vendor', $customer->refresh()->role);
    }

    // 25. rejected does not receive role
    public function test_rejected_does_not_get_vendor_role(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => 'pending']);
        $this->actingAs($admin)->post(route('admin.vendor-applications.reject', $app), ['rejection_reason' => 'No'])->assertRedirect();
        $this->assertEquals('rejected', $app->refresh()->status->value);
        $this->assertEquals('customer', $customer->refresh()->role);
    }
}
