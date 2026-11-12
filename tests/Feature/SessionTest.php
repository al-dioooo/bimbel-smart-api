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
