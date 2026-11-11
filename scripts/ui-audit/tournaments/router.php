<?php

/*
 * Router for the harness's `php -S`: serves /storage/* straight from storage/app/public (public/storage is a
 * machine-specific symlink that may point elsewhere), and hands everything else to Laravel's own dev router.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($path, '/storage/')) {
    $file = realpath(__DIR__.'/../../../storage/app/public/'.substr($path, 9));
    $root = realpath(__DIR__.'/../../../storage/app/public');
    if ($file !== false && $root !== false && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)) {
        header('Content-Type: '.(mime_content_type($file) ?: 'application/octet-stream'));
        readfile($file);

        return true;
    }
}

// Static files under public/ must be served by PHP itself, which needs the router's own return value (false).
return require __DIR__.'/../../../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
