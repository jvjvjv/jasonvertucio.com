<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resume Data Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the driver used for reading and writing resume data.
    | Supported: "json", "database"
    |
    */

    'driver' => env('RESUME_DRIVER', 'database'),

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
    | Shared Document Template Path
    |--------------------------------------------------------------------------
    |
    | The one DOCX template behind every document the site generates — the
    | main resume, targeted resumes, and cover letters. It carries only the
    | header placeholders ({name}, {title}, {email}, {phone}, {url}) and no
    | body; each document type composes its own body onto it at generation
    | time. Editing this file in Word restyles all three documents at once.
    |
    */

    'template' => resource_path('resume/2026 template.docx'),

    /*
    |--------------------------------------------------------------------------
    | Cover Letter Signature Image
    |--------------------------------------------------------------------------
    |
    | The source image SignatureImageService recolors and embeds into cover
    | letters. Like the template path, this is the single source of truth —
    | no service may hardcode its own.
    |
    */

    'signature' => resource_path('resume/assets/signature.png'),

    /*
    |--------------------------------------------------------------------------
    | Embeddable Font Directory
    |--------------------------------------------------------------------------
    |
    | Static TTF faces that `resume:embed-fonts` writes into the template's
    | font parts. Keeping the faces in the repo makes embedding reproducible
    | instead of dependent on whatever is installed on whoever last opened the
    | template in Word — a variable-font install silently collapses every
    | weight onto one face and renders the whole document Thin.
    |
    */

    'fonts' => resource_path('resume/assets/fonts'),

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

    /*
    |--------------------------------------------------------------------------
    | Ghosted Threshold
    |--------------------------------------------------------------------------
    |
    | Number of days an application can sit in the "applied" state with no
    | further status update before it is treated as "ghosted". This is a
    | computed display status; it is never stored on the record.
    |
    */

    'ghosted_after_days' => (int) env('RESUME_GHOSTED_AFTER_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | AI Persona Edit Batch Window
    |--------------------------------------------------------------------------
    |
    | How many hours of inactivity before the next AI persona resume edit
    | starts a new draft revision instead of continuing the latest pending
    | one for the same base resume version.
    |
    */

    'ai_edit_batch_window_hours' => (int) env('RESUME_AI_EDIT_BATCH_WINDOW_HOURS', 12),

];
