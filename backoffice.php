<?php
/**
 * Stage - Routeur backoffice principal
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';
requireAdmin();

$pdo = getPDO();
$uid = $_SESSION['user']['id'];

// ═══════════════════════════════════════════════════════════════
// POST ACTIONS (PRG pattern)
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Valider candidature (niv1 ou niv2) ──────────────────────
    if ($action === 'valider_candidature') {
        $cand_id  = (int)($_POST['candidature_id'] ?? 0);
        $niveau   = $_POST['niveau'] ?? 'niv1';
        $statut   = $_POST['statut'] ?? 'valide';
        $comment  = trim($_POST['commentaire'] ?? '');

        // Map decision to global status
        $mapNiv1 = ['valide' => 'niv1_valide', 'rejete' => 'niv1_rejete', 'complement' => 'complement', 'en_attente' => 'niv1_en_cours'];
        $mapNiv2 = ['valide' => 'validee', 'rejete' => 'rejetee', 'complement' => 'complement'];

        $map = $niveau === 'niv1' ? $mapNiv1 : $mapNiv2;
        $global = $map[$statut] ?? 'soumise';

        // Upsert validation
        $chk = $pdo->prepare('SELECT id FROM validations_candidature WHERE candidature_id = ? AND niveau_validation = ?');
        $chk->execute([$cand_id, $niveau]);
        $existing = $chk->fetch();
        if ($existing) {
            $pdo->prepare('UPDATE validations_candidature SET statut=?, validateur_id=?, commentaire=?, date_decision=NOW() WHERE id=?')
                ->execute([$statut, $uid, $comment, $existing['id']]);
        } else {
            $pdo->prepare('INSERT INTO validations_candidature (candidature_id, niveau_validation, validateur_id, statut, commentaire, date_decision) VALUES (?,?,?,?,?,NOW())')
                ->execute([$cand_id, $niveau, $uid, $statut, $comment]);
        }
        $pdo->prepare('UPDATE candidatures SET statut_global = ? WHERE id = ?')->execute([$global, $cand_id]);

        // Notify stagiaire
        $c = $pdo->prepare('SELECT c.*, u.email, u.prenom, u.nom FROM candidatures c JOIN stagiaires s ON s.id = c.stagiaire_id JOIN utilisateurs u ON u.id = s.utilisateur_id WHERE c.id = ?');
        $c->execute([$cand_id]);
        $cData = $c->fetch();
        if ($cData) {
            $pdo->prepare('INSERT INTO notifications (utilisateur_id, type_notification, objet, message) SELECT s.utilisateur_id, ?, ?, ? FROM stagiaires s WHERE s.id = ?')
                ->execute(['candidature', 'Décision sur ' . $cData['reference'], 'Votre candidature ' . $cData['reference'] . ' : ' . ucfirst($statut), $cData['stagiaire_id']]);
            sendMail($cData['email'], 'Décision sur votre candidature ' . $cData['reference'], MailTemplates::decision($cData['prenom'], $cData['reference'], $statut, $comment, $niveau), $cData['prenom'] . ' ' . $cData['nom']);
        }
        flash('Décision enregistrée avec succès.');
        redirect('backoffice.php?page=candidature_detail&id=' . $cand_id);
    }

    // ── Créer stage depuis candidature ───────────────────────────
    if ($action === 'creer_stage_depuis_cand') {
        $cand_id     = (int)($_POST['candidature_id'] ?? 0);
        $date_debut  = $_POST['date_debut'] ?? '';
        $date_fin    = $_POST['date_fin'] ?? '';
        $encadrant   = (int)($_POST['encadrant_id'] ?? 0);
        $transport   = isset($_POST['remboursement_transport']) ? 1 : 0;
        $montant     = max(0.0, (float)($_POST['montant_transport'] ?? 0));

        if (!$cand_id || !$date_debut || !$date_fin) {
            flash('Données manquantes pour créer le stage.', 'error');
            redirect('backoffice.php?page=candidature_detail&id=' . $cand_id);
        }

        $cand = $pdo->prepare('SELECT * FROM candidatures WHERE id = ?');
        $cand->execute([$cand_id]);
        $c = $cand->fetch();
        if (!$c) { flash('Candidature introuvable.', 'error'); redirect('backoffice.php?page=candidatures'); }

        $d1 = new DateTime($date_debut);
        $d2 = new DateTime($date_fin);
        $mois = $d1->diff($d2)->m + ($d1->diff($d2)->y * 12);
        $ref = genRef('STG', 'stages', 'reference');

        $pdo->prepare('INSERT INTO stages (reference, candidature_id, stagiaire_id, direction_id, domaine_id, encadrant_id, date_debut, date_fin, duree_initiale_mois, duree_totale_mois, remboursement_transport, montant_transport, statut) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$ref, $cand_id, $c['stagiaire_id'], $c['direction_id'], $c['domaine_id'], $encadrant ?: null, $date_debut, $date_fin, $mois, $mois, $transport, $montant, 'en_cours']);
        $stage_id = (int)$pdo->lastInsertId();

        // Notify
        $s = $pdo->prepare('SELECT s.utilisateur_id, u.email, u.prenom, u.nom FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id WHERE s.id = ?');
        $s->execute([$c['stagiaire_id']]);
        $sData = $s->fetch();
        if ($sData) {
            $pdo->prepare('INSERT INTO notifications (utilisateur_id, type_notification, objet, message) VALUES (?,?,?,?)')
                ->execute([$sData['utilisateur_id'], 'stage', 'Stage créé', 'Votre stage ' . $ref . ' a été créé et débute le ' . date('d/m/Y', strtotime($date_debut)) . '.']);
            sendMail($sData['email'], 'Votre stage ' . $ref, MailTemplates::lettreStage($sData['prenom'], $sData['nom'], $sData['email'], $ref, $date_debut, $date_fin, $mois, $c['reference']), $sData['prenom'] . ' ' . $sData['nom']);
        }
        flash('Stage ' . $ref . ' créé avec succès.');
        redirect('backoffice.php?page=stage_detail&id=' . $stage_id);
    }

    // ── Gérer un stage (statut/dates/encadrant/transport) ─────────
    if ($action === 'gerer_stage') {
        $stage_id = (int)($_POST['stage_id'] ?? 0);
        $sub      = $_POST['sub_action'] ?? '';

        if ($sub === 'changer_statut') {
            $statut = $_POST['statut'] ?? '';
            $allowed = ['preparation','en_cours','renouvele','termine','interrompu','annule'];
            if (in_array($statut, $allowed, true)) {
                $pdo->prepare('UPDATE stages SET statut = ? WHERE id = ?')->execute([$statut, $stage_id]);
                flash('Statut du stage mis à jour.');
            }
        } elseif ($sub === 'modifier_dates') {
            $dd = $_POST['date_debut'] ?? '';
            $df = $_POST['date_fin'] ?? '';
            if ($dd && $df) {
                $d1 = new DateTime($dd); $d2 = new DateTime($df);
                $m = $d1->diff($d2)->m + ($d1->diff($d2)->y * 12);
                $pdo->prepare('UPDATE stages SET date_debut=?,date_fin=?,duree_totale_mois=? WHERE id=?')->execute([$dd, $df, $m, $stage_id]);
                flash('Dates du stage mises à jour.');
            }
        } elseif ($sub === 'modifier_encadrant') {
            $enc = (int)($_POST['encadrant_id'] ?? 0);
            $pdo->prepare('UPDATE stages SET encadrant_id = ? WHERE id = ?')->execute([$enc ?: null, $stage_id]);
            flash('Encadrant mis à jour.');
        } elseif ($sub === 'modifier_transport') {
            $transp = isset($_POST['remboursement_transport']) ? 1 : 0;
            $montant = max(0.0, (float)($_POST['montant_transport'] ?? 0));
            $pdo->prepare('UPDATE stages SET remboursement_transport=?,montant_transport=? WHERE id=?')->execute([$transp, $montant, $stage_id]);
            flash('Transport mis à jour.');
        }
        redirect('backoffice.php?page=stage_detail&id=' . $stage_id);
    }

    // ── Valider renouvellement ────────────────────────────────────
    if ($action === 'valider_renouvellement') {
        $renouv_id = (int)($_POST['renouvellement_id'] ?? 0);
        $decision  = $_POST['decision'] ?? '';
        $comment   = trim($_POST['commentaire'] ?? '');

        if (!in_array($decision, ['valide', 'rejete', 'precisions'], true)) {
            flash('Décision invalide.', 'error');
            redirect('backoffice.php?page=renouvellements');
        }

        $r = $pdo->prepare('SELECT * FROM renouvellements_stage WHERE id = ?');
        $r->execute([$renouv_id]);
        $renouv = $r->fetch();
        if ($renouv) {
            $pdo->prepare('UPDATE renouvellements_stage SET statut=?,traite_par=?,commentaire_decision=?,date_decision=NOW() WHERE id=?')
                ->execute([$decision, $uid, $comment, $renouv_id]);

            if ($decision === 'valide') {
                $pdo->prepare('UPDATE stages SET date_fin=?,nb_renouvellements=nb_renouvellements+1,duree_totale_mois=duree_totale_mois+?,statut=? WHERE id=?')
                    ->execute([$renouv['date_fin_proposee'], $renouv['duree_ajoutee_mois'], 'renouvele', $renouv['stage_id']]);
            }

            // Notify
            $s = $pdo->prepare('SELECT sg.reference, s.utilisateur_id, u.email, u.prenom, u.nom FROM stages sg JOIN stagiaires s ON s.id = sg.stagiaire_id JOIN utilisateurs u ON u.id = s.utilisateur_id WHERE sg.id = ?');
            $s->execute([$renouv['stage_id']]);
            $sData = $s->fetch();
            if ($sData) {
                $pdo->prepare('INSERT INTO notifications (utilisateur_id, type_notification, objet, message) VALUES (?,?,?,?)')
                    ->execute([$sData['utilisateur_id'], 'renouvellement', 'Décision renouvellement', 'Votre demande de renouvellement de stage ' . $sData['reference'] . ' : ' . ucfirst($decision)]);
                sendMail($sData['email'], 'Décision renouvellement – ' . $sData['reference'], MailTemplates::renouvellement($sData['prenom'], $sData['reference'], $decision, $renouv['date_fin_proposee'] ?? ''), $sData['prenom'] . ' ' . $sData['nom']);
            }
            flash('Renouvellement traité avec succès.');
        }
        redirect('backoffice.php?page=renouvellements');
    }

    // ── Créer offre ───────────────────────────────────────────────
    if ($action === 'creer_offre') {
        $titre  = trim($_POST['titre'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $profil = trim($_POST['profil_recherche'] ?? '');
        $dir_id = (int)($_POST['direction_id'] ?? 0);
        $dom_id = (int)($_POST['domaine_id'] ?? 0);
        $niveau = $_POST['niveau_minimum'] ?? 'bac+3';
        $places = (int)($_POST['nb_places'] ?? 1);
        $dlim   = $_POST['date_limite'] ?? null;
        $dpub   = date('Y-m-d');

        if (!$titre) { flash('Le titre est obligatoire.', 'error'); redirect('backoffice.php?page=offres'); }

        $ref = genRef('OFF', 'offres_stage', 'reference');
        $pdo->prepare('INSERT INTO offres_stage (reference, titre, description, profil_recherche, direction_id, domaine_id, niveau_minimum, nb_places, date_publication, date_limite, statut, creee_par) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$ref, $titre, $desc, $profil, $dir_id ?: null, $dom_id ?: null, $niveau, $places, $dpub, $dlim ?: null, 'ouverte', $uid]);
        flash('Offre ' . $ref . ' créée avec succès.');
        redirect('backoffice.php?page=offres');
    }

    // ── Modifier offre ────────────────────────────────────────────
    if ($action === 'modifier_offre') {
        $oid    = (int)($_POST['offre_id'] ?? 0);
        $titre  = trim($_POST['titre'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $profil = trim($_POST['profil_recherche'] ?? '');
        $dir_id = (int)($_POST['direction_id'] ?? 0);
        $dom_id = (int)($_POST['domaine_id'] ?? 0);
        $niveau = $_POST['niveau_minimum'] ?? 'bac+3';
        $places = (int)($_POST['nb_places'] ?? 1);
        $dlim   = $_POST['date_limite'] ?? null;
        $pdo->prepare('UPDATE offres_stage SET titre=?,description=?,profil_recherche=?,direction_id=?,domaine_id=?,niveau_minimum=?,nb_places=?,date_limite=? WHERE id=?')
            ->execute([$titre, $desc, $profil, $dir_id ?: null, $dom_id ?: null, $niveau, $places, $dlim ?: null, $oid]);
        flash('Offre mise à jour.');
        redirect('backoffice.php?page=offres');
    }

    // ── Créer utilisateur ─────────────────────────────────────────
    if ($action === 'create_user') {
        if (!hasRole('administrateur', 'superviseur')) { flash('Accès refusé.', 'error'); redirect('backoffice.php?page=utilisateurs'); }
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $pass   = $_POST['password'] ?? '';
        $role   = $_POST['role'] ?? 'habilite';
        $civil  = $_POST['civilite'] ?? 'M.';

        if (!$nom || !$prenom || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            flash('Données invalides.', 'error');
            redirect('backoffice.php?page=utilisateurs');
        }
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, civilite) VALUES (?,?,?,?,?,?)')
            ->execute([$nom, $prenom, $email, $hash, $role, $civil]);
        flash('Utilisateur créé avec succès.');
        redirect('backoffice.php?page=utilisateurs');
    }

    // ── Modifier utilisateur ──────────────────────────────────────
    if ($action === 'edit_user') {
        if (!hasRole('administrateur')) { flash('Accès refusé.', 'error'); redirect('backoffice.php?page=utilisateurs'); }
        $id     = (int)($_POST['user_id'] ?? 0);
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $role   = $_POST['role'] ?? 'habilite';
        $civil  = $_POST['civilite'] ?? 'M.';
        $mdp    = $_POST['nouveau_mdp'] ?? '';

        if (!$id || !$nom || !$prenom || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Données invalides.', 'error');
            redirect('backoffice.php?page=utilisateurs');
        }

        // Vérifier unicité email
        $chk = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ? AND id != ?');
        $chk->execute([$email, $id]);
        if ($chk->fetch()) {
            flash('Cet email est déjà utilisé par un autre compte.', 'error');
            redirect('backoffice.php?page=utilisateurs');
        }

        $pdo->prepare('UPDATE utilisateurs SET nom=?, prenom=?, email=?, role=?, civilite=? WHERE id=?')
            ->execute([$nom, $prenom, $email, $role, $civil, $id]);

        if (strlen($mdp) >= 8) {
            $pdo->prepare('UPDATE utilisateurs SET mot_de_passe=? WHERE id=?')
                ->execute([password_hash($mdp, PASSWORD_DEFAULT), $id]);
        }

        flash('Utilisateur mis à jour avec succès.');
        redirect('backoffice.php?page=utilisateurs');
    }

    // ── Add direction ─────────────────────────────────────────────
    if ($action === 'add_direction') {
        $lib = trim($_POST['libelle'] ?? '');
        $abr = trim($_POST['abreviation'] ?? '');
        if ($lib) {
            $pdo->prepare('INSERT INTO directions (libelle, abreviation) VALUES (?,?)')->execute([$lib, $abr]);
            flash('Direction ajoutée.');
        }
        redirect('backoffice.php?page=directions');
    }

    // ── Edit direction ────────────────────────────────────────────
    if ($action === 'edit_direction') {
        $id  = (int)($_POST['dir_id'] ?? 0);
        $lib = trim($_POST['libelle'] ?? '');
        $abr = trim($_POST['abreviation'] ?? '');
        if ($id && $lib) {
            $pdo->prepare('UPDATE directions SET libelle=?,abreviation=? WHERE id=?')->execute([$lib, $abr, $id]);
            flash('Direction mise à jour.');
        }
        redirect('backoffice.php?page=directions');
    }

    // ── Add domaine ───────────────────────────────────────────────
    if ($action === 'add_domaine') {
        $lib = trim($_POST['libelle'] ?? '');
        if ($lib) {
            $pdo->prepare('INSERT INTO domaines (libelle) VALUES (?)')->execute([$lib]);
            flash('Domaine ajouté.');
        }
        redirect('backoffice.php?page=directions');
    }

    // ── Edit domaine ──────────────────────────────────────────────
    if ($action === 'edit_domaine') {
        $id  = (int)($_POST['dom_id'] ?? 0);
        $lib = trim($_POST['libelle'] ?? '');
        if ($id && $lib) {
            $pdo->prepare('UPDATE domaines SET libelle=? WHERE id=?')->execute([$lib, $id]);
            flash('Domaine mis à jour.');
        }
        redirect('backoffice.php?page=directions');
    }

    // ── SMTP config save ──────────────────────────────────────────
    if ($action === 'save_smtp') {
        flash('Configuration SMTP sauvegardée (rechargez pour tester).', 'success');
        redirect('backoffice.php?page=email-config');
    }
}

// ═══════════════════════════════════════════════════════════════
// GET ACTIONS
// ═══════════════════════════════════════════════════════════════
$getAction = $_GET['action'] ?? '';

if ($getAction === 'logout') {
    session_destroy();
    redirect('admin.php');
}

if ($getAction === 'toggle_user' && hasRole('administrateur', 'superviseur')) {
    $id = (int)($_GET['id'] ?? 0);
    $pdo->prepare('UPDATE utilisateurs SET actif = NOT actif WHERE id = ? AND id != ?')->execute([$id, $uid]);
    flash('Statut utilisateur modifié.');
    redirect('backoffice.php?page=utilisateurs');
}

if ($getAction === 'toggle_offre') {
    $id = (int)($_GET['id'] ?? 0);
    $o = $pdo->prepare('SELECT statut FROM offres_stage WHERE id = ?');
    $o->execute([$id]);
    $row = $o->fetch();
    if ($row) {
        $new = $row['statut'] === 'ouverte' ? 'fermee' : 'ouverte';
        $pdo->prepare('UPDATE offres_stage SET statut = ? WHERE id = ?')->execute([$new, $id]);
        flash('Statut de l\'offre modifié.');
    }
    redirect('backoffice.php?page=offres');
}

if ($getAction === 'toggle_dir') {
    $id = (int)($_GET['id'] ?? 0);
    $pdo->prepare('UPDATE directions SET actif = NOT actif WHERE id = ?')->execute([$id]);
    flash('Statut direction modifié.');
    redirect('backoffice.php?page=directions');
}

if ($getAction === 'toggle_dom') {
    $id = (int)($_GET['id'] ?? 0);
    $pdo->prepare('UPDATE domaines SET actif = NOT actif WHERE id = ?')->execute([$id]);
    flash('Statut domaine modifié.');
    redirect('backoffice.php?page=directions');
}

if ($getAction === 'del_dir' && hasRole('administrateur')) {
    $id = (int)($_GET['id'] ?? 0);
    $pdo->prepare('DELETE FROM directions WHERE id = ?')->execute([$id]);
    flash('Direction supprimée.');
    redirect('backoffice.php?page=directions');
}

if ($getAction === 'del_dom' && hasRole('administrateur')) {
    $id = (int)($_GET['id'] ?? 0);
    $pdo->prepare('DELETE FROM domaines WHERE id = ?')->execute([$id]);
    flash('Domaine supprimé.');
    redirect('backoffice.php?page=directions');
}

// CSV Export stagiaires
if ($getAction === 'export_stagiaires') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="stagiaires_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ID','Nom','Prenom','Email','Niveau','Telephone','Ville','Date inscription'], ';');
    $rows = $pdo->query('SELECT s.id, u.nom, u.prenom, u.email, s.niveau_etude, s.telephone, s.ville, s.date_inscription FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id ORDER BY s.date_inscription DESC')->fetchAll();
    foreach ($rows as $r) {
        fputcsv($fh, [$r['id'], $r['nom'], $r['prenom'], $r['email'], $r['niveau_etude'], $r['telephone'], $r['ville'], $r['date_inscription']], ';');
    }
    fclose($fh);
    exit;
}

// ═══════════════════════════════════════════════════════════════
// ROUTING
// ═══════════════════════════════════════════════════════════════
$page = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['page'] ?? 'dashboard');
$file = __DIR__ . "/pages/admin/{$page}.php";

if (!file_exists($file)) {
    $page = 'dashboard';
    $file = __DIR__ . '/pages/admin/dashboard.php';
}

include $file;
