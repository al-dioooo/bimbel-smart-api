<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // UC-01 3a.
        $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'identifier.required' => 'Username dan password tidak boleh kosong.',
            'password.required' => 'Username dan password tidak boleh kosong.',
        ]);

        $identifier = $request->identifier;

        // Try matching by email first, then username
        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Username atau password salah.', // UC-01 4a, REQ-007
            ], 401);
        }

        // If mentor, validate mentor profile
        if ($user->isMentor() && ! $user->mentor) {
            return response()->json([
                'message' => 'Profil mentor belum lengkap. Hubungi admin.',
            ], 403);
        }

        // Revoke all tokens (optional but recommended)
        $user->tokens()->delete();

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token,
                'user' => $user->load('mentor'),
            ],
        ]);
    }

    /**
     * REQ-009 / REQ-010: revoke the token on the server.
     *
     * The route sits outside auth:sanctum (logging out without a token keeps
     * answering 200), so the default guard knew no user and nothing was ever
     * deleted (D12). Resolve the user through the sanctum guard instead.
     */
    public function logout(Request $request)
    {
        $token = $request->user('sanctum')?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Anda telah berhasil logout.']); // UC-02 4a
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user()->update($request->validated());

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'message' => 'Data pengguna berhasil diambil.',
            'data' => $request->user()->load('mentor'),
        ]);
    }
}
