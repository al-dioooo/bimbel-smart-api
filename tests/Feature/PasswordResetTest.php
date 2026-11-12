<?php

use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
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

        // Every session opened before the reset ended, before any new login.
        expect(PersonalAccessToken::findToken($old))->toBeNull();

        $this->postJson('/api/login', ['identifier' => 'budi', 'password' => 'sandi-baru-uji'])->assertOk();
        $this->postJson('/api/login', ['identifier' => 'budi', 'password' => BimbelFixture::PASSWORD])->assertUnauthorized();
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

describe('no reset link in the logs (review #32)', function () {
    beforeEach(function () {
        $this->app['env'] = 'production';
    });

    test('a log or array mailer outside local/testing answers 503 and sends nothing', function (string $mailer) {
        config(['mail.default' => $mailer]);

        $this->postJson('/api/forgot-password', ['email' => 'budi@uji.test'])
            ->assertStatus(503)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'pengiriman email belum dikonfigurasi'));

        Notification::assertNothingSent();
        expect(DB::table('password_reset_tokens')->count())->toBe(0);
    })->with(['log', 'array']);

    test('a failover that falls back to the log is refused too', function () {
        config(['mail.default' => 'failover', 'mail.mailers.failover' => ['transport' => 'failover', 'mailers' => ['smtp', 'log']]]);

        $this->postJson('/api/forgot-password', ['email' => 'budi@uji.test'])->assertStatus(503);
    });

    test('an SMTP mailer sends the link', function () {
        config(['mail.default' => 'smtp']);

        $this->postJson('/api/forgot-password', ['email' => 'budi@uji.test'])->assertOk();
        Notification::assertSentTo($this->f->budi, ResetPasswordNotification::class);
    });
});

test('the email is matched whatever its case, and the stored address is used', function () {
    $this->postJson('/api/forgot-password', ['email' => 'BUDI@Uji.Test'])->assertOk();

    $token = null;
    Notification::assertSentTo($this->f->budi, ResetPasswordNotification::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->postJson('/api/reset-password', [
        'email' => 'Budi@UJI.test', 'token' => $token,
        'password' => 'sandi-baru-uji', 'password_confirmation' => 'sandi-baru-uji',
    ])->assertOk();
    $this->postJson('/api/login', ['identifier' => 'budi', 'password' => 'sandi-baru-uji'])->assertOk();
});

describe('rate limits (review #32)', function () {
    test('one visitor IP gets 6 requests a minute', function () {
        foreach (range(1, 6) as $i) {
            $this->postJson('/api/forgot-password', ['email' => "orang{$i}@uji.test"])->assertUnprocessable();
        }

        $this->postJson('/api/forgot-password', ['email' => 'orang7@uji.test'])->assertTooManyRequests();
    });

    test('one email gets 5 requests a minute, whatever the IP', function () {
        config(['services.frontend_proxy.secret' => 'rahasia-proxy']);

        foreach (range(1, 5) as $i) {
            $this->withHeaders(['X-Proxy-Secret' => 'rahasia-proxy', 'X-Client-IP' => "203.0.113.{$i}"])
                ->postJson('/api/reset-password', ['email' => 'budi@uji.test', 'token' => 'x', 'password' => 'sandi-baru-uji', 'password_confirmation' => 'sandi-baru-uji'])
                ->assertUnprocessable();
        }

        $this->withHeaders(['X-Proxy-Secret' => 'rahasia-proxy', 'X-Client-IP' => '203.0.113.9'])
            ->postJson('/api/forgot-password', ['email' => 'BUDI@uji.test'])->assertTooManyRequests();
    });

    test('visitors behind the frontend proxy get their own buckets', function () {
        config(['services.frontend_proxy.secret' => 'rahasia-proxy']);
        $as = fn (string $ip, string $secret = 'rahasia-proxy') => $this->withHeaders(['X-Proxy-Secret' => $secret, 'X-Client-IP' => $ip]);

        foreach (range(1, 6) as $i) {
            $as('198.51.100.1')->postJson('/api/forgot-password', ['email' => "a{$i}@uji.test"])->assertUnprocessable();
        }
        $as('198.51.100.1')->postJson('/api/forgot-password', ['email' => 'a7@uji.test'])->assertTooManyRequests();
        $as('198.51.100.2')->postJson('/api/forgot-password', ['email' => 'b1@uji.test'])->assertUnprocessable();
    });
});

describe('client IP from the frontend proxy (review #32)', function () {
    beforeEach(function () {
        Route::get('/api/_uji/ip', fn (Request $request) => [
            'ip' => $request->ip(),
            'secret' => $request->header('X-Proxy-Secret'),
        ]);
        config(['services.frontend_proxy.secret' => 'rahasia-proxy']);
    });

    test('the forwarded IP is used only with the right secret', function (?string $secret, string $expected) {
        $headers = ['X-Client-IP' => '198.51.100.7'] + ($secret === null ? [] : ['X-Proxy-Secret' => $secret]);

        $this->withHeaders($headers)->getJson('/api/_uji/ip')
            ->assertJsonPath('ip', $expected)
            ->assertJsonPath('secret', null);
    })->with([
        'right secret' => ['rahasia-proxy', '198.51.100.7'],
        'wrong secret' => ['tebakan', '127.0.0.1'],
        'no secret' => [null, '127.0.0.1'],
    ]);

    test('nothing is trusted while no secret is configured', function () {
        config(['services.frontend_proxy.secret' => null]);

        $this->withHeaders(['X-Client-IP' => '198.51.100.7', 'X-Proxy-Secret' => ''])->getJson('/api/_uji/ip')
            ->assertJsonPath('ip', '127.0.0.1');
    });

    test('a value that is not an IP is ignored', function () {
        $this->withHeaders(['X-Client-IP' => '198.51.100.7, 10.0.0.1', 'X-Proxy-Secret' => 'rahasia-proxy'])->getJson('/api/_uji/ip')
            ->assertJsonPath('ip', '127.0.0.1');
    });
});
