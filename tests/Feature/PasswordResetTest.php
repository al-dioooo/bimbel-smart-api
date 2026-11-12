<?php

use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
    Notification::fake();
    config(['app.frontend_url' => 'https://bimbel.example/']);

    $this->requestLink = function (): string {
        $this->postJson('/api/forgot-password', ['email' => 'budi@uji.test'])->assertOk();
        $token = null;
        Notification::assertSentTo($this->f->budi, ResetPasswordNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return $token;
    };
});

describe('forgot password (REQ-013, REQ-014, REQ-018)', function () {
    test('a registered email gets a reset link and a confirmation', function () {
        $this->postJson('/api/forgot-password', ['email' => 'budi@uji.test'])
            ->assertOk()
            ->assertJsonPath('message', 'Tautan reset telah dikirim ke email Anda.');

        Notification::assertSentTo($this->f->budi, ResetPasswordNotification::class, function ($notification) {
            $mail = $notification->toMail($this->f->budi);

            return $mail->subject === 'Atur Ulang Kata Sandi '.config('app.name')
                && $mail->actionUrl === 'https://bimbel.example/reset-password?token='.$notification->token.'&email=budi%40uji.test';
        });
    });

    test('an unregistered email gets 422 with a relevant message and no mail (§4.1.2 f)', function () {
        $this->postJson('/api/forgot-password', ['email' => 'tidakterdaftar@uji.test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Email tidak terdaftar.']);

        Notification::assertNothingSent();
    });

    test('an empty or malformed email is a validation error', function (string $email) {
        $this->postJson('/api/forgot-password', ['email' => $email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    })->with(['', 'budi-at-uji']);

    test('a second request within a minute is throttled by the broker', function () {
        ($this->requestLink)();

        $this->postJson('/api/forgot-password', ['email' => 'budi@uji.test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'tunggu sebentar']);
    });
});

describe('reset password (REQ-015)', function () {
    test('the emailed token sets a new password, which then logs in', function () {
        $token = ($this->requestLink)();
        $old = $this->f->budi->createToken('auth_token')->plainTextToken;

        $this->postJson('/api/reset-password', [
            'email' => 'budi@uji.test', 'token' => $token,
            'password' => 'sandi-baru-uji', 'password_confirmation' => 'sandi-baru-uji',
        ])->assertOk()->assertJsonPath('message', 'Kata sandi berhasil diatur ulang. Silakan login dengan kata sandi baru.');

        $this->postJson('/api/login', ['identifier' => 'budi', 'password' => 'sandi-baru-uji'])->assertOk();
        $this->postJson('/api/login', ['identifier' => 'budi', 'password' => BimbelFixture::PASSWORD])->assertUnauthorized();
        // Every session opened before the reset ended (the login above is the only token).
        expect(PersonalAccessToken::findToken($old))->toBeNull();
    });

    test('a token is used once', function () {
        $token = ($this->requestLink)();
        $body = ['email' => 'budi@uji.test', 'token' => $token, 'password' => 'sandi-baru-uji', 'password_confirmation' => 'sandi-baru-uji'];

        $this->postJson('/api/reset-password', $body)->assertOk();
        $this->postJson('/api/reset-password', $body)->assertUnprocessable()->assertJsonValidationErrors('token');
    });

    test('a wrong or expired token is refused and the password stays', function () {
        $token = ($this->requestLink)();

        $this->postJson('/api/reset-password', [
            'email' => 'budi@uji.test', 'token' => 'kode-dari-email',
            'password' => 'sandi-baru-uji', 'password_confirmation' => 'sandi-baru-uji',
        ])->assertUnprocessable()->assertJsonValidationErrors(['token' => 'tidak valid atau sudah kedaluwarsa']);

        $this->travel(61)->minutes();
        $this->postJson('/api/reset-password', [
            'email' => 'budi@uji.test', 'token' => $token,
            'password' => 'sandi-baru-uji', 'password_confirmation' => 'sandi-baru-uji',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->postJson('/api/login', ['identifier' => 'budi', 'password' => BimbelFixture::PASSWORD])->assertOk();
    });

    test('the new password must be confirmed and at least 8 characters', function (array $body, string $field) {
        $this->postJson('/api/reset-password', $body + ['email' => 'budi@uji.test', 'token' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        [['password' => 'sandi-baru-uji', 'password_confirmation' => 'lain-lagi-uji'], 'password'],
        [['password' => 'pendek', 'password_confirmation' => 'pendek'], 'password'],
        [['password' => 'sandi-baru-uji', 'password_confirmation' => 'sandi-baru-uji', 'token' => ''], 'token'],
    ]);
});
