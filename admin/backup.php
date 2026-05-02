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
   HANDLE DOWNLOAD BACKUP
========================= */
if (isset($_POST['backup_download'])) {

    $date = date("Y-m-d_H-i-s");
    $filename = "backup_" . $date . ".sql";

    $tempFile = sys_get_temp_dir() . "/" . $filename;

    $command = "\"$mysqldump\" --host=$dbHost --user=$dbUser --password=\"$dbPass\" $dbName > \"$tempFile\"";

    exec($command, $output, $result);

    if ($result !== 0 || !file_exists($tempFile)) {
        $msg = "Backup FAILED";
    } else {

        // 🔥 FORCE DOWNLOAD
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));

        readfile($tempFile);
        unlink($tempFile); // cleanup
        exit;
    }
}

/* =========================
   HANDLE SAVE TO SERVER
========================= */
if (isset($_POST['backup_server'])) {

    $date = date("Y-m-d_H-i-s");
    $file = $backupDir . "backup_" . $date . ".sql";

    $command = "\"$mysqldump\" --host=$dbHost --user=$dbUser --password=\"$dbPass\" $dbName > \"$file\"";

    exec($command, $output, $result);

    if ($result !== 0) {
        $msg = "Backup FAILED";
    } else {
        $msg = "Backup saved to server";
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

<form method="POST" class="mb-3">

    <button name="backup_download" class="btn btn-success">
        ⬇️ Download Backup
    </button>

    <button name="backup_server" class="btn btn-primary">
        💾 Save to Server
    </button>

</form>

<hr>

<h5>Backup Files (Server)</h5>

<ul>

<?php

$files = glob($backupDir . "*.sql");

if (!$files) {
    echo "<li>No backups yet</li>";
} else {
    foreach ($files as $f) {
        echo "<li>" . basename($f) . "</li>";
    }
}

?>

</ul>

</div>

<?php include 'footer.php'; ?>