<?php

return [
    // From the kirimdev dashboard: API key and the WhatsApp number's phone_number_id.
    'api_key' => env('KIRIMDEV_API_KEY'),
    'phone_number_id' => env('KIRIMDEV_PHONE_NUMBER_ID'),
    'base_url' => rtrim((string) env('KIRIMDEV_BASE_URL', 'https://api.kirimdev.com/v1'), '/'),

    /*
     * The buyer has never chatted with us, so WhatsApp only allows a template
     * Meta has approved. Body variables, in order:
     * {{1}} first name, {{2}} class title, {{3}} login link, {{4}} link lifetime, {{5}} email.
     */
    'access_template' => env('KIRIMDEV_ACCESS_TEMPLATE', 'academy_akses_kelas'),
    'template_language' => env('KIRIMDEV_TEMPLATE_LANGUAGE', 'id'),

    /*
     * Send the access message on its own after payment. Off until the template
     * is approved and the owner switches it on; until then access goes by email,
     * and the panel's manual buttons (kirimdev or wa.me) work either way.
     */
    'access_auto' => (bool) env('KIRIMDEV_ACCESS_AUTO', false),

    // Attempts per message before the panel shows it as failed.
    'max_attempts' => 3,
];
