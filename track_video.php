<?php
/* Endpoint AJAX — mise à jour durée de visionnage */
session_start();
if (empty($_SESSION['viewer']['id'])) { http_response_code(403); exit; }
require_once __DIR__ . '/admin/db.php';

$logId   = (int)($_POST['log_id']  ?? 0);
$seconds = (int)($_POST['seconds'] ?? 0);

if ($logId > 0 && $seconds >= 0) {
    try {
        $pdo->prepare("UPDATE video_watch_log SET watch_seconds = ?
                       WHERE id = ? AND viewer_id = ?")
            ->execute([$seconds, $logId, $_SESSION['viewer']['id']]);
    } catch (Exception $e) {}
}
echo 'ok';
