<?php
$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.*,
           u.nom, u.prenom, u.email, u.civilite,
           st.niveau_etude, st.telephone,
           d.libelle AS direction, d.abreviation AS dir_abr,
           dom.libelle AS domaine,
           eu.nom AS enc_nom, eu.prenom AS enc_prenom,
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

// Renouvellements
$renouvStmt = $pdo->prepare("
    SELECT r.*, u.nom AS trait_nom, u.prenom AS trait_prenom
    FROM renouvellements_stage r
    LEFT JOIN utilisateurs u ON u.id = r.traite_par
    WHERE r.stage_id = ?
    ORDER BY r.numero_renouvellement
");
$renouvStmt->execute([$id]);
$renouvellements = $renouvStmt->fetchAll();

$encadrants = $pdo->query("SELECT id, nom, prenom FROM utilisateurs WHERE role IN ('habilite','administrateur','superviseur') AND actif = 1 ORDER BY nom")->fetchAll();

$peutGerer = hasRole('habilite', 'administrateur', 'superviseur');
$isActif   = in_array($stage['statut'], ['en_cours', 'renouvele', 'preparation'], true);
$peutRenouveler = $isActif && $stage['nb_renouvellements'] < 3;
?>

<div class="page-header">
    <div>
        <a href="backoffice.php?page=stages" class="btn btn-ghost btn-sm" style="margin-bottom:8px">← Retour</a>
        <h1>Stage <?= h($stage['reference']) ?></h1>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <?= statusBadge($stage['statut']) ?>
        <a href="backoffice.php?page=lettre_stage&id=<?= $id ?>" class="btn btn-ghost btn-sm" target="_blank">🖨 Lettre de stage</a>
        <?php if ($stage['statut'] === 'termine'): ?>
        <a href="backoffice.php?page=attestation_stage&id=<?= $id ?>" class="btn btn-ghost btn-sm" target="_blank">📄 Attestation</a>
        <?php endif; ?>
    </div>
</div>

<div class="section-grid">
    <!-- Infos stage -->
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">ℹ️ Informations du stage</div></div>
            <div class="card-body">
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:9px 16px;font-size:13.5px">
                    <dt class="text-muted">Référence</dt>
                    <dd class="mono"><?= h($stage['reference']) ?></dd>
                    <?php if ($stage['ref_cand']): ?>
                    <dt class="text-muted">Candidature</dt>
                    <dd><a href="backoffice.php?page=candidature_detail&id=<?= $stage['candidature_id'] ?>" class="mono"><?= h($stage['ref_cand']) ?></a></dd>
                    <?php endif; ?>
                    <dt class="text-muted">Stagiaire</dt>
                    <dd class="fw-600"><?= h($stage['civilite'] . ' ' . $stage['prenom'] . ' ' . $stage['nom']) ?></dd>
                    <dt class="text-muted">Email</dt>
                    <dd><?= h($stage['email']) ?></dd>
                    <dt class="text-muted">Direction</dt>
                    <dd><?= h($stage['direction'] ?? '—') ?></dd>
                    <dt class="text-muted">Domaine</dt>
                    <dd><?= h($stage['domaine'] ?? '—') ?></dd>
                    <dt class="text-muted">Encadrant</dt>
                    <dd><?= $stage['enc_nom'] ? h($stage['enc_prenom'] . ' ' . $stage['enc_nom']) : '<span class="text-muted">Non défini</span>' ?></dd>
                    <dt class="text-muted">Date de début</dt>
                    <dd><?= date('d/m/Y', strtotime($stage['date_debut'])) ?></dd>
                    <dt class="text-muted">Date de fin</dt>
                    <dd><?= date('d/m/Y', strtotime($stage['date_fin'])) ?></dd>
                    <dt class="text-muted">Durée totale</dt>
                    <dd><?= number_format((float)$stage['duree_totale_mois'], 1) ?> mois</dd>
                    <dt class="text-muted">Renouvellements</dt>
                    <dd><?= $stage['nb_renouvellements'] ?> / 3</dd>
                    <dt class="text-muted">Transport</dt>
                    <dd><?= $stage['remboursement_transport'] ? number_format((float)$stage['montant_transport'], 0, ',', ' ') . ' GNF/mois' : 'Non' ?></dd>
                </dl>
            </div>
        </div>

        <!-- Renouvellements -->
        <?php if ($renouvellements): ?>
        <div class="card">
            <div class="card-header"><div class="card-title">🔄 Historique des renouvellements</div></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Période proposée</th>
                            <th>Durée</th>
                            <th>Statut</th>
                            <th>Traité par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($renouvellements as $r): ?>
                        <tr>
                            <td><?= $r['numero_renouvellement'] ?></td>
                            <td class="text-sm">
                                <?= date('d/m/Y', strtotime($r['date_debut_proposee'])) ?> →
                                <?= date('d/m/Y', strtotime($r['date_fin_proposee'])) ?>
                            </td>
                            <td><?= $r['duree_ajoutee_mois'] ?> mois</td>
                            <td><?= statusBadge($r['statut']) ?></td>
                            <td class="text-sm"><?= $r['trait_nom'] ? h($r['trait_prenom'] . ' ' . $r['trait_nom']) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <?php if ($peutGerer): ?>
    <div>
        <!-- Actions de statut -->
        <?php if ($isActif): ?>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">⚙️ Actions</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
                <?php if ($stage['statut'] === 'preparation'): ?>
                <form method="POST" action="backoffice.php?action=gerer_stage">
            <?= csrfField() ?>
                    <input type="hidden" name="stage_id" value="<?= $id ?>">
                    <input type="hidden" name="sous_action" value="demarrer">
                    <button type="submit" class="btn btn-success" style="width:100%">▶ Démarrer le stage</button>
                </form>
                <?php endif; ?>

                <?php if (in_array($stage['statut'], ['en_cours', 'renouvele'], true)): ?>
                <form method="POST" action="backoffice.php?action=gerer_stage" onsubmit="return confirm('Confirmer la fin du stage ?')">
            <?= csrfField() ?>
                    <input type="hidden" name="stage_id" value="<?= $id ?>">
                    <input type="hidden" name="sous_action" value="terminer">
                    <button type="submit" class="btn btn-navy" style="width:100%">✓ Marquer comme terminé</button>
                </form>
                <form method="POST" action="backoffice.php?action=gerer_stage" onsubmit="return confirm('Confirmer l\'interruption du stage ?')">
            <?= csrfField() ?>
                    <input type="hidden" name="stage_id" value="<?= $id ?>">
                    <input type="hidden" name="sous_action" value="interrompre">
                    <button type="submit" class="btn btn-danger" style="width:100%">✗ Interrompre</button>
                </form>
                <?php endif; ?>

                <?php if ($peutRenouveler): ?>
                <button class="btn btn-amber" style="width:100%" onclick="openModal('modal-renouveler')">🔄 Demander un renouvellement</button>
                <?php endif; ?>

                <hr class="divider">
                <button class="btn btn-ghost" onclick="openModal('modal-encadrant')">👤 Changer l'encadrant</button>
                <button class="btn btn-ghost" onclick="openModal('modal-transport')">🚌 Transport</button>
                <button class="btn btn-ghost" onclick="openModal('modal-dates')">📅 Modifier les dates</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal renouvellement -->
<div class="modal-overlay" id="modal-renouveler">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Demande de renouvellement</div>
            <button class="modal-close" onclick="closeModal('modal-renouveler')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=gerer_stage">
            <?= csrfField() ?>
            <input type="hidden" name="stage_id" value="<?= $id ?>">
            <input type="hidden" name="sous_action" value="renouveler">
            <div class="modal-body">
                <div class="alert alert-info">Renouvellement n°<?= $stage['nb_renouvellements'] + 1 ?> — max 3 autorisés.</div>
                <div class="form-group" style="margin-bottom:14px">
                    <label>Nouvelle date de fin *</label>
                    <input type="date" name="date_fin_proposee" required min="<?= date('Y-m-d', strtotime($stage['date_fin'] . ' +1 day')) ?>">
                </div>
                <div class="form-group">
                    <label>Motif</label>
                    <textarea name="motif_demande" rows="3" placeholder="Raison du renouvellement..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-renouveler')">Annuler</button>
                <button type="submit" class="btn btn-amber">Soumettre la demande</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal encadrant -->
<div class="modal-overlay" id="modal-encadrant">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Changer l'encadrant</div>
            <button class="modal-close" onclick="closeModal('modal-encadrant')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=gerer_stage">
            <?= csrfField() ?>
            <input type="hidden" name="stage_id" value="<?= $id ?>">
            <input type="hidden" name="sous_action" value="encadrant">
            <div class="modal-body">
                <div class="form-group">
                    <label>Encadrant</label>
                    <select name="encadrant_id">
                        <option value="">— Non défini —</option>
                        <?php foreach ($encadrants as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $stage['encadrant_id'] == $e['id'] ? 'selected' : '' ?>>
                            <?= h($e['prenom'] . ' ' . $e['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-encadrant')">Annuler</button>
                <button type="submit" class="btn btn-navy">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal transport -->
<div class="modal-overlay" id="modal-transport">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Remboursement transport</div>
            <button class="modal-close" onclick="closeModal('modal-transport')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=gerer_stage">
            <?= csrfField() ?>
            <input type="hidden" name="stage_id" value="<?= $id ?>">
            <input type="hidden" name="sous_action" value="transport">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:14px">
                    <label>
                        <input type="checkbox" name="remboursement" value="1" <?= $stage['remboursement_transport'] ? 'checked' : '' ?>>
                        Remboursement transport activé
                    </label>
                </div>
                <div class="form-group">
                    <label>Montant mensuel (GNF)</label>
                    <input type="number" name="montant_transport" value="<?= (float)$stage['montant_transport'] ?>" min="0" step="1000">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-transport')">Annuler</button>
                <button type="submit" class="btn btn-navy">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal dates -->
<div class="modal-overlay" id="modal-dates">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Modifier les dates</div>
            <button class="modal-close" onclick="closeModal('modal-dates')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=gerer_stage">
            <?= csrfField() ?>
            <input type="hidden" name="stage_id" value="<?= $id ?>">
            <input type="hidden" name="sous_action" value="dates">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date de début</label>
                        <input type="date" name="date_debut" value="<?= h($stage['date_debut']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Date de fin</label>
                        <input type="date" name="date_fin" value="<?= h($stage['date_fin']) ?>" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-dates')">Annuler</button>
                <button type="submit" class="btn btn-navy">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>
