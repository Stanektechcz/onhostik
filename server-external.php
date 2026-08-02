<?php

/**
 * Local dev router for `php -S` that runs the app against the EXTERNAL database
 * defined in .env.production (s2.onhost.cz).
 *
 * Kept separate from server-preview.php (SQLite) so the choice of backing
 * database is explicit in the command you run, never an accident of shell env.
 */
putenv('APP_ENV=production');
$_ENV['APP_ENV']    = 'production';
$_SERVER['APP_ENV'] = 'production';

require __DIR__ . '/server.php';
