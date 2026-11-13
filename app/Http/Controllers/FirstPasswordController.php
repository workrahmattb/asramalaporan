<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class FirstPasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.first-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $request->user()->forceFill(['password' => $data['password'], 'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
