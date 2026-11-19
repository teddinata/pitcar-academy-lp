<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'whatsapp' => ['required', 'string', 'regex:/^[0-9+\-\s()]{9,20}$/'],
            'order' => ['nullable', 'string', 'max:64'],
            'claim' => ['nullable', 'string', 'max:64'],
            // Slugs of courses the main course offers at checkout; prices come from the server.
            'addons' => ['nullable', 'array', 'max:5'],
            'addons.*' => ['string', 'max:120'],
            // Where the buyer came from, kept on the order for ad reporting.
            'attribution' => ['nullable', 'array'],
            'attribution.landing_page' => ['nullable', 'string', 'max:2000'],
            'attribution.checkout_url' => ['nullable', 'string', 'max:2000'],
            'attribution.referrer' => ['nullable', 'string', 'max:2000'],
            'attribution.utm_source' => ['nullable', 'string', 'max:255'],
            'attribution.utm_medium' => ['nullable', 'string', 'max:255'],
            'attribution.utm_campaign' => ['nullable', 'string', 'max:255'],
            'attribution.utm_content' => ['nullable', 'string', 'max:255'],
            'attribution.utm_term' => ['nullable', 'string', 'max:255'],
            'attribution.fbclid' => ['nullable', 'string', 'max:500'],
            'attribution.gclid' => ['nullable', 'string', 'max:500'],
            'attribution.ttclid' => ['nullable', 'string', 'max:500'],
            'attribution.fbp' => ['nullable', 'string', 'max:255'],
            'attribution.fbc' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama terlalu pendek.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum benar.',
            'whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
            'whatsapp.regex' => 'Nomor WhatsApp belum benar. Contoh: 081234567890.',
        ];
    }
}
