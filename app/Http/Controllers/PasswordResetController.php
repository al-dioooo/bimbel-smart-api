<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Lupa kata sandi (REQ-012–REQ-015, REQ-018) through Laravel's password
 * broker: the token is stored hashed in password_reset_tokens, expires after
 * auth.passwords.users.expire minutes, and is sent with the configured mailer.
 */
class PasswordResetController extends Controller
{
    public function forgot(Request $request)
    {
        $request->validate(
            ['email' => ['required', 'email', 'max:255']],
            ['email.required' => 'Email wajib diisi.', 'email.email' => 'Format email tidak valid.'],
        );

        // §4.1.2 f: an unregistered address gets a relevant message.
        if (! User::where('email', $request->input('email'))->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email tidak terdaftar.',
            ]);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'Tautan reset baru saja dikirim. Silakan tunggu sebentar sebelum meminta lagi.',
            ]);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => 'Tautan reset tidak dapat dikirim. Silakan coba lagi.',
            ]);
        }

        // REQ-018.
        return response()->json([
            'message' => 'Tautan reset telah dikirim ke email Anda.',
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'token.required' => 'Kode reset wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $status = Password::reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();

                // Sessions opened with the old password end too.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(match ($status) {
                Password::INVALID_USER => ['email' => 'Email tidak terdaftar.'],
                Password::RESET_THROTTLED => ['email' => 'Terlalu banyak percobaan. Silakan tunggu sebentar.'],
                default => ['token' => 'Tautan atau kode reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.'],
            });
        }

        return response()->json([
            'message' => 'Kata sandi berhasil diatur ulang. Silakan login dengan kata sandi baru.',
        ]);
    }
}
