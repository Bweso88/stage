<?php
/**
 * api/test.php — Diagnostic de configuration StagIA
 */
require_once __DIR__ . '/../config.php';

$checks = [];

// PHP version
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$checks[] = ['label' => 'PHP >= 8.0', 'ok' => $phpOk, 'detail' => PHP_VERSION];

// Extensions
foreach (['pdo', 'pdo_mysql', 'curl', 'mbstring', 'openssl'] as $ext) {
    $checks[] = ['label' => "Extension $ext", 'ok' => extension_loaded($ext), 'detail' => extension_loaded($ext) ? 'Chargée' : 'Manquante'];
}

// .env
$envOk = file_exists(__DIR__ . '/../.env');
$checks[] = ['label' => 'Fichier .env', 'ok' => $envOk, 'detail' => $envOk ? 'Trouvé' : 'Absent (copiez .env.example en .env)'];

// Connexion BDD
$dbOk = false;
$dbDetail = '';
try {
    $pdo = getPDO();
    $pdo->query('SELECT 1');
    $dbOk = true;
    $dbDetail = 'Connexion réussie à ' . ($_ENV['DB_NAME'] ?? 'stagia');
} catch (Throwable $e) {
    $dbDetail = $e->getMessage();
}
$checks[] = ['label' => 'Connexion MySQL', 'ok' => $dbOk, 'detail' => $dbDetail];

// Clé Anthropic
$anthropicKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
$anthropicOk  = !empty($anthropicKey) && $anthropicKey !== 'sk-ant-api03-YOUR_KEY_HERE' && str_starts_with($anthropicKey, 'sk-ant-');
$checks[] = ['label' => 'Clé API Anthropic', 'ok' => $anthropicOk, 'detail' => $anthropicOk ? 'Configurée (' . substr($anthropicKey, 0, 20) . '...)' : 'Non configurée'];

// SMTP
$smtpOk = !empty($_ENV['SMTP_HOST']) && !empty($_ENV['SMTP_USER']);
$checks[] = ['label' => 'Config SMTP', 'ok' => $smtpOk, 'detail' => $smtpOk ? ($_ENV['SMTP_HOST'] . ':' . ($_ENV['SMTP_PORT'] ?? '587')) : 'Non configurée'];

// Node.js
$nodeOk = false;
$nodeDetail = '';
exec('node --version 2>&1', $nodeOut, $nodeRet);
if ($nodeRet === 0) {
    $nodeOk = true;
    $nodeDetail = $nodeOut[0] ?? 'Disponible';
} else {
    $nodeDetail = 'Node.js non trouvé (optionnel, pour génération DOCX)';
}
$checks[] = ['label' => 'Node.js', 'ok' => $nodeOk, 'detail' => $nodeDetail];

// Dossier uploads
$uploadsDir = __DIR__ . '/../uploads';
$uploadsOk  = is_dir($uploadsDir) && is_writable($uploadsDir);
$checks[] = ['label' => 'Dossier uploads', 'ok' => $uploadsOk, 'detail' => $uploadsOk ? 'Accessible en écriture' : 'Absent ou non accessible'];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>StagIA — Diagnostic</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Montserrat',sans-serif;background:#f4f5f7;margin:0;padding:30px;color:#2c3e50}
h1{color:#1b2a6b;margin-bottom:4px}
p.sub{color:#6b7280;margin-bottom:24px}
.card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.07);overflow:hidden;max-width:700px}
.row{display:flex;align-items:center;padding:14px 20px;border-bottom:1px solid #e0e3ed;gap:12px}
.row:last-child{border:none}
.icon{font-size:18px;width:24px;text-align:center}
.label{font-weight:600;flex:1}
.detail{color:#6b7280;font-size:.85rem}
.ok{color:#10b981}
.fail{color:#e8001c}
.summary{max-width:700px;margin-top:16px;padding:14px 20px;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.07);display:flex;gap:20px;align-items:center}
.summary strong{font-size:1.1rem}
</style>
</head>
<body>
<h1>StagIA — Diagnostic système</h1>
<p class="sub">Vérification de la configuration au <?= date('d/m/Y H:i') ?></p>

<div class="card">
<?php foreach ($checks as $c): ?>
<div class="row">
    <span class="icon <?= $c['ok'] ? 'ok' : 'fail' ?>"><?= $c['ok'] ? '✓' : '✗' ?></span>
    <span class="label"><?= h($c['label']) ?></span>
    <span class="detail"><?= h($c['detail']) ?></span>
</div>
<?php endforeach ?>
</div>

<?php
$totalOk   = count(array_filter($checks, fn($c) => $c['ok']));
$totalFail = count($checks) - $totalOk;
?>
<div class="summary">
    <strong class="ok">✓ <?= $totalOk ?> OK</strong>
    <?php if ($totalFail > 0): ?>
    <strong class="fail">✗ <?= $totalFail ?> problème(s)</strong>
    <?php else: ?>
    <span style="color:#10b981">Tout est opérationnel !</span>
    <?php endif ?>
</div>
</body>
</html>
