<?php
// pages/admin/email-config.php — Configuration SMTP
requireAdmin();
if (!hasRole('administrateur')) {
    flash('Seuls les administrateurs peuvent modifier la configuration email.', 'error');
    redirect('backoffice.php');
}
define('STAGIA_PAGE', 'Configuration Email');

// Traitement test envoi
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $to = filter_var(trim($_POST['to_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$to) {
        $testResult = ['ok' => false, 'msg' => 'Adresse email invalide.'];
    } else {
        $html = '<h2>Test StagIA</h2><p>Si vous recevez cet email, la configuration SMTP est correcte.</p><p>Envoyé le ' . date('d/m/Y à H:i') . '</p>';
        $ok = sendMail($to, 'Test StagIA — ' . date('d/m/Y H:i'), $html, 'Test StagIA');
        $testResult = ['ok' => $ok, 'msg' => $ok ? 'Email envoyé avec succès à ' . $to : 'Échec de l\'envoi. Vérifiez les paramètres SMTP.'];
    }
}

require __DIR__ . '/../../includes/header.php';

$smtpHost   = $_ENV['SMTP_HOST'] ?? '';
$smtpPort   = $_ENV['SMTP_PORT'] ?? '587';
$smtpUser   = $_ENV['SMTP_USER'] ?? '';
$smtpFrom   = $_ENV['SMTP_FROM_EMAIL'] ?? '';
$smtpName   = $_ENV['SMTP_FROM_NAME'] ?? 'StagIA';
$smtpSecure = $_ENV['SMTP_SECURE'] ?? 'tls';
?>
<div class="page-header">
  <h1>Configuration Email (SMTP)</h1>
</div>

<?php if ($testResult): ?>
<div class="alert alert-<?= $testResult['ok'] ? 'success' : 'danger' ?>" style="margin-bottom:20px">
  <?= $testResult['ok'] ? '✔' : '✗' ?> <?= h($testResult['msg']) ?>
</div>
<?php endif ?>

<div class="section-grid">
  <!-- Config actuelle -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">Configuration actuelle (.env)</h3></div>
    <div class="card-body">
      <table style="width:100%;font-size:.86rem">
        <tr>
          <td style="color:#9ca3af;width:40%">SMTP_HOST</td>
          <td><?= $smtpHost ? h($smtpHost) : '<span style="color:#e8001c">Non défini</span>' ?></td>
        </tr>
        <tr>
          <td style="color:#9ca3af">SMTP_PORT</td>
          <td><?= h($smtpPort) ?></td>
        </tr>
        <tr>
          <td style="color:#9ca3af">SMTP_SECURE</td>
          <td><?= h($smtpSecure) ?></td>
        </tr>
        <tr>
          <td style="color:#9ca3af">SMTP_USER</td>
          <td><?= $smtpUser ? h($smtpUser) : '<span style="color:#e8001c">Non défini</span>' ?></td>
        </tr>
        <tr>
          <td style="color:#9ca3af">SMTP_PASS</td>
          <td><?= !empty($_ENV['SMTP_PASS']) ? '●●●●●●●●' : '<span style="color:#e8001c">Non défini</span>' ?></td>
        </tr>
        <tr>
          <td style="color:#9ca3af">SMTP_FROM_EMAIL</td>
          <td><?= $smtpFrom ? h($smtpFrom) : '<span style="color:#e8001c">Non défini</span>' ?></td>
        </tr>
        <tr>
          <td style="color:#9ca3af">SMTP_FROM_NAME</td>
          <td><?= h($smtpName) ?></td>
        </tr>
      </table>

      <div class="alert alert-warning" style="margin-top:16px">
        Pour modifier la configuration, éditez le fichier <code>.env</code> à la racine du projet.
      </div>
    </div>
  </div>

  <!-- Test d'envoi -->
  <div>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Tester l'envoi</h3></div>
      <div class="card-body">
        <form method="POST">
          <div class="form-group">
            <label>Adresse email de test *</label>
            <input type="email" name="to_email" placeholder="exemple@gmail.com" required
                   value="<?= h($smtpUser) ?>">
          </div>
          <button type="submit" name="test_email" value="1" class="btn btn-red"
                  <?= empty($smtpHost) ? 'disabled title="SMTP non configuré"' : '' ?>>
            📧 Envoyer un email de test
          </button>
        </form>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-header"><h3 class="card-title">Guide de configuration Gmail</h3></div>
      <div class="card-body" style="font-size:.85rem;line-height:1.8">
        <ol style="padding-left:20px">
          <li>Activez la validation en 2 étapes sur votre compte Google</li>
          <li>Allez dans <strong>Compte Google → Sécurité → Mots de passe des applications</strong></li>
          <li>Créez un mot de passe pour "Mail" / "Autre application"</li>
          <li>Copiez les 16 caractères générés dans <code>SMTP_PASS</code></li>
          <li>Configurez <code>SMTP_HOST=smtp.gmail.com</code>, <code>SMTP_PORT=587</code>, <code>SMTP_SECURE=tls</code></li>
        </ol>
        <div class="alert alert-success" style="margin-top:12px">
          Les mots de passe d'application contournent la validation 2FA et sont recommandés pour les applications.
        </div>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-header"><h3 class="card-title">Templates emails disponibles</h3></div>
      <div class="card-body" style="font-size:.85rem">
        <div style="margin-bottom:8px">📧 <strong>Bienvenue</strong> — Envoyé lors de l'inscription du stagiaire</div>
        <div style="margin-bottom:8px">📧 <strong>Décision</strong> — Validation ou rejet de candidature</div>
        <div style="margin-bottom:8px">📧 <strong>Lettre de stage</strong> — Programmation du stage</div>
        <div>📧 <strong>Renouvellement</strong> — Décision sur un renouvellement</div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
