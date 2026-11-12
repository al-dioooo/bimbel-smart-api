<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
});

test('the admin dashboard shows the total mentors, siswa and kelas (REQ-067)', function () {
    Sanctum::actingAs($this->f->admin);

    $this->getJson('/api/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.total_mentor', 3)
        ->assertJsonPath('data.total_siswa', 4)
        ->assertJsonPath('data.total_kelas', 3);
});

test('a mentor sees their own kelas and siswa, and no mentor count', function () {
    Sanctum::actingAs($this->f->budi);

    $data = $this->getJson('/api/dashboard/stats')->assertOk()->json('data');

    expect($data['total_kelas'])->toBe(2)
        ->and($data['total_siswa'])->toBe(3)
        ->and($data)->not->toHaveKey('total_mentor');
});

test('a mentor without kelas sees zero', function () {
    Sanctum::actingAs($this->f->rina);

    $this->getJson('/api/dashboard/stats')->assertJsonPath('data.total_kelas', 0)->assertJsonPath('data.total_siswa', 0);
});
