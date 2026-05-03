<?php
// pages/admin/rapports.php
requireAdmin();
define('STAGIA_PAGE', 'Rapports & Statistiques');
$pdo = getPDO();

// Stats générales
$stats = [];
$stats['total_stagiaires']   = (int) $pdo->query('SELECT COUNT(*) FROM stagiaires')->fetchColumn();
$stats['total_candidatures'] = (int) $pdo->query('SELECT COUNT(*) FROM candidatures')->fetchColumn();
$stats['candidatures_valides'] = (int) $pdo->query("SELECT COUNT(*) FROM candidatures WHERE statut_global='validee'")->fetchColumn();
$stats['candidatures_rejetes'] = (int) $pdo->query("SELECT COUNT(*) FROM candidatures WHERE statut_global IN ('rejetee','niv1_rejete')")->fetchColumn();
$stats['stages_actifs']      = (int) $pdo->query("SELECT COUNT(*) FROM stages WHERE statut IN ('en_cours','renouvele','preparation')")->fetchColumn();
$stats['stages_termines']    = (int) $pdo->query("SELECT COUNT(*) FROM stages WHERE statut='termine'")->fetchColumn();
$stats['offres_ouvertes']    = (int) $pdo->query("SELECT COUNT(*) FROM offres_stage WHERE statut='ouverte'")->fetchColumn();
$stats['renouvellements']    = (int) $pdo->query("SELECT COUNT(*) FROM renouvellements_stage WHERE statut='valide'")->fetchColumn();

// Candidatures par direction
$byDir = $pdo->query('
    SELECT d.libelle, COUNT(c.id) AS total,
           SUM(c.statut_global = \'validee\') AS valides,
           SUM(c.statut_global IN (\'rejetee\',\'niv1_rejete\')) AS rejetes
    FROM candidatures c
    LEFT JOIN directions d ON c.direction_id = d.id
    GROUP BY c.direction_id, d.libelle
    ORDER BY total DESC
')->fetchAll();

// Stages par mois (12 derniers mois)
$byMonth = $pdo->query("
    SELECT DATE_FORMAT(date_debut, '%Y-%m') AS mois,
           DATE_FORMAT(date_debut, '%m/%Y') AS mois_label,
           COUNT(*) AS total
    FROM stages
    WHERE date_debut >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date_debut, '%Y-%m')
    ORDER BY mois
")->fetchAll();

// Niveau d'étude distribution
$byNiveau = $pdo->query("
    SELECT niveau_etude, COUNT(*) AS total
    FROM stagiaires
    WHERE niveau_etude IS NOT NULL
    GROUP BY niveau_etude
    ORDER BY total DESC
")->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <h1>Rapports &amp; Statistiques</h1>
  <div style="display:flex;gap:8px">
    <a href="backoffice.php?action=export_rapport&type=candidatures" class="btn btn-ghost">⬇ Export Candidatures CSV</a>
    <a href="backoffice.php?action=export_rapport&type=stages" class="btn btn-ghost">⬇ Export Stages CSV</a>
  </div>
</div>

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon" style="background:#e8f0fe;color:#1b2a6b">👤</div>
    <div class="stat-value"><?= $stats['total_stagiaires'] ?></div>
    <div class="stat-label">Stagiaires inscrits</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fef3c7;color:#d97706">📋</div>
    <div class="stat-value"><?= $stats['total_candidatures'] ?></div>
    <div class="stat-label">Candidatures totales</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#d1fae5;color:#059669">✔</div>
    <div class="stat-value"><?= $stats['stages_actifs'] ?></div>
    <div class="stat-label">Stages actifs</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fee2e2;color:#dc2626">📁</div>
    <div class="stat-value"><?= $stats['stages_termines'] ?></div>
    <div class="stat-label">Stages terminés</div>
  </div>
</div>

<div class="section-grid">
  <!-- Candidatures par direction -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">Candidatures par direction</h3></div>
    <div class="card-body">
      <?php if (empty($byDir)): ?>
      <p style="color:#9ca3af;text-align:center">Aucune donnée.</p>
      <?php endif ?>
      <?php foreach ($byDir as $row):
        $maxVal = max(1, ...array_column($byDir, 'total'));
        $pct = round(($row['total'] / $maxVal) * 100);
      ?>
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:4px">
          <span><?= h($row['libelle'] ?? 'Non spécifiée') ?></span>
          <span style="color:#6b7280"><?= $row['total'] ?> total — <span style="color:#10b981"><?= $row['valides'] ?> validées</span></span>
        </div>
        <div class="progress-wrap">
          <div class="progress-bar" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>

  <!-- Niveau d'étude -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">Répartition par niveau d'étude</h3></div>
    <div class="card-body">
      <?php
      $totalStag = max(1, $stats['total_stagiaires']);
      foreach ($byNiveau as $row):
        $pct = round(($row['total'] / $totalStag) * 100);
      ?>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div style="width:60px;font-size:.8rem;font-weight:600;color:#1b2a6b"><?= h($row['niveau_etude']) ?></div>
        <div class="progress-wrap" style="flex:1">
          <div class="progress-bar" style="width:<?= $pct ?>%;background:var(--navy)"></div>
        </div>
        <div style="width:40px;text-align:right;font-size:.82rem;color:#6b7280"><?= $row['total'] ?></div>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</div>

<!-- Stages par mois -->
<?php if (!empty($byMonth)): ?>
<div class="card" style="margin-top:20px">
  <div class="card-header"><h3 class="card-title">Stages démarrés (12 derniers mois)</h3></div>
  <div class="card-body">
    <?php $maxStages = max(1, ...array_column($byMonth, 'total')); ?>
    <div style="display:flex;align-items:flex-end;gap:8px;height:120px">
      <?php foreach ($byMonth as $m):
        $h = round(($m['total'] / $maxStages) * 100);
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
        <div style="font-size:.7rem;color:#6b7280"><?= $m['total'] ?></div>
        <div style="width:100%;background:var(--navy);border-radius:3px 3px 0 0;height:<?= $h ?>px"></div>
        <div style="font-size:.65rem;color:#9ca3af;transform:rotate(-45deg);transform-origin:top left;white-space:nowrap"><?= $m['mois_label'] ?></div>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</div>
<?php endif ?>

<!-- Tableau de synthèse -->
<div class="card" style="margin-top:20px">
  <div class="card-header"><h3 class="card-title">Synthèse globale</h3></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Indicateur</th><th>Valeur</th><th>Détail</th></tr>
      </thead>
      <tbody>
        <tr><td>Taux de validation</td><td><strong><?= $stats['total_candidatures'] > 0 ? round(($stats['candidatures_valides'] / $stats['total_candidatures']) * 100) : 0 ?>%</strong></td><td><?= $stats['candidatures_valides'] ?> validées / <?= $stats['total_candidatures'] ?> soumises</td></tr>
        <tr><td>Taux de rejet</td><td><strong><?= $stats['total_candidatures'] > 0 ? round(($stats['candidatures_rejetes'] / $stats['total_candidatures']) * 100) : 0 ?>%</strong></td><td><?= $stats['candidatures_rejetes'] ?> rejetées</td></tr>
        <tr><td>Offres ouvertes</td><td><strong><?= $stats['offres_ouvertes'] ?></strong></td><td>Actuellement disponibles</td></tr>
        <tr><td>Renouvellements accordés</td><td><strong><?= $stats['renouvellements'] ?></strong></td><td>Total historique</td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
