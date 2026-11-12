<?php

namespace App\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Turns a unique-index violation into the same 422 the validator gives.
 *
 * The validators refuse duplicates first; the database indexes catch two
 * requests that pass validation at the same time. Those must not end as a
 * 500 carrying SQL. PostgreSQL names the index, SQLite the columns.
 */
class UniqueViolation
{
    /** needle in the driver message => [field, message] */
    private const KNOWN = [
        'aturan_gaji_kelas_id_unique' => ['kelas_id', 'Kelas ini sudah memiliki aturan gaji. Ubah aturan yang sudah ada.'],
        'aturan_gaji.kelas_id' => ['kelas_id', 'Kelas ini sudah memiliki aturan gaji. Ubah aturan yang sudah ada.'],
        'kelas_nama_tingkat_unique' => ['nama', 'Kelas dengan nama dan tingkat yang sama sudah terdaftar.'],
        'mentor_kontak_unique' => ['kontak', 'Nomor telepon sudah dipakai mentor lain.'],
        'mentor.kontak' => ['kontak', 'Nomor telepon sudah dipakai mentor lain.'],
        'users_username_unique' => ['username', 'Username sudah digunakan.'],
        'users.username' => ['username', 'Username sudah digunakan.'],
        'users_email_unique' => ['email', 'Email sudah digunakan.'],
        'users.email' => ['email', 'Email sudah digunakan.'],
        'absensi_jadwal_id_siswa_id_unique' => ['absensi', 'Absensi sudah diisi. Perubahan dilakukan oleh admin melalui Ubah Absensi.'],
        'absensi.jadwal_id, absensi.siswa_id' => ['absensi', 'Absensi sudah diisi. Perubahan dilakukan oleh admin melalui Ubah Absensi.'],
    ];

    /** Rethrows a known unique violation as a ValidationException; otherwise does nothing. */
    public static function rethrow(\Throwable $e): void
    {
        if (! $e instanceof UniqueConstraintViolationException) {
            return;
        }

        foreach (self::KNOWN as $needle => [$field, $message]) {
            if (str_contains($e->getMessage(), $needle)) {
                throw ValidationException::withMessages([$field => $message]);
            }
        }
    }
}
