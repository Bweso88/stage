<?php
define('STAGIA_LOADED', true);
require_once __DIR__ . '/config.php';

// ─── Déconnexion ──────────────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    redirect('/admin.php');
}

requireAdmin();

$pdo    = getPDO();
$action = $_GET['action'] ?? '';

// ─────────────────────────────────────────────────────────────────────────────
// ACTIONS GET
// ─────────────────────────────────────────────────────────────────────────────

// Activer/désactiver utilisateur
if (isset($_GET['toggle_user'])) {
    $uid = (int)$_GET['toggle_user'];
    $stmt = $pdo->prepare("SELECT actif FROM utilisateurs WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    if ($u) {
        $pdo->prepare("UPDATE utilisateurs SET actif = ? WHERE id = ?")->execute([!$u['actif'], $uid]);
        flash($u['actif'] ? 'Utilisateur désactivé.' : 'Utilisateur activé.');
    }
    redirect('/backoffice.php?page=utilisateurs');
}

if (isset($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT actif FROM utilisateurs WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    if ($u) {
        $pdo->prepare("UPDATE utilisateurs SET actif = ? WHERE id = ?")->execute([!$u['actif'], $uid]);
        flash($u['actif'] ? 'Utilisateur désactivé.' : 'Utilisateur activé.');
    }
    redirect('/backoffice.php?page=utilisateurs');
}

// Activer/désactiver offre
if (isset($_GET['toggle_offre'])) {
    $oid = (int)$_GET['toggle_offre'];
    $stmt = $pdo->prepare("SELECT statut FROM offres_stage WHERE id = ?");
    $stmt->execute([$oid]);
    $o = $stmt->fetch();
    if ($o) {
        $newStatut = $o['statut'] === 'ouverte' ? 'fermee' : 'ouverte';
        $pdo->prepare("UPDATE offres_stage SET statut = ? WHERE id = ?")->execute([$newStatut, $oid]);
        flash('Statut de l\'offre mis à jour.');
    }
    redirect('/backoffice.php?page=offres');
}

// Activer/désactiver direction
if (isset($_GET['toggle_dir'])) {
    $did = (int)$_GET['toggle_dir'];
    $stmt = $pdo->prepare("SELECT actif FROM directions WHERE id = ?");
    $stmt->execute([$did]);
    $d = $stmt->fetch();
    if ($d) {
        $pdo->prepare("UPDATE directions SET actif = ? WHERE id = ?")->execute([!$d['actif'], $did]);
        flash('Direction mise à jour.');
    }
    redirect('/backoffice.php?page=directions');
}

// Activer/désactiver domaine
if (isset($_GET['toggle_dom'])) {
    $domid = (int)$_GET['toggle_dom'];
    $stmt = $pdo->prepare("SELECT actif FROM domaines WHERE id = ?");
    $stmt->execute([$domid]);
    $d = $stmt->fetch();
    if ($d) {
        $pdo->prepare("UPDATE domaines SET actif = ? WHERE id = ?")->execute([!$d['actif'], $domid]);
        flash('Domaine mis à jour.');
    }
    redirect('/backoffice.php?page=directions');
}

// Désactiver direction
if (isset($_GET['del_dir'])) {
    $did = (int)$_GET['del_dir'];
    $pdo->prepare("UPDATE directions SET actif = 0 WHERE id = ?")->execute([$did]);
    flash('Direction désactivée.');
    redirect('/backoffice.php?page=directions');
}

// Désactiver domaine
if (isset($_GET['del_dom'])) {
    $domid = (int)$_GET['del_dom'];
    $pdo->prepare("UPDATE domaines SET actif = 0 WHERE id = ?")->execute([$domid]);
    flash('Domaine désactivé.');
    redirect('/backoffice.php?page=directions');
}

// Export CSV stagiaires
if (($_GET['page'] ?? '') === 'stagiaires' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->query("
        SELECT u.nom, u.prenom, u.email, u.civilite,
               s.niveau_etude, s.telephone, s.ville, s.nationalite,
               s.date_inscription,
               d.libelle AS domaine
        FROM stagiaires s
        JOIN utilisateurs u ON u.id = s.utilisateur_id
        LEFT JOIN domaines d ON d.id = s.domaine_principal_id
        ORDER BY s.date_inscription DESC
    ");
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="stagiaires-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Nom', 'Prénom', 'Email', 'Civilité', 'Niveau', 'Téléphone', 'Ville', 'Nationalité', 'Domaine', 'Inscription'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['nom'], $r['prenom'], $r['email'], $r['civilite'],
            $r['niveau_etude'], $r['telephone'], $r['ville'], $r['nationalite'],
            $r['domaine'], date('d/m/Y', strtotime($r['date_inscription']))
        ], ';');
    }
    fclose($out);
    exit;
}

