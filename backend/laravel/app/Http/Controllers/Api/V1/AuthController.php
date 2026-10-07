<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $valid = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:100',
        ]);

        $user = User::where('email', $valid['email'])->first();
        if (! $user || ! Hash::check($valid['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }
        abort_if($user->email_verified_at === null, 403, 'Email belum diverifikasi');

        $days = (int) config('monitoring.api_token_days', 30);
        $token = $user->createToken($valid['device_name'], ['*'], $days > 0 ? now()->addDays($days) : null);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => optional($token->accessToken->expires_at)->toIso8601String(),
            'user' => $this->userData($user),
        ], 201);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $this->userData($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $valid = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'locale' => ['sometimes', 'required', Rule::in(array_keys(config('app.available_locales', [])))],
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user->fill(collect($valid)->except('image')->all());
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            if ($user->image && $user->image !== 'default.jpg' && file_exists(public_path('img/profile/' . $user->image))) {
                unlink(public_path('img/profile/' . $user->image));
            }
            $name = time() . '.' . $request->file('image')->extension();
            $request->file('image')->move(public_path('img/profile/'), $name);
            $user->image = $name;
        }
        $user->save();

        return response()->json(['data' => $this->userData($user->fresh())]);
    }

    public function updatePassword(Request $request)
    {
        $valid = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', PasswordRule::defaults(), 'confirmed'],
        ]);
        $request->user()->update(['password' => Hash::make($valid['password'])]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'Bila email terdaftar, tautan reset password sudah dikirim.']);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'iot_id' => $user->iot_id,
            'locale' => $user->locale ?? config('app.locale'),
            'image_url' => asset('img/profile/' . ($user->image ?: 'default.jpg')),
            'roles' => $user->getRoleNames()->values(),
        ];
    }
}
