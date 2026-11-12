<?php

namespace App\Http\Requests;

use App\Models\PenyesuaianGaji;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePenyesuaianGajiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'mentor_id' => ['sometimes', 'required', 'integer', 'exists:mentor,id'],
            'bulan' => ['sometimes', 'required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'jenis' => ['sometimes', 'required', 'in:'.implode(',', PenyesuaianGaji::JENIS)],
            'nominal' => ['sometimes', 'required', 'numeric', 'min:1', 'max:9999999999999'],
            'keterangan' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return StorePenyesuaianGajiRequest::penyesuaianMessages();
    }
}
