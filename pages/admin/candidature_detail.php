<?php
$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT c.*,
           u.nom, u.prenom, u.email, u.civilite,
           st.niveau_etude, st.telephone, st.ville, st.cv_fichier,
           d.libelle AS direction, dom.libelle AS domaine,
           o.titre AS offre_titre, o.reference AS offre_ref
    FROM candidatures c
    JOIN stagiaires st ON st.id = c.stagiaire_id
    JOIN utilisateurs u ON u.id = st.utilisateur_id
    LEFT JOIN directions d ON d.id = c.direction_id
    LEFT JOIN domaines dom ON dom.id = c.domaine_id
    LEFT JOIN offres_stage o ON o.id = c.offre_id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$cand = $stmt->fetch();

if (!$cand) {
    echo '<div class="alert alert-danger">Candidature introuvable.</div>';
    return;
}

// Validations
$validStmt = $pdo->prepare("
    SELECT v.*, u.nom, u.prenom, u.role
    FROM validations_candidature v
    LEFT JOIN utilisateurs u ON u.id = v.validateur_id
    WHERE v.candidature_id = ?
    ORDER BY v.niveau_validation
");
$validStmt->execute([$id]);
$validations = $validStmt->fetchAll();

// Stage associé
$stageStmt = $pdo->prepare("SELECT * FROM stages WHERE candidature_id = ?");
$stageStmt->execute([$id]);
$stage = $stageStmt->fetch();

// ID du stagiaire (déjà dans c.stagiaire_id via c.*)
$stagId = (int)($cand['stagiaire_id'] ?? 0);

$formStmt = $pdo->prepare("SELECT * FROM formations WHERE stagiaire_id = ? ORDER BY annee_fin DESC");
$formStmt->execute([$stagId]);
$formations = $formStmt->fetchAll();

$compStmt = $pdo->prepare("SELECT libelle FROM competences WHERE stagiaire_id = ?");
$compStmt->execute([$stagId]);
$competences = array_column($compStmt->fetchAll(), 'libelle');

// Pour modal programmation
$encStmt = $pdo->prepare("SELECT id, nom, prenom FROM utilisateurs WHERE role IN ('habilite','administrateur','superviseur') AND actif = 1 ORDER BY nom");
$encStmt->execute();
$encadrants = $encStmt->fetchAll();

$canValidNiv1 = hasRole('habilite', 'administrateur');
$canValidNiv2 = hasRole('superviseur', 'administrateur');

$niv1Done = array_filter($validations, fn($v) => $v['niveau_validation'] === 'niv1');
$niv2Done = array_filter($validations, fn($v) => $v['niveau_validation'] === 'niv2');
?>

<div class="page-header">
    <div>
        <a href="backoffice.php?page=candidatures" class="btn btn-ghost btn-sm" style="margin-bottom:8px">← Retour</a>
        <h1>Candidature <?= h($cand['reference']) ?></h1>
    </div>
    <div style="display:flex;gap:8px">
        <?= statusBadge($cand['statut_global']) ?>
        <?= workflowDots($cand['statut_global']) ?>
    </div>
</div>

<div class="section-grid">
    <!-- Infos candidature -->
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">👤 Stagiaire</div></div>
            <div class="card-body">
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:13.5px">
                    <dt class="text-muted">Nom complet</dt>
                    <dd class="fw-600"><?= h($cand['civilite'] . ' ' . $cand['prenom'] . ' ' . $cand['nom']) ?></dd>
                    <dt class="text-muted">Email</dt>
                    <dd><?= h($cand['email']) ?></dd>
                    <dt class="text-muted">Téléphone</dt>
                    <dd><?= h($cand['telephone'] ?? '—') ?></dd>
                    <dt class="text-muted">Ville</dt>
                    <dd><?= h($cand['ville'] ?? '—') ?></dd>
                    <dt class="text-muted">Niveau</dt>
                    <dd><?= h($cand['niveau_etude'] ?? '—') ?></dd>
                </dl>
                <hr class="divider">
                <?php if ($competences): ?>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">
                    <?php foreach ($competences as $comp): ?>
                    <span class="badge badge-blue"><?= h($comp) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px">
                    <a href="backoffice.php?page=stagiaire_detail&id=<?= $stagId ?>" class="btn btn-ghost btn-sm">Voir profil complet</a>
                    <?php if ($cand['cv_fichier']): ?>
                    <a href="/stage2/<?= h($cand['cv_fichier']) ?>" target="_blank" class="btn btn-ghost btn-sm" download>📎 Télécharger le CV</a>
                    <?php else: ?>
                    <span class="btn btn-ghost btn-sm" style="opacity:.5;cursor:default">📎 Pas de CV</span>
                    <?php endif; ?>
                    <a href="backoffice.php?page=analyse-cv&stagiaire_id=<?= $stagId ?>&candidature_id=<?= $id ?>" class="btn btn-ghost btn-sm">🤖 Analyse IA</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📋 Détails de la candidature</div></div>
            <div class="card-body">
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:13.5px">
                    <dt class="text-muted">Référence</dt>
                    <dd class="mono"><?= h($cand['reference']) ?></dd>
                    <dt class="text-muted">Type</dt>
                    <dd><?= $cand['type_candidature'] === 'offre' ? 'Sur offre' : 'Spontanée' ?></dd>
                    <?php if ($cand['offre_titre']): ?>
                    <dt class="text-muted">Offre</dt>
                    <dd><?= h($cand['offre_ref'] . ' — ' . $cand['offre_titre']) ?></dd>
                    <?php endif; ?>
                    <dt class="text-muted">Direction</dt>
                    <dd><?= h($cand['direction'] ?? '—') ?></dd>
                    <dt class="text-muted">Domaine</dt>
                    <dd><?= h($cand['domaine'] ?? '—') ?></dd>
                    <dt class="text-muted">Score</dt>
                    <dd>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="progress-wrap" style="width:100px">
                                <div class="progress-bar <?= $cand['score_tri'] >= 70 ? 'green' : ($cand['score_tri'] >= 40 ? 'amber' : 'red') ?>" style="width:<?= min(100,(float)$cand['score_tri']) ?>%"></div>
                            </div>
                            <span class="fw-600"><?= number_format((float)$cand['score_tri'], 1) ?>/100</span>
                        </div>
                    </dd>
                    <dt class="text-muted">Date de soumission</dt>
                    <dd><?= date('d/m/Y à H:i', strtotime($cand['date_candidature'])) ?></dd>
                </dl>
                <?php if ($cand['motivation']): ?>
                <hr class="divider">
                <div class="text-muted text-sm" style="margin-bottom:4px">Motivation</div>
                <div style="font-size:13.5px;line-height:1.7"><?= nl2br(h($cand['motivation'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Validations et actions -->
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">✅ Validations</div></div>
            <div class="card-body">
                <?php
                $niveauxLabels = ['niv1' => 'Niveau 1 — Habilité', 'niv2' => 'Niveau 2 — Superviseur'];
                foreach (['niv1', 'niv2'] as $niv):
                    $val = current(array_filter($validations, fn($v) => $v['niveau_validation'] === $niv));
                ?>
                <div style="margin-bottom:16px;padding:14px;border:1px solid var(--gray-line);border-radius:8px">
                    <div style="font-weight:600;font-size:13px;margin-bottom:8px"><?= $niveauxLabels[$niv] ?></div>
                    <?php if ($val): ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                            <?= statusBadge($val['statut']) ?>
                            <span class="text-muted text-sm"><?= h($val['prenom'] . ' ' . $val['nom']) ?></span>
                            <span class="text-muted text-sm"><?= $val['date_decision'] ? date('d/m/Y', strtotime($val['date_decision'])) : '' ?></span>
                        </div>
                        <?php if ($val['commentaire']): ?>
                        <div class="text-sm text-muted"><?= h($val['commentaire']) ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted text-sm">En attente</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <!-- Actions de validation -->
                <?php
                $statutGlobal = $cand['statut_global'];
                $peutValiderNiv1 = $canValidNiv1 && in_array($statutGlobal, ['soumise', 'niv1_en_cours', 'complement'], true);
                $peutValiderNiv2 = $canValidNiv2 && $statutGlobal === 'niv1_valide';
                ?>

                <?php if ($peutValiderNiv1 && !$stage): ?>
                <button class="btn btn-navy" onclick="openModal('modal-decision-niv1')">Décision Niveau 1</button>
                <?php endif; ?>

                <?php if ($peutValiderNiv2 && !$stage): ?>
                <button class="btn btn-red" onclick="openModal('modal-decision-niv2')">Décision Niveau 2</button>
                <?php endif; ?>

                <?php if ($statutGlobal === 'validee' && !$stage && hasRole('habilite','administrateur')): ?>
                <button class="btn btn-success" onclick="openModal('modal-programmer')">🎓 Programmer le stage</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($stage): ?>
        <div class="card">
            <div class="card-header"><div class="card-title">🎓 Stage associé</div></div>
            <div class="card-body">
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:13.5px">
                    <dt class="text-muted">Référence</dt>
                    <dd class="mono"><?= h($stage['reference']) ?></dd>
                    <dt class="text-muted">Début</dt>
                    <dd><?= date('d/m/Y', strtotime($stage['date_debut'])) ?></dd>
                    <dt class="text-muted">Fin</dt>
                    <dd><?= date('d/m/Y', strtotime($stage['date_fin'])) ?></dd>
                    <dt class="text-muted">Statut</dt>
                    <dd><?= statusBadge($stage['statut']) ?></dd>
                </dl>
                <a href="backoffice.php?page=stage_detail&id=<?= $stage['id'] ?>" class="btn btn-ghost btn-sm" style="margin-top:12px">Voir le stage</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($formations): ?>
        <div class="card" style="margin-top:16px">
            <div class="card-header"><div class="card-title">🎓 Formations</div></div>
            <div class="card-body">
                <?php foreach ($formations as $f): ?>
                <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--gray-line)">
                    <div class="fw-600 text-sm"><?= h($f['diplome'] ?? '') ?> <?= h($f['specialite'] ? '— ' . $f['specialite'] : '') ?></div>
                    <div class="text-muted text-sm"><?= h($f['etablissement'] ?? '') ?> <?= $f['annee_fin'] ? '(' . $f['annee_fin'] . ')' : '' ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal décision Niv1 -->
<div class="modal-overlay" id="modal-decision-niv1">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Décision — Niveau 1</div>
            <button class="modal-close" onclick="closeModal('modal-decision-niv1')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=valider_candidature">
            <?= csrfField() ?>
            <input type="hidden" name="candidature_id" value="<?= $id ?>">
            <input type="hidden" name="niveau" value="niv1">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:14px">
                    <label>Décision *</label>
                    <select name="statut_val" required>
                        <option value="valide">✓ Valider</option>
                        <option value="rejete">✗ Rejeter</option>
                        <option value="complement">⚠ Demander un complément</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Commentaire</label>
                    <textarea name="commentaire" rows="3" placeholder="Motif de la décision..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-decision-niv1')">Annuler</button>
                <button type="submit" class="btn btn-navy">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal décision Niv2 -->
<div class="modal-overlay" id="modal-decision-niv2">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Décision — Niveau 2 (Superviseur)</div>
            <button class="modal-close" onclick="closeModal('modal-decision-niv2')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=valider_candidature">
            <?= csrfField() ?>
            <input type="hidden" name="candidature_id" value="<?= $id ?>">
            <input type="hidden" name="niveau" value="niv2">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:14px">
                    <label>Décision finale *</label>
                    <select name="statut_val" required>
                        <option value="valide">✓ Approuver le stage</option>
                        <option value="rejete">✗ Rejeter définitivement</option>
                        <option value="complement">⚠ Demander un complément</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Commentaire</label>
                    <textarea name="commentaire" rows="3" placeholder="Motif..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-decision-niv2')">Annuler</button>
                <button type="submit" class="btn btn-red">Enregistrer la décision</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal programmer le stage -->
<?php if (!$stage): ?>
<div class="modal-overlay" id="modal-programmer">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">🎓 Programmer le stage</div>
            <button class="modal-close" onclick="closeModal('modal-programmer')">×</button>
        </div>
        <form method="POST" action="backoffice.php?action=creer_stage_depuis_cand">
            <?= csrfField() ?>
            <input type="hidden" name="candidature_id" value="<?= $id ?>">
            <div class="modal-body">
                <div class="alert alert-info">La durée initiale est limitée à <strong>31 jours</strong>. Des renouvellements pourront être demandés ensuite.</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date de début *</label>
                        <input type="date" name="date_debut" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Date de fin *</label>
                        <input type="date" name="date_fin" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    </div>
                    <div class="form-group full">
                        <label>Encadrant</label>
                        <select name="encadrant_id">
                            <option value="">— Non défini —</option>
                            <?php foreach ($encadrants as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= h($e['prenom'] . ' ' . $e['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="remboursement" value="1">
                            Remboursement transport
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Montant transport (GNF)</label>
                        <input type="number" name="montant_transport" value="0" min="0" step="1000">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-programmer')">Annuler</button>
                <button type="submit" class="btn btn-success">Créer le stage</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
