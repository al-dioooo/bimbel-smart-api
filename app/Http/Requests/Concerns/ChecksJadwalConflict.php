<?php

namespace App\Http\Requests\Concerns;

use App\Models\Jadwal;
use Illuminate\Validation\Validator;

/**
 * Shared by the jadwal store and update requests: refuse a session that
 * overlaps another one of the same kelas or the same mentor (REQ-032,
 * REQ-034) with the UC-13 7a message.
 */
trait ChecksJadwalConflict
{
    /**
     * @param  array{kelas_id: mixed, tanggal: mixed, waktu_mulai: mixed, waktu_selesai: mixed}  $jadwal
     */
    protected function checkJadwalConflict(Validator $validator, array $jadwal, ?int $ignoreId = null): void
    {
        if ($validator->errors()->hasAny(['kelas_id', 'tanggal', 'waktu_mulai', 'waktu_selesai'])) {
            return;
        }

        if (in_array(null, $jadwal, true)) {
            return;
        }

        if (Jadwal::normalizeTime($jadwal['waktu_selesai']) <= Jadwal::normalizeTime($jadwal['waktu_mulai'])) {
            $validator->errors()->add('waktu_selesai', 'Waktu selesai harus setelah waktu mulai.');

            return;
        }

        $conflict = Jadwal::findConflict(
            (int) $jadwal['kelas_id'],
            (string) $jadwal['tanggal'],
            (string) $jadwal['waktu_mulai'],
            (string) $jadwal['waktu_selesai'],
            $ignoreId,
        );

        if ($conflict) {
            $validator->errors()->add('waktu_mulai', sprintf(
                'Jadwal sudah terisi. Silakan pilih waktu lain. Bentrok dengan %s pukul %s–%s.',
                $conflict->kelas?->nama ?? 'jadwal lain',
                substr($conflict->waktu_mulai, 0, 5),
                substr($conflict->waktu_selesai, 0, 5),
            ));
        }
    }
}
