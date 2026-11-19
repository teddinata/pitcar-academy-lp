<?php

namespace App\Services\Kirimdev;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KirimdevClient
{
    // Errors that will not go away by sending again: wrong key, unknown template, invalid number.
    private const FINAL_CODES = ['unauthorized', 'forbidden', 'template_not_found', 'template_not_approved', 'invalid_recipient', 'validation_error'];

    public function configured(): bool
    {
        return filled(config('kirimdev.api_key')) && filled(config('kirimdev.phone_number_id'));
    }

    /**
     * Sends an approved template. Returns ['ok' => bool, 'id' => ?string, 'error' => ?string, 'retry' => bool].
     *
     * @param  list<string>  $bodyParams
     */
    public function sendTemplate(string $phone, string $template, array $bodyParams, string $idempotencyKey): array
    {
        $url = config('kirimdev.base_url').'/'.config('kirimdev.phone_number_id').'/messages';

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => '+'.$phone,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => config('kirimdev.template_language'),
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(fn (string $v) => ['type' => 'text', 'text' => self::paramText($v)], $bodyParams),
                ]],
            ],
        ];

        try {
            $response = Http::withToken((string) config('kirimdev.api_key'))
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->acceptJson()
                ->timeout(10)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'id' => null, 'error' => 'Koneksi ke kirimdev gagal: '.Str::limit($e->getMessage(), 200), 'retry' => true];
        }

        if ($response->successful()) {
            $status = strtolower((string) $response->json('data.status'));

            if ($status === 'failed') {
                return ['ok' => false, 'id' => $response->json('data.id'), 'error' => 'kirimdev menandai pesan gagal', 'retry' => false];
            }

            return ['ok' => true, 'id' => $response->json('data.id'), 'error' => null, 'retry' => false];
        }

        $code = (string) ($response->json('error.code') ?? 'http_'.$response->status());
        $message = (string) ($response->json('error.message') ?? Str::limit($response->body(), 300));

        return [
            'ok' => false,
            'id' => null,
            'error' => $code.': '.$message,
            'retry' => ! in_array($code, self::FINAL_CODES, true) && ($response->status() === 429 || $response->serverError() || $code === 'idempotency_in_progress'),
        ];
    }

    /** Meta rejects template values with newlines, tabs or more than four spaces in a row. */
    private static function paramText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
