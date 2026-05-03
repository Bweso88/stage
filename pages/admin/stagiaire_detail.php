<?php
// pages/admin/stagiaire_detail.php
requireAdmin();
define('STAGIA_PAGE', 'Détail stagiaire');
$pdo = getPDO();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { flash('Stagiaire introuvable.', 'error'); redirect('backoffice.php?page=stagiaires'); }

$stmt = $pdo->prepare('
    SELECT s.*, u.nom, u.prenom, u.email, u.actif, u.civilite, u.role,
           d.libelle AS domaine_libelle
    FROM stagiaires s
    JOIN utilisateurs u ON s.utilisateur_id = u.id
    LEFT JOIN domaines d ON s.domaine_principal_id = d.id
    WHERE s.id = ?
');
$stmt->execute([$id]);
$stag = $stmt->fetch();
if (!$stag) { flash('Stagiaire introuvable.', 'error'); redirect('backoffice.php?page=stagiaires'); }

$formations  = $pdo->prepare('SELECT * FROM formations WHERE stagiaire_id = ? ORDER BY annee_fin DESC');
$formations->execute([$id]);
$formations  = $formations->fetchAll();

$competences = $pdo->prepare('SELECT * FROM competences WHERE stagiaire_id = ? ORDER BY id');
$competences->execute([$id]);
$competences = $competences->fetchAll();

$experiences = $pdo->prepare('SELECT * FROM experiences WHERE stagiaire_id = ? ORDER BY date_debut DESC');
$experiences->execute([$id]);
$experiences = $experiences->fetchAll();

$candidatures = $pdo->prepare('
    SELECT c.*, o.titre AS offre_titre, d.libelle AS direction, dom.libelle AS domaine
    FROM candidatures c
    LEFT JOIN offres_stage o ON c.offre_id = o.id
    LEFT JOIN directions d ON c.direction_id = d.id
    LEFT JOIN domaines dom ON c.domaine_id = dom.id
    WHERE c.stagiaire_id = ?
    ORDER BY c.date_candidature DESC
');
$candidatures->execute([$id]);
$candidatures = $candidatures->fetchAll();

$stages = $pdo->prepare('
    SELECT sg.*, d.libelle AS direction, u.nom AS enc_nom, u.prenom AS enc_prenom
    FROM stages sg
    LEFT JOIN directions d ON sg.direction_id = d.id
    LEFT JOIN utilisateurs u ON sg.encadrant_id = u.id
    WHERE sg.stagiaire_id = ?
    ORDER BY sg.date_debut DESC
');
$stages->execute([$id]);
$stages = $stages->fetchAll();

$score = calculerScore($id);

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <a href="backoffice.php?page=stagiaires" class="btn btn-ghost">← Retour</a>
  <h1><?= h($stag['prenom'] . ' ' . $stag['nom']) ?></h1>
  <span class="badge <?= $stag['actif'] ? 'badge-success' : 'badge-gray' ?>"><?= $stag['actif'] ? 'Actif' : 'Inactif' ?></span>
</div>

<div class="section-grid">
  <!-- Profil -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">Informations personnelles</h3></div>
    <div class="card-body">
      <div style="text-align:center;margin-bottom:20px">
        <div class="avatar" style="width:64px;height:64px;font-size:1.5rem;margin:0 auto 8px">
          <?= mb_strtoupper(mb_substr($stag['prenom'],0,1)) . mb_strtoupper(mb_substr($stag['nom'],0,1)) ?>
        </div>
        <div style="font-size:.8rem;color:#6b7280">Score profil</div>
        <div style="font-size:1.8rem;font-weight:700;color:var(--navy)"><?= number_format($score, 0) ?><span style="font-size:.9rem">/100</span></div>
        <div class="progress-wrap" style="margin:6px auto;max-width:160px">
          <div class="progress-bar" style="width:<?= $score ?>%"></div>
        </div>
      </div>
      <table style="width:100%;font-size:.86rem">
        <tr><td style="color:#9ca3af;width:40%">Civilité</td><td><?= h($stag['civilite'] ?? '—') ?></td></tr>
        <tr><td style="color:#9ca3af">Nom complet</td><td><strong><?= h($stag['prenom'] . ' ' . $stag['nom']) ?></strong></td></tr>
        <tr><td style="color:#9ca3af">Email</td><td><?= h($stag['email']) ?></td></tr>
        <tr><td style="color:#9ca3af">Téléphone</td><td><?= h($stag['telephone'] ?? '—') ?></td></tr>
        <tr><td style="color:#9ca3af">Date de naissance</td><td><?= $stag['date_naissance'] ? date('d/m/Y', strtotime($stag['date_naissance'])) : '—' ?></td></tr>
        <tr><td style="color:#9ca3af">Sexe</td><td><?= h($stag['sexe'] ?? '—') ?></td></tr>
        <tr><td style="color:#9ca3af">Nationalité</td><td><?= h($stag['nationalite'] ?? '—') ?></td></tr>
        <tr><td style="color:#9ca3af">Ville</td><td><?= h($stag['ville'] ?? '—') ?></td></tr>
        <tr><td style="color:#9ca3af">Adresse</td><td><?= h($stag['adresse'] ?? '—') ?></td></tr>
        <tr><td style="color:#9ca3af">Niveau d\'étude</td><td><?= h($stag['niveau_etude'] ?? '—') ?></td></tr>
        <tr><td style="color:#9ca3af">Domaine</td><td><?= h($stag['domaine_libelle'] ?? '—') ?></td></tr>
      </table>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px">
    <!-- Formations -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Formations (<?= count($formations) ?>)</h3></div>
      <div class="card-body">
        <?php if (empty($formations)): ?>
        <p style="color:#9ca3af;text-align:center">Aucune formation enregistrée.</p>
        <?php else: ?>
        <?php foreach ($formations as $f): ?>
        <div style="border-left:3px solid var(--navy);padding:8px 12px;margin-bottom:10px">
          <strong><?= h($f['diplome']) ?></strong> — <?= h($f['specialite'] ?? '') ?><br>
          <small style="color:#6b7280"><?= h($f['etablissement'] ?? '') ?> <?= $f['annee_fin'] ? '(' . $f['annee_fin'] . ')' : '' ?></small>
        </div>
        <?php endforeach ?>
        <?php endif ?>
      </div>
    </div>

    <!-- Compétences -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Compétences (<?= count($competences) ?>)</h3></div>
      <div class="card-body">
        <?php if (empty($competences)): ?>
        <p style="color:#9ca3af;text-align:center">Aucune compétence enregistrée.</p>
        <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach ($competences as $c): ?>
          <span class="badge badge-blue"><?= h($c['libelle']) ?></span>
          <?php endforeach ?>
        </div>
        <?php endif ?>
      </div>
    </div>
  </div>
</div>

<!-- Expériences -->
<?php if (!empty($experiences)): ?>
<div class="card" style="margin-top:20px">
  <div class="card-header"><h3 class="card-title">Expériences professionnelles (<?= count($experiences) ?>)</h3></div>
  <div class="card-body">
    <?php foreach ($experiences as $e): ?>
    <div style="border-left:3px solid var(--red);padding:8px 12px;margin-bottom:12px">
      <strong><?= h($e['poste']) ?></strong> — <?= h($e['entreprise'] ?? '') ?><br>
      <small style="color:#6b7280">
        <?= $e['date_debut'] ? date('m/Y', strtotime($e['date_debut'])) : '' ?>
        <?= $e['date_fin'] ? ' → ' . date('m/Y', strtotime($e['date_fin'])) : ' → En cours' ?>
      </small>
      <?php if ($e['description']): ?>
      <p style="margin-top:4px;font-size:.82rem;color:#4b5563"><?= h($e['description']) ?></p>
      <?php endif ?>
    </div>
    <?php endforeach ?>
  </div>
</div>
<?php endif ?>

<!-- Candidatures -->
<div class="card" style="margin-top:20px">
  <div class="card-header"><h3 class="card-title">Historique des candidatures (<?= count($candidatures) ?>)</h3></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Référence</th><th>Offre / Domaine</th><th>Direction</th><th>Score</th><th>Statut</th><th>Date</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($candidatures)): ?>
        <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px">Aucune candidature.</td></tr>
        <?php endif ?>
        <?php foreach ($candidatures as $c): ?>
        <tr>
          <td><code class="mono"><?= h($c['reference']) ?></code></td>
          <td><?= h($c['offre_titre'] ?? $c['domaine'] ?? '—') ?></td>
          <td><?= h($c['direction'] ?? '—') ?></td>
          <td><?= number_format((float)$c['score_tri'], 0) ?>/100</td>
          <td><?= statusBadge($c['statut_global']) ?></td>
          <td><?= date('d/m/Y', strtotime($c['date_candidature'])) ?></td>
          <td><a href="backoffice.php?page=candidature_detail&id=<?= $c['id'] ?>" class="btn btn-sm btn-ghost">👁</a></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Stages -->
<div class="card" style="margin-top:20px">
  <div class="card-header"><h3 class="card-title">Historique des stages (<?= count($stages) ?>)</h3></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Référence</th><th>Direction</th><th>Encadrant</th><th>Début</th><th>Fin</th><th>Statut</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($stages)): ?>
        <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px">Aucun stage.</td></tr>
        <?php endif ?>
        <?php foreach ($stages as $sg): ?>
        <tr>
          <td><code class="mono"><?= h($sg['reference']) ?></code></td>
          <td><?= h($sg['direction'] ?? '—') ?></td>
          <td><?= $sg['enc_prenom'] ? h($sg['enc_prenom'] . ' ' . $sg['enc_nom']) : '—' ?></td>
          <td><?= $sg['date_debut'] ? date('d/m/Y', strtotime($sg['date_debut'])) : '—' ?></td>
          <td><?= $sg['date_fin'] ? date('d/m/Y', strtotime($sg['date_fin'])) : '—' ?></td>
          <td><?= statusBadge($sg['statut']) ?></td>
          <td><a href="backoffice.php?page=stage_detail&id=<?= $sg['id'] ?>" class="btn btn-sm btn-ghost">👁</a></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
