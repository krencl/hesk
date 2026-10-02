<?php

use App\Kernel;

/**
 * Hlavní rozcestník aplikace (front controller v kořeni projektu, vedle backend/ a frontend/):
 *
 *  - /api/*              → Symfony (backend/)
 *  - /static/*           → statika z web/static/ (jen vestavěný PHP server; v produkci ji servíruje web server)
 *  - cokoli jiného       → SPA (web/static/index.html), routing řeší React Router
 *
 * Dev: php -S 127.0.0.1:8000 -t web web/index.php
 */

$staticDir = __DIR__.'/static';
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if (!str_starts_with($path, '/api')) {
    if ('cli-server' === PHP_SAPI && str_starts_with($path, '/static/') && !str_contains($path, '..') && is_file(__DIR__.$path)) {
        return false;
    }

    header('Content-Type: text/html; charset=utf-8');
    readfile($staticDir.'/index.html');

    return true;
}

// Symfony Runtime bere skript z SCRIPT_FILENAME; vestavěný server by tu podstrčil statický soubor z docroot.
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

// Aplikace sama žije v backend/ (cache, logy a .env zůstávají tam).
$_SERVER['APP_RUNTIME_OPTIONS']['project_dir'] = dirname(__DIR__).'/backend';

require_once dirname(__DIR__).'/backend/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
