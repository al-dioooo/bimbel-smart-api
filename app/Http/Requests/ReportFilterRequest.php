<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query filters shared by the laporan gaji and laporan absensi endpoints.
 *
 * A filter that cannot be applied used to be dropped silently, so the report
 * showed every period while looking filtered (D8, G6). REQ-063 asks for an
 * informative error instead.
 */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bulan' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'bulan.regex' => 'Format bulan harus YYYY-MM, misalnya 2026-09.',
            'from.required_with' => 'Tanggal awal (from) wajib diisi bersama tanggal akhir (to).',
            'to.required_with' => 'Tanggal akhir (to) wajib diisi bersama tanggal awal (from).',
            'from.date_format' => 'Tanggal awal (from) harus berformat YYYY-MM-DD.',
            'to.date_format' => 'Tanggal akhir (to) harus berformat YYYY-MM-DD.',
            'to.after_or_equal' => 'Tanggal akhir (to) tidak boleh sebelum tanggal awal (from).',
        ];
    }
}
