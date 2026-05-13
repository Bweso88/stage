<?php
if (!hasRole('administrateur')) {
    echo '<div class="alert alert-danger">Accès réservé aux administrateurs.</div>';
    return;
}

$testResult = null;
if (isset($_POST['test_email'])) {
    $to   = trim($_POST['test_to'] ?? '');
    $sent = sendMail($to, 'Test StagIA', '<h2>Test email StagIA</h2><p>Si vous recevez cet email, la configuration SMTP est correcte.</p>', 'Test');
    $testResult = $sent ? ['ok' => true, 'msg' => 'Email de test envoyé avec succès à ' . $to] : ['ok' => false, 'msg' => 'Échec de l\'envoi. Vérifiez les logs et la configuration SMTP.'];
}

$config = [
    'SMTP_HOST'       => $_ENV['SMTP_HOST']       ?? '',
    'SMTP_PORT'       => $_ENV['SMTP_PORT']        ?? '',
    'SMTP_SECURE'     => $_ENV['SMTP_SECURE']      ?? '',
    'SMTP_USER'       => $_ENV['SMTP_USER']        ?? '',
    'SMTP_FROM_EMAIL' => $_ENV['SMTP_FROM_EMAIL']  ?? '',
    'SMTP_FROM_NAME'  => $_ENV['SMTP_FROM_NAME']   ?? '',
    'SMTP_PASS'       => !empty($_ENV['SMTP_PASS']) ? '••••••••' : '(non configuré)',
];

$apiKey     = $_ENV['ANTHROPIC_API_KEY'] ?? '';
$apiStatus  = (!empty($apiKey) && $apiKey !== 'sk-ant-api03-YOUR_KEY_HERE');
?>

<div class="page-header">
    <h1>Configuration email & API</h1>
</div>

<?php if ($testResult): ?>
<div class="alert <?= $testResult['ok'] ? 'alert-success' : 'alert-danger' ?>"><?= h($testResult['msg']) ?></div>
<?php endif; ?>

<div class="section-grid">
    <!-- Config SMTP -->
    <div class="card">
        <div class="card-header"><div class="card-title">✉️ Configuration SMTP</div></div>
        <div class="card-body">
            <table style="width:100%;font-size:13.5px">
                <tbody>
                    <?php foreach ($config as $key => $val): ?>
                    <tr>
                        <td style="padding:8px 0;color:var(--text-muted);font-weight:600;width:180px"><?= h($key) ?></td>
                        <td style="padding:8px 0">
                            <code style="background:var(--gray-bg);padding:2px 8px;border-radius:4px"><?= $val ? h($val) : '<span style="color:#dc2626">Non configuré</span>' ?></code>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr class="divider">

            <form method="POST">
            <?= csrfField() ?>
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Envoyer un email de test à *</label>
                        <input type="email" name="test_to" required placeholder="destinataire@email.com">
                    </div>
                </div>
                <button type="submit" name="test_email" value="1" class="btn btn-navy" style="margin-top:12px">▶ Envoyer le test</button>
            </form>
        </div>
    </div>

    <!-- Config API -->
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">🤖 API Anthropic (IA)</div></div>
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
                    <div style="width:12px;height:12px;border-radius:50%;background:<?= $apiStatus ? '#16a34a' : '#dc2626' ?>"></div>
                    <span class="fw-600"><?= $apiStatus ? 'Clé API configurée' : 'Clé API non configurée' ?></span>
                </div>
                <?php if ($apiStatus): ?>
                <div class="alert alert-success">L'analyse IA des CV est activée.</div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <strong>Pour activer l'IA :</strong><br>
                    1. Obtenez une clé sur console.anthropic.com<br>
                    2. Ajoutez <code>ANTHROPIC_API_KEY=sk-ant-...</code> dans le fichier <code>.env</code>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">🔧 Diagnostic système</div></div>
            <div class="card-body">
                <table style="width:100%;font-size:13px">
                    <?php
                    $checks = [
                        'PHP ' . PHP_VERSION => version_compare(PHP_VERSION, '8.0', '>='),
                        'PDO MySQL'          => extension_loaded('pdo_mysql'),
                        'cURL'               => extension_loaded('curl'),
                        'JSON'               => extension_loaded('json'),
                        'mbstring'           => extension_loaded('mbstring'),
                        'Connexion BDD'      => true, // Nous sommes déjà connectés
                        'API Anthropic'      => $apiStatus,
                    ];
                    foreach ($checks as $label => $ok):
                    ?>
                    <tr>
                        <td style="padding:6px 0"><?= h($label) ?></td>
                        <td style="padding:6px 0">
                            <span class="badge <?= $ok ? 'badge-success' : 'badge-danger' ?>"><?= $ok ? '✓ OK' : '✗ KO' ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <div style="margin-top:16px">
                    <a href="<?= BASE ?>/api/test.php" target="_blank" class="btn btn-ghost btn-sm">📋 Rapport JSON complet</a>
                </div>
            </div>
        </div>
    </div>
</div>
