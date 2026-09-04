<?php

namespace Tests\Feature\Admin;

use App\Models\PromotionalPopup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PromotionalPopupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
        Storage::fake('public');
    }

    public function test_admin_can_create_and_replace_the_promotional_popup_image(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.promotional-popup.store', absolute: false), [
            'image_upload' => UploadedFile::fake()->image('janmashtami.jpg', 1200, 900),
            'title' => 'Janmashtami Special',
            'cta_url' => 'https://example.test/packages',
            'is_active' => true,
        ])->assertRedirect();

        $popup = PromotionalPopup::firstOrFail();
        $oldImagePath = $popup->image_path;
        $this->assertTrue($popup->is_active);
        $this->assertSame('Janmashtami Special', $popup->title);
        Storage::disk('public')->assertExists($oldImagePath);

        $this->actingAs($admin)->post(route('admin.promotional-popup.store', absolute: false), [
            'image_upload' => UploadedFile::fake()->image('holi.webp', 1200, 900),
            'title' => 'Braj Holi Special',
            'cta_url' => '',
            'is_active' => true,
        ])->assertRedirect();

        $popup->refresh();
        $this->assertSame('Braj Holi Special', $popup->title);
        $this->assertNotSame($oldImagePath, $popup->image_path);
        Storage::disk('public')->assertMissing($oldImagePath);
        Storage::disk('public')->assertExists($popup->image_path);

        $this->get(route('home', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('promotionalPopup.id', $popup->id)
                ->where('promotionalPopup.title', 'Braj Holi Special')
                ->where('promotionalPopup.image_url', '/storage/'.$popup->image_path));
    }

    public function test_deleting_the_image_disables_the_popup_and_hides_it_from_the_frontend(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $popup = PromotionalPopup::create([
            'image_path' => 'promotional-popups/offer.jpg',
            'title' => 'Special Offer',
            'is_active' => true,
        ]);
        Storage::disk('public')->put($popup->image_path, 'image');

        $this->actingAs($admin)->post(route('admin.promotional-popup.store', absolute: false), [
            'remove_image' => true,
            'is_active' => true,
        ])->assertRedirect();

        $popup->refresh();
        $this->assertNull($popup->image_path);
        $this->assertFalse($popup->is_active);
        Storage::disk('public')->assertMissing('promotional-popups/offer.jpg');

        $this->get(route('home', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('promotionalPopup', null));
    }

    public function test_non_admin_cannot_manage_the_promotional_popup(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->get(route('admin.promotional-popup.index', absolute: false))
            ->assertForbidden();
    }
}
