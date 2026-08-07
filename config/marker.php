<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marker PDF extraction settings
    |--------------------------------------------------------------------------
    |
    | Marker (marker-pdf) is used to extract clean reading-order text from
    | PDFs so that author names, affiliations and emails can be parsed more
    | reliably than from raw PDF text.
    |
    */

    'enabled' => env('MARKER_ENABLED', true),

    'python' => env('MARKER_PYTHON', 'C:\\pdfhub\\venv-marker\\Scripts\\python.exe'),

    'script' => env('MARKER_SCRIPT', base_path('scripts/marker_extract.py')),

    'mode' => env('MARKER_MODE', 'fast'),

    'disable_ocr' => env('MARKER_DISABLE_OCR', true),

    'timeout_sec' => (int) env('MARKER_TIMEOUT_SEC', 600),
];
