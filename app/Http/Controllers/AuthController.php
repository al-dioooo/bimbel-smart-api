<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => ['required', 'string'],
            'password'   => ['required', 'string'],
        ]);

        $identifier = $request->identifier;

        // Try matching by email first, then username
        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // If mentor, validate mentor profile
        if ($user->role === 'mentor' && !$user->mentor) {
            return response()->json([
                'message' => 'Mentor profile is incomplete',
            ], 403);
        }

        // Revoke all tokens (optional but recommended)
        $user->tokens()->delete();

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user'    => $user->load('mentor')
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return $request->user()->load('mentor');
    }
}
