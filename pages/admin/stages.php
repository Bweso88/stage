<?php
/**
 * StagIA - Liste stages
 */
define('STAGIA_PAGE', 'Stages');
require_once __DIR__ . '/../../includes/header.php';

$pdo = getPDO();
$tab = $_GET['tab'] ?? 'actifs';

$sqlBase = 'SELECT sg.*, u.nom, u.prenom, d.libelle as direction_libelle, eu.nom as enc_nom, eu.prenom as enc_prenom FROM stages sg JOIN stagiaires s ON s.id = sg.stagiaire_id JOIN utilisateurs u ON u.id = s.utilisateur_id LEFT JOIN directions d ON d.id = sg.direction_id LEFT JOIN utilisateurs eu ON eu.id = sg.encadrant_id';

if ($tab === 'actifs') {
    $stages = $pdo->query($sqlBase . " WHERE sg.statut IN ('en_cours','renouvele','preparation') ORDER BY sg.date_fin ASC")->fetchAll();
} else {
    $stages = $pdo->query($sqlBase . " WHERE sg.statut IN ('termine','interrompu','annule') ORDER BY sg.date_fin DESC")->fetchAll();
}
?>

<div class="page-header">
  <h1>Stages</h1>
  <p>Suivi de tous les stages de la plateforme.</p>
</div>

<div class="tabs">
  <a href="backoffice.php?page=stages&tab=actifs" class="tab <?= $tab==='actifs'?'active':'' ?>">🟢 Actifs</a>
  <a href="backoffice.php?page=stages&tab=archives" class="tab <?= $tab==='archives'?'active':'' ?>">📁 Archives</a>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= $tab==='actifs'?'Stages actifs':'Stages archiv&eacute;s' ?> (<?= count($stages) ?>)</span>
  </div>
  <?php if (empty($stages)): ?>
  <div class="empty-state"><div class="empty-icon">🎓</div><p>Aucun stage trouv&eacute;.</p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>R&eacute;f&eacute;rence</th>
          <th>Stagiaire</th>
          <th>Direction</th>
          <th>Encadrant</th>
          <th>D&eacute;but</th>
          <th>Fin</th>
          <th>Dur&eacute;e</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($stages as $s): ?>
        <?php
          $today = new DateTime();
          $fin   = new DateTime($s['date_fin']);
          $daysLeft = (int)$today->diff($fin)->days * ($fin > $today ? 1 : -1);
        ?>
        <tr>
          <td><a href="backoffice.php?page=stage_detail&id=<?= $s['id'] ?>" class="fw-bold" style="color:var(--navy)"><?= h($s['reference']) ?></a></td>
          <td><?= h($s['prenom'] . ' ' . $s['nom']) ?></td>
          <td><?= h($s['direction_libelle'] ?? '-') ?></td>
          <td><?= h(($s['enc_prenom'] ?? '-') . ' ' . ($s['enc_nom'] ?? '')) ?></td>
          <td><?= h(date('d/m/Y', strtotime($s['date_debut']))) ?></td>
          <td>
            <?= h(date('d/m/Y', strtotime($s['date_fin']))) ?>
            <?php if ($tab === 'actifs' && $daysLeft <= 30 && $daysLeft >= 0): ?>
            <span class="badge badge-warning" style="margin-left:4px"><?= $daysLeft ?>j</span>
            <?php elseif ($tab === 'actifs' && $daysLeft < 0): ?>
            <span class="badge badge-danger" style="margin-left:4px">Expiré</span>
            <?php endif; ?>
          </td>
          <td><?= h($s['duree_totale_mois']) ?> mois</td>
          <td><?= statusBadge($s['statut']) ?></td>
          <td>
            <a href="backoffice.php?page=stage_detail&id=<?= $s['id'] ?>" class="btn btn-primary btn-xs">D&eacute;tail</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
