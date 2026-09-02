<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
