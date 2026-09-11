<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
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

                //seo info
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


            ],
        ]);
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
