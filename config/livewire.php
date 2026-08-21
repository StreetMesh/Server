<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary file uploads
    |--------------------------------------------------------------------------
    |
    | Where a file goes between somebody choosing it and the component doing
    | something with it. Livewire's own default is "wherever the application
    | keeps everything else", and that is the wrong answer here for a reason
    | worth writing down.
    |
    | The moment `FILESYSTEM_DISK` points at a bucket, Livewire notices the
    | driver is `s3` and changes strategy: it hands the browser a pre-signed URL
    | and the file is uploaded straight from the browser to the bucket, never
    | passing through this server. That is a good design for scale and it needs
    | the bucket to allow cross-origin PUTs from this application's own address.
    | A private bucket does not, so the upload fails in the browser, the
    | component's property is never set, and the only thing anybody sees is a
    | form insisting a file is required while the file's name sits next to the
    | button. Nothing is logged, because nothing reached this server.
    |
    | So temporary uploads stay local. They are temporary by definition —
    | cleaned up within a day, and consumed seconds after they are written — so
    | the durability that made blobs move to a bucket does not apply to them.
    |
    | The one thing this costs: the request that finishes an upload has to reach
    | the same instance that started it. On more than one instance, either route
    | sessions stickily or set this to a shared disk and configure CORS on the
    | bucket to allow PUT from this application's origin.
    |
    */

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', 'local'),
        'rules' => null,
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
