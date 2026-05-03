<?php
$pdo = getPDO();

// Stats globales
$stats = [];
$stats['candidatures']  = (int)$pdo->query("SELECT COUNT(*) FROM candidatures")->fetchColumn();
$stats['en_attente']    = (int)$pdo->query("SELECT COUNT(*) FROM candidatures WHERE statut_global IN ('soumise','niv1_en_cours','niv1_valide')")->fetchColumn();
$stats['stages_actifs'] = (int)$pdo->query("SELECT COUNT(*) FROM stages WHERE statut IN ('en_cours','renouvele','preparation')")->fetchColumn();
$stats['stagiaires']    = (int)$pdo->query("SELECT COUNT(*) FROM stagiaires")->fetchColumn();
$stats['offres']        = (int)$pdo->query("SELECT COUNT(*) FROM offres_stage WHERE statut = 'ouverte'")->fetchColumn();
$stats['renouv_att']    = (int)$pdo->query("SELECT COUNT(*) FROM renouvellements_stage WHERE statut = 'en_attente'")->fetchColumn();

// Dernières candidatures
$dernCand = $pdo->query("
    SELECT c.*, u.nom, u.prenom, d.libelle AS direction
    FROM candidatures c
    JOIN stagiaires s ON s.id = c.stagiaire_id
    JOIN utilisateurs u ON u.id = s.utilisateur_id
    LEFT JOIN directions d ON d.id = c.direction_id
    ORDER BY c.date_candidature DESC
    LIMIT 5
")->fetchAll();

// Stages en cours
$stagesActifs = $pdo->query("
    SELECT s.*, u.nom, u.prenom, d.libelle AS direction
    FROM stages s
    JOIN stagiaires st ON st.id = s.stagiaire_id
    JOIN utilisateurs u ON u.id = st.utilisateur_id
    LEFT JOIN directions d ON d.id = s.direction_id
    WHERE s.statut IN ('en_cours','renouvele','preparation')
    ORDER BY s.date_fin ASC
    LIMIT 5
")->fetchAll();
?>

<div class="page-header">
    <h1>Tableau de bord</h1>
    <span class="text-muted text-sm"><?= date('l d F Y') ?></span>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon navy">📋</div>
        <div>
            <div class="stat-value"><?= $stats['candidatures'] ?></div>
            <div class="stat-label">Total candidatures</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">⏳</div>
        <div>
            <div class="stat-value"><?= $stats['en_attente'] ?></div>
            <div class="stat-label">En attente de décision</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">🎓</div>
        <div>
            <div class="stat-value"><?= $stats['stages_actifs'] ?></div>
            <div class="stat-label">Stages actifs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon navy">👥</div>
        <div>
            <div class="stat-value"><?= $stats['stagiaires'] ?></div>
            <div class="stat-label">Stagiaires inscrits</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">📢</div>
        <div>
            <div class="stat-value"><?= $stats['offres'] ?></div>
            <div class="stat-label">Offres ouvertes</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">🔄</div>
        <div>
            <div class="stat-value"><?= $stats['renouv_att'] ?></div>
            <div class="stat-label">Renouvellements en attente</div>
        </div>
    </div>
</div>

<?php if ($stats['renouv_att'] > 0): ?>
<div class="alert alert-warning">
    ⚠️ <strong><?= $stats['renouv_att'] ?> renouvellement(s)</strong> en attente de traitement.
    <a href="backoffice.php?page=renouvellements" style="color:inherit;font-weight:600;margin-left:8px">Voir →</a>
</div>
<?php endif; ?>

<div class="section-grid">
    <!-- Dernières candidatures -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">📋 Dernières candidatures</div>
            <a href="backoffice.php?page=candidatures" class="btn btn-ghost btn-sm">Voir tout</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Stagiaire</th>
                        <th>Statut</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$dernCand): ?>
                    <tr><td colspan="4"><div class="empty-state"><div class="icon">📭</div>Aucune candidature</div></td></tr>
                    <?php else: ?>
                    <?php foreach ($dernCand as $c): ?>
                    <tr>
                        <td><a href="backoffice.php?page=candidature_detail&id=<?= $c['id'] ?>" class="mono"><?= h($c['reference']) ?></a></td>
                        <td><?= h($c['prenom'] . ' ' . $c['nom']) ?></td>
                        <td><?= statusBadge($c['statut_global']) ?></td>
                        <td class="text-muted text-sm"><?= date('d/m/Y', strtotime($c['date_candidature'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stages actifs -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">🎓 Stages en cours</div>
            <a href="backoffice.php?page=stages" class="btn btn-ghost btn-sm">Voir tout</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Stagiaire</th>
                        <th>Fin le</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$stagesActifs): ?>
                    <tr><td colspan="4"><div class="empty-state"><div class="icon">🎓</div>Aucun stage actif</div></td></tr>
                    <?php else: ?>
                    <?php foreach ($stagesActifs as $s): ?>
                    <?php
                        $fin = strtotime($s['date_fin']);
                        $today = time();
                        $jours = (int)(($fin - $today) / 86400);
                        $finClass = $jours < 7 ? 'color:#dc2626;font-weight:600' : ($jours < 14 ? 'color:#d97706' : '');
                    ?>
                    <tr>
                        <td><a href="backoffice.php?page=stage_detail&id=<?= $s['id'] ?>" class="mono"><?= h($s['reference']) ?></a></td>
                        <td><?= h($s['prenom'] . ' ' . $s['nom']) ?></td>
                        <td style="<?= $finClass ?>"><?= date('d/m/Y', $fin) ?></td>
                        <td><?= statusBadge($s['statut']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
