<?php

return [
    'show_warnings' => false,
    'public_path' => public_path(),
    'convert_entities' => true,
    'options' => [
        'font_dir' => storage_path('fonts'),
        'font_cache' => storage_path('fonts'),
        'temp_dir' => storage_path('app/private/pdf-temp'),
        'chroot' => base_path(),
        'default_font' => 'DejaVu Sans',
        'default_paper_size' => 'a4',
        'is_remote_enabled' => false,
        'is_php_enabled' => false,
        'is_javascript_enabled' => false,
        'enable_remote' => false,
        'enable_php' => false,
        'enable_javascript' => false,
    ],
];
