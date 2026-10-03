<?php

namespace Modules\Identity\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Actions\AcceptInvitation;
use Modules\Shared\Exceptions\DomainException;

final class AcceptInvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(string $token, AcceptInvitation $accept): Response
    {
        try {
            $invitation = $accept->find($token);
        } catch (DomainException $e) {
            return Inertia::render('identity/invitations/accept', [
                'token' => $token,
                'invalid' => true,
                'email' => null,
                'name' => null,
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
            ]);
        }

        return Inertia::render('identity/invitations/accept', [
            'token' => $token,
            'invalid' => false,
            'email' => $invitation->email,
            'name' => $invitation->user->name,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request, string $token, AcceptInvitation $accept): RedirectResponse
    {
        $validated = $request->validate([
            'password' => $this->passwordRules(),
        ]);

        $user = $accept->handle($token, $validated['password']);

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome aboard!')]);

        return redirect()->route('dashboard');
    }
}
