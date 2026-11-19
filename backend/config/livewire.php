<?php

/*
 * Only what differs from Livewire's defaults. Livewire merges this file with
 * its own config per top-level key, so temporary_file_upload is given whole.
 */
return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        // Class attachments (Produk online › Lampiran) go up to 20 MB; the default is 12 MB.
        // PHP's upload_max_filesize and post_max_size, and nginx's client_max_body_size, must allow it too.
        'rules' => ['required', 'file', 'max:20480'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],
];
