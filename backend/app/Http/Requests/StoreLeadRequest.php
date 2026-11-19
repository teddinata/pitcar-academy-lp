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

            'goal' => ['required', Rule::in(array_keys(LeadOptions::GOALS))],
            'readiness' => ['required', Rule::in(array_keys(LeadOptions::READINESS))],
            'program_interest' => ['required', Rule::in(array_keys(LeadOptions::PROGRAMS))],

            // Dropped from the short form but still accepted, so a client that
            // has not been redeployed yet keeps working instead of 422-ing.
            'activity' => ['nullable', Rule::in(array_keys(LeadOptions::ACTIVITIES))],
            'timeline' => ['nullable', Rule::in(array_keys(LeadOptions::TIMELINES))],
            'investment_readiness' => ['nullable', Rule::in(array_keys(LeadOptions::INVESTMENT_READINESS))],

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

            // Meta's own cookies, forwarded for Conversions API matching.
            // Opaque strings; neither identifies a person by itself.
            'attribution.fbp' => ['nullable', 'string', 'max:255'],
            'attribution.fbc' => ['nullable', 'string', 'max:255'],
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
