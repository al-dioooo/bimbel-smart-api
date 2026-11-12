<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
    $this->f->mBudi->update([
        'tempat_tanggal_lahir' => 'Bogor, 1 Januari 1990',
        'nik' => '3201010101900001',
        'npwp' => '01.234.567.8-901.000',
        'alamat' => 'Jl. Pajajaran 1',
    ]);
    Sanctum::actingAs($this->f->admin);

    $this->state = function () {
        $mentor = $this->f->mBudi->fresh();

        return [
            'name' => $mentor->user->name,
            'username' => $mentor->user->username,
            'email' => $mentor->user->email,
            'kontak' => $mentor->kontak,
            'tempat_tanggal_lahir' => $mentor->tempat_tanggal_lahir,
            'nik' => $mentor->nik,
            'npwp' => $mentor->npwp,
            'alamat' => $mentor->alamat,
        ];
    };
    $this->before = ($this->state)();
});

test('sending only the name changes only the name (D15, G9)', function () {
    $this->putJson("/api/mentor/{$this->f->mBudi->id}", ['name' => 'Budi Santoso, S.Pd.'])->assertOk()
        ->assertJsonPath('data.user.name', 'Budi Santoso, S.Pd.');

    expect(($this->state)())->toBe(array_merge($this->before, ['name' => 'Budi Santoso, S.Pd.']))
        ->and(Hash::check(BimbelFixture::PASSWORD, $this->f->budi->fresh()->password))->toBeTrue();
});

test('account fields without profile fields keep the profile (G10)', function () {
    $this->putJson("/api/mentor/{$this->f->mBudi->id}", ['name' => 'Budi Baru', 'username' => 'budi', 'email' => 'budi@uji.test'])
        ->assertOk();

    expect(($this->state)())->toBe(array_merge($this->before, ['name' => 'Budi Baru']));
});

test('profile fields alone leave the account untouched', function () {
    $this->putJson("/api/mentor/{$this->f->mBudi->id}", ['alamat' => 'Jl. Dramaga 2'])->assertOk();

    expect(($this->state)())->toBe(array_merge($this->before, ['alamat' => 'Jl. Dramaga 2']));
});

test('a field sent as empty is cleared on purpose', function () {
    $this->putJson("/api/mentor/{$this->f->mBudi->id}", ['npwp' => ''])->assertOk();

    expect(($this->state)())->toBe(array_merge($this->before, ['npwp' => null]));
});

test('a new password is hashed, an empty one keeps the old', function () {
    $this->putJson("/api/mentor/{$this->f->mBudi->id}", ['password' => ''])->assertOk();
    expect(Hash::check(BimbelFixture::PASSWORD, $this->f->budi->fresh()->password))->toBeTrue();

    $this->putJson("/api/mentor/{$this->f->mBudi->id}", ['password' => 'sandi-baru-123'])->assertOk();
    $hash = $this->f->budi->fresh()->password;
    expect(Hash::check('sandi-baru-123', $hash))->toBeTrue()
        ->and($hash)->not->toBe('sandi-baru-123');
});
