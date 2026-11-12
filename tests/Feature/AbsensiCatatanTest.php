<?php

use App\Models\Absensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BimbelFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->f = BimbelFixture::make();
});

test('a mentor records a note per siswa and gets it back (REQ-044)', function () {
    Sanctum::actingAs($this->f->budi);

    $res = $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jLalu->id, 'absensi' => [
        ['siswa_id' => $this->f->siswa['Andi']->id, 'status' => 'I', 'catatan' => 'Acara keluarga'],
        ['siswa_id' => $this->f->siswa['Bela']->id, 'status' => 'H'],
    ]])->assertCreated();

    $rows = collect($res->json('data'))->keyBy('siswa_id');
    expect($rows[$this->f->siswa['Andi']->id]['catatan'])->toBe('Acara keluarga')
        ->and($rows[$this->f->siswa['Bela']->id]['catatan'])->toBeNull();

    $this->getJson("/api/absensi?jadwal_id={$this->f->jLalu->id}&paginate=false")
        ->assertJsonPath('data.0.catatan', 'Acara keluarga');
});

test('a note longer than 255 characters is refused', function () {
    Sanctum::actingAs($this->f->budi);

    $this->postJson('/api/absensi', ['jadwal_id' => $this->f->jLalu->id, 'absensi' => [
        ['siswa_id' => $this->f->siswa['Andi']->id, 'status' => 'I', 'catatan' => str_repeat('c', 256)],
    ]])->assertUnprocessable()->assertJsonValidationErrors('absensi.0.catatan');
});

test('the admin edits the note, alone or with the status (REQ-049)', function () {
    Sanctum::actingAs($this->f->admin);
    $bela = Absensi::where('jadwal_id', $this->f->jIsi->id)->where('siswa_id', $this->f->siswa['Bela']->id)->first();

    $this->putJson("/api/absensi/{$bela->id}", ['catatan' => 'Salah input, seharusnya izin'])
        ->assertOk()
        ->assertJsonPath('data.catatan', 'Salah input, seharusnya izin')
        ->assertJsonPath('data.status', 's');

    $this->putJson("/api/absensi/{$bela->id}", ['status' => 'I', 'catatan' => null])->assertOk();
    expect($bela->fresh()->only('status', 'catatan'))->toBe(['status' => 'i', 'catatan' => null]);
});
