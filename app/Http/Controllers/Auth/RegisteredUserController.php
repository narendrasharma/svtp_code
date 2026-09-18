<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    use NotifiesAdmins;

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => 'nullable|string|max:20',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            // Role is server-controlled: public registration always yields a
            // customer and can never self-assign admin (or future) roles.
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => UserRole::Customer->value,
        ]);

        event(new Registered($user));

        $this->notifyAdmins(new AdminAlert('customer_registered', [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]));

        Auth::login($user);

        return redirect()->intended(route('account.dashboard', absolute: false));
    }
}
