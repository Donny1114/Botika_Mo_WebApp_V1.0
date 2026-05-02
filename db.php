
<?php

$config = require __DIR__ . '/config.php';

$db = $config['db'];

$conn = mysqli_connect(
    $db['host'],
    $db['user'],
    $db['pass'],
    $db['name']
);

if (!$conn) {
    die("DB Error");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
