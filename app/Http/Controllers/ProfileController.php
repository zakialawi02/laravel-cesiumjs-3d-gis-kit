<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the profile form of the signed-in user.
     */
    public function edit(Request $request): View
    {
        return view('pages.dashboard.profile.edit', ['user' => $request->user()]);
    }

    /**
     * Update name, username and email of the signed-in user.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        return redirect()->route('profile.edit')->with('success', __('Profile updated.'));
    }

    /**
     * Change the password, then sign out the user's other sessions.
     */
    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $newPassword = $request->validated('password');

        $request->user()->update(['password' => $newPassword]);
        Auth::logoutOtherDevices($newPassword);
        $request->session()->regenerate();

        return redirect()->route('profile.edit')->with('success', __('Password changed.'));
    }
}
