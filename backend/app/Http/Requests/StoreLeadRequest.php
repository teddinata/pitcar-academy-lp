<?php

namespace App\Http\Requests;

use App\Rules\IndonesianWhatsAppNumber;
use App\Support\LeadOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
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
        // Everything the browser can send is validated here. Anything not in
        // this list (score, qualification, status, lead_code, consultant) is
        // simply never read out of the request.
        return [
            'submission_id' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:100'],
            'whatsapp_number' => ['required', 'string', 'max:20', new IndonesianWhatsAppNumber],
            'domicile' => ['required', 'string', 'max:100'],

            'activity' => ['required', Rule::in(array_keys(LeadOptions::ACTIVITIES))],
            'goal' => ['required', Rule::in(array_keys(LeadOptions::GOALS))],
            'timeline' => ['required', Rule::in(array_keys(LeadOptions::TIMELINES))],
            'investment_readiness' => ['required', Rule::in(array_keys(LeadOptions::INVESTMENT_READINESS))],
            'program_interest' => ['required', Rule::in(array_keys(LeadOptions::PROGRAMS))],

            'source' => ['required', Rule::in(['website'])],
            'source_cta' => ['required', 'string', 'max:100'],

            // A small forward tolerance covers client clock skew; anything
            // further ahead is not a real consent timestamp.
            'consent_at' => [
                'required', 'date',
                'after:'.now()->subDay()->toDateTimeString(),
                'before:'.now()->addMinutes(10)->toDateTimeString(),
            ],

            'attribution' => ['required', 'array'],
            'attribution.landing_page' => ['required', 'string', 'url', 'max:2048'],
            'attribution.referrer' => ['nullable', 'string', 'url', 'max:2048'],
            'attribution.utm_source' => ['nullable', 'string', 'max:255'],
            'attribution.utm_medium' => ['nullable', 'string', 'max:255'],
            'attribution.utm_campaign' => ['nullable', 'string', 'max:255'],
            'attribution.utm_content' => ['nullable', 'string', 'max:255'],
            'attribution.utm_term' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Bagian ini belum diisi.',
            'consent_at.before' => 'Waktu persetujuan tidak valid.',
            'consent_at.after' => 'Waktu persetujuan sudah kedaluwarsa, silakan kirim ulang form.',
        ];
    }
}
