<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();

    $this->login = fn () => $this->postJson('/api/login', ['identifier' => 'budi', 'password' => BimbelFixture::PASSWORD])->json('data.token');
    // A real bearer request; guards are reset so no user is cached between calls.
    $this->call = function (string $method, string $uri, ?string $token) {
        app('auth')->forgetGuards();

        return $this->json($method, $uri, [], $token ? ['Authorization' => "Bearer $token"] : []);
    };
});

describe('logout (D12)', function () {
    test('logout revokes the token, so it no longer opens anything (REQ-010, REQ-017)', function () {
        $token = ($this->login)();
        ($this->call)('GET', '/api/me', $token)->assertOk();

        ($this->call)('POST', '/api/logout', $token)->assertOk();

        expect(PersonalAccessToken::count())->toBe(0);
        ($this->call)('GET', '/api/me', $token)->assertUnauthorized();
        ($this->call)('GET', '/api/jadwal', $token)->assertUnauthorized();
    });

    test('logout without a token still answers 200 (unchanged)', function () {
        ($this->call)('POST', '/api/logout', null)->assertOk();
    });
});

describe('idle timeout (D14, REQ-016)', function () {
    test('a token unused for up to 30 minutes still works', function (int $minutes) {
        $token = ($this->login)();

        $this->travel($minutes)->minutes();

        ($this->call)('GET', '/api/me', $token)->assertOk();
    })->with([29, 30]);

    test('a token unused for 31 minutes is rejected and deleted', function () {
        $token = ($this->login)();

        $this->travel(31)->minutes();

        ($this->call)('GET', '/api/me', $token)->assertUnauthorized();
        expect(PersonalAccessToken::count())->toBe(0);
    });

    test('30 minutes and one second is already too long', function () {
        $token = ($this->login)();

        $this->travel(30 * 60 + 1)->seconds();

        ($this->call)('GET', '/api/me', $token)->assertUnauthorized();
    });

    test('every request restarts the idle clock', function () {
        $token = ($this->login)();

        $this->travel(20)->minutes();
        ($this->call)('GET', '/api/me', $token)->assertOk();

        $this->travel(25)->minutes(); // 45 minutes after login, 25 after the last use
        ($this->call)('GET', '/api/me', $token)->assertOk();
    });

    test('the timeout is configurable and 0 turns it off', function () {
        config(['sanctum.idle_timeout' => 0]);
        $token = ($this->login)();

        $this->travel(1)->days();

        ($this->call)('GET', '/api/me', $token)->assertOk();
    });
});
