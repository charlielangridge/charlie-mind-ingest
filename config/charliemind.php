<?php

return [
    'capture_api_token' => env('CAPTURE_API_TOKEN'),
    'disk' => env('CHARLIEMIND_DISK', env('FILESYSTEM_DISK', 'local')),
    'root' => trim(env('CHARLIEMIND_STORAGE_ROOT', 'charliemind'), '/'),
    'processor_enabled' => env('CAPTURE_PROCESSOR_ENABLED', true),
    'processor_dry_run' => env('CAPTURE_PROCESSOR_DRY_RUN', false),
    'processor_max_per_run' => env('CAPTURE_PROCESSOR_MAX_PER_RUN', 20),
    'processor_archive_raw' => env('CAPTURE_PROCESSOR_ARCHIVE_RAW', false),
    'openai_text_model' => env('OPENAI_TEXT_MODEL', 'gpt-4.1-mini'),
    'openai_transcription_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
];
