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
        // A log or array mailer would write the reset link into the logs,
        // where anyone who reads them could reset any account.
        if ($this->mailerWouldLeakLink()) {
            return response()->json([
                'message' => 'Fitur lupa kata sandi belum dapat digunakan karena pengiriman email belum dikonfigurasi. '
                    .'Silakan hubungi admin untuk mengatur ulang kata sandi.',
            ], 503);
        }

        $request->validate(
            ['email' => ['required', 'email', 'max:255']],
            ['email.required' => 'Email wajib diisi.', 'email.email' => 'Format email tidak valid.'],
        );

        // §4.1.2 f: an unregistered address gets a relevant message.
        $user = $this->findByEmail($request->input('email'));
        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'Email tidak terdaftar.',
            ]);
        }

        $status = Password::sendResetLink(['email' => $user->email]);

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

        // The broker matches the stored address exactly.
        $email = $this->findByEmail($request->input('email'))?->email ?? $request->input('email');

        $status = Password::reset(
            ['email' => $email] + $request->only('token', 'password', 'password_confirmation'),
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

    /** Emails are compared case-insensitively (PostgreSQL compares exactly). */
    private function findByEmail(string $email): ?User
    {
        return User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    /**
     * True outside local/testing when the mailer, or a fallback of it, only
     * writes mail to the log or to memory.
     */
    private function mailerWouldLeakLink(): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return false;
        }

        $mailer = (string) config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport", $mailer);
        $mailers = in_array($transport, ['failover', 'roundrobin'], true)
            ? (array) config("mail.mailers.{$mailer}.mailers", [])
            : [$mailer];

        foreach ($mailers as $name) {
            if (in_array(config("mail.mailers.{$name}.transport", $name), ['log', 'array'], true)) {
                return true;
            }
        }

        return false;
    }
}
