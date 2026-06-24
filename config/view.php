<?php

return [

    'paths' => [
        resource_path('views'),
    ],

    // realpath() returns false if the directory doesn't exist at boot time,
    // which breaks containerized deployments. Use storage_path() directly.
    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),

];
