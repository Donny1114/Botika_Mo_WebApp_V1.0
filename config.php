<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

return [

    'app_name' => $_ENV['APP_NAME'] ?? '',

    'db' => [
        'host' => $_ENV['DB_HOST'],
        'name' => $_ENV['DB_NAME'],
        'user' => $_ENV['DB_USER'],
        'pass' => $_ENV['DB_PASS'],
    ],

    'mail' => [
        'user' => $_ENV['MAIL_USER'],
        'pass' => $_ENV['MAIL_PASS'],
    ],

    'store' => [
        'name'  => $_ENV['STORE_NAME'],
        'email' => $_ENV['STORE_EMAIL'],
        'phone' => $_ENV['STORE_PHONE'],
    ]

];
