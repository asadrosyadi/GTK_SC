<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Support\Arr;


class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('pages.account.profile', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $validatedData = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        'current_password' => 'nullable|required_with:password|string',
        'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user->fill(Arr::except($validatedData, ['password', 'current_password', 'password_confirmation']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
        if ($user->image !== 'default.jpg' && file_exists(public_path('img/profile/' . $user->image))) {
            unlink(public_path('img/profile/' . $user->image));
        }

        $imageName = time() . '.' . $request->image->extension();
        $request->image->move(public_path('img/profile/'), $imageName);
        $user->image = $imageName;
        }

        if ($request->filled('password')) {
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => __('label.current_password')
            ]);
        }

        $user->password = Hash::make($request->password);
        }

        $user->save();

        return Redirect::route('account.profile.edit')->with('notification', $this->successNotification('notification.success_update', 'menu.profile'));    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
