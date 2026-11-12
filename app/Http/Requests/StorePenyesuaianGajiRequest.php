<?php

namespace App\Http\Requests;

use App\Models\PenyesuaianGaji;
use Illuminate\Foundation\Http\FormRequest;

class StorePenyesuaianGajiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return self::penyesuaianRules();
    }

    public function messages(): array
    {
        return self::penyesuaianMessages();
    }

    /** Shared with the aturan gaji route, which accepts bonus/potongan too (UC-11). */
    public static function penyesuaianRules(): array
    {
        return [
            'mentor_id' => ['required', 'integer', 'exists:mentor,id'],
            'bulan' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'jenis' => ['required', 'in:'.implode(',', PenyesuaianGaji::JENIS)],
            'nominal' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function penyesuaianMessages(): array
    {
        return [
            'bulan.regex' => 'Format bulan harus YYYY-MM, misalnya 2026-10.',
            'jenis.in' => 'Jenis penyesuaian harus bonus atau potongan.',
            'nominal.min' => 'Nominal penyesuaian minimal 1.',
        ];
    }
}
