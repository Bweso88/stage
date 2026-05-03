<?php
/**
 * api/generer-lettre.php — Endpoint pour générer et télécharger la lettre .docx
 */
require_once __DIR__ . '/../config.php';
requireAdmin();

$stageId = (int) ($_GET['stage_id'] ?? 0);
if (!$stageId) {
    http_response_code(400);
    echo 'ID de stage manquant';
    exit;
}

$pdo = getPDO();

$stmt = $pdo->prepare('
    SELECT s.*,
           u.nom, u.prenom, u.email,
           d.libelle AS direction_libelle,
           c.reference AS ref_cand
    FROM stages s
    JOIN stagiaires st ON s.stagiaire_id = st.id
    JOIN utilisateurs u ON st.utilisateur_id = u.id
    JOIN directions d ON s.direction_id = d.id
    LEFT JOIN candidatures c ON s.candidature_id = c.id
    WHERE s.id = ?
');
$stmt->execute([$stageId]);
$stage = $stmt->fetch();

if (!$stage) {
    http_response_code(404);
    echo 'Stage introuvable';
    exit;
}

// Check Node.js availability
exec('which node 2>/dev/null', $nodeOut, $nodeRet);
if ($nodeRet !== 0) {
    http_response_code(503);
    echo 'Node.js non disponible sur ce serveur. Utilisez la version HTML imprimable.';
    exit;
}

$scriptPath = __DIR__ . '/generer-lettre.js';
if (!file_exists($scriptPath)) {
    http_response_code(500);
    echo 'Script de génération introuvable';
    exit;
}

$tmpFile = sys_get_temp_dir() . '/lettre_' . $stage['reference'] . '_' . time() . '.docx';

$mois = 0;
if ($stage['date_debut'] && $stage['date_fin']) {
    $d1  = new DateTime($stage['date_debut']);
    $d2  = new DateTime($stage['date_fin']);
    $diff = $d1->diff($d2);
    $mois = round($diff->days / 30, 1);
}

$cmd = sprintf(
    'node %s --prenom=%s --nom=%s --ref=%s --debut=%s --fin=%s --mois=%s --ref_cand=%s --direction=%s --out=%s 2>&1',
    escapeshellarg($scriptPath),
    escapeshellarg($stage['prenom']),
    escapeshellarg($stage['nom']),
    escapeshellarg($stage['reference']),
    escapeshellarg($stage['date_debut'] ?? ''),
    escapeshellarg($stage['date_fin'] ?? ''),
    escapeshellarg((string) $mois),
    escapeshellarg($stage['ref_cand'] ?? ''),
    escapeshellarg($stage['direction_libelle']),
    escapeshellarg($tmpFile)
);

exec($cmd, $output, $retCode);

if ($retCode !== 0 || !file_exists($tmpFile)) {
    http_response_code(500);
    echo 'Erreur génération DOCX : ' . implode("\n", $output);
    exit;
}

$filename = 'Lettre_stage_' . $stage['reference'] . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache');
readfile($tmpFile);
unlink($tmpFile);
exit;
