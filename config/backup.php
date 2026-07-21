<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Offsite database backup (audit INFRA #7)
    |--------------------------------------------------------------------------
    |
    | The `db:backup` command dumps the database, gzips it and uploads it here.
    | `disk` must be an offsite filesystem (the `backup-s3` disk in
    | config/filesystems.php) — a backup on the same server it protects is not
    | a backup. `keep_days` is the rolling retention window.
    |
    */
    'disk'      => env('BACKUP_DISK', 'backup-s3'),
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

];
