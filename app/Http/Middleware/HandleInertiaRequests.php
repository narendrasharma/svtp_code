<?php

namespace App\Http\Middleware;

use App\Models\PromotionalPopup;
use App\Models\TourCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('flash'),
            ],
            'securityQuestion' => $this->securityQuestion($request),
            'tourCategories' => fn () => TourCategory::active()
                ->whereHas('tourPackages', fn ($query) => $query->active())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'icon']),
            'promotionalPopup' => fn () => Cache::remember('active.promotional_popup', 3600, function (): ?array {
                $popup = PromotionalPopup::query()
                    ->where('is_active', true)
                    ->whereNotNull('image_path')
                    ->latest('id')
                    ->first(['id', 'image_path', 'title', 'cta_url']);

                return $popup ? [
                    'id' => $popup->id,
                    'image_url' => Storage::disk('public')->url($popup->image_path),
                    'title' => $popup->title,
                    'cta_url' => $popup->cta_url,
                ] : null;
            }),
        ];
    }

    private function securityQuestion(Request $request): string
    {
        if (! $request->session()->has('enquiry_math_answer')
            || ! $request->session()->has('enquiry_math_question')) {
            $left = random_int(1, 9);
            $right = random_int(1, 9);

            $request->session()->put([
                'enquiry_math_answer' => $left + $right,
                'enquiry_math_question' => "{$left} + {$right} = ?",
            ]);
        }

        return $request->session()->get('enquiry_math_question');
    }
}
