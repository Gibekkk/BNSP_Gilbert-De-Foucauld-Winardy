<?php

namespace App\Http\Requests;

use App\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Parameter laporan sewa:
 *  - group=monthly → year wajib
 *  - group=daily   → year dan month wajib
 *  - group=type / tenant → year dan month opsional (kosong = semua periode)
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group' => ['required', Rule::in(ReportService::GROUPS)],
            'year' => [
                Rule::requiredIf(in_array($this->query('group'), [ReportService::GROUP_MONTHLY, ReportService::GROUP_DAILY], true)),
                'nullable', 'integer', 'min:2000', 'max:2100',
            ],
            'month' => [
                Rule::requiredIf($this->query('group') === ReportService::GROUP_DAILY),
                'nullable', 'integer', 'min:1', 'max:12',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'group.required' => 'Jenis laporan wajib dipilih.',
            'group.in' => 'Jenis laporan tidak dikenal.',
            'year.required' => 'Tahun wajib dipilih.',
            'year.integer' => 'Tahun tidak valid.',
            'month.required' => 'Bulan wajib dipilih.',
            'month.integer' => 'Bulan tidak valid.',
            'month.min' => 'Bulan tidak valid.',
            'month.max' => 'Bulan tidak valid.',
        ];
    }
}
