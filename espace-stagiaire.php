<?php
/**
 * Stage - Espace stagiaire privé
 */
require_once __DIR__ . '/config.php';
requireStagiaire();

$pdo = getPDO();
$uid = $_SESSION['user']['id'];
$sid = $_SESSION['user']['stagiaire_id'] ?? null;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profil' && $sid) {
        $tel    = trim($_POST['telephone'] ?? '');
        $ville  = trim($_POST['ville'] ?? '');
        $addr   = trim($_POST['adresse'] ?? '');
        $nat    = trim($_POST['nationalite'] ?? '');
        $niveau = $_POST['niveau_etude'] ?? 'bac+3';
        $pdo->prepare('UPDATE stagiaires SET telephone=?,ville=?,adresse=?,nationalite=?,niveau_etude=? WHERE id=?')
            ->execute([$tel, $ville, $addr, $nat, $niveau, $sid]);
        flash('Profil mis &agrave; jour avec succ&egrave;s.');
        redirect('espace-stagiaire.php?tab=profil');
    }

    if ($action === 'demande_renouvellement' && $sid) {
        $stage_id    = (int)($_POST['stage_id'] ?? 0);
        $date_fin    = $_POST['date_fin_proposee'] ?? '';
        $motif       = trim($_POST['motif'] ?? '');
        $duree_mois  = (int)($_POST['duree_ajoutee_mois'] ?? 1);

        $errors = verifierRenouvellement($stage_id, $date_fin);
        if ($errors) {
            flash(implode(' ', $errors), 'error');
        } else {
            $stage = $pdo->prepare('SELECT * FROM stages WHERE id = ? AND stagiaire_id = ?');
            $stage->execute([$stage_id, $sid]);
            $s = $stage->fetch();
            if ($s) {
                $num = $s['nb_renouvellements'] + 1;
                $pdo->prepare('INSERT INTO renouvellements_stage (stage_id, numero_renouvellement, date_debut_proposee, date_fin_proposee, duree_ajoutee_mois, motif_demande) VALUES (?,?,?,?,?,?)')
                    ->execute([$stage_id, $num, $s['date_fin'], $date_fin, $duree_mois, $motif]);
                flash('Demande de renouvellement soumise avec succ&egrave;s.');
            } else {
                flash('Stage introuvable.', 'error');
            }
        }
        redirect('espace-stagiaire.php?tab=stage');
    }
}

$tab = $_GET['tab'] ?? 'home';

// Load stagiaire data
$stagiaire = null;
$candidatures = [];
$stage = null;
$formations = [];
$competences = [];

if ($sid) {
    $stmt = $pdo->prepare('SELECT s.*, u.nom, u.prenom, u.email, u.civilite FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id WHERE s.id = ?');
    $stmt->execute([$sid]);
    $stagiaire = $stmt->fetch();

    $stmt2 = $pdo->prepare('SELECT c.*, o.titre as offre_titre, d.libelle as direction_libelle, dm.libelle as domaine_libelle FROM candidatures c LEFT JOIN offres_stage o ON o.id = c.offre_id LEFT JOIN directions d ON d.id = c.direction_id LEFT JOIN domaines dm ON dm.id = c.domaine_id WHERE c.stagiaire_id = ? ORDER BY c.date_candidature DESC');
    $stmt2->execute([$sid]);
    $candidatures = $stmt2->fetchAll();

    $stmt3 = $pdo->prepare('SELECT sg.*, d.libelle as direction_libelle, dm.libelle as domaine_libelle, u.nom as enc_nom, u.prenom as enc_prenom FROM stages sg LEFT JOIN directions d ON d.id = sg.direction_id LEFT JOIN domaines dm ON dm.id = sg.domaine_id LEFT JOIN utilisateurs u ON u.id = sg.encadrant_id WHERE sg.stagiaire_id = ? AND sg.statut IN (?,?,?) ORDER BY sg.created_at DESC LIMIT 1');
    $stmt3->execute([$sid, 'en_cours', 'renouvele', 'preparation']);
    $stage = $stmt3->fetch();

    $formations = $pdo->prepare('SELECT * FROM formations WHERE stagiaire_id = ?');
    $formations->execute([$sid]);
    $formations = $formations->fetchAll();

    $competences = $pdo->prepare('SELECT * FROM competences WHERE stagiaire_id = ?');
    $competences->execute([$sid]);
    $competences = $competences->fetchAll();
}

define('STAGIA_PAGE', 'Espace Stagiaire');
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1>Bienvenue, <?= h($_SESSION['user']['prenom']) ?> 👋</h1>
  <p>G&eacute;rez vos candidatures et suivez l'avancement de votre stage.</p>
