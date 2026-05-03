<?php
$pdo = getPDO();

// Stats générales
$totalCand    = (int)$pdo->query("SELECT COUNT(*) FROM candidatures")->fetchColumn();
$candValidees = (int)$pdo->query("SELECT COUNT(*) FROM candidatures WHERE statut_global = 'validee'")->fetchColumn();
$candRejetees = (int)$pdo->query("SELECT COUNT(*) FROM candidatures WHERE statut_global IN ('niv1_rejete','rejetee')")->fetchColumn();

$totalStages  = (int)$pdo->query("SELECT COUNT(*) FROM stages")->fetchColumn();
$stagesActifs = (int)$pdo->query("SELECT COUNT(*) FROM stages WHERE statut IN ('en_cours','renouvele','preparation')")->fetchColumn();
$stagesTermin = (int)$pdo->query("SELECT COUNT(*) FROM stages WHERE statut = 'termine'")->fetchColumn();

$totalStagiaires = (int)$pdo->query("SELECT COUNT(*) FROM stagiaires")->fetchColumn();
$totalOffres     = (int)$pdo->query("SELECT COUNT(*) FROM offres_stage WHERE statut = 'ouverte'")->fetchColumn();

// Candidatures par direction
$parDirection = $pdo->query("
    SELECT d.libelle, COUNT(*) AS nb
    FROM candidatures c
    JOIN directions d ON d.id = c.direction_id
    GROUP BY d.id
    ORDER BY nb DESC
    LIMIT 10
")->fetchAll();

// Candidatures par mois (12 derniers)
$parMois = $pdo->query("
    SELECT DATE_FORMAT(date_candidature, '%Y-%m') AS mois, COUNT(*) AS nb
    FROM candidatures
    WHERE date_candidature >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY mois
    ORDER BY mois
")->fetchAll();

// Stages par direction
$stagesParDir = $pdo->query("
    SELECT d.libelle, COUNT(*) AS nb
    FROM stages s
    JOIN directions d ON d.id = s.direction_id
    GROUP BY d.id
    ORDER BY nb DESC
")->fetchAll();

// Niveaux des stagiaires
$niveaux = $pdo->query("
    SELECT niveau_etude, COUNT(*) AS nb
    FROM stagiaires
    GROUP BY niveau_etude
    ORDER BY nb DESC
")->fetchAll();
?>

<div class="page-header">
    <h1>Rapports & Statistiques</h1>
    <div style="display:flex;gap:8px">
        <a href="backoffice.php?action=export_rapport&type=candidatures" class="btn btn-ghost btn-sm">📊 Export candidatures CSV</a>
        <a href="backoffice.php?action=export_rapport&type=stages" class="btn btn-ghost btn-sm">📊 Export stages CSV</a>
    </div>
</div>

<!-- Stats globales -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-icon navy">📋</div>
        <div><div class="stat-value"><?= $totalCand ?></div><div class="stat-label">Candidatures</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✓</div>
        <div><div class="stat-value"><?= $candValidees ?></div><div class="stat-label">Validées</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">✗</div>
        <div><div class="stat-value"><?= $candRejetees ?></div><div class="stat-label">Rejetées</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon navy">🎓</div>
        <div><div class="stat-value"><?= $totalStages ?></div><div class="stat-label">Stages total</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">▶</div>
        <div><div class="stat-value"><?= $stagesActifs ?></div><div class="stat-label">Stages actifs</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">■</div>
        <div><div class="stat-value"><?= $stagesTermin ?></div><div class="stat-label">Terminés</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon navy">👥</div>
        <div><div class="stat-value"><?= $totalStagiaires ?></div><div class="stat-label">Stagiaires</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">📢</div>
        <div><div class="stat-value"><?= $totalOffres ?></div><div class="stat-label">Offres ouvertes</div></div>
    </div>
</div>

<div class="section-grid">
    <!-- Candidatures par direction -->
    <div class="card">
        <div class="card-header"><div class="card-title">📋 Candidatures par direction</div></div>
        <div class="card-body">
            <?php if (!$parDirection): ?>
            <div class="empty-state">Aucune donnée</div>
            <?php else: ?>
            <?php $maxDir = max(array_column($parDirection, 'nb')); ?>
            <?php foreach ($parDirection as $row): ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <span><?= h($row['libelle']) ?></span>
                    <span class="fw-600"><?= $row['nb'] ?></span>
                </div>
                <div class="progress-wrap">
                    <div class="progress-bar" style="width:<?= $maxDir > 0 ? round($row['nb'] / $maxDir * 100) : 0 ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stages par direction -->
    <div class="card">
        <div class="card-header"><div class="card-title">🎓 Stages par direction</div></div>
        <div class="card-body">
            <?php if (!$stagesParDir): ?>
            <div class="empty-state">Aucune donnée</div>
            <?php else: ?>
            <?php $maxStDir = max(array_column($stagesParDir, 'nb')); ?>
            <?php foreach ($stagesParDir as $row): ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <span><?= h($row['libelle']) ?></span>
                    <span class="fw-600"><?= $row['nb'] ?></span>
                </div>
                <div class="progress-wrap">
                    <div class="progress-bar green" style="width:<?= $maxStDir > 0 ? round($row['nb'] / $maxStDir * 100) : 0 ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Niveaux d'étude -->
    <div class="card">
        <div class="card-header"><div class="card-title">📚 Niveaux d'étude</div></div>
        <div class="card-body">
            <?php if (!$niveaux): ?>
            <div class="empty-state">Aucune donnée</div>
            <?php else: ?>
            <?php $maxNiv = max(array_column($niveaux, 'nb')); ?>
            <?php foreach ($niveaux as $n): ?>
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                    <span class="badge badge-blue"><?= h($n['niveau_etude']) ?></span>
                    <span class="fw-600"><?= $n['nb'] ?> stagiaire(s)</span>
                </div>
                <div class="progress-wrap">
                    <div class="progress-bar amber" style="width:<?= $maxNiv > 0 ? round($n['nb'] / $maxNiv * 100) : 0 ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Candidatures par mois -->
    <div class="card">
        <div class="card-header"><div class="card-title">📅 Candidatures par mois</div></div>
        <div class="card-body">
            <?php if (!$parMois): ?>
            <div class="empty-state">Aucune donnée</div>
            <?php else: ?>
            <?php $maxM = max(array_column($parMois, 'nb')); ?>
            <div style="display:flex;align-items:flex-end;gap:4px;height:120px">
                <?php foreach ($parMois as $m): ?>
                <?php $h = $maxM > 0 ? round($m['nb'] / $maxM * 100) : 0; ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;gap:4px" title="<?= h($m['mois']) ?>: <?= $m['nb'] ?> candidature(s)">
                    <span class="text-sm fw-600"><?= $m['nb'] ?></span>
                    <div style="width:100%;background:var(--navy);border-radius:4px 4px 0 0;height:<?= max(4,$h) ?>%"></div>
                    <span style="font-size:10px;color:var(--text-muted);white-space:nowrap"><?= substr($m['mois'], -2) ?>/<?= substr($m['mois'], 2, 2) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
