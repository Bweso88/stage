<?php
$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.*,
           u.nom, u.prenom, u.email, u.civilite,
           d.libelle AS direction,
           dom.libelle AS domaine
    FROM stages s
    JOIN stagiaires st ON st.id = s.stagiaire_id
    JOIN utilisateurs u ON u.id = st.utilisateur_id
    LEFT JOIN directions d ON d.id = s.direction_id
    LEFT JOIN domaines dom ON dom.id = s.domaine_id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$stage = $stmt->fetch();

if (!$stage) {
    echo '<div class="alert alert-danger">Stage introuvable.</div>';
    return;
}

$mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

function dateFrA($date, $mois) {
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $mois[(int)$d->format('m') - 1] . ' ' . $d->format('Y');
}

$dateDebutFr = dateFrA($stage['date_debut'], $mois);
$dateFinFr   = dateFrA($stage['date_fin'], $mois);
$dateLettres = dateFrA(date('Y-m-d'), $mois);
$annee       = date('Y');

$numRef = str_pad($id, 3, '0', STR_PAD_LEFT);

$civiliteAtt = match(strtolower($stage['civilite'] ?? '')) {
    'madame', 'mme'  => 'madame',
    'mademoiselle', 'mlle' => 'mademoiselle',
    default => 'monsieur',
};

// Thème = domaine si défini, sinon direction
$theme = $stage['domaine'] ?? $stage['direction'] ?? 'Non précisé';
$lieu  = $stage['direction'] ?? 'Fédération des MUCODEC';
?>

<div class="page-header no-print" style="margin-bottom:16px">
    <div>
        <a href="backoffice.php?page=stage_detail&id=<?= $id ?>" class="btn btn-ghost btn-sm">← Retour</a>
    </div>
    <button class="btn btn-navy btn-sm" onclick="window.print()">🖨 Imprimer / PDF</button>
</div>

<style>
@media print {
    .sidebar, .main-wrap > header, .page-header, .topbar, .flash-container, .no-print { display: none !important; }
    .main-wrap { margin-left: 0 !important; }
    .page-content { padding: 0 !important; }
    body { background: #fff !important; }
    .lettre-page { box-shadow: none !important; border: none !important; margin: 0 !important; }
}
.lettre-page {
    background: #fff;
    max-width: 780px;
    margin: 0 auto;
    padding: 60px 70px;
    box-shadow: 0 2px 20px rgba(0,0,0,.08);
    border: 1px solid #e5e7eb;
    font-family: 'Times New Roman', Times, serif;
    font-size: 13px;
    line-height: 1.8;
    color: #000;
}
.lettre-entete {
    text-align: center;
    margin-bottom: 40px;
}
.lettre-logo-title {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.lettre-logo-sub { font-size: 11px; color: #444; margin-top: 2px; }
.lettre-ref-line { font-size: 12px; margin-bottom: 10px; }
.lettre-date-line { text-align: right; font-size: 13px; margin-bottom: 40px; }
.lettre-titre {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    text-decoration: underline;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 30px 0 40px;
}
.lettre-corps p { margin-bottom: 16px; text-align: justify; }
.lettre-corps strong { font-weight: bold; }
.lettre-sig-zone { margin-top: 50px; text-align: right; font-size: 13px; }
.lettre-sig-zone p { margin: 2px 0; }
.lettre-footer { margin-top: 60px; border-top: 1px solid #888; padding-top: 8px; font-size: 11px; text-align: center; color: #444; }
</style>

<div class="lettre-page">

    <div class="lettre-entete">
        <div class="lettre-logo-title">Fédération des MUCODEC</div>
        <div class="lettre-logo-sub">Mutuelles Congolaises d'Epargne et de Crédit</div>
    </div>

    <div class="lettre-ref-line">
        N/Réf. : <strong><?= $numRef ?>AFS/DRH/SF/<?= $annee ?></strong>
    </div>

    <div class="lettre-date-line">
        Brazzaville, le <?= $dateLettres ?>
    </div>

    <div class="lettre-titre">Attestation de stage</div>

    <div class="lettre-corps">
        <p>
            La Directrice des Ressources Humaines à la <strong>Fédération des MUCODEC</strong>, soussignée,
        </p>

        <p>
            Atteste que (<?= $civiliteAtt ?>) : <strong><?= h(strtoupper($stage['nom']) . ' ' . $stage['prenom']) ?></strong>.
        </p>

        <p>
            A effectué un stage sur le thème : <strong><?= h($theme) ?></strong>.
        </p>

        <p>
            Qui s'est déroulé du : <strong><?= $dateDebutFr ?></strong> au <strong><?= $dateFinFr ?></strong>.
        </p>

        <p>
            Lieu : <strong><?= h($lieu) ?></strong>.
        </p>

        <p>
            En foi de quoi, la présente attestation lui est délivrée pour servir et valoir ce que de droit.
        </p>
    </div>

    <div class="lettre-sig-zone">
        <p>La Directrice,</p>
        <br><br><br>
        <p><strong>Nelly Christelle ELIRA</strong></p>
    </div>

    <div class="lettre-footer">
        1, rue Colbert - Centre-ville - BP : 13 237 - Tél. : (+242) 05 547 90 00 / 06 987 90 00 - Brazzaville – Congo
    </div>

</div>
