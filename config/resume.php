<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resume Version File Path
    |--------------------------------------------------------------------------
    |
    | The path to the version.json file that tracks the current resume version.
    | Version format: YYYY.X.X (e.g., 2026.1.0)
    |
    */

    'version_file' => resource_path('resume/version.json'),

    /*
    |--------------------------------------------------------------------------
    | Resume Template Path
    |--------------------------------------------------------------------------
    |
    | The path to the DOCX template file used for generating downloadable
    | resume documents. This template should contain docxtemplater placeholders.
    |
    */

    'template' => resource_path('resume/2026 resume template.docx'),

    /*
    |--------------------------------------------------------------------------
    | Saved Documents Path
    |--------------------------------------------------------------------------
    |
    | The directory where generated resume documents will be stored when
    | users download their resumes. This creates a record of downloads.
    |
    */

    'saved_documents' => storage_path('app/resumes'),

    /*
    |--------------------------------------------------------------------------
    | Download Expiration
    |--------------------------------------------------------------------------
    |
    | When a resume viewer tries to download, how long do they have before the
    | authorization expires?
    */

    'download_expiration' => env('APP_DEBUG') ? 60 : 5,

];
