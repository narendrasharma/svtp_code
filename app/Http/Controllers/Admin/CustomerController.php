<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lightweight customer creation for desk / lead flows. Never creates
 * staff or vendor accounts — role is forced to customer.
 */
class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customers) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:20'],
        ]);

        $customer = $this->customers->createLightweight($validated, $request->user());

        if ($request->wantsJson()) {
            return redirect()->back()->with('flash', "Customer {$customer->name} created.");
        }

        return back()->with('flash', "Customer {$customer->name} created.");
    }

    /**
     * Candidate lookup for the convert/link step. Scoped to customers,
     * minimal identity fields only.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(Request $request): array
    {
        return $this->customers
            ->searchCandidates((string) $request->query('q', ''))
            ->map(fn (User $user): array => [
                'value' => $user->id,
                'label' => $user->name,
                'meta' => trim('ID #'.$user->id.' · '.($user->phone ?? '').' · '.($user->email ?? '')),
            ])
            ->all();
    }
}
