<?php

return [
    'teacher_key' => env('TEACHER_KEY', ''),
    'voting_lifetime' => 300,
    'base_session_lifetime_minutes' => (int) env('SESSION_LIFETIME', 5),
    'teacher_session_lifetime_minutes' => 30,
    'backup_directory' => env('BACKUP_DIRECTORY', storage_path('app/private/backups')),
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
];
