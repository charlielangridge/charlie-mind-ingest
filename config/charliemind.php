<?php

return [
    'capture_api_token' => env('CAPTURE_API_TOKEN'),
    'disk' => env('CHARLIEMIND_DISK', env('FILESYSTEM_DISK', 'local')),
    'root' => trim(env('CHARLIEMIND_STORAGE_ROOT', 'charliemind'), '/'),
    'processor_enabled' => env('CAPTURE_PROCESSOR_ENABLED', true),
    'processor_dry_run' => env('CAPTURE_PROCESSOR_DRY_RUN', false),
    'processor_max_per_run' => env('CAPTURE_PROCESSOR_MAX_PER_RUN', 20),
    'processor_archive_raw' => env('CAPTURE_PROCESSOR_ARCHIVE_RAW', false),
    'processor_review_mode' => env('CAPTURE_PROCESSOR_REVIEW_MODE', 'confidence'),
    'processor_review_confidence_threshold' => env('CAPTURE_PROCESSOR_REVIEW_CONFIDENCE_THRESHOLD', 'low'),
    'processor_medium_review_tag' => filter_var(env('CAPTURE_PROCESSOR_MEDIUM_REVIEW_TAG', true), FILTER_VALIDATE_BOOLEAN),
    'processor_review_folder' => trim(env('CAPTURE_PROCESSOR_REVIEW_FOLDER', 'Review'), '/'),
    'processor_review_index' => trim(env('CAPTURE_PROCESSOR_REVIEW_INDEX', 'Review/_Review Index.md'), '/'),
    'openai_text_model' => env('OPENAI_TEXT_MODEL', 'gpt-4.1-mini'),
    'openai_transcription_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
];
