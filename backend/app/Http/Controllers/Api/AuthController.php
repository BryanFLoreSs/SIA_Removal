<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
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
        if ($user->mfa_enabled_at) {
            $challenge = Str::random(48);
            DB::table('mfa_challenges')->where('user_id', $user->id)->delete();
            DB::table('mfa_challenges')->insert([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $challenge),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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

    public function setupMfa(Request $request)
    {
        $user = $request->user();
        if ($user->mfa_enabled_at) return response()->json(['message' => 'Authenticator MFA is already enabled.'], 409);
        $secret = $this->base32Encode(random_bytes(20));
        $user->mfa_secret = $secret;
        $user->mfa_enabled_at = null;
        $user->save();
        $label = rawurlencode('CityPulse:' . $user->email);
        $uri = 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=CityPulse&algorithm=SHA1&digits=6&period=30';
        return response()->json(['secret' => $secret, 'otpauth_uri' => $uri]);
    }

    public function enableMfa(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        if (!$user->mfa_secret || !$this->validTotp($user->mfa_secret, $data['code'])) {
            return response()->json(['message' => 'The authenticator code is invalid.'], 422);
        }
        $user->mfa_enabled_at = now();
        $user->save();
        return response()->json(['message' => 'Authenticator MFA is enabled.']);
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
        if (!$user || !$user->mfa_enabled_at || !$this->validTotp($user->mfa_secret, $data['code'])) {
            DB::table('mfa_challenges')->where('id', $challenge->id)->increment('attempts');
            return response()->json(['message' => 'The authenticator code is invalid.'], 422);
        }
        DB::table('mfa_challenges')->where('id', $challenge->id)->delete();
        return response()->json(['user' => $user->only('id', 'name', 'email'), 'token' => $user->createToken('web')->plainTextToken]);
    }

    private function validTotp(string $secret, string $code): bool
    {
        $key = $this->base32Decode($secret);
        $time = intdiv(time(), 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            $counter = pack('N2', 0, $time + $offset);
            $hash = hash_hmac('sha1', $counter, $key, true);
            $index = ord($hash[19]) & 0x0f;
            $number = ((ord($hash[$index]) & 0x7f) << 24) | ((ord($hash[$index + 1]) & 0xff) << 16) | ((ord($hash[$index + 2]) & 0xff) << 8) | (ord($hash[$index + 3]) & 0xff);
            if (hash_equals(str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT), $code)) return true;
        }
        return false;
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = '';
        foreach (str_split($data) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        $output = '';
        foreach (str_split($bits, 5) as $chunk) $output .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        return $output;
    }

    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = '';
        foreach (str_split(rtrim(strtoupper($data), '=')) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) return '';
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $output .= chr(bindec($byte));
        return $output;
    }
}
