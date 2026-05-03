<?php
$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.*, u.nom, u.prenom, u.email, u.civilite, u.actif,
           d.libelle AS domaine
    FROM stagiaires s
    JOIN utilisateurs u ON u.id = s.utilisateur_id
    LEFT JOIN domaines d ON d.id = s.domaine_principal_id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$stag = $stmt->fetch();

if (!$stag) {
    echo '<div class="alert alert-danger">Stagiaire introuvable.</div>';
    return;
}

$formations  = $pdo->prepare("SELECT * FROM formations WHERE stagiaire_id = ? ORDER BY annee_fin DESC");
$formations->execute([$id]);
$formations  = $formations->fetchAll();

$competences = $pdo->prepare("SELECT libelle FROM competences WHERE stagiaire_id = ?");
$competences->execute([$id]);
$competences = $competences->fetchAll(PDO::FETCH_COLUMN);

$experiences = $pdo->prepare("SELECT * FROM experiences WHERE stagiaire_id = ? ORDER BY date_debut DESC");
$experiences->execute([$id]);
$experiences = $experiences->fetchAll();

$candidatures = $pdo->prepare("
    SELECT c.*, d.libelle AS direction, dom.libelle AS domaine
    FROM candidatures c
    LEFT JOIN directions d ON d.id = c.direction_id
    LEFT JOIN domaines dom ON dom.id = c.domaine_id
    WHERE c.stagiaire_id = ?
    ORDER BY c.date_candidature DESC
");
$candidatures->execute([$id]);
$candidatures = $candidatures->fetchAll();

$stages = $pdo->prepare("
    SELECT s.*, d.libelle AS direction
    FROM stages s
    LEFT JOIN directions d ON d.id = s.direction_id
    WHERE s.stagiaire_id = ?
    ORDER BY s.date_debut DESC
");
$stages->execute([$id]);
$stages = $stages->fetchAll();

$score = calculerScore($id);
?>

<div class="page-header">
    <div>
        <a href="backoffice.php?page=stagiaires" class="btn btn-ghost btn-sm" style="margin-bottom:8px">← Retour</a>
        <h1><?= h($stag['civilite'] . ' ' . $stag['prenom'] . ' ' . $stag['nom']) ?></h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <span class="badge <?= $stag['actif'] ? 'badge-success' : 'badge-danger' ?>"><?= $stag['actif'] ? 'Actif' : 'Inactif' ?></span>
        <a href="backoffice.php?page=analyse-cv&stagiaire_id=<?= $id ?>" class="btn btn-ghost btn-sm">🤖 Analyse IA CV</a>
    </div>
</div>

<div class="section-grid">
    <div>
        <!-- Infos personnelles -->
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <div class="card-title">👤 Informations personnelles</div>
                <div style="display:flex;align-items:center;gap:8px">
                    <div class="progress-wrap" style="width:60px"><div class="progress-bar <?= $score >= 70 ? 'green' : ($score >= 40 ? 'amber' : 'red') ?>" style="width:<?= $score ?>%"></div></div>
                    <span class="fw-600"><?= number_format($score,1) ?>/100</span>
                </div>
            </div>
            <div class="card-body">
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:13.5px">
                    <dt class="text-muted">Email</dt><dd><?= h($stag['email']) ?></dd>
                    <dt class="text-muted">Téléphone</dt><dd><?= h($stag['telephone'] ?? '—') ?></dd>
                    <dt class="text-muted">Ville</dt><dd><?= h($stag['ville'] ?? '—') ?></dd>
                    <dt class="text-muted">Nationalité</dt><dd><?= h($stag['nationalite'] ?? '—') ?></dd>
                    <dt class="text-muted">Naissance</dt><dd><?= $stag['date_naissance'] ? date('d/m/Y', strtotime($stag['date_naissance'])) : '—' ?></dd>
                    <dt class="text-muted">Sexe</dt><dd><?= $stag['sexe'] === 'F' ? 'Féminin' : 'Masculin' ?></dd>
                    <dt class="text-muted">Niveau</dt><dd><span class="badge badge-blue"><?= h($stag['niveau_etude'] ?? '—') ?></span></dd>
                    <dt class="text-muted">Domaine</dt><dd><?= h($stag['domaine'] ?? '—') ?></dd>
                </dl>
            </div>
        </div>

        <!-- Compétences -->
        <?php if ($competences): ?>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">💡 Compétences</div></div>
            <div class="card-body">
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                    <?php foreach ($competences as $c): ?>
                    <span class="badge badge-blue"><?= h($c) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formations -->
        <?php if ($formations): ?>
        <div class="card">
            <div class="card-header"><div class="card-title">🎓 Formations</div></div>
            <div class="card-body">
                <?php foreach ($formations as $f): ?>
                <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--gray-line)">
                    <div class="fw-600"><?= h($f['diplome'] ?? '') ?> <?= $f['specialite'] ? '— ' . h($f['specialite']) : '' ?></div>
                    <div class="text-muted text-sm"><?= h($f['etablissement'] ?? '') ?> <?= $f['annee_fin'] ? '(' . $f['annee_fin'] . ')' : '' ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <!-- Candidatures -->
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <div class="card-title">📋 Candidatures (<?= count($candidatures) ?>)</div>
            </div>
            <?php if ($candidatures): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Réf.</th><th>Type</th><th>Statut</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($candidatures as $c): ?>
                        <tr>
                            <td><a href="backoffice.php?page=candidature_detail&id=<?= $c['id'] ?>" class="mono"><?= h($c['reference']) ?></a></td>
                            <td class="text-sm"><?= $c['type_candidature'] === 'offre' ? 'Sur offre' : 'Spontanée' ?></td>
                            <td><?= statusBadge($c['statut_global']) ?></td>
                            <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($c['date_candidature'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body"><div class="empty-state"><div class="icon">📭</div>Aucune candidature</div></div>
            <?php endif; ?>
        </div>

        <!-- Stages -->
        <div class="card">
            <div class="card-header"><div class="card-title">🎓 Stages (<?= count($stages) ?>)</div></div>
            <?php if ($stages): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Réf.</th><th>Direction</th><th>Période</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php foreach ($stages as $s): ?>
                        <tr>
                            <td><a href="backoffice.php?page=stage_detail&id=<?= $s['id'] ?>" class="mono"><?= h($s['reference']) ?></a></td>
                            <td class="text-sm"><?= h($s['direction'] ?? '—') ?></td>
                            <td class="text-sm"><?= date('d/m/Y', strtotime($s['date_debut'])) ?> → <?= date('d/m/Y', strtotime($s['date_fin'])) ?></td>
                            <td><?= statusBadge($s['statut']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body"><div class="empty-state"><div class="icon">🎓</div>Aucun stage</div></div>
            <?php endif; ?>
        </div>
    </div>
</div>
