<?php

return [
    'local_development' => filter_var(
        (string) env(
            'ADMIN_LOCAL_DEVELOPMENT',
            env('APP_ENV', 'production') === 'local' ? 'true' : 'false'
        ),
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE
    ) ?? false,
    'use_mock_data' => filter_var(
        (string) env('ADMIN_USE_MOCK_DATA', 'false'),
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE
    ) ?? false,
    'token_ttl_hours' => (int) env('ADMIN_TOKEN_TTL_HOURS', 12),
    'log_auth_failures' => filter_var(
        (string) env('ADMIN_LOG_AUTH_FAILURES', 'true'),
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE
    ) ?? true,
    'vehicle_image_url_prefix' => (string) env('ADMIN_VEHICLE_IMAGE_URL_PREFIX', '/gallery/'),
    'vehicle_gallery_path' => (string) env('ADMIN_VEHICLE_GALLERY_PATH', public_path('gallery')),
];
