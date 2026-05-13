<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

if (isLoggedIn()) {
    if (isStagiaire()) redirect('/espace-stagiaire.php');
    if (isAdmin())    redirect('/backoffice.php');
}

$pdo    = getPDO();
$errors = [];
$step   = (int)($_GET['step'] ?? 1);
$offreId = (int)($_GET['offre_id'] ?? 0);

// Étape 1 : Compte utilisateur
// Étape 2 : Profil complet
// Étape 3 : Candidature

$offres   = $pdo->query("SELECT id, reference, titre FROM offres_stage WHERE statut = 'ouverte' ORDER BY titre")->fetchAll();
$domaines = $pdo->query("SELECT id, libelle FROM domaines WHERE actif = 1 ORDER BY libelle")->fetchAll();
$directions = $pdo->query("SELECT id, libelle FROM directions WHERE actif = 1 ORDER BY libelle")->fetchAll();
$niveaux  = ['bac','bac+2','bac+3','bac+4','bac+5','doctorat'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    // Étape 1 : Créer compte
    if (isset($_POST['etape1'])) {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom    = trim($_POST['nom']    ?? '');
        $email  = trim($_POST['email']  ?? '');
        $mdp    = $_POST['mot_de_passe'] ?? '';
        $mdp2   = $_POST['mot_de_passe2'] ?? '';

        if (!$prenom || !$nom || !$email || !$mdp) {
            $errors[] = 'Tous les champs sont obligatoires.';
        } elseif (strlen($mdp) < 6) {
            $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
        } elseif ($mdp !== $mdp2) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        } else {
            $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'Cet email est déjà utilisé.';
            }
        }

        if (!$errors) {
            $hash = password_hash($mdp, PASSWORD_DEFAULT);

            $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES (?,?,?,?,'stagiaire')")
                ->execute([$nom, $prenom, $email, $hash]);
            $userId = $pdo->lastInsertId();

            // Créer le profil stagiaire
            $niveau = $_POST['niveau_etude'] ?? 'bac+3';
            $tel    = trim($_POST['telephone'] ?? '');
            $ville  = trim($_POST['ville'] ?? '');
            $domId  = (int)($_POST['domaine_principal_id'] ?? 0) ?: null;
            $nais   = $_POST['date_naissance'] ?? null;
            $sexe   = $_POST['sexe'] ?? 'M';

            $pdo->prepare("INSERT INTO stagiaires (utilisateur_id, niveau_etude, telephone, ville, date_naissance, sexe, domaine_principal_id) VALUES (?,?,?,?,?,?,?)")
                ->execute([$userId, $niveau, $tel, $ville, $nais ?: null, $sexe, $domId]);
            $stagId = $pdo->lastInsertId();

            // Formation
            $diplome = trim($_POST['diplome'] ?? '');
            $spec    = trim($_POST['specialite'] ?? '');
            $etab    = trim($_POST['etablissement'] ?? '');
            $annee   = (int)($_POST['annee_fin'] ?? 0) ?: null;
            if ($diplome) {
                $pdo->prepare("INSERT INTO formations (stagiaire_id, diplome, specialite, etablissement, annee_fin) VALUES (?,?,?,?,?)")
                    ->execute([$stagId, $diplome, $spec, $etab, $annee]);
            }

            // Compétences
            $comps = array_filter(array_map('trim', explode(',', $_POST['competences'] ?? '')));
            foreach ($comps as $comp) {
                if ($comp) {
                    $pdo->prepare("INSERT INTO competences (stagiaire_id, libelle) VALUES (?,?)")->execute([$stagId, $comp]);
                }
            }

            // Connexion automatique
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'           => $userId,
                'nom'          => $nom,
                'prenom'       => $prenom,
                'email'        => $email,
                'role'         => 'stagiaire',
                'stagiaire_id' => $stagId,
            ];

            // Candidature automatique si offre sélectionnée
            $offreIdPost = (int)($_POST['offre_id'] ?? 0);
            $typeC       = $offreIdPost ? 'offre' : 'spontanee';
            $domCandId   = (int)($_POST['domaine_cand_id'] ?? 0) ?: $domId;
            $dirId       = (int)($_POST['direction_id'] ?? 0) ?: null;
            $motiv       = trim($_POST['motivation'] ?? '');

            $score = calculerScore($stagId);
            $ref   = genRef('CAND', 'candidatures', 'reference');
            $pdo->prepare("INSERT INTO candidatures (reference, stagiaire_id, offre_id, type_candidature, domaine_id, direction_id, score_tri, motivation) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$ref, $stagId, $offreIdPost ?: null, $typeC, $domCandId, $dirId, $score, $motiv]);

            // Email de bienvenue
            $offTitre = '';
            if ($offreIdPost) {
                $offStmt = $pdo->prepare("SELECT titre FROM offres_stage WHERE id = ?");
                $offStmt->execute([$offreIdPost]);
                $offTitre = $offStmt->fetchColumn() ?: '';
            }
            sendMail($email, 'Bienvenue sur StagIA — Candidature reçue',
                MailTemplates::bienvenue($prenom, $email, $ref, $score, $offTitre ?: 'Candidature spontanée'),
                $prenom . ' ' . $nom);

            flash('Inscription réussie ! Votre candidature ' . $ref . ' a été soumise.');
            redirect('/espace-stagiaire.php');
        }
    }
}

