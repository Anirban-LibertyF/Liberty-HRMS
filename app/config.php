<?php
// Central configuration. Override DB settings with TF_DB_* environment variables if needed.
return [
    'db' => [
        'host' => getenv('TF_DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('TF_DB_PORT') ?: 3306),
        'name' => getenv('TF_DB_NAME') ?: 'trackflow',
        'user' => getenv('TF_DB_USER') ?: 'trackflow',
        'pass' => getenv('TF_DB_PASS') ?: 'trackflow_dev_pw',
    ],
    'timezone'    => 'Asia/Kolkata',
    'weekend'     => [],                   // ISO weekday numbers treated as off (none: every day is a working day)
    'upload_dir'  => dirname(__DIR__) . '/storage/uploads',
    'max_upload'  => 10 * 1024 * 1024,
    'allowed_ext' => ['pdf', 'png', 'jpg', 'jpeg'],
    'weekoff_per_month' => 4,               // week offs credited each month (max 1 per ISO week); unused ones carry over
    'weekoff_start'   => '2026-10',         // first month credits start accruing (earlier months are not back-filled)
    'session_hours_remember' => 12,
    'session_idle_minutes'   => 60,
];
