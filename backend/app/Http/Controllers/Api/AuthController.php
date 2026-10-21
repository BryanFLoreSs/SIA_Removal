<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        return response()->json(['user' => $user->only('id', 'name', 'email'), 'token' => $user->createToken('web')->plainTextToken], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }
        // TODO: Require a TOTP challenge before issuing a token for MFA-enrolled users.
        return response()->json(['user' => $user->only('id', 'name', 'email'), 'token' => $user->createToken('web')->plainTextToken]);
    }

    public function me(Request $request) { return response()->json(['user' => $request->user()->only('id', 'name', 'email')]); }
    public function logout(Request $request) { $request->user()->currentAccessToken()->delete(); return response()->json(['message' => 'Signed out.']); }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        // Uses Laravel's broker, configured expiration and throttling. Always return a generic response.
        \Illuminate\Support\Facades\Password::sendResetLink($request->only('email'));
        return response()->json(['message' => 'If the account can receive a reset link, instructions will be sent shortly.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'min:12', 'confirmed']]);
        $status = \Illuminate\Support\Facades\Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
        });
        if ($status !== \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json(['message' => 'The reset link is invalid or expired.'], 422);
        }
        return response()->json(['message' => 'Password updated.']);
    }
}
