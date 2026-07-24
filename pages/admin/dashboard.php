<?php
/**
 * Stage - Dashboard admin
 */
define('STAGIA_PAGE', 'Tableau de bord');
require_once __DIR__ . '/../../includes/header.php';

$pdo = getPDO();

// Stats
$stats = [
    'candidatures' => $pdo->query('SELECT COUNT(*) FROM candidatures')->fetchColumn(),
    'stages_actifs' => $pdo->query("SELECT COUNT(*) FROM stages WHERE statut IN ('en_cours','renouvele','preparation')")->fetchColumn(),
    'renouvellements' => $pdo->query("SELECT COUNT(*) FROM renouvellements_stage WHERE statut = 'en_attente'")->fetchColumn(),
    'offres_ouvertes' => $pdo->query("SELECT COUNT(*) FROM offres_stage WHERE statut = 'ouverte'")->fetchColumn(),
    'stagiaires' => $pdo->query('SELECT COUNT(*) FROM stagiaires')->fetchColumn(),
    'a_valider' => $pdo->query("SELECT COUNT(*) FROM candidatures WHERE statut_global IN ('soumise','niv1_valide')")->fetchColumn(),
];

// Recent candidatures
$recent_cands = $pdo->query('SELECT c.*, u.nom, u.prenom, o.titre as offre_titre FROM candidatures c JOIN stagiaires s ON s.id = c.stagiaire_id JOIN utilisateurs u ON u.id = s.utilisateur_id LEFT JOIN offres_stage o ON o.id = c.offre_id ORDER BY c.date_candidature DESC LIMIT 5')->fetchAll();

// Recent stages
$recent_stages = $pdo->query("SELECT sg.*, u.nom, u.prenom, d.libelle as direction_libelle FROM stages sg JOIN stagiaires s ON s.id = sg.stagiaire_id JOIN utilisateurs u ON u.id = s.utilisateur_id LEFT JOIN directions d ON d.id = sg.direction_id WHERE sg.statut IN ('en_cours','renouvele') ORDER BY sg.created_at DESC LIMIT 5")->fetchAll();

// Pending renewals
$pending_renouv = $pdo->query("SELECT r.*, sg.reference as stage_ref, u.nom, u.prenom FROM renouvellements_stage r JOIN stages sg ON sg.id = r.stage_id JOIN stagiaires st ON st.id = sg.stagiaire_id JOIN utilisateurs u ON u.id = st.utilisateur_id WHERE r.statut = 'en_attente' ORDER BY r.created_at ASC LIMIT 5")->fetchAll();
?>

<div class="page-header">
  <h1>Tableau de bord</h1>
  <p>Vue d'ensemble de la plateforme Stage &mdash; <?= date('d/m/Y') ?></p>
</div>

<!-- Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon navy">📋</div>
    <div><div class="stat-val"><?= $stats['candidatures'] ?></div><div class="stat-label">Candidatures totales</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">🎓</div>
    <div><div class="stat-val"><?= $stats['stages_actifs'] ?></div><div class="stat-label">Stages actifs</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange">🔄</div>
    <div><div class="stat-val"><?= $stats['renouvellements'] ?></div><div class="stat-label">Renouvellements en attente</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue">📢</div>
    <div><div class="stat-val"><?= $stats['offres_ouvertes'] ?></div><div class="stat-label">Offres ouvertes</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon navy">👥</div>
    <div><div class="stat-val"><?= $stats['stagiaires'] ?></div><div class="stat-label">Stagiaires inscrits</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red">⚡</div>
    <div><div class="stat-val"><?= $stats['a_valider'] ?></div><div class="stat-label">Candidatures &agrave; traiter</div></div>
  </div>
</div>

<div class="grid-2">
  <!-- Recent candidatures -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📋 Candidatures r&eacute;centes</span>
      <a href="backoffice.php?page=candidatures" class="btn btn-secondary btn-sm">Voir tout</a>
    </div>
    <?php if (empty($recent_cands)): ?>
    <div class="empty-state"><div class="empty-icon">📋</div><p>Aucune candidature.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>R&eacute;f&eacute;rence</th><th>Stagiaire</th><th>Statut</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recent_cands as $c): ?>
          <tr>
            <td><a href="backoffice.php?page=candidature_detail&id=<?= $c['id'] ?>" style="color:var(--navy);font-weight:700"><?= h($c['reference']) ?></a></td>
            <td><?= h($c['prenom'] . ' ' . $c['nom']) ?></td>
            <td><?= statusBadge($c['statut_global']) ?></td>
            <td class="text-sm text-muted"><?= h(date('d/m/Y', strtotime($c['date_candidature']))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Stages actifs -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🎓 Stages en cours</span>
      <a href="backoffice.php?page=stages" class="btn btn-secondary btn-sm">Voir tout</a>
    </div>
    <?php if (empty($recent_stages)): ?>
    <div class="empty-state"><div class="empty-icon">🎓</div><p>Aucun stage actif.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>R&eacute;f&eacute;rence</th><th>Stagiaire</th><th>Direction</th><th>Fin</th></tr></thead>
        <tbody>
          <?php foreach ($recent_stages as $s): ?>
          <tr>
            <td><a href="backoffice.php?page=stage_detail&id=<?= $s['id'] ?>" style="color:var(--navy);font-weight:700"><?= h($s['reference']) ?></a></td>
            <td><?= h($s['prenom'] . ' ' . $s['nom']) ?></td>
            <td><?= h($s['direction_libelle'] ?? '-') ?></td>
            <td class="text-sm"><?= h(date('d/m/Y', strtotime($s['date_fin']))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($pending_renouv)): ?>
<div class="card mt-4">
  <div class="card-header">
    <span class="card-title">🔄 Renouvellements en attente</span>
    <a href="backoffice.php?page=renouvellements" class="btn btn-warning btn-sm">Traiter (<?= count($pending_renouv) ?>)</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Stage</th><th>Stagiaire</th><th>Date fin propos&eacute;e</th><th>Dur&eacute;e</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($pending_renouv as $r): ?>
        <tr>
          <td class="fw-bold"><?= h($r['stage_ref']) ?></td>
          <td><?= h($r['prenom'] . ' ' . $r['nom']) ?></td>
          <td><?= h(date('d/m/Y', strtotime($r['date_fin_proposee']))) ?></td>
          <td><?= h($r['duree_ajoutee_mois']) ?> mois</td>
          <td><a href="backoffice.php?page=renouvellements" class="btn btn-primary btn-xs">Traiter</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