</div>

<!-- Tabs -->
<div class="tabs">
  <a href="?tab=home" class="tab <?= $tab==='home'?'active':'' ?>">🏠 Accueil</a>
  <a href="?tab=candidatures" class="tab <?= $tab==='candidatures'?'active':'' ?>">📋 Mes candidatures</a>
  <a href="?tab=stage" class="tab <?= $tab==='stage'?'active':'' ?>">🎓 Mon stage</a>
  <a href="?tab=profil" class="tab <?= $tab==='profil'?'active':'' ?>">👤 Mon profil</a>
</div>

<?php if ($tab === 'home'): ?>
<!-- HOME TAB -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon navy">📋</div>
    <div>
      <div class="stat-val"><?= count($candidatures) ?></div>
      <div class="stat-label">Candidature(s)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">🎓</div>
    <div>
      <div class="stat-val"><?= $stage ? 1 : 0 ?></div>
      <div class="stat-label">Stage actif</div>
    </div>
  </div>
  <?php if ($stage): ?>
  <div class="stat-card">
    <div class="stat-icon orange">📅</div>
    <div>
      <div class="stat-val"><?= h(date('d/m/Y', strtotime($stage['date_fin']))) ?></div>
      <div class="stat-label">Fin de stage</div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($stage): ?>
<div class="card">
  <div class="card-header">
    <span class="card-title">🎓 Mon stage en cours</span>
    <?= statusBadge($stage['statut']) ?>
  </div>
  <div class="card-body">
    <div class="grid-2">
      <div>
        <div class="info-row"><span class="info-label">R&eacute;f&eacute;rence</span><span class="info-val fw-bold"><?= h($stage['reference']) ?></span></div>
        <div class="info-row"><span class="info-label">Direction</span><span class="info-val"><?= h($stage['direction_libelle'] ?? '-') ?></span></div>
        <div class="info-row"><span class="info-label">Encadrant</span><span class="info-val"><?= h(($stage['enc_prenom'] ?? '') . ' ' . ($stage['enc_nom'] ?? '-')) ?></span></div>
      </div>
      <div>
        <div class="info-row"><span class="info-label">Date de d&eacute;but</span><span class="info-val"><?= h(date('d/m/Y', strtotime($stage['date_debut']))) ?></span></div>
        <div class="info-row"><span class="info-label">Date de fin</span><span class="info-val"><?= h(date('d/m/Y', strtotime($stage['date_fin']))) ?></span></div>
        <div class="info-row"><span class="info-label">Dur&eacute;e totale</span><span class="info-val"><?= h($stage['duree_totale_mois']) ?> mois</span></div>
      </div>
    </div>
    <?php if ($stage['remboursement_transport']): ?>
    <div class="mt-4" style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:7px;padding:12px 16px;">
      <span style="font-size:13px;color:#065f46;">✅ Remboursement transport: <strong><?= number_format($stage['montant_transport'], 0, ',', ' ') ?> GNF/mois</strong></span>
    </div>
    <?php endif; ?>
    <div class="mt-4">
      <a href="?tab=stage" class="btn btn-primary btn-sm">Voir les d&eacute;tails →</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($candidatures)): ?>
<div class="card mt-4">
  <div class="card-header">
    <span class="card-title">📋 Mes candidatures r&eacute;centes</span>
    <a href="?tab=candidatures" class="btn btn-secondary btn-sm">Voir tout</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>R&eacute;f&eacute;rence</th><th>Type</th><th>Offre</th><th>Statut</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach (array_slice($candidatures, 0, 3) as $c): ?>
        <tr>
          <td class="fw-bold"><?= h($c['reference']) ?></td>
          <td><?= $c['type_candidature'] === 'offre' ? '<span class="badge badge-info">Sur offre</span>' : '<span class="badge badge-secondary">Spontan&eacute;e</span>' ?></td>
          <td><?= h($c['offre_titre'] ?? 'Spontan&eacute;e') ?></td>
          <td><?= statusBadge($c['statut_global']) ?></td>
          <td class="text-sm text-muted"><?= h(date('d/m/Y', strtotime($c['date_candidature']))) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'candidatures'): ?>
