<?php

return [
    'teacher_key' => env('TEACHER_KEY', ''),
    'voting_lifetime' => 300,
    'backup_directory' => env('BACKUP_DIRECTORY', storage_path('app/private/backups')),
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
];
