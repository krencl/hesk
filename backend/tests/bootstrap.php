<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Schéma testovací databáze se vytváří jednou za celý běh (mezi testy se jen mažou data, viz TestDatabase).
\App\Tests\Support\TestDatabase::createSchema();
