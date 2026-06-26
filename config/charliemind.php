<?php

return [
    'capture_api_token' => env('CAPTURE_API_TOKEN'),
    'disk' => env('CHARLIEMIND_DISK', env('FILESYSTEM_DISK', 'local')),
    'root' => trim(env('CHARLIEMIND_STORAGE_ROOT', 'charliemind'), '/'),
];