<!-- CANDIDATURES TAB -->
<div class="card">
  <div class="card-header">
    <span class="card-title">📋 Mes candidatures</span>
  </div>
  <?php if (empty($candidatures)): ?>
  <div class="empty-state">
    <div class="empty-icon">📋</div>
    <p>Vous n'avez pas encore de candidature.<br>
    <a href="index.php" class="btn btn-primary btn-sm mt-2">Voir les offres</a></p>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>R&eacute;f&eacute;rence</th><th>Type</th><th>Offre / Direction</th><th>Score</th><th>Workflow</th><th>Statut</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($candidatures as $c): ?>
        <tr>
          <td class="fw-bold"><?= h($c['reference']) ?></td>
          <td><?= $c['type_candidature'] === 'offre' ? '<span class="badge badge-info">Offre</span>' : '<span class="badge badge-secondary">Spontan&eacute;e</span>' ?></td>
          <td><?= h($c['offre_titre'] ?? ($c['direction_libelle'] ?? 'N/A')) ?></td>
          <td><strong><?= number_format((float)$c['score_tri'], 1) ?>/100</strong></td>
          <td><?= workflowDots($c['statut_global']) ?></td>
          <td><?= statusBadge($c['statut_global']) ?></td>
          <td class="text-sm text-muted"><?= h(date('d/m/Y', strtotime($c['date_candidature']))) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'stage'): ?>
<!-- STAGE TAB -->
<?php if (!$stage): ?>
<div class="empty-state">
  <div class="empty-icon">🎓</div>
  <p>Vous n'avez pas encore de stage actif.<br>Votre candidature doit &ecirc;tre valid&eacute;e pour qu'un stage vous soit attribu&eacute;.</p>
</div>
<?php else: ?>
<div class="card">
  <div class="card-header">
    <span class="card-title">🎓 Mon stage – <?= h($stage['reference']) ?></span>
    <?= statusBadge($stage['statut']) ?>
  </div>
  <div class="card-body">
    <div class="grid-2">
      <div>
        <div class="info-row"><span class="info-label">Direction</span><span class="info-val"><?= h($stage['direction_libelle'] ?? '-') ?></span></div>
        <div class="info-row"><span class="info-label">Domaine</span><span class="info-val"><?= h($stage['domaine_libelle'] ?? '-') ?></span></div>
        <div class="info-row"><span class="info-label">Encadrant</span><span class="info-val"><?= h(($stage['enc_prenom'] ?? '') . ' ' . ($stage['enc_nom'] ?? '-')) ?></span></div>
        <div class="info-row"><span class="info-label">Date de d&eacute;but</span><span class="info-val"><?= h(date('d/m/Y', strtotime($stage['date_debut']))) ?></span></div>
      </div>
      <div>
        <div class="info-row"><span class="info-label">Date de fin</span><span class="info-val"><?= h(date('d/m/Y', strtotime($stage['date_fin']))) ?></span></div>
        <div class="info-row"><span class="info-label">Dur&eacute;e initiale</span><span class="info-val"><?= h($stage['duree_initiale_mois']) ?> mois</span></div>
        <div class="info-row"><span class="info-label">Dur&eacute;e totale</span><span class="info-val"><?= h($stage['duree_totale_mois']) ?> mois</span></div>
        <div class="info-row"><span class="info-label">Renouvellements</span><span class="info-val"><?= h($stage['nb_renouvellements']) ?>/3</span></div>
      </div>
    </div>
    <?php if ($stage['remboursement_transport']): ?>
    <div class="mt-4" style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:7px;padding:12px 16px;">
      ✅ Transport rembours&eacute; : <strong><?= number_format($stage['montant_transport'], 0, ',', ' ') ?> GNF/mois</strong>
    </div>
    <?php endif; ?>
    <div class="mt-4 d-flex gap-2">
      <?php if ($stage['statut'] === 'en_cours' && $stage['nb_renouvellements'] < 3): ?>
      <button class="btn btn-primary btn-sm" onclick="openModal('modal-renouv')">🔄 Demander un renouvellement</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Renouvellements history -->
