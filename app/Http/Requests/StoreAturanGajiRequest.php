<?php

namespace App\Http\Requests;

use App\Models\PenyesuaianGaji;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAturanGajiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    /**
     * UC-11 treats bonus and potongan as salary rules too ("skema
     * pemotongan"), so this route also accepts them with jenis=bonus|potongan
     * and hands them to the penyesuaian gaji rules. Without jenis, or with
     * jenis=tarif, it is the per-kelas tarif as before.
     */
    public function isPenyesuaian(): bool
    {
        return in_array($this->input('jenis'), PenyesuaianGaji::JENIS, true);
    }

    public function rules(): array
    {
        if ($this->isPenyesuaian()) {
            return StorePenyesuaianGajiRequest::penyesuaianRules();
        }

        return [
            'jenis' => ['nullable', 'in:tarif,'.implode(',', PenyesuaianGaji::JENIS)],
            // One tarif per kelas (REQ-055); a second rule doubled the salary rows.
            'kelas_id' => ['required', 'exists:kelas,id', 'unique:aturan_gaji,kelas_id'],
            'tarif' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        if ($this->isPenyesuaian()) {
            return StorePenyesuaianGajiRequest::penyesuaianMessages();
        }

        return [
            'kelas_id.unique' => 'Kelas ini sudah memiliki aturan gaji. Ubah aturan yang sudah ada.',
        ];
    }
}
