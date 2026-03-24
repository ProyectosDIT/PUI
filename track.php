<?php
// /var/www/html/dit/tools/pui/track.php
require_once __DIR__ . '/config/db.php';

if (isset($_GET['c'])) {
    $code = $_GET['c'];
    $stmt = $pdo_hub->prepare("UPDATE logs_correos SET abierto = 1, fecha_apertura = NOW() WHERE tracking_code = ? AND abierto = 0");
    $stmt->execute([$code]);
}

header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
exit;
?>