$offreSelectionnee = null;
if ($offreId) {
    $stmt = $pdo->prepare("SELECT * FROM offres_stage WHERE id = ? AND statut = 'ouverte'");
    $stmt->execute([$offreId]);
    $offreSelectionnee = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StagIA — Inscription & Candidature</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Montserrat', sans-serif; background: #f4f5f7; color: #111827; }
        .navbar { background: #1b2a6b; padding: 0 32px; height: 60px; display: flex; align-items: center; justify-content: space-between; }
        .brand { font-size: 22px; font-weight: 700; color: #fff; }
        .brand span { color: #e8001c; }
        a { color: #1b2a6b; }

        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e4ea; overflow: hidden; margin-bottom: 20px; }
        .card-header { background: #1b2a6b; padding: 20px 28px; color: #fff; }
        .card-header h1 { font-size: 20px; }
        .card-header p  { font-size: 13px; opacity: .7; margin-top: 4px; }
        .card-body { padding: 28px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 12.5px; font-weight: 600; color: #374151; }
        input, select, textarea { padding: 9px 12px; border: 1.5px solid #e2e4ea; border-radius: 8px; font-family: inherit; font-size: 13.5px; color: #111827; background: #fff; outline: none; transition: border-color .2s; }
        input:focus, select:focus, textarea:focus { border-color: #1b2a6b; }
        textarea { resize: vertical; min-height: 80px; }

        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border: none; border-radius: 8px; font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: opacity .15s; }
        .btn:hover { opacity: .88; }
        .btn-red   { background: #e8001c; color: #fff; }
        .btn-ghost { background: #f4f5f7; color: #1b2a6b; border: 1px solid #e2e4ea; }

        .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; margin-bottom: 20px; }
        .alert-info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; margin-bottom: 16px; }
        .section-title { font-size: 15px; font-weight: 600; color: #1b2a6b; margin-bottom: 16px; margin-top: 8px; padding-bottom: 8px; border-bottom: 2px solid #e2e4ea; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="/stage2/index.php" class="brand">Stag<span>IA</span></a>
    <a href="/stage2/index.php" style="color:rgba(255,255,255,.7);font-size:13px;text-decoration:none">← Retour aux offres</a>
</nav>

<div class="container">
    <?php if ($offreSelectionnee): ?>
    <div class="alert-info">
        📢 Vous postulez à : <strong><?= h($offreSelectionnee['reference'] . ' — ' . $offreSelectionnee['titre']) ?></strong>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <div class="alert-error">
        <?php foreach ($errors as $e): ?>
        <div>• <?= h($e) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h1>Inscription & Candidature</h1>
            <p>Créez votre compte et soumettez votre candidature en une seule étape.</p>
        </div>
        <div class="card-body">
            <form method="POST">
            <?= csrfField() ?>
                <input type="hidden" name="etape1" value="1">

                <!-- Compte -->
                <div class="section-title">👤 Votre compte</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Civilité</label>
                        <select name="civilite">
                            <option value="M.">M.</option>
                            <option value="Mme">Mme</option>
                        </select>
                    </div>
                    <div class="form-group"></div>
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" required value="<?= h($_POST['prenom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" required value="<?= h($_POST['nom'] ?? '') ?>">
                    </div>
                    <div class="form-group full">
                        <label>Email *</label>
                        <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Mot de passe *</label>
                        <input type="password" name="mot_de_passe" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirmer le mot de passe *</label>
                        <input type="password" name="mot_de_passe2" required minlength="6">
                    </div>
                </div>

                <!-- Profil -->
                <div class="section-title" style="margin-top:24px">📋 Votre profil</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Niveau d'étude *</label>
                        <select name="niveau_etude" required>
                            <?php foreach ($niveaux as $n): ?>
                            <option value="<?= $n ?>" <?= ($n === 'bac+3' ? 'selected' : '') ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sexe</label>
                        <select name="sexe">
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="telephone" value="<?= h($_POST['telephone'] ?? '') ?>" placeholder="+224 620 000 000">
                    </div>
                    <div class="form-group">
                        <label>Ville</label>
                        <input type="text" name="ville" value="<?= h($_POST['ville'] ?? '') ?>" placeholder="Conakry">
                    </div>
                    <div class="form-group">
                        <label>Date de naissance</label>
                        <input type="date" name="date_naissance">
                    </div>
                    <div class="form-group">
                        <label>Domaine principal</label>
                        <select name="domaine_principal_id">
                            <option value="">— Aucun —</option>
                            <?php foreach ($domaines as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= h($d['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Formation -->
                <div class="section-title" style="margin-top:24px">🎓 Formation principale</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Diplôme</label>
                        <input type="text" name="diplome" placeholder="Licence, Master...">
                    </div>
                    <div class="form-group">
                        <label>Spécialité</label>
                        <input type="text" name="specialite" placeholder="Informatique de gestion...">
                    </div>
                    <div class="form-group">
                        <label>Établissement</label>
                        <input type="text" name="etablissement" placeholder="Université...">
                    </div>
                    <div class="form-group">
                        <label>Année de fin</label>
                        <input type="number" name="annee_fin" min="1990" max="<?= date('Y') ?>" placeholder="<?= date('Y') ?>">
                    </div>
                    <div class="form-group full">
                        <label>Compétences (séparées par des virgules)</label>
                        <input type="text" name="competences" placeholder="PHP, MySQL, JavaScript, Excel...">
                    </div>
                </div>

                <!-- Candidature -->
                <div class="section-title" style="margin-top:24px">📩 Votre candidature</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Type de candidature</label>
                        <select name="type_cand_disp" id="type-cand" onchange="toggleOffre()">
                            <option value="spontanee">Candidature spontanée</option>
                            <option value="offre" <?= $offreId ? 'selected' : '' ?>>Sur une offre</option>
                        </select>
                    </div>
                    <div class="form-group" id="offre-select" style="<?= !$offreId ? 'display:none' : '' ?>">
                        <label>Offre visée</label>
                        <select name="offre_id">
                            <option value="">— Aucune —</option>
                            <?php foreach ($offres as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= $o['id'] == $offreId ? 'selected' : '' ?>>
                                <?= h($o['reference'] . ' — ' . $o['titre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Direction souhaitée</label>
                        <select name="direction_id">
                            <option value="">— Aucune —</option>
                            <?php foreach ($directions as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= h($d['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Domaine de candidature</label>
                        <select name="domaine_cand_id">
                            <option value="">— Aucun —</option>
                            <?php foreach ($domaines as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= h($d['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Lettre de motivation</label>
                        <textarea name="motivation" rows="4" placeholder="Présentez-vous et expliquez votre motivation..."></textarea>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:24px">
                    <a href="/stage2/index.php" class="btn btn-ghost">← Annuler</a>
                    <button type="submit" class="btn btn-red" style="font-size:15px;padding:12px 28px">
                        Créer mon compte & Soumettre →
                    </button>
                </div>
            </form>
        </div>
    </div>

    <p style="text-align:center;font-size:13px;color:#6b7280">
        Déjà inscrit ? <a href="/stage2/index.php">Connectez-vous</a>
    </p>
</div>

<script>
function toggleOffre() {
    const type = document.getElementById('type-cand').value;
    document.getElementById('offre-select').style.display = type === 'offre' ? '' : 'none';
}
</script>
</body>
</html>
