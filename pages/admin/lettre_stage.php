<?php
// pages/admin/lettre_stage.php — Lettre de stage imprimable
requireAdmin();
$pdo = getPDO();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { flash('Stage introuvable.', 'error'); redirect('backoffice.php?page=stages'); }

$stmt = $pdo->prepare('
    SELECT sg.*,
           u.nom, u.prenom, u.email, u.civilite,
           d.libelle AS direction_libelle,
           dom.libelle AS domaine_libelle,
           enc.nom AS enc_nom, enc.prenom AS enc_prenom,
           c.reference AS ref_cand
    FROM stages sg
    JOIN stagiaires st ON sg.stagiaire_id = st.id
    JOIN utilisateurs u ON st.utilisateur_id = u.id
    LEFT JOIN directions d ON sg.direction_id = d.id
    LEFT JOIN domaines dom ON sg.domaine_id = dom.id
    LEFT JOIN utilisateurs enc ON sg.encadrant_id = enc.id
    LEFT JOIN candidatures c ON sg.candidature_id = c.id
    WHERE sg.id = ?
');
$stmt->execute([$id]);
$stage = $stmt->fetch();
if (!$stage) { flash('Stage introuvable.', 'error'); redirect('backoffice.php?page=stages'); }

$mois = 0;
if ($stage['date_debut'] && $stage['date_fin']) {
    $d1   = new DateTime($stage['date_debut']);
    $d2   = new DateTime($stage['date_fin']);
    $mois = round($d1->diff($d2)->days / 30, 1);
}

$civilite = $stage['civilite'] === 'F' ? 'Mme' : 'M.';
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Lettre de stage — <?= h($stage['reference']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<style>
@page { size: A4; margin: 2cm 2.5cm; }
*{box-sizing:border-box}
body{font-family:'Montserrat',sans-serif;font-size:12pt;color:#1a1a1a;background:#fff;margin:0;padding:20px}
.no-print{margin-bottom:20px;display:flex;gap:10px}
@media print{.no-print{display:none!important}body{padding:0}}
.header-org{text-align:center;border-bottom:3px double #1b2a6b;padding-bottom:16px;margin-bottom:20px}
.header-org h1{font-size:18pt;color:#1b2a6b;margin:0 0 4px}
.header-org p{margin:0;font-size:10pt;color:#4b5563}
.ref-box{text-align:right;font-size:10pt;color:#6b7280;margin-bottom:20px}
.titre{text-align:center;font-size:16pt;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#1b2a6b;margin:30px 0 6px}
.sous-titre{text-align:center;font-size:10pt;color:#6b7280;margin-bottom:30px}
.corps{text-align:justify;line-height:1.8;margin-bottom:20px}
.corps strong{color:#1b2a6b}
.info-box{border:1.5px solid #1b2a6b;border-radius:6px;padding:14px 20px;margin:20px 0;background:#f0f4ff}
.info-box table{width:100%;font-size:11pt}
.info-box td:first-child{color:#6b7280;width:40%;padding-right:12px}
.signature{margin-top:50px;display:flex;justify-content:space-between;align-items:flex-end}
.signature .bloc{text-align:center;min-width:200px}
.signature .ligne{border-top:1px solid #1b2a6b;margin-top:40px;padding-top:6px;font-size:10pt;color:#4b5563}
.footer-note{margin-top:40px;border-top:1px solid #e0e3ed;padding-top:12px;font-size:9pt;color:#9ca3af;text-align:center}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:6px;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;border:none}
.btn-navy{background:#1b2a6b;color:#fff}
.btn-ghost{background:#f4f5f7;color:#2c3e50;border:1px solid #e0e3ed}
</style>
</head>
<body>

<div class="no-print">
  <button class="btn btn-navy" onclick="window.print()">🖨 Imprimer</button>
  <a href="api/generer-lettre.php?stage_id=<?= $id ?>" class="btn btn-ghost">⬇ Télécharger .docx</a>
  <a href="backoffice.php?page=stage_detail&id=<?= $id ?>" class="btn btn-ghost">← Retour au stage</a>
</div>

<div class="header-org">
  <h1>ORGANISATION</h1>
  <p>Département des Ressources Humaines — Direction des Stages</p>
</div>

<div class="ref-box">
  Fait à [Ville], le <?= date('d/m/Y') ?><br>
  Réf. : <?= h($stage['reference']) ?>
</div>

<div class="titre">Lettre de Stage</div>
<div class="sous-titre">Délivrée en vertu de la convention de stage</div>

<div class="corps">
  <p>Nous, soussignés, responsables de l'organisation, certifions par la présente que :</p>
</div>

<div class="info-box">
  <table>
    <tr><td>Stagiaire</td><td><strong><?= h($civilite . ' ' . $stage['prenom'] . ' ' . strtoupper($stage['nom'])) ?></strong></td></tr>
    <tr><td>Email</td><td><?= h($stage['email']) ?></td></tr>
    <tr><td>Direction d'accueil</td><td><?= h($stage['direction_libelle'] ?? '—') ?></td></tr>
    <tr><td>Domaine</td><td><?= h($stage['domaine_libelle'] ?? '—') ?></td></tr>
    <tr><td>Encadrant</td><td><?= $stage['enc_prenom'] ? h($stage['enc_prenom'] . ' ' . $stage['enc_nom']) : '—' ?></td></tr>
    <tr><td>Période</td><td>Du <?= $stage['date_debut'] ? date('d/m/Y', strtotime($stage['date_debut'])) : '—' ?> au <?= $stage['date_fin'] ? date('d/m/Y', strtotime($stage['date_fin'])) : '—' ?></td></tr>
    <tr><td>Durée</td><td><strong><?= $mois ?> mois</strong></td></tr>
    <tr><td>Réf. candidature</td><td><code><?= h($stage['ref_cand'] ?? '—') ?></code></td></tr>
    <?php if ($stage['remboursement_transport']): ?>
    <tr><td>Remboursement transport</td><td><?= number_format((float)$stage['montant_transport'], 0, ',', ' ') ?> GNF/mois</td></tr>
    <?php endif ?>
  </table>
</div>

<div class="corps">
  <p>
    effectue un stage au sein de notre organisation dans les conditions définies par la convention de stage
    et se soumet aux règlements intérieurs en vigueur.
  </p>
  <p>
    Ce stage est effectué à titre de formation professionnelle, dans le cadre de la candidature
    référencée <strong><?= h($stage['ref_cand'] ?? $stage['reference']) ?></strong>.
  </p>
  <p>
    La présente lettre est délivrée à l'intéressé(e) pour servir et valoir ce que de droit.
  </p>
</div>

<div class="signature">
  <div class="bloc">
    <div class="ligne">Le/La Stagiaire<br><?= h($civilite . ' ' . $stage['prenom'] . ' ' . $stage['nom']) ?></div>
  </div>
  <div class="bloc">
    <div class="ligne">Le Responsable RH<br>Signature &amp; Cachet</div>
  </div>
</div>

<div class="footer-note">
  Stage — Système de gestion des stages • Réf. <?= h($stage['reference']) ?> • Généré le <?= date('d/m/Y à H:i') ?>
</div>

</body>
</html>
