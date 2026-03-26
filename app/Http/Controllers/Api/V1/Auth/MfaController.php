<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class MfaController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasMfaEnabled()) {
            return response()->json(['message' => 'MFA is already enabled.'], 422);
        }

        $secret = $this->google2fa->generateSecretKey();

        $user->update(['two_factor_secret' => encrypt($secret)]);

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Generate SVG QR code
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return response()->json([
            'secret' => $secret,
            'qr_code' => base64_encode($qrCodeSvg),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'MFA setup not initiated.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $validated['code']);

        if (!$valid) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        // Generate recovery codes
        $recoveryCodes = collect(range(1, 8))->map(fn () => Str::random(10))->toArray();

        $user->update([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        return response()->json([
            'message' => 'MFA enabled successfully.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!password_verify($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid password.'], 403);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json(['message' => 'MFA disabled successfully.']);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user->hasMfaEnabled()) {
            return response()->json(['message' => 'MFA is not enabled.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $validated['code']);

        if (!$valid) {
            // Try recovery codes
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            $codeIndex = array_search($validated['code'], $recoveryCodes);

            if ($codeIndex === false) {
                return response()->json(['message' => 'Invalid MFA code.'], 401);
            }

            // Consume the recovery code
            unset($recoveryCodes[$codeIndex]);
            $user->update([
                'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
            ]);
        }

        // Delete the temporary MFA token
        $user->currentAccessToken()->delete();

        // Issue a full access token
        $token = $user->createToken('auth-token', ['*'], now()->addDays(30));
        $token->accessToken->update([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
        ]);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasMfaEnabled()) {
            return response()->json(['message' => 'MFA is not enabled.'], 422);
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        return response()->json(['recovery_codes' => $codes]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasMfaEnabled()) {
            return response()->json(['message' => 'MFA is not enabled.'], 422);
        }

        $recoveryCodes = collect(range(1, 8))->map(fn () => Str::random(10))->toArray();

        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }
}