// Export rapport CSV
if ($action === 'export_rapport') {
    $type = $_GET['type'] ?? '';
    if ($type === 'candidatures') {
        $stmt = $pdo->query("
            SELECT c.reference, u.nom, u.prenom, c.type_candidature,
                   c.statut_global, c.score_tri,
                   d.libelle AS direction, dom.libelle AS domaine,
                   c.date_candidature
            FROM candidatures c
            JOIN stagiaires s ON s.id = c.stagiaire_id
            JOIN utilisateurs u ON u.id = s.utilisateur_id
            LEFT JOIN directions d ON d.id = c.direction_id
            LEFT JOIN domaines dom ON dom.id = c.domaine_id
            ORDER BY c.date_candidature DESC
        ");
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="candidatures-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Référence', 'Nom', 'Prénom', 'Type', 'Statut', 'Score', 'Direction', 'Domaine', 'Date'], ';');
        foreach ($stmt->fetchAll() as $r) {
            fputcsv($out, [
                $r['reference'], $r['nom'], $r['prenom'], $r['type_candidature'],
                $r['statut_global'], $r['score_tri'], $r['direction'], $r['domaine'],
                date('d/m/Y', strtotime($r['date_candidature']))
            ], ';');
        }
        fclose($out);
        exit;
    }
    if ($type === 'stages') {
        $stmt = $pdo->query("
            SELECT s.reference, u.nom, u.prenom,
                   d.libelle AS direction, dom.libelle AS domaine,
                   s.date_debut, s.date_fin, s.duree_totale_mois,
                   s.statut, s.nb_renouvellements
            FROM stages s
            JOIN stagiaires st ON st.id = s.stagiaire_id
            JOIN utilisateurs u ON u.id = st.utilisateur_id
            LEFT JOIN directions d ON d.id = s.direction_id
            LEFT JOIN domaines dom ON dom.id = s.domaine_id
            ORDER BY s.date_debut DESC
        ");
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="stages-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Référence', 'Nom', 'Prénom', 'Direction', 'Domaine', 'Début', 'Fin', 'Durée (mois)', 'Statut', 'Renouvellements'], ';');
        foreach ($stmt->fetchAll() as $r) {
            fputcsv($out, [
                $r['reference'], $r['nom'], $r['prenom'], $r['direction'], $r['domaine'],
                date('d/m/Y', strtotime($r['date_debut'])),
                date('d/m/Y', strtotime($r['date_fin'])),
                $r['duree_totale_mois'], $r['statut'], $r['nb_renouvellements']
            ], ';');
        }
        fclose($out);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTIONS POST
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    // Valider candidature
    if ($action === 'valider_candidature') {
        $cid     = (int)($_POST['candidature_id'] ?? 0);
        $niveau  = $_POST['niveau'] ?? '';
        $statut  = $_POST['statut_val'] ?? '';
        $comment = trim($_POST['commentaire'] ?? '');

        if (!in_array($niveau, ['niv1', 'niv2'], true) || !in_array($statut, ['valide', 'rejete', 'complement'], true)) {
            flash('Paramètres invalides.', 'error');
            redirect('/backoffice.php?page=candidature_detail&id=' . $cid);
        }

        if ($niveau === 'niv1' && !hasRole('habilite', 'administrateur')) {
            flash('Accès refusé.', 'error');
            redirect('/backoffice.php?page=candidatures');
        }
        if ($niveau === 'niv2' && !hasRole('superviseur', 'administrateur')) {
            flash('Accès refusé.', 'error');
            redirect('/backoffice.php?page=candidatures');
        }

        $stmt = $pdo->prepare("SELECT * FROM candidatures WHERE id = ?");
        $stmt->execute([$cid]);
        $cand = $stmt->fetch();

        if (!$cand) { flash('Candidature introuvable.', 'error'); redirect('/backoffice.php?page=candidatures'); }

        // Insérer ou mettre à jour la validation
        $existStmt = $pdo->prepare("SELECT id FROM validations_candidature WHERE candidature_id = ? AND niveau_validation = ?");
        $existStmt->execute([$cid, $niveau]);
        $existId = $existStmt->fetchColumn();

        if ($existId) {
            $pdo->prepare("UPDATE validations_candidature SET validateur_id=?, statut=?, commentaire=?, date_decision=NOW() WHERE id=?")
                ->execute([$_SESSION['user']['id'], $statut, $comment, $existId]);
        } else {
            $pdo->prepare("INSERT INTO validations_candidature (candidature_id, niveau_validation, validateur_id, statut, commentaire, date_decision) VALUES (?,?,?,?,?,NOW())")
                ->execute([$cid, $niveau, $_SESSION['user']['id'], $statut, $comment]);
        }

        // Mettre à jour le statut global
        if ($niveau === 'niv1') {
            $newStatut = match($statut) {
                'valide'     => 'niv1_valide',
                'rejete'     => 'niv1_rejete',
                'complement' => 'complement',
                default      => $cand['statut_global']
            };
        } else {
            $newStatut = match($statut) {
                'valide'     => 'validee',
                'rejete'     => 'rejetee',
                'complement' => 'complement',
                default      => $cand['statut_global']
            };
        }

        $pdo->prepare("UPDATE candidatures SET statut_global = ? WHERE id = ?")->execute([$newStatut, $cid]);

        // Notification et email si validée définitivement
        if ($newStatut === 'validee') {
            $stagStmt = $pdo->prepare("SELECT s.utilisateur_id, u.email, u.prenom FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id WHERE s.id = ?");
            $stagStmt->execute([$cand['stagiaire_id']]);
            $stag = $stagStmt->fetch();

            if ($stag) {
                $pdo->prepare("INSERT INTO notifications (utilisateur_id, type_notification, objet, message) VALUES (?, 'candidature', ?, ?)")
                    ->execute([
                        $stag['utilisateur_id'],
                        'Candidature validée',
                        'Votre candidature ' . $cand['reference'] . ' a été validée. Un stage vous sera attribué prochainement.'
                    ]);

                // Notifier les habilités
                $habStmt = $pdo->query("SELECT id FROM utilisateurs WHERE role IN ('habilite','administrateur') AND actif = 1");
                foreach ($habStmt->fetchAll() as $hab) {
                    $pdo->prepare("INSERT INTO notifications (utilisateur_id, type_notification, objet, message) VALUES (?, 'validation', ?, ?)")
                        ->execute([
                            $hab['id'],
                            'Candidature à programmer',
                            'La candidature ' . $cand['reference'] . ' a été validée et attend d\'être programmée.'
                        ]);
                }

                sendMail($stag['email'], 'Décision sur votre candidature ' . $cand['reference'],
                    MailTemplates::decision($stag['prenom'], $cand['reference'], 'valide', $comment, $niveau),
                    $stag['prenom']);
            }
        } elseif (in_array($newStatut, ['niv1_rejete', 'rejetee'], true)) {
            $stagStmt = $pdo->prepare("SELECT s.id, u.email, u.prenom FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id WHERE s.id = ?");
            $stagStmt->execute([$cand['stagiaire_id']]);
            $stag = $stagStmt->fetch();
            if ($stag) {
                sendMail($stag['email'], 'Décision sur votre candidature ' . $cand['reference'],
                    MailTemplates::decision($stag['prenom'], $cand['reference'], 'rejete', $comment, $niveau),
                    $stag['prenom']);
            }
        }

        flash('Décision enregistrée avec succès.');
        redirect('/backoffice.php?page=candidature_detail&id=' . $cid);
    }

    // Créer stage depuis candidature
    if ($action === 'creer_stage_depuis_cand') {
        if (!hasRole('habilite', 'administrateur')) {
            flash('Accès refusé.', 'error');
            redirect('/backoffice.php?page=candidatures');
        }

        $cid         = (int)($_POST['candidature_id'] ?? 0);
        $dateDebut   = $_POST['date_debut'] ?? '';
        $dateFin     = $_POST['date_fin']   ?? '';
        $encadrantId = (int)($_POST['encadrant_id'] ?? 0) ?: null;
        $remb        = isset($_POST['remboursement']) ? 1 : 0;
        $montant     = max(0.0, (float)($_POST['montant_transport'] ?? 0));

        if (!$cid || !$dateDebut || !$dateFin) {
            flash('Données manquantes.', 'error');
            redirect('/backoffice.php?page=candidature_detail&id=' . $cid);
        }

        if (strtotime($dateDebut) < strtotime('today')) {
            flash('La date de début ne peut pas être dans le passé.', 'error');
            redirect('/backoffice.php?page=candidature_detail&id=' . $cid);
        }

        if (strtotime($dateFin) <= strtotime($dateDebut)) {
            flash('La date de fin doit être après la date de début.', 'error');
            redirect('/backoffice.php?page=candidature_detail&id=' . $cid);
        }

        $diffDays = (strtotime($dateFin) - strtotime($dateDebut)) / 86400;
        if ($diffDays > 31) {
            flash('La durée initiale ne peut pas dépasser 31 jours.', 'error');
            redirect('/backoffice.php?page=candidature_detail&id=' . $cid);
        }

        $stmt = $pdo->prepare("SELECT * FROM candidatures WHERE id = ? AND statut_global = 'validee'");
        $stmt->execute([$cid]);
        $cand = $stmt->fetch();

        if (!$cand) { flash('Candidature introuvable ou non validée.', 'error'); redirect('/backoffice.php?page=candidatures'); }

        // Vérifier qu'il n'a pas déjà un stage actif
        $activeStmt = $pdo->prepare("SELECT id FROM stages WHERE stagiaire_id = ? AND statut IN ('en_cours','renouvele','preparation')");
        $activeStmt->execute([$cand['stagiaire_id']]);
        if ($activeStmt->fetch()) {
            flash('Ce stagiaire a déjà un stage actif.', 'error');
            redirect('/backoffice.php?page=candidature_detail&id=' . $cid);
        }

        $duree = round(($diffDays / 30.5), 1);
        $ref   = genRef('STG', 'stages', 'reference');

        $pdo->prepare("INSERT INTO stages (reference, candidature_id, stagiaire_id, direction_id, domaine_id, encadrant_id, date_debut, date_fin, duree_initiale_mois, duree_totale_mois, remboursement_transport, montant_transport, statut) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'preparation')")
            ->execute([$ref, $cid, $cand['stagiaire_id'], $cand['direction_id'], $cand['domaine_id'], $encadrantId, $dateDebut, $dateFin, $duree, $duree, $remb, $montant]);

        $stageId = $pdo->lastInsertId();

        // Email lettre de stage
        $stagStmt = $pdo->prepare("SELECT s.*, s.utilisateur_id AS uid, u.email, u.prenom, u.nom FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id WHERE s.id = ?");
        $stagStmt->execute([$cand['stagiaire_id']]);
        $stag = $stagStmt->fetch();

        if ($stag) {
            $pdo->prepare("INSERT INTO notifications (utilisateur_id, type_notification, objet, message) VALUES (?, 'stage', ?, ?)")
                ->execute([
                    $stag['uid'],
                    'Stage créé',
                    'Votre stage ' . $ref . ' a été créé. Début le ' . date('d/m/Y', strtotime($dateDebut)) . '.'
                ]);

            sendMail($stag['email'], 'Votre lettre de stage — ' . $ref,
                MailTemplates::lettreStage($stag['prenom'], $stag['nom'], $stag['email'], $ref, date('d/m/Y', strtotime($dateDebut)), date('d/m/Y', strtotime($dateFin)), $duree, $cand['reference']),
                $stag['prenom'] . ' ' . $stag['nom']);
        }

        flash('Stage ' . $ref . ' créé avec succès.');
        redirect('/backoffice.php?page=stage_detail&id=' . $stageId);
    }

    // Valider renouvellement
    if ($action === 'valider_renouvellement') {
        $rid      = (int)($_POST['renouvellement_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $comment  = trim($_POST['commentaire'] ?? '');

        if (!in_array($decision, ['valide', 'rejete', 'precisions'], true)) {
            flash('Décision invalide.', 'error');
            redirect('/backoffice.php?page=renouvellements');
        }

        $stmt = $pdo->prepare("SELECT * FROM renouvellements_stage WHERE id = ?");
        $stmt->execute([$rid]);
        $renouv = $stmt->fetch();

        if (!$renouv) { flash('Renouvellement introuvable.', 'error'); redirect('/backoffice.php?page=renouvellements'); }

        if ($decision === 'valide') {
            $errors = verifierRenouvellement($renouv['stage_id'], $renouv['date_fin_proposee']);
            if ($errors) {
                flash(implode(' ', $errors), 'error');
                redirect('/backoffice.php?page=renouvellements');
            }
        }

        $pdo->prepare("UPDATE renouvellements_stage SET statut=?, traite_par=?, commentaire_decision=?, date_decision=NOW() WHERE id=?")
            ->execute([$decision, $_SESSION['user']['id'], $comment, $rid]);

        if ($decision === 'valide') {
            $stageStmt = $pdo->prepare("SELECT * FROM stages WHERE id = ?");
            $stageStmt->execute([$renouv['stage_id']]);
            $stage = $stageStmt->fetch();

            if ($stage) {
                $newDuree = $stage['duree_totale_mois'] + $renouv['duree_ajoutee_mois'];
                $newNb    = $stage['nb_renouvellements'] + 1;
                $pdo->prepare("UPDATE stages SET date_fin=?, duree_totale_mois=?, nb_renouvellements=?, statut='renouvele' WHERE id=?")
                    ->execute([$renouv['date_fin_proposee'], $newDuree, $newNb, $renouv['stage_id']]);

                // Email au stagiaire
                $stagStmt = $pdo->prepare("SELECT s.id, u.email, u.prenom FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id WHERE s.id = ?");
                $stagStmt->execute([$stage['stagiaire_id']]);
                $stag = $stagStmt->fetch();
                if ($stag) {
                    sendMail($stag['email'], 'Renouvellement de votre stage',
                        MailTemplates::renouvellement($stag['prenom'], $stage['reference'], $decision, date('d/m/Y', strtotime($renouv['date_fin_proposee']))),
                        $stag['prenom']);
                }
            }
        }

        flash('Décision de renouvellement enregistrée.');
        redirect('/backoffice.php?page=renouvellements');
    }

    // Créer offre
    if ($action === 'creer_offre') {
        if (!hasRole('habilite', 'administrateur', 'superviseur')) {
            flash('Accès refusé.', 'error');
            redirect('/backoffice.php?page=offres');
        }
        $ref = genRef('OFF', 'offres_stage', 'reference');
        $pdo->prepare("INSERT INTO offres_stage (reference, titre, description, profil_recherche, direction_id, domaine_id, niveau_minimum, experience_minimale, nb_places, date_publication, date_limite, statut, creee_par) VALUES (?,?,?,?,?,?,?,?,?,?,?,'ouverte',?)")
            ->execute([
                $ref,
                trim($_POST['titre'] ?? ''),
                trim($_POST['description'] ?? ''),
                trim($_POST['profil_recherche'] ?? ''),
                (int)($_POST['direction_id'] ?? 0) ?: null,
                (int)($_POST['domaine_id'] ?? 0) ?: null,
                $_POST['niveau_minimum'] ?? 'bac+3',
                (int)($_POST['experience_minimale'] ?? 0),
                (int)($_POST['nb_places'] ?? 1),
                $_POST['date_publication'] ?? date('Y-m-d'),
                $_POST['date_limite'] ?? null,
                $_SESSION['user']['id'],
            ]);
        flash('Offre ' . $ref . ' créée.');
        redirect('/backoffice.php?page=offres');
    }

    // Modifier offre
    if ($action === 'modifier_offre') {
        $oid = (int)($_POST['offre_id'] ?? 0);
        $pdo->prepare("UPDATE offres_stage SET titre=?, description=?, profil_recherche=?, direction_id=?, domaine_id=?, niveau_minimum=?, experience_minimale=?, nb_places=?, date_limite=? WHERE id=?")
            ->execute([
                trim($_POST['titre'] ?? ''),
                trim($_POST['description'] ?? ''),
                trim($_POST['profil_recherche'] ?? ''),
                (int)($_POST['direction_id'] ?? 0) ?: null,
                (int)($_POST['domaine_id'] ?? 0) ?: null,
                $_POST['niveau_minimum'] ?? 'bac+3',
                (int)($_POST['experience_minimale'] ?? 0),
                (int)($_POST['nb_places'] ?? 1),
                $_POST['date_limite'] ?? null,
                $oid,
            ]);
        flash('Offre mise à jour.');
        redirect('/backoffice.php?page=offres');
    }

    // Créer utilisateur
    if ($action === 'create_user') {
        if (!hasRole('administrateur')) {
            flash('Accès refusé.', 'error');
            redirect('/backoffice.php?page=utilisateurs');
        }
        $email = trim($_POST['email'] ?? '');
        $stmt  = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            flash('Cet email est déjà utilisé.', 'error');
            redirect('/backoffice.php?page=utilisateurs');
        }
        $hash = password_hash($_POST['mot_de_passe'] ?? 'password', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, civilite) VALUES (?,?,?,?,?,?)")
            ->execute([
                trim($_POST['nom'] ?? ''),
                trim($_POST['prenom'] ?? ''),
                $email,
                $hash,
                $_POST['role'] ?? 'habilite',
                $_POST['civilite'] ?? 'M.',
            ]);
        flash('Utilisateur créé.');
        redirect('/backoffice.php?page=utilisateurs');
    }

    // Gérer stage (terminer, archiver, modifier dates, encadrant, transport)
    if ($action === 'gerer_stage') {
        $stageId    = (int)($_POST['stage_id'] ?? 0);
        $sousAction = $_POST['sous_action'] ?? '';

        if (!in_array($sousAction, ['terminer', 'interrompre', 'demarrer', 'dates', 'encadrant', 'transport', 'renouveler'], true)) {
            flash('Action invalide.', 'error');
            redirect('/backoffice.php?page=stages');
        }

        $stmt = $pdo->prepare("SELECT * FROM stages WHERE id = ?");
        $stmt->execute([$stageId]);
        $stage = $stmt->fetch();
        if (!$stage) { flash('Stage introuvable.', 'error'); redirect('/backoffice.php?page=stages'); }

        if ($sousAction === 'terminer') {
            $pdo->prepare("UPDATE stages SET statut='termine' WHERE id=?")->execute([$stageId]);
            flash('Stage marqué comme terminé.');
        } elseif ($sousAction === 'interrompre') {
            $pdo->prepare("UPDATE stages SET statut='interrompu' WHERE id=?")->execute([$stageId]);
            flash('Stage interrompu.');
        } elseif ($sousAction === 'demarrer') {
            $pdo->prepare("UPDATE stages SET statut='en_cours' WHERE id=?")->execute([$stageId]);
            flash('Stage démarré.');
        } elseif ($sousAction === 'dates') {
            $pdo->prepare("UPDATE stages SET date_debut=?, date_fin=? WHERE id=?")
                ->execute([$_POST['date_debut'], $_POST['date_fin'], $stageId]);
            flash('Dates mises à jour.');
        } elseif ($sousAction === 'encadrant') {
            $encId = (int)($_POST['encadrant_id'] ?? 0) ?: null;
            $pdo->prepare("UPDATE stages SET encadrant_id=? WHERE id=?")->execute([$encId, $stageId]);
            flash('Encadrant mis à jour.');
        } elseif ($sousAction === 'transport') {
            $pdo->prepare("UPDATE stages SET remboursement_transport=?, montant_transport=? WHERE id=?")
                ->execute([
                    isset($_POST['remboursement']) ? 1 : 0,
                    max(0.0, (float)($_POST['montant_transport'] ?? 0)),
                    $stageId
                ]);
            flash('Informations transport mises à jour.');
        } elseif ($sousAction === 'renouveler') {
            $dateFin   = $_POST['date_fin_proposee'] ?? '';
            $motif     = trim($_POST['motif_demande'] ?? '');
            $diffMois  = round((strtotime($dateFin) - strtotime($stage['date_fin'])) / (30.5 * 86400), 1);
            $numRenouv = $stage['nb_renouvellements'] + 1;
            $pdo->prepare("INSERT INTO renouvellements_stage (stage_id, numero_renouvellement, date_debut_proposee, date_fin_proposee, duree_ajoutee_mois, motif_demande) VALUES (?,?,?,?,?,?)")
                ->execute([$stageId, $numRenouv, $stage['date_fin'], $dateFin, $diffMois, $motif]);
            flash('Demande de renouvellement soumise.');
        }

        redirect('/backoffice.php?page=stage_detail&id=' . $stageId);
    }

    // Direction : add
    if (isset($_POST['add_direction'])) {
        $pdo->prepare("INSERT INTO directions (libelle, abreviation) VALUES (?,?)")
            ->execute([trim($_POST['libelle'] ?? ''), strtoupper(trim($_POST['abreviation'] ?? ''))]);
        flash('Direction ajoutée.');
        redirect('/backoffice.php?page=directions');
    }

    // Direction : edit
    if (isset($_POST['edit_direction'])) {
        $pdo->prepare("UPDATE directions SET libelle=?, abreviation=? WHERE id=?")
            ->execute([trim($_POST['libelle'] ?? ''), strtoupper(trim($_POST['abreviation'] ?? '')), (int)($_POST['dir_id'] ?? 0)]);
        flash('Direction mise à jour.');
        redirect('/backoffice.php?page=directions');
    }

    // Domaine : add
    if (isset($_POST['add_domaine'])) {
        $pdo->prepare("INSERT INTO domaines (libelle) VALUES (?)")->execute([trim($_POST['libelle_dom'] ?? '')]);
        flash('Domaine ajouté.');
        redirect('/backoffice.php?page=directions');
    }

    // Domaine : edit
    if (isset($_POST['edit_domaine'])) {
        $pdo->prepare("UPDATE domaines SET libelle=? WHERE id=?")
            ->execute([trim($_POST['libelle_dom'] ?? ''), (int)($_POST['dom_id'] ?? 0)]);
        flash('Domaine mis à jour.');
        redirect('/backoffice.php?page=directions');
    }

    // Soumettre candidature (backoffice)
    if ($action === 'soumettre_candidature') {
        $stagId  = (int)($_POST['stagiaire_id'] ?? 0);
        $typeC   = $_POST['type_candidature'] ?? 'spontanee';
        if (!in_array($typeC, ['offre', 'spontanee'], true)) {
            $typeC = 'spontanee';
        }
        $offreId = (int)($_POST['offre_id'] ?? 0) ?: null;
        $domId   = (int)($_POST['domaine_id'] ?? 0) ?: null;
        $dirId   = (int)($_POST['direction_id'] ?? 0) ?: null;
        $motiv   = trim($_POST['motivation'] ?? '');

        $score = calculerScore($stagId);
        $ref   = genRef('CAND', 'candidatures', 'reference');

        $pdo->prepare("INSERT INTO candidatures (reference, stagiaire_id, offre_id, type_candidature, domaine_id, direction_id, score_tri, motivation) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$ref, $stagId, $offreId, $typeC, $domId, $dirId, $score, $motiv]);
        flash('Candidature ' . $ref . ' soumise.');
        redirect('/backoffice.php?page=candidatures');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ROUTING PAGES
// ─────────────────────────────────────────────────────────────────────────────
$page = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['page'] ?? 'dashboard');
$pageFile = __DIR__ . '/pages/admin/' . $page . '.php';

include __DIR__ . '/includes/header.php';

if (file_exists($pageFile)) {
    include $pageFile;
} else {
    echo '<div class="alert alert-danger">Page introuvable : <code>' . h($page) . '</code></div>';
}

include __DIR__ . '/includes/footer.php';