<?php
$renouv = $pdo->prepare('SELECT * FROM renouvellements_stage WHERE stage_id = ? ORDER BY created_at DESC');
$renouv->execute([$stage['id']]);
$renouvList = $renouv->fetchAll();
if (!empty($renouvList)):
?>
<div class="card mt-4">
  <div class="card-header"><span class="card-title">🔄 Mes demandes de renouvellement</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>N°</th><th>Date fin propos&eacute;e</th><th>Dur&eacute;e</th><th>Motif</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach ($renouvList as $r): ?>
        <tr>
          <td><?= h($r['numero_renouvellement']) ?></td>
          <td><?= h(date('d/m/Y', strtotime($r['date_fin_proposee']))) ?></td>
          <td><?= h($r['duree_ajoutee_mois']) ?> mois</td>
          <td><?= h(substr($r['motif_demande'] ?? '', 0, 60)) ?></td>
          <td><?= statusBadge($r['statut']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php elseif ($tab === 'profil'): ?>
<!-- PROFIL TAB -->
<div class="grid-2">
  <div class="card">
    <div class="card-header"><span class="card-title">👤 Informations personnelles</span></div>
    <div class="card-body">
      <?php if ($stagiaire): ?>
      <div class="info-row"><span class="info-label">Nom complet</span><span class="info-val"><?= h(($stagiaire['civilite'] ?? '') . ' ' . $stagiaire['prenom'] . ' ' . $stagiaire['nom']) ?></span></div>
      <div class="info-row"><span class="info-label">Email</span><span class="info-val"><?= h($stagiaire['email']) ?></span></div>
      <div class="info-row"><span class="info-label">T&eacute;l&eacute;phone</span><span class="info-val"><?= h($stagiaire['telephone'] ?? '-') ?></span></div>
      <div class="info-row"><span class="info-label">Ville</span><span class="info-val"><?= h($stagiaire['ville'] ?? '-') ?></span></div>
      <div class="info-row"><span class="info-label">Niveau d'&eacute;tudes</span><span class="info-val"><strong><?= h(strtoupper($stagiaire['niveau_etude'] ?? '-')) ?></strong></span></div>
      <?php endif; ?>
      <div class="mt-4">
        <button class="btn btn-outline btn-sm" onclick="openModal('modal-edit-profil')">Modifier le profil</button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">🎓 Formation</span></div>
    <div class="card-body">
      <?php if (!empty($formations)): ?>
        <?php foreach ($formations as $f): ?>
        <div class="info-row"><span class="info-label"><?= h($f['diplome'] ?? 'Dipl&ocirc;me') ?></span><span class="info-val"><?= h($f['specialite'] ?? '') ?></span></div>
        <div class="info-row"><span class="info-label">Etablissement</span><span class="info-val"><?= h($f['etablissement'] ?? '-') ?></span></div>
        <div class="info-row"><span class="info-label">Ann&eacute;e</span><span class="info-val"><?= h($f['annee_fin'] ?? '-') ?></span></div>
        <?php endforeach; ?>
      <?php else: ?>
      <p class="text-muted text-sm">Aucune formation renseign&eacute;e.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title">⚡ Comp&eacute;tences</span></div>
    <div class="card-body">
      <?php if (!empty($competences)): ?>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ($competences as $comp): ?>
        <span class="badge badge-primary"><?= h($comp['libelle']) ?></span>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <p class="text-muted text-sm">Aucune comp&eacute;tence renseign&eacute;e.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Modal: Edit profil -->
<div class="modal-overlay" id="modal-edit-profil">
  <div class="modal">
    <div class="modal-header">
      <h3>Modifier mon profil</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-profil')">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="update_profil">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>T&eacute;l&eacute;phone</label>
            <input type="tel" name="telephone" class="form-control" value="<?= h($stagiaire['telephone'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Ville</label>
            <input type="text" name="ville" class="form-control" value="<?= h($stagiaire['ville'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Adresse</label>
            <input type="text" name="adresse" class="form-control" value="<?= h($stagiaire['adresse'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Nationalit&eacute;</label>
            <input type="text" name="nationalite" class="form-control" value="<?= h($stagiaire['nationalite'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Niveau d'&eacute;tudes</label>
            <select name="niveau_etude" class="form-control">
              <?php foreach (['bac','bac+2','bac+3','bac+4','bac+5','doctorat'] as $niv): ?>
              <option value="<?= $niv ?>" <?= ($stagiaire['niveau_etude']??'')===$niv?'selected':'' ?>><?= strtoupper($niv) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-profil')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php if ($stage): ?>
<!-- Modal: Renouvellement -->
<div class="modal-overlay" id="modal-renouv">
  <div class="modal">
    <div class="modal-header">
      <h3>Demande de renouvellement</h3>
      <button class="modal-close" onclick="closeModal('modal-renouv')">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="demande_renouvellement">
      <input type="hidden" name="stage_id" value="<?= $stage['id'] ?>">
      <div class="modal-body">
        <div class="form-group">
          <label>Nouvelle date de fin souhait&eacute;e</label>
          <input type="date" name="date_fin_proposee" class="form-control" required min="<?= $stage['date_fin'] ?>">
        </div>
        <div class="form-group mt-2">
          <label>Dur&eacute;e ajout&eacute;e (mois)</label>
          <select name="duree_ajoutee_mois" class="form-control">
            <?php for ($m = 1; $m <= 6; $m++): ?>
            <option value="<?= $m ?>"><?= $m ?> mois</option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group mt-2">
          <label>Motif de la demande</label>
          <textarea name="motif" class="form-control" rows="4" placeholder="Expliquez pourquoi vous souhaitez renouveler votre stage..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-renouv')">Annuler</button>
        <button type="submit" class="btn btn-primary">Soumettre la demande</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
