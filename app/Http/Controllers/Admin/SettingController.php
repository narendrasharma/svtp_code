<?php

namespace App\Http\Controllers\Admin;

use App\AI\Support\AIProviderRegistry;
use App\AI\Support\AISettings;
use App\AI\Support\EmbeddingProviderRegistry;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MarketplaceCommissionService;
use App\Services\VendorLedgerService;
use App\Support\OperationsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(AISettings $aiSettings, AIProviderRegistry $aiProviders, EmbeddingProviderRegistry $embeddingProviders): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [

                // Branding
                'site_logo' => Setting::getValue('site_logo'),
                'site_favicon' => Setting::getValue('site_favicon'),

                // Basic
                'site_name' => Setting::getValue(
                    'site_name',
                    'Your Website Tour Packages'
                ),

                'site_tagline' => Setting::getValue(
                    'site_tagline',
                    'Explore the tours with us'
                ),

                'copyright_text' => Setting::getValue(
                    'copyright_text',
                    'All rights reserved.'
                ),

                // Contact
                'primary_phone' => Setting::getValue('primary_phone'),
                'secondary_phone' => Setting::getValue('secondary_phone'),
                'contact_email' => Setting::getValue('contact_email'),
                'website_url' => Setting::getValue('website_url'),
                'office_address' => Setting::getValue('office_address'),
                'google_maps_url' => Setting::getValue('google_maps_url'),

                // Social
                'facebook_url' => Setting::getValue('facebook_url'),
                'instagram_url' => Setting::getValue('instagram_url'),
                'youtube_url' => Setting::getValue('youtube_url'),
                'whatsapp_number' => Setting::getValue('whatsapp_number'),
                'whatsapp_message' => Setting::getValue(
                    'whatsapp_message',
                    'Hello! I would like to know more about your tour packages.'
                ),

                // seo info
                'seo_meta_title' => Setting::getValue(
                    'seo_meta_title'
                ),

                'seo_meta_description' => Setting::getValue(
                    'seo_meta_description'
                ),

                'seo_meta_keywords' => Setting::getValue(
                    'seo_meta_keywords'
                ),

                'seo_og_image' => Setting::getValue(
                    'seo_og_image'
                ),

                'seo_index' => Setting::getValue(
                    'seo_index',
                    '1'
                ),

                'seo_follow' => Setting::getValue(
                    'seo_follow',
                    '1'
                ),

                'google_site_verification' => Setting::getValue(
                    'google_site_verification'
                ),

                // Marketplace / vendor commission (Phase 6: one global
                // default; snapshots at booking time, future bookings only).
                'platform_commission_percentage' => Setting::getValue(
                    'platform_commission_percentage',
                    MarketplaceCommissionService::FALLBACK_PERCENTAGE
                ),

                // Marketplace / withdrawals (Phase 7: minimum payout request).
                'minimum_withdrawal_amount' => Setting::getValue(
                    'minimum_withdrawal_amount',
                    VendorLedgerService::DEFAULT_MIN_WITHDRAWAL
                ),

                // Operations / scheduler-driven platform work (11.5D).
                'operations' => OperationsSettings::all(),

                'ai' => [
                    'enabled' => $aiSettings->enabled(),
                    'provider' => $aiSettings->providerKey(),
                    'model' => $aiSettings->model($aiSettings->providerKey()),
                    'credential_configured' => $aiSettings->hasCredential($aiSettings->providerKey()),
                    'credentials' => collect($aiProviders->keys())
                        ->mapWithKeys(fn (string $provider): array => [$provider => $aiSettings->hasCredential($provider)])
                        ->all(),
                    'azure_endpoint' => $aiSettings->azureEndpoint(),
                    'azure_tool_calling' => $aiSettings->azureToolCallingEnabled(),
                    'providers' => $aiProviders->keys(),
                    'default_models' => collect($aiProviders->keys())
                        ->mapWithKeys(fn (string $provider): array => [$provider => config("services.ai.providers.{$provider}.model")])
                        ->all(),
                    'knowledge_enabled' => $aiSettings->knowledgeEnabled(),
                    'agent_actions_enabled' => $aiSettings->agentActionsEnabled(),
                    'embedding_provider' => $aiSettings->embeddingProviderKey(),
                    'embedding_model' => $aiSettings->embeddingModel(),
                    'embedding_providers' => $embeddingProviders->keys(),
                    'embedding_models' => collect($embeddingProviders->keys())
                        ->mapWithKeys(fn (string $provider): array => [$provider => config("services.ai.embedding.models.{$provider}", [])])
                        ->all(),
                    'embedding_credential_configured' => $aiSettings->hasCredential($aiSettings->embeddingProviderKey()),
                    'embedding_credentials' => collect($embeddingProviders->keys())
                        ->mapWithKeys(fn (string $provider): array => [$provider => $aiSettings->hasCredential($provider)])
                        ->all(),
                ],

            ],
        ]);
    }

    public function updateAi(Request $request, AISettings $aiSettings, AIProviderRegistry $aiProviders, EmbeddingProviderRegistry $embeddingProviders): RedirectResponse
    {
        $embeddingProvider = $request->input('embedding_provider');
        $embeddingModels = is_string($embeddingProvider) ? config("services.ai.embedding.models.{$embeddingProvider}", []) : [];

        $validated = $request->validate([
            'ai_enabled' => ['required', 'boolean'],
            'ai_provider' => ['required', 'string', Rule::in($aiProviders->keys())],
            'ai_model' => ['required_if:ai_provider,azure', 'nullable', 'string', 'max:100'],
            'azure_endpoint' => ['required_if:ai_provider,azure', 'nullable', 'url:https', 'max:500'],
            'azure_tool_calling' => ['sometimes', 'boolean'],
            'api_key' => [Rule::requiredIf(fn (): bool => $request->input('ai_provider') === 'azure' && ! $aiSettings->hasCredential('azure')), 'nullable', 'string', 'max:2048'],
            'knowledge_enabled' => ['required', 'boolean'],
            'agent_actions_enabled' => ['sometimes', 'boolean'],
            'embedding_provider' => ['required', 'string', Rule::in($embeddingProviders->keys())],
            'embedding_model' => ['required', 'string', Rule::in($embeddingModels)],
            'embedding_api_key' => ['nullable', 'string', 'max:2048'],
        ]);

        Setting::setValue('ai.enabled', $validated['ai_enabled'] ? '1' : '0');
        Setting::setValue('ai.provider', $validated['ai_provider']);
        Setting::setValue('ai.model', trim($validated['ai_model'] ?? '') ?: null);

        if ($validated['ai_provider'] === 'azure') {
            Setting::setValue('ai.azure.endpoint', rtrim(trim($validated['azure_endpoint']), '/'));
            Setting::setValue('ai.azure.tool_calling', ($validated['azure_tool_calling'] ?? false) ? '1' : '0');
        }
        Setting::setValue('ai.knowledge.enabled', $validated['knowledge_enabled'] ? '1' : '0');
        Setting::setValue('ai.agent_actions.enabled', ($validated['agent_actions_enabled'] ?? false) ? '1' : '0');
        Setting::setValue('ai.embedding.provider', $validated['embedding_provider']);
        Setting::setValue('ai.embedding.model', $validated['embedding_model']);

        if (filled($validated['api_key'] ?? null)) {
            $aiSettings->saveCredential($validated['ai_provider'], trim($validated['api_key']));
        }

        if (filled($validated['embedding_api_key'] ?? null)) {
            $aiSettings->saveCredential($validated['embedding_provider'], trim($validated['embedding_api_key']));
        }

        return back()->with('flash', 'AI settings updated successfully.');
    }

    public function updateSeo(Request $request)
    {
        $validated = $request->validate([
            'seo_meta_title' => [
                'nullable',
                'string',
                'max:70',
            ],

            'seo_meta_description' => [
                'nullable',
                'string',
                'max:170',
            ],

            'seo_meta_keywords' => [
                'nullable',
                'string',
                'max:500',
            ],

            'seo_index' => [
                'required',
                'boolean',
            ],

            'seo_follow' => [
                'required',
                'boolean',
            ],

            'google_site_verification' => [
                'nullable',
                'string',
                'max:255',
            ],
            'remove_seo_og_image' => [
                'nullable',
                'boolean',
            ],
        ]);

        foreach (collect($validated)->except('remove_seo_og_image')->all() as $key => $value) {
            Setting::setValue($key, $value);
        }

        /*
         * Delete OG image if image is marked removed
         */
        if ($request->boolean('remove_seo_og_image')) {

            $oldImage = Setting::getValue('seo_og_image');

            if ($oldImage) {
                Storage::disk('public')->delete($oldImage);
            }

            Setting::setValue('seo_og_image', null);
        }

        /*
         * Handle OG image separately.
         */
        if ($request->hasFile('seo_og_image')) {

            $request->validate([
                'seo_og_image' => [
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:4096',
                ],
            ]);

            $oldImage = Setting::getValue('seo_og_image');

            $path = $request
                ->file('seo_og_image')
                ->store('settings/seo', 'public');

            Setting::setValue(
                'seo_og_image',
                $path
            );

            if ($oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
        }

        return back()->with(
            'flash',
            'SEO settings updated successfully.'
        );
    }

    public function updateBasic(Request $request)
    {
        $validated = $request->validate([
            'site_name' => [
                'required',
                'string',
                'max:150',
            ],

            'site_tagline' => [
                'nullable',
                'string',
                'max:255',
            ],

            'copyright_text' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value);
        }

        return back()->with(
            'flash',
            'Basic settings updated successfully.'
        );
    }

    public function updateContact(Request $request)
    {
        $validated = $request->validate([
            'primary_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'secondary_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'contact_email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'website_url' => [
                'nullable',
                'string',
                'max:255',
            ],

            'office_address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'google_maps_url' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value);
        }

        return back()->with(
            'flash',
            'Contact settings updated successfully.'
        );
    }

    public function updateSocial(Request $request)
    {
        $validated = $request->validate([
            'facebook_url' => [
                'nullable',
                'string',
                'max:500',
            ],

            'instagram_url' => [
                'nullable',
                'string',
                'max:500',
            ],

            'youtube_url' => [
                'nullable',
                'string',
                'max:500',
            ],

            'whatsapp_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'whatsapp_message' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        foreach ($validated as $key => $value) {
            Setting::setValue($key, $value);
        }

        return back()->with(
            'flash',
            'Social settings updated successfully.'
        );
    }

    public function updateMarketplace(Request $request)
    {
        $validated = $request->validate([
            'platform_commission_percentage' => [
                'required',
                'numeric',
                'between:0,100',
                'decimal:0,2',
            ],
            'minimum_withdrawal_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999.99',
                'decimal:0,2',
            ],
        ]);

        // Store normalized so snapshots always read a clean 2-decimal rate.
        Setting::setValue(
            'platform_commission_percentage',
            MarketplaceCommissionService::normalizePercentage($validated['platform_commission_percentage'])
        );

        Setting::setValue(
            'minimum_withdrawal_amount',
            VendorLedgerService::toDecimal($validated['minimum_withdrawal_amount'])
        );

        return back()->with(
            'flash',
            'Marketplace settings updated successfully. Changes apply only to future bookings.'
        );
    }

    /**
     * Operational settings for scheduler-driven platform work (11.5D).
     * Small, deliberate list — reminders, digests, retention.
     */
    public function updateOperations(Request $request)
    {
        $validated = $request->validate([
            'ops_reminders_enabled' => ['required', 'boolean'],
            'ops_followup_reminders_enabled' => ['required', 'boolean'],
            'ops_quotation_expiry_enabled' => ['required', 'boolean'],
            'ops_quotation_expiry_reminder_days' => ['required', 'integer', 'min:1', 'max:30'],
            'ops_payment_reminder_offsets' => ['nullable', 'string', 'max:50'],
            'ops_travel_reminder_customer_offsets' => ['nullable', 'string', 'max:50'],
            'ops_travel_reminder_vendor_offsets' => ['nullable', 'string', 'max:50'],
            'ops_campaigns_scheduled_enabled' => ['required', 'boolean'],
            'ops_admin_digest_frequency' => ['required', 'in:off,daily,weekly'],
            'ops_notification_retention_days' => ['required', 'integer', 'min:30', 'max:730'],
            'ops_notify_lead_created' => ['required', 'boolean'],
        ]);

        $this->storeOffsetList('ops.payment_reminder_offsets', $validated['ops_payment_reminder_offsets'] ?? null);
        $this->storeOffsetList('ops.travel_reminder_customer_offsets', $validated['ops_travel_reminder_customer_offsets'] ?? null);
        $this->storeOffsetList('ops.travel_reminder_vendor_offsets', $validated['ops_travel_reminder_vendor_offsets'] ?? null);

        Setting::setValue('ops.reminders_enabled', $validated['ops_reminders_enabled'] ? '1' : '0');
        Setting::setValue('ops.followup_reminders_enabled', $validated['ops_followup_reminders_enabled'] ? '1' : '0');
        Setting::setValue('ops.quotation_expiry_enabled', $validated['ops_quotation_expiry_enabled'] ? '1' : '0');
        Setting::setValue('ops.quotation_expiry_reminder_days', (string) $validated['ops_quotation_expiry_reminder_days']);
        Setting::setValue('ops.campaigns_scheduled_enabled', $validated['ops_campaigns_scheduled_enabled'] ? '1' : '0');
        Setting::setValue('ops.admin_digest_frequency', $validated['ops_admin_digest_frequency']);
        Setting::setValue('ops.notification_retention_days', (string) $validated['ops_notification_retention_days']);
        Setting::setValue('ops.notify_lead_created', $validated['ops_notify_lead_created'] ? '1' : '0');

        return back()->with('flash', 'Operations settings updated successfully.');
    }

    /**
     * Normalize a comma-separated day-offset list ("3,1,0") or clear it.
     */
    protected function storeOffsetList(string $key, ?string $raw): void
    {
        if ($raw === null || trim($raw) === '') {
            Setting::setValue($key, '');

            return;
        }

        $offsets = [];

        foreach (explode(',', $raw) as $part) {
            $part = trim($part);

            if ($part !== '' && is_numeric($part)) {
                $offsets[] = max(0, (int) $part);
            }
        }

        Setting::setValue($key, implode(',', array_values(array_unique($offsets))));
    }

    public function updateLogo(Request $request)
    {
        $request->validate([
            'logo' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,svg',
                'max:4096',
            ],

            'favicon' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,svg,ico',
                'max:2048',
            ],
        ]);

        /*
         * Logo
         */
        if ($request->hasFile('logo')) {

            $oldLogo = Setting::getValue('site_logo');

            $path = $request
                ->file('logo')
                ->store('settings', 'public');

            Setting::setValue('site_logo', $path);

            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
        }

        /*
         * Favicon
         */
        if ($request->hasFile('favicon')) {

            $oldFavicon = Setting::getValue('site_favicon');

            $path = $request
                ->file('favicon')
                ->store('settings', 'public');

            Setting::setValue('site_favicon', $path);

            if ($oldFavicon) {
                Storage::disk('public')->delete($oldFavicon);
            }
        }

        return back()->with(
            'flash',
            'Logo settings updated successfully.'
        );
    }
}
