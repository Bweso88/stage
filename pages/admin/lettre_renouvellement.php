<?php
$pdo = getPDO();
$rid = (int)($_GET['id'] ?? 0); // renouvellement_id

$stmt = $pdo->prepare("
    SELECT r.*,
           s.reference AS ref_stage, s.direction_id, s.domaine_id, s.stagiaire_id,
           s.montant_transport, s.remboursement_transport,
           u.nom, u.prenom, u.civilite,
           d.libelle AS direction,
           dom.libelle AS domaine
    FROM renouvellements_stage r
    JOIN stages s ON s.id = r.stage_id
    JOIN stagiaires st ON st.id = s.stagiaire_id
    JOIN utilisateurs u ON u.id = st.utilisateur_id
    LEFT JOIN directions d ON d.id = s.direction_id
    LEFT JOIN domaines dom ON dom.id = s.domaine_id
    WHERE r.id = ?
");
$stmt->execute([$rid]);
$r = $stmt->fetch();

if (!$r) {
    echo '<div class="alert alert-danger">Renouvellement introuvable.</div>';
    return;
}

$mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

function dateFrR($date, $mois) {
    $d = new DateTime($date);
    return $d->format('d') . ' ' . $mois[(int)$d->format('m') - 1] . ' ' . $d->format('Y');
}

$dateDebutFr = dateFrR($r['date_debut_proposee'], $mois);
$dateFinFr   = dateFrR($r['date_fin_proposee'], $mois);
$dateLettres = dateFrR(date('Y-m-d'), $mois);
$annee       = date('Y');

$numRef = str_pad($rid, 3, '0', STR_PAD_LEFT);

$civiliteDest = match(strtolower($r['civilite'] ?? '')) {
    'madame', 'mme'  => 'Madame',
    'mademoiselle', 'mlle' => 'Mademoiselle',
    default => 'Monsieur',
};

$ordinals = ['', 'premier', 'deuxième', 'troisième'];
$ordinal  = $ordinals[$r['numero_renouvellement']] ?? ($r['numero_renouvellement'] . 'ème');
?>

<div class="page-header no-print" style="margin-bottom:16px">
    <div>
        <a href="backoffice.php?page=renouvellements" class="btn btn-ghost btn-sm">← Retour</a>
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
    padding: 50px 60px;
    box-shadow: 0 2px 20px rgba(0,0,0,.08);
    border: 1px solid #e5e7eb;
    font-family: 'Times New Roman', Times, serif;
    font-size: 13px;
    line-height: 1.6;
    color: #000;
}
.lettre-entete {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
}
.lettre-logo-title {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}
.lettre-logo-sub { font-size: 11px; color: #444; }
.lettre-dest-zone { text-align: right; font-size: 13px; min-width: 220px; }
.lettre-ref-line { font-size: 12px; margin-bottom: 20px; }
.lettre-date-line { text-align: right; font-size: 13px; margin-bottom: 24px; }
.lettre-objet { margin-bottom: 24px; font-size: 13px; }
.lettre-objet strong { text-decoration: underline; }
.lettre-corps p { margin-bottom: 14px; text-align: justify; }
.lettre-sig-zone { margin-top: 40px; text-align: right; font-size: 13px; }
.lettre-sig-zone p { margin: 2px 0; }
.lettre-ampli { margin-top: 50px; font-size: 12px; border-top: 1px solid #000; padding-top: 8px; }
.lettre-footer { margin-top: 40px; border-top: 1px solid #888; padding-top: 8px; font-size: 11px; text-align: center; color: #444; }
</style>

<div class="lettre-page">

    <div class="lettre-entete">
        <div>
            <div class="lettre-logo-title">Fédération des MUCODEC</div>
            <div class="lettre-logo-sub">Mutuelles Congolaises d'Epargne et de Crédit</div>
        </div>
        <div class="lettre-dest-zone">
            <div><?= $civiliteDest ?></div>
            <div><strong><?= h(strtoupper($r['nom']) . ' ' . $r['prenom']) ?></strong></div>
            <div>Brazzaville</div>
        </div>
    </div>

    <div class="lettre-ref-line">
        N/Réf. : <strong><?= $numRef ?>L/DGM/DRH/SRH/<?= $annee ?></strong>
    </div>

    <div class="lettre-date-line">
        Brazzaville, le <?= $dateLettres ?>
    </div>

    <div class="lettre-objet">
        <strong>Objet : Renouvellement</strong>
    </div>

    <div class="lettre-corps">
        <p>
            Suite à votre stage au sein de la Fédération des MUCODEC
            <?= $r['direction'] ? 'à la ' . h($r['direction']) : '' ?>,
            nous avons l'honneur de vous informer que votre stage fait l'objet
            d'un <strong><?= $ordinal ?> renouvellement</strong>,
            et ce du <strong><?= $dateDebutFr ?></strong> au <strong><?= $dateFinFr ?></strong>.
        </p>

        <?php if ($r['remboursement_transport'] && (int)$r['montant_transport'] > 0): ?>
        <p>
            Une indemnité de transport d'un montant de
            <strong><?= number_format((int)$r['montant_transport'], 0, ',', ' ') ?> Francs CFA</strong>
            vous sera allouée mensuellement.
        </p>
        <?php endif; ?>

        <p>
            Veuillez agréer, <?= $civiliteDest ?>, l'expression de nos salutations distinguées.
        </p>
    </div>

    <div class="lettre-sig-zone">
        <p>Le Directeur Général Adjoint,</p>
        <br><br><br>
        <p><strong>Romaric METALA</strong></p>
    </div>

    <div class="lettre-ampli">
        <strong>Ampliations :</strong> DRBP &nbsp;–&nbsp; CLM TALANGAI &nbsp;–&nbsp; PV BIKAROUA &nbsp;–&nbsp; CHRONO
    </div>

    <div class="lettre-footer">
        Avenue Paul Doumer BP 13 237 - Tél. : (242) 81 07 57 &nbsp;Fax : (242) 81 01 68 - Brazzaville-Congo
    </div>

</div>
