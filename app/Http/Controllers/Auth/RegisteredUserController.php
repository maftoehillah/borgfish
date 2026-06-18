<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'role' => ['required', 'in:penjual,pembeli'],
        ]);

        return redirect()->route('auth.google.redirect', [
            'flow' => 'register',
            'role' => $request->role,
        ]);
    }
}
