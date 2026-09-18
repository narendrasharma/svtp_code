<?php

namespace Tests\Feature;

use App\Enums\VendorApplicationStatus;
use App\Enums\VendorVerificationStatus;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorDocument;
use App\Models\VendorProfile;
use App\Models\VendorVerification;
use App\Services\VendorKycRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VendorKycTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Storage::fake('vendor_kyc');
        Storage::fake('public');
        config()->set('vendor_kyc.require_kyc_before_approval', true);
    }

    protected function customer(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'customer'], $overrides));
    }

    protected function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'admin'], $overrides));
    }

    protected function vendor(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'vendor'], $overrides));
        VendorProfile::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    // ---------- Application ----------

    public function test_customer_can_submit_vendor_application(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post('/vendor/apply', [
                'business_name' => 'Test Travels',
                'entity_type' => 'individual',
                'phone' => '9999999999',
                'email' => 'biz@example.com',
                'address' => 'Vrindavan Street',
                'city' => 'Vrindavan',
                'state' => 'UP',
                'country_code' => 'IN',
                'business_description' => 'Test',
                'consent' => true,
            ])
            ->assertRedirect(route('vendor.application.show'));

        $this->assertDatabaseHas('vendor_applications', [
            'user_id' => $customer->id,
            'business_name' => 'Test Travels',
            'status' => VendorApplicationStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('vendor_verifications', [
            'user_id' => $customer->id,
            'status' => VendorVerificationStatus::Pending->value,
            'country_code' => 'IN',
        ]);
    }

    public function test_applicant_cannot_set_role_or_status_via_mass_assignment(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post('/vendor/apply', [
                'business_name' => 'Hacked',
                'entity_type' => 'individual',
                'phone' => '9999999999',
                'email' => 'hacked@example.com',
                'address' => 'Addr',
                'city' => 'City',
                'state' => 'State',
                'country_code' => 'IN',
                'consent' => true,
                'role' => 'admin',
                'status' => 'approved',
            ]);

        $app = VendorApplication::where('user_id', $customer->id)->first();
        $this->assertNotNull($app);
        $this->assertSame(VendorApplicationStatus::Pending->value, $app->status->value);
        $customer->refresh();
        $this->assertSame('customer', $customer->role);
    }

    public function test_duplicate_pending_application_is_blocked(): void
    {
        $customer = $this->customer();
        VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value]);

        $this->actingAs($customer)
            ->post('/vendor/apply', [
                'business_name' => 'Another',
                'entity_type' => 'individual',
                'phone' => '9999999999',
                'email' => 'biz@example.com',
                'address' => 'Addr',
                'city' => 'City',
                'state' => 'State',
                'country_code' => 'IN',
                'consent' => true,
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('vendor_applications', 1);
    }

    public function test_resubmission_requested_can_be_updated(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create([
            'user_id' => $customer->id,
            'status' => VendorApplicationStatus::ResubmissionRequested->value,
            'entity_type' => 'individual',
            'country_code' => 'IN',
        ]);
        VendorVerification::factory()->create([
            'user_id' => $customer->id,
            'vendor_application_id' => $app->id,
            'status' => VendorVerificationStatus::NeedsResubmission->value,
            'country_code' => 'IN',
            'entity_type' => 'individual',
        ]);

        $this->actingAs($customer)
            ->patch('/vendor/application', [
                'business_name' => 'Updated Biz',
                'entity_type' => 'individual',
                'phone' => '8888888888',
                'email' => 'updated@example.com',
                'address' => 'New Addr',
                'city' => 'New City',
                'state' => 'UP',
                'country_code' => 'IN',
                'consent' => true,
            ])
            ->assertRedirect(route('vendor.application.show'));

        $app->refresh();
        $this->assertSame('pending', $app->status->value);
        $this->assertSame('Updated Biz', $app->business_name);
    }

    // ---------- Documents ----------

    public function test_vendor_can_upload_document(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value]);
        $ver = VendorVerification::factory()->create([
            'user_id' => $customer->id,
            'vendor_application_id' => $app->id,
            'status' => VendorVerificationStatus::Pending->value,
            'country_code' => 'IN',
            'entity_type' => 'individual',
        ]);

        $file = UploadedFile::fake()->create('pan.pdf', 100, 'application/pdf');

        $this->actingAs($customer)
            ->post('/vendor/documents', [
                'document_type' => 'pan',
                'document_file' => $file,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vendor_documents', [
            'vendor_verification_id' => $ver->id,
            'document_type' => 'pan',
            'status' => 'pending',
        ]);

        Storage::disk('vendor_kyc')->assertExists(VendorDocument::first()->storage_path);
    }

    public function test_arbitrary_document_type_is_rejected(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'country_code' => 'IN', 'entity_type' => 'individual']);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->actingAs($customer)
            ->post('/vendor/documents', [
                'document_type' => 'arbitrary_fake_type',
                'document_file' => $file,
            ])
            ->assertSessionHasErrors('document_type');

        $this->assertDatabaseCount('vendor_documents', 0);
    }

    public function test_executable_file_is_rejected(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'country_code' => 'IN', 'entity_type' => 'individual']);

        $file = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');

        $this->actingAs($customer)
            ->post('/vendor/documents', [
                'document_type' => 'pan',
                'document_file' => $file,
            ])
            ->assertSessionHasErrors();
    }

    public function test_oversized_file_is_rejected(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'country_code' => 'IN', 'entity_type' => 'individual']);

        // 6MB file (exceeds 5MB limit)
        $file = UploadedFile::fake()->create('large.pdf', 6000, 'application/pdf');

        $this->actingAs($customer)
            ->post('/vendor/documents', [
                'document_type' => 'pan',
                'document_file' => $file,
            ])
            ->assertSessionHasErrors('document_file');
    }

    public function test_invalid_mime_is_rejected(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'country_code' => 'IN', 'entity_type' => 'individual']);

        $file = UploadedFile::fake()->create('doc.txt', 100, 'text/plain');

        $this->actingAs($customer)
            ->post('/vendor/documents', [
                'document_type' => 'pan',
                'document_file' => $file,
            ])
            ->assertSessionHasErrors('document_file');
    }

    public function test_masked_aadhaar_stores_only_last_four_digits(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'country_code' => 'IN', 'entity_type' => 'individual']);

        $file = UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf');

        // Simulate full 12-digit number input (should be masked)
        $this->actingAs($customer)
            ->post('/vendor/documents', [
                'document_type' => 'masked_aadhaar',
                'document_file' => $file,
                'document_number_masked' => '123456789012',
            ])
            ->assertRedirect();

        $doc = VendorDocument::where('document_type', 'masked_aadhaar')->first();
        $this->assertNotNull($doc);
        $this->assertStringContainsString('9012', $doc->document_number_masked);
        $this->assertStringNotContainsString('123456789012', $doc->document_number_masked);
        $this->assertStringContainsString('XXXX', $doc->document_number_masked);

        // Ensure last 4 only with masked input
        $file2 = UploadedFile::fake()->create('aadhaar2.pdf', 100, 'application/pdf');
        $this->actingAs($customer)
            ->post('/vendor/documents', [
                'document_type' => 'masked_aadhaar',
                'document_file' => $file2,
                'document_number_masked' => 'XXXX XXXX 5678',
            ])
            ->assertRedirect();

        $doc2 = VendorDocument::latest('id')->first();
        $this->assertStringContainsString('5678', $doc2->document_number_masked);
        // Must not store full number even if provided via API
        $this->assertStringNotContainsString('1234 5678 9012', $doc2->document_number_masked ?? '');
    }

    // ---------- Document Authorization ----------

    public function test_unauthenticated_cannot_view_document(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id]);
        $doc = VendorDocument::factory()->create(['vendor_verification_id' => $ver->id]);

        $this->get("/vendor/documents/{$doc->id}/download")->assertRedirect(route('login'));
        $this->get("/admin/vendor-documents/{$doc->id}/download")->assertRedirect(route('login'));
    }

    public function test_another_customer_cannot_view_document(): void
    {
        $owner = $this->customer();
        $other = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $owner->id]);
        $ver = VendorVerification::factory()->create(['user_id' => $owner->id, 'vendor_application_id' => $app->id]);
        $doc = VendorDocument::factory()->create(['vendor_verification_id' => $ver->id]);
        Storage::disk('vendor_kyc')->put($doc->storage_path, 'fake content');

        $this->actingAs($other)->get("/vendor/documents/{$doc->id}/download")->assertForbidden();
    }

    public function test_vendor_can_view_own_document(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id]);
        $doc = VendorDocument::factory()->create(['vendor_verification_id' => $ver->id]);
        Storage::disk('vendor_kyc')->put($doc->storage_path, 'fake content');

        $this->actingAs($customer)->get("/vendor/documents/{$doc->id}/download")->assertOk();
    }

    public function test_admin_can_view_document(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id]);
        $doc = VendorDocument::factory()->create(['vendor_verification_id' => $ver->id]);
        Storage::disk('vendor_kyc')->put($doc->storage_path, 'fake content');

        $this->actingAs($admin)->get("/admin/vendor-documents/{$doc->id}/download")->assertOk();
    }

    public function test_public_storage_url_cannot_retrieve_kyc_file(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id]);
        $doc = VendorDocument::factory()->create(['vendor_verification_id' => $ver->id]);
        Storage::disk('vendor_kyc')->put($doc->storage_path, 'secret content');

        // Public disk should not have the file
        $this->assertFalse(Storage::disk('public')->exists($doc->storage_path));
        $this->assertTrue(Storage::disk('vendor_kyc')->exists($doc->storage_path));

        // Direct public URL should not be accessible (no file on public)
        $publicUrl = Storage::disk('public')->url($doc->storage_path);
        $this->assertStringNotContainsString('vendor-kyc', $publicUrl);
        // The raw storage_path should never be exposed to view
        $response = $this->actingAs($customer)->get(route('vendor.application.show'));
        // Ensure page does not expose storage_path
        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('storage_path', $content);
        $this->assertStringNotContainsString($doc->storage_path, $content);
    }

    public function test_applicant_cannot_change_verification_status(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value]);
        VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::Pending->value]);

        // Try to PATCH verification status via any vendor route (should not exist)
        $this->actingAs($customer)->patch('/vendor/application', [
            'business_name' => 'Hacked',
            'entity_type' => 'individual',
            'phone' => '9999999999',
            'email' => 'h@h.com',
            'address' => 'Addr',
            'city' => 'City',
            'state' => 'State',
            'country_code' => 'IN',
            'consent' => true,
            'status' => 'approved',
            'verification_status' => 'verified',
        ]);

        $app->refresh();
        $ver = VendorVerification::where('vendor_application_id', $app->id)->first();
        $this->assertNotSame('verified', $ver->status->value);
        $this->assertNotSame('approved', $app->status->value);
    }

    // ---------- KYC Requirement Resolution ----------

    public function test_india_individual_requirements(): void
    {
        $service = app(VendorKycRequirementService::class);
        $req = $service->requirements('IN', 'individual');
        $this->assertContains('pan', $req);
        // Should contain one-of group for address proof
        $hasOneOf = false;
        foreach ($req as $r) {
            if (is_array($r) && in_array('masked_aadhaar', $r, true)) {
                $hasOneOf = true;
            }
        }
        $this->assertTrue($hasOneOf, 'Individual requirements should include masked_aadhaar one-of group');
    }

    public function test_private_limited_requires_additional_docs(): void
    {
        $service = app(VendorKycRequirementService::class);
        $individualReq = $service->requirements('IN', 'individual');
        $pvtReq = $service->requirements('IN', 'private_limited');

        $this->assertGreaterThan(count($individualReq), count($pvtReq));
        $flatPvt = collect($pvtReq)->flatten()->all();
        $this->assertContains('certificate_of_incorporation', $flatPvt);
        $this->assertContains('gst_certificate', $flatPvt);
    }

    public function test_default_country_fallback(): void
    {
        $service = app(VendorKycRequirementService::class);
        $req = $service->requirements('US', 'individual');
        $this->assertNotEmpty($req);
        // Should not require pan for default maybe, but contains passport one-of
        $flat = collect($req)->flatten()->all();
        $this->assertContains('passport', $flat);
    }

    public function test_kyc_summary_is_complete_only_when_required_docs_verified(): void
    {
        $service = app(VendorKycRequirementService::class);
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'entity_type' => 'individual', 'country_code' => 'IN']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::Pending->value, 'country_code' => 'IN', 'entity_type' => 'individual']);

        // No docs => not complete
        $this->assertFalse($service->isVerificationComplete($ver));

        // Add verified pan
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'pan', 'status' => 'verified']);
        $ver->refresh();
        $this->assertFalse($service->isVerificationComplete($ver));

        // Add address proof
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'masked_aadhaar', 'status' => 'verified']);
        $ver->refresh();
        $this->assertFalse($service->isVerificationComplete($ver));

        // Add bank proof
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'cancelled_cheque', 'status' => 'verified']);
        $ver->refresh();
        $this->assertTrue($service->isVerificationComplete($ver));

        // Pending doc should not count
        $ver2 = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::Pending->value, 'country_code' => 'IN', 'entity_type' => 'individual']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver2->id, 'document_type' => 'pan', 'status' => 'pending']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver2->id, 'document_type' => 'masked_aadhaar', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver2->id, 'document_type' => 'cancelled_cheque', 'status' => 'verified']);
        $this->assertFalse($service->isVerificationComplete($ver2));
    }

    // ---------- Approval ----------

    public function test_admin_can_approve_with_incomplete_kyc(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value, 'entity_type' => 'individual', 'country_code' => 'IN']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::Pending->value, 'country_code' => 'IN', 'entity_type' => 'individual']);
        // No documents — previously blocked, now allowed with override
        $this->actingAs($admin)
            ->post("/admin/vendor-applications/{$app->id}/approve")
            ->assertRedirect();

        $app->refresh();
        $customer->refresh();
        $ver->refresh();
        $this->assertSame('approved', $app->status->value);
        $this->assertSame('vendor', $customer->role);
        $this->assertSame('pending', $ver->status->value);
        $this->assertDatabaseHas('vendor_profiles', ['user_id' => $customer->id]);
    }

    public function test_approval_with_needs_resubmission_kyc_still_succeeds(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value]);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::NeedsResubmission->value]);

        $this->actingAs($admin)->post("/admin/vendor-applications/{$app->id}/approve")->assertRedirect();
        $this->assertSame('approved', $app->refresh()->status->value);
        $this->assertSame('vendor', $customer->refresh()->role);
    }

    public function test_approval_succeeds_when_kyc_complete(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value, 'entity_type' => 'individual', 'country_code' => 'IN']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::Pending->value, 'country_code' => 'IN', 'entity_type' => 'individual']);

        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'pan', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'masked_aadhaar', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'cancelled_cheque', 'status' => 'verified']);
        $ver->update(['status' => VendorVerificationStatus::Verified->value, 'verified_at' => now()]);

        $this->actingAs($admin)
            ->post("/admin/vendor-applications/{$app->id}/approve")
            ->assertRedirect();

        $app->refresh();
        $customer->refresh();
        $this->assertSame('approved', $app->status->value);
        $this->assertSame('vendor', $customer->role);
        $this->assertDatabaseHas('vendor_profiles', ['user_id' => $customer->id, 'business_name' => $app->business_name]);
    }

    public function test_approval_without_kyc_allowed_when_config_false(): void
    {
        config()->set('vendor_kyc.require_kyc_before_approval', false);
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value]);
        VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::Pending->value]);

        $this->actingAs($admin)
            ->post("/admin/vendor-applications/{$app->id}/approve")
            ->assertRedirect();

        $app->refresh();
        $this->assertSame('approved', $app->status->value);
    }

    public function test_rejected_kyc_does_not_grant_vendor_role(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value]);

        $this->actingAs($admin)
            ->post("/admin/vendor-applications/{$app->id}/reject", ['rejection_reason' => 'Invalid docs'])
            ->assertRedirect();

        $app->refresh();
        $customer->refresh();
        $this->assertSame('rejected', $app->status->value);
        $this->assertSame('customer', $customer->role);
        $this->assertDatabaseMissing('vendor_profiles', ['user_id' => $customer->id]);
    }

    public function test_verified_and_approved_grants_vendor_role_once(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value, 'entity_type' => 'individual', 'country_code' => 'IN']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::Verified->value, 'verified_at' => now(), 'country_code' => 'IN', 'entity_type' => 'individual']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'pan', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'masked_aadhaar', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'cancelled_cheque', 'status' => 'verified']);

        $this->actingAs($admin)->post("/admin/vendor-applications/{$app->id}/approve")->assertRedirect();
        $this->actingAs($admin)->post("/admin/vendor-applications/{$app->id}/approve")->assertSessionHasErrors();

        $this->assertDatabaseCount('vendor_profiles', 1);
        $this->assertSame('vendor', $customer->refresh()->role);
    }

    public function test_duplicate_race_approval_is_safe(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value, 'entity_type' => 'individual', 'country_code' => 'IN']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::Verified->value, 'verified_at' => now(), 'country_code' => 'IN', 'entity_type' => 'individual']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'pan', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'masked_aadhaar', 'status' => 'verified']);
        VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'cancelled_cheque', 'status' => 'verified']);

        // First approval succeeds
        $this->actingAs($admin)->post("/admin/vendor-applications/{$app->id}/approve")->assertRedirect();
        // Second attempt should fail gracefully, not create duplicate profile
        $this->actingAs($admin)->post("/admin/vendor-applications/{$app->id}/approve")->assertSessionHasErrors();
        $this->assertDatabaseCount('vendor_profiles', 1);
    }

    // ---------- Vendor Dashboard/Profile ----------

    public function test_vendor_dashboard_requires_vendor_role(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->get('/vendor')->assertForbidden();

        $vendor = $this->vendor();
        $this->actingAs($vendor)->get('/vendor')->assertOk();
    }

    public function test_vendor_profile_safe_fields_update(): void
    {
        $vendor = $this->vendor();
        $profile = $vendor->vendorProfile()->first();

        $this->actingAs($vendor)
            ->patch('/vendor/profile', [
                'business_name' => 'Updated Name',
                'phone' => '8888888888',
                'email' => 'new@example.com',
                'address' => 'New Addr',
                'city' => 'New City',
                'state' => 'UP',
                'country_code' => 'IN',
                'is_active' => false,
                'approved_at' => '2020-01-01',
                'role' => 'admin',
            ])
            ->assertRedirect(route('vendor.profile.show'));

        $profile->refresh();
        $this->assertSame('Updated Name', $profile->business_name);
        $this->assertTrue($profile->is_active);
        $vendor->refresh();
        $this->assertSame('vendor', $vendor->role);
    }

    public function test_vendor_cannot_edit_protected_fields_via_mass_assignment(): void
    {
        $vendor = $this->vendor();
        $profile = $vendor->vendorProfile()->first();
        $originalApproved = $profile->approved_at;

        $this->actingAs($vendor)
            ->patch('/vendor/profile', [
                'business_name' => 'Hacked',
                'phone' => '9999999999',
                'email' => 'hack@example.com',
                'country_code' => 'IN',
                'is_active' => false,
                'verification_status' => 'verified',
            ]);

        $profile->refresh();
        $this->assertSame('Hacked', $profile->business_name);
        $this->assertTrue($profile->is_active);
        $this->assertEquals($originalApproved, $profile->approved_at);
    }

    public function test_login_redirects_by_role(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $vendor = $this->vendor();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard', absolute: false));
        $this->post('/admin/logout');

        $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect(route('account.dashboard', absolute: false));
        $this->post('/admin/logout');

        $this->post('/admin/login', ['email' => $vendor->email, 'password' => 'password'])->assertRedirect(route('vendor.dashboard', absolute: false));
    }

    public function test_customer_admin_regression_still_works(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->get(route('admin.bookings.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('account.dashboard'))->assertOk();
    }

    // ---------- Admin Review ----------

    public function test_admin_can_verify_document_and_reject_with_reason(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id]);
        $doc = VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'status' => 'pending']);

        $this->actingAs($admin)->post("/admin/vendor-documents/{$doc->id}/verify")->assertRedirect();
        $this->assertSame('verified', $doc->refresh()->status->value);

        // Reject with reason
        $doc2 = VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'status' => 'pending']);
        $this->actingAs($admin)->post("/admin/vendor-documents/{$doc2->id}/reject", ['rejection_reason' => 'Blurry'])->assertRedirect();
        $this->assertSame('rejected', $doc2->refresh()->status->value);
        $this->assertSame('Blurry', $doc2->rejection_reason);
    }

    public function test_vendor_can_replace_rejected_document(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'status' => VendorApplicationStatus::Pending->value]);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::NeedsResubmission->value]);
        $doc = VendorDocument::factory()->create(['vendor_verification_id' => $ver->id, 'document_type' => 'pan', 'status' => 'rejected', 'rejection_reason' => 'Blurry']);
        Storage::disk('vendor_kyc')->put($doc->storage_path, 'old');

        $file = UploadedFile::fake()->create('pan2.pdf', 100, 'application/pdf');
        $this->actingAs($customer)
            ->post('/vendor/documents', [
                'document_type' => 'pan',
                'document_file' => $file,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('vendor_documents', 2);
        $new = VendorDocument::latest('id')->first();
        $this->assertSame('pending', $new->status->value);
        $this->assertSame('pan', $new->document_type->value);
    }

    public function test_storage_uses_random_non_public_names(): void
    {
        $customer = $this->customer();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id]);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id]);
        $file = UploadedFile::fake()->create('mydoc.pdf', 100, 'application/pdf');
        $this->actingAs($customer)->post('/vendor/documents', ['document_type' => 'pan', 'document_file' => $file]);

        $doc = VendorDocument::first();
        $this->assertStringNotContainsString('mydoc', $doc->storage_path);
        $this->assertMatchesRegularExpression('/kyc\/\d+\/[a-zA-Z0-9]{32}\.pdf/', $doc->storage_path);
        $this->assertStringNotContainsString($doc->original_filename, $doc->storage_path);
    }

    public function test_full_aadhaar_not_exposed_in_ui(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();
        $app = VendorApplication::factory()->create(['user_id' => $customer->id, 'entity_type' => 'individual', 'country_code' => 'IN']);
        $ver = VendorVerification::factory()->create(['user_id' => $customer->id, 'vendor_application_id' => $app->id, 'status' => VendorVerificationStatus::Pending->value, 'country_code' => 'IN', 'entity_type' => 'individual']);
        $file = UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf');
        $this->actingAs($customer)->post('/vendor/documents', [
            'document_type' => 'masked_aadhaar',
            'document_file' => $file,
            'document_number_masked' => '123456789012',
        ]);

        $doc = VendorDocument::first();
        // Ensure original full number never stored
        $this->assertStringNotContainsString('123456789012', $doc->document_number_masked);
        $this->assertStringContainsString('XXXX', $doc->document_number_masked);

        // Ensure UI does not expose raw number
        $this->actingAs($customer)->get(route('vendor.application.show'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('documents', 1)
            ->where('documents.0.document_number_masked', $doc->document_number_masked)
        );

        $this->actingAs($admin)->get("/admin/vendor-applications/{$app->id}")->assertOk();
        // Storage path should not be visible
        $this->assertStringNotContainsString($doc->storage_path, json_encode($doc->toArray()));
    }
}
