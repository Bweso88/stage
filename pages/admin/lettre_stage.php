<?php
$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.*,
           u.nom, u.prenom, u.email, u.civilite,
           d.libelle AS direction, d.abreviation AS dir_abr,
           dom.libelle AS domaine,
           eu.nom AS enc_nom, eu.prenom AS enc_prenom, eu.civilite AS enc_civ,
           c.reference AS ref_cand
    FROM stages s
    JOIN stagiaires st ON st.id = s.stagiaire_id
    JOIN utilisateurs u ON u.id = st.utilisateur_id
    LEFT JOIN directions d ON d.id = s.direction_id
    LEFT JOIN domaines dom ON dom.id = s.domaine_id
    LEFT JOIN utilisateurs eu ON eu.id = s.encadrant_id
    LEFT JOIN candidatures c ON c.id = s.candidature_id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$stage = $stmt->fetch();

if (!$stage) {
    echo '<div class="alert alert-danger">Stage introuvable.</div>';
    return;
}

$dateDebutFr = (new DateTime($stage['date_debut']))->format('d') . ' ' .
    ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'][(int)(new DateTime($stage['date_debut']))->format('m')-1] . ' ' .
    (new DateTime($stage['date_debut']))->format('Y');
$dateFinFr   = (new DateTime($stage['date_fin']))->format('d') . ' ' .
    ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'][(int)(new DateTime($stage['date_fin']))->format('m')-1] . ' ' .
    (new DateTime($stage['date_fin']))->format('Y');
$dateLettres = (new DateTime())->format('d') . ' ' .
    ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'][(int)(new DateTime())->format('m')-1] . ' ' .
    (new DateTime())->format('Y');
?>

<div class="page-header" style="margin-bottom:16px">
    <div>
        <a href="backoffice.php?page=stage_detail&id=<?= $id ?>" class="btn btn-ghost btn-sm">← Retour</a>
    </div>
    <div style="display:flex;gap:8px">
        <a href="api/generer-lettre.php?stage_id=<?= $id ?>" class="btn btn-navy btn-sm">📥 Télécharger DOCX</a>
        <button class="btn btn-red btn-sm" onclick="window.print()">🖨 Imprimer</button>
    </div>
</div>

<style>
@media print {
    .sidebar, .main-wrap > header, .page-header, .topbar, .flash-container { display: none !important; }
    .main-wrap { margin-left: 0 !important; }
    .page-content { padding: 0 !important; }
    .lettre-wrap { box-shadow: none !important; border: none !important; }
}
.lettre-wrap {
    background: #fff;
    max-width: 760px;
    margin: 0 auto;
    padding: 60px;
    box-shadow: 0 2px 20px rgba(0,0,0,.08);
    border: 1px solid var(--gray-line);
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
}
.lettre-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
.lettre-brand { font-size: 24px; font-weight: 700; color: #1b2a6b; }
.lettre-brand span { color: #e8001c; }
.lettre-date { text-align: right; font-size: 13px; color: #6b7280; }
.lettre-objet { margin: 24px 0; font-weight: 600; font-size: 14px; border-left: 3px solid #1b2a6b; padding-left: 12px; }
.lettre-corps { font-size: 13.5px; line-height: 1.85; color: #374151; }
.lettre-corps p { margin-bottom: 14px; }
.lettre-info { background: #f0f4ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 16px 20px; margin: 20px 0; }
.lettre-info table { width: 100%; font-size: 13px; }
.lettre-info td { padding: 4px 8px; }
.lettre-info td:first-child { font-weight: 600; color: #1b2a6b; width: 160px; }
.lettre-signature { margin-top: 40px; display: flex; justify-content: space-between; }
.lettre-sig-bloc { font-size: 13px; }
.lettre-sig-bloc .sig-label { font-weight: 600; margin-bottom: 4px; }
.lettre-sig-ligne { margin-top: 50px; border-top: 1px solid #d1d5db; width: 180px; padding-top: 4px; font-size: 12px; color: #6b7280; }
.lettre-ref { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #6b7280; margin-top: 32px; text-align: center; }
</style>

<div class="lettre-wrap">
    <div class="lettre-header">
        <div>
            <div class="lettre-brand">Stag<span>IA</span></div>
            <div style="font-size:12px;color:#6b7280;margin-top:4px">Gestion des stages</div>
        </div>
        <div class="lettre-date">
            Conakry, le <?= $dateLettres ?>
        </div>
    </div>

    <div class="lettre-objet">Objet : Lettre de stage — <?= h($stage['reference']) ?></div>

    <div class="lettre-corps">
        <p>Madame, Monsieur,</p>

        <p>Nous avons le plaisir de confirmer par la présente que <?= h($stage['civilite'] . ' ' . $stage['prenom'] . ' ' . $stage['nom']) ?> effectue un stage au sein de notre organisation selon les modalités suivantes :</p>

        <div class="lettre-info">
            <table>
                <tr>
                    <td>Référence du stage</td>
                    <td><?= h($stage['reference']) ?></td>
                </tr>
                <?php if ($stage['ref_cand']): ?>
                <tr>
                    <td>Réf. candidature</td>
                    <td><?= h($stage['ref_cand']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Stagiaire</td>
                    <td><?= h($stage['civilite'] . ' ' . $stage['prenom'] . ' ' . $stage['nom']) ?></td>
                </tr>
                <tr>
                    <td>Direction d'accueil</td>
                    <td><?= h($stage['direction'] ?? 'Non définie') ?></td>
                </tr>
                <tr>
                    <td>Domaine</td>
                    <td><?= h($stage['domaine'] ?? 'Non défini') ?></td>
                </tr>
                <tr>
                    <td>Date de début</td>
                    <td><?= $dateDebutFr ?></td>
                </tr>
                <tr>
                    <td>Date de fin</td>
                    <td><?= $dateFinFr ?></td>
                </tr>
                <tr>
                    <td>Durée totale</td>
                    <td><?= number_format((float)$stage['duree_totale_mois'], 1) ?> mois</td>
                </tr>
                <?php if ($stage['enc_nom']): ?>
                <tr>
                    <td>Encadrant(e)</td>
                    <td><?= h($stage['enc_civ'] . ' ' . $stage['enc_prenom'] . ' ' . $stage['enc_nom']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($stage['remboursement_transport']): ?>
                <tr>
                    <td>Transport</td>
                    <td><?= number_format((float)$stage['montant_transport'], 0, ',', ' ') ?> GNF/mois</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <p>Cette lettre atteste de la validité du stage susmentionné et peut être présentée à toute entité en faisant la demande.</p>

        <p>Nous vous prions d'agréer, Madame, Monsieur, l'expression de nos salutations distinguées.</p>
    </div>

    <div class="lettre-signature">
        <div class="lettre-sig-bloc">
            <div class="sig-label">Le/La Stagiaire</div>
            <div class="lettre-sig-ligne"><?= h($stage['prenom'] . ' ' . $stage['nom']) ?></div>
        </div>
        <div class="lettre-sig-bloc" style="text-align:right">
            <div class="sig-label">La Direction</div>
            <div class="lettre-sig-ligne" style="margin-left:auto">Signature &amp; Cachet</div>
        </div>
    </div>

    <div class="lettre-ref">Réf. <?= h($stage['reference']) ?> | Émise le <?= $dateLettres ?></div>
</div>
