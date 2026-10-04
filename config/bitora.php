<?php

return [
    'version' => env('BITORA_VERSION', '0.1.1'),
    'image_repository' => env('BITORA_IMAGE_REPOSITORY', 'mrbohem7/bitora'),
    'dockerhub_tags_url' => 'https://hub.docker.com/v2/repositories/'
        .env('BITORA_IMAGE_REPOSITORY', 'mrbohem7/bitora')
        .'/tags?page_size=100',
    'update_status_path' => storage_path('app/bitora/update-status.json'),
];
