<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MfaMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private MfaMailer $mfaMailer) {}

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
        if ($user->mfa_enabled_at) {
            $challenge = Str::random(48);
            $code = (string) random_int(100000, 999999);
            DB::table('mfa_challenges')->where('user_id', $user->id)->delete();
            DB::table('mfa_challenges')->insert([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $challenge),
                'code_hash' => hash('sha256', $code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            try {
                $this->mfaMailer->sendCode($user->email, $code);
            } catch (\RuntimeException) {
                DB::table('mfa_challenges')->where('token_hash', hash('sha256', $challenge))->delete();
                return response()->json(['message' => 'We could not send a verification code. Please try again shortly.'], 503);
            }
            return response()->json(['mfa_required' => true, 'challenge' => $challenge]);
        }
        return response()->json(['user' => $user->only('id', 'name', 'email'), 'token' => $user->createToken('web')->plainTextToken]);
    }

    public function me(Request $request) { return response()->json(['user' => [...$request->user()->only('id', 'name', 'email'), 'mfa_enabled' => (bool) $request->user()->mfa_enabled_at]]); }
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

    public function enableMfa(Request $request)
    {
        $user = $request->user();
        $user->mfa_enabled_at = now();
        $user->save();
        return response()->json(['message' => 'Email MFA is enabled.']);
    }

    public function verifyMfaChallenge(Request $request)
    {
        $data = $request->validate(['challenge' => ['required', 'string'], 'code' => ['required', 'digits:6']]);
        $challenge = DB::table('mfa_challenges')->where('token_hash', hash('sha256', $data['challenge']))->first();
        if (!$challenge || Carbon::parse($challenge->expires_at)->isPast() || $challenge->attempts >= 5) {
            if ($challenge) DB::table('mfa_challenges')->where('id', $challenge->id)->delete();
            return response()->json(['message' => 'This verification request has expired. Sign in again.'], 422);
        }
        $user = User::find($challenge->user_id);
        if (!$user || !$user->mfa_enabled_at || !hash_equals($challenge->code_hash ?? '', hash('sha256', $data['code']))) {
            DB::table('mfa_challenges')->where('id', $challenge->id)->increment('attempts');
            return response()->json(['message' => 'The email verification code is invalid.'], 422);
        }
        DB::table('mfa_challenges')->where('id', $challenge->id)->delete();
        return response()->json(['user' => $user->only('id', 'name', 'email'), 'token' => $user->createToken('web')->plainTextToken]);
    }

}
