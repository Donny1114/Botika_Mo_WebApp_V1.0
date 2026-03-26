<?php
session_start();

if (!isset($_SESSION['admin'])) {
    exit("Unauthorized");
}

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/..");
$dotenv->load();


/* =========================
   ENV VARIABLES
========================= */

$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USER'];
$dbPass = $_ENV['DB_PASS'];

$mysqldump = $_ENV['MYSQLDUMP_PATH'];


/* =========================
   BACKUP DIR
========================= */

$backupDir = __DIR__ . "/../backups/";

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}


/* =========================
   BACKUP
========================= */

if (isset($_POST['backup'])) {

    $date = date("Y-m-d_H-i-s");

    $file = $backupDir . "backup_" . $date . ".sql";


    $command =
        "\"$mysqldump\" --host=$dbHost --user=$dbUser --password=$dbPass $dbName > \"$file\"";


    exec($command, $output, $result);


    if ($result !== 0) {
        $msg = "Backup FAILED";
    } else {
        $msg = "Backup created";
    }


    /* =========================
       DELETE OLD (15 days)
    ========================= */

    $files = glob($backupDir . "*.sql");

    $now = time();

    foreach ($files as $f) {

        if ($now - filemtime($f) > (15 * 24 * 60 * 60)) {

            unlink($f);
        }
    }
}

include 'header.php';
?>

<div class="container mt-4">

<h3>Database Backup</h3>

<?php if (!empty($msg)): ?>
<div class="alert alert-info">
<?= $msg ?>
</div>
<?php endif; ?>

<form method="POST">

<button name="backup" class="btn btn-primary">
Backup Database
</button>

</form>

<hr>

<h5>Backup Files</h5>

<ul>

<?php

$files = glob($backupDir . "*.sql");

foreach ($files as $f) {

echo "<li>" . basename($f) . "</li>";

}

?>

</ul>

</div>

<?php include 'footer.php'; ?>