<?php
/**
 * StagIA - Inscription stagiaire + dépôt de candidature
 */
require_once __DIR__ . '/config.php';

if (isLoggedIn() && isStagiaire()) redirect('espace-stagiaire.php');

$pdo = getPDO();
$errors = [];
$step = (int)($_GET['step'] ?? 1);
$offre_id = (int)($_GET['offre_id'] ?? 0);

// Load form data
$domaines = $pdo->query('SELECT * FROM domaines WHERE actif = 1 ORDER BY libelle')->fetchAll();
$directions = $pdo->query('SELECT * FROM directions WHERE actif = 1 ORDER BY libelle')->fetchAll();
$offres = $pdo->query('SELECT * FROM offres_stage WHERE statut = \'ouverte\' ORDER BY titre')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        // Validate
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $pass   = $_POST['password'] ?? '';
        $pass2  = $_POST['password2'] ?? '';
        $tel    = trim($_POST['telephone'] ?? '');
        $dob    = $_POST['date_naissance'] ?? '';
        $sexe   = $_POST['sexe'] ?? 'M';
        $civil  = $_POST['civilite'] ?? 'M.';
        $ville  = trim($_POST['ville'] ?? '');
        $addr   = trim($_POST['adresse'] ?? '');
        $nat    = trim($_POST['nationalite'] ?? 'Guineenne');
        $niveau = $_POST['niveau_etude'] ?? 'bac+3';
        $domaine_id = (int)($_POST['domaine_id'] ?? 0);

        // Formation
        $diplome = trim($_POST['diplome'] ?? '');
        $specialite = trim($_POST['specialite'] ?? '');
        $etablissement = trim($_POST['etablissement'] ?? '');
        $annee_fin = (int)($_POST['annee_fin'] ?? date('Y'));

        // Competences
        $competences = array_filter(array_map('trim', explode(',', $_POST['competences'] ?? '')));

        // Candidature info
        $type_cand = $_POST['type_candidature'] ?? 'spontanee';
        $offre_sel = (int)($_POST['offre_id'] ?? 0);
        $direction_sel = (int)($_POST['direction_id'] ?? 0);
        $motivation = trim($_POST['motivation'] ?? '');

        if (!$nom) $errors[] = 'Le nom est obligatoire.';
        if (!$prenom) $errors[] = 'Le pr&eacute;nom est obligatoire.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse email invalide.';
        if (strlen($pass) < 8) $errors[] = 'Le mot de passe doit faire au moins 8 caract&egrave;res.';
        if ($pass !== $pass2) $errors[] = 'Les mots de passe ne correspondent pas.';
        if (!$motivation) $errors[] = 'La lettre de motivation est obligatoire.';

        // Check email unique
        if (!$errors) {
            $chk = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ?');
            $chk->execute([$email]);
            if ($chk->fetch()) $errors[] = 'Cet email est d&eacute;j&agrave; utilis&eacute;.';
        }

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                // 1. Create user
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, civilite) VALUES (?,?,?,?,?,?)')
                    ->execute([$nom, $prenom, $email, $hash, 'stagiaire', $civil]);
                $uid = (int)$pdo->lastInsertId();

                // 2. Create stagiaire
                $pdo->prepare('INSERT INTO stagiaires (utilisateur_id, niveau_etude, telephone, adresse, ville, nationalite, date_naissance, sexe, domaine_principal_id) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$uid, $niveau, $tel, $addr, $ville, $nat, $dob ?: null, $sexe, $domaine_id ?: null]);
                $sid = (int)$pdo->lastInsertId();

                // 3. Formation
                if ($diplome) {
                    $pdo->prepare('INSERT INTO formations (stagiaire_id, diplome, specialite, etablissement, annee_fin) VALUES (?,?,?,?,?)')
                        ->execute([$sid, $diplome, $specialite, $etablissement, $annee_fin]);
                }

                // 4. Competences
                foreach ($competences as $comp) {
                    if (strlen($comp) > 1) {
                        $pdo->prepare('INSERT INTO competences (stagiaire_id, libelle) VALUES (?,?)')->execute([$sid, $comp]);
                    }
                }

                // 5. Fichiers joints (CV, pièce d'identité, diplôme)
                $cv_file     = null;
                $pi_file     = null;
                $dip_file    = null;

                $uploadDefs = [
                    'cv'       => ['exts' => ['pdf','doc','docx'], 'dir' => 'cv',       'col' => 'cv_fichier',      'var' => &$cv_file],
                    'pi'       => ['exts' => ['pdf','jpg','jpeg','png'], 'dir' => 'identite',  'col' => 'piece_identite',  'var' => &$pi_file],
                    'diplome'  => ['exts' => ['pdf','jpg','jpeg','png','doc','docx'], 'dir' => 'diplomes', 'col' => 'diplome_fichier', 'var' => &$dip_file],
                ];
                foreach ($uploadDefs as $field => $cfg) {
                    if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, $cfg['exts'], true)) {
                            $uploadDir = __DIR__ . '/uploads/' . $cfg['dir'] . '/';
                            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                            $fname = $field . '_' . $sid . '_' . time() . '.' . $ext;
                            if (move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $fname)) {
                                $cfg['var'] = $fname;
                                $pdo->prepare('UPDATE stagiaires SET ' . $cfg['col'] . ' = ? WHERE id = ?')->execute([$fname, $sid]);
                            }
                        }
                    }
                }

                // 6. Score
                $score = calculerScore($sid);

                // 7. Candidature
                $ref = genRef('CAND', 'candidatures', 'reference');
                $dir_id = null;
                $dom_id = $domaine_id ?: null;
                if ($offre_sel) {
                    $of = $pdo->prepare('SELECT * FROM offres_stage WHERE id = ?');
                    $of->execute([$offre_sel]);
                    $ofData = $of->fetch();
                    if ($ofData) {
                        $dir_id = $ofData['direction_id'];
                        $dom_id = $ofData['domaine_id'];
                    }
                } else {
                    $dir_id = $direction_sel ?: null;
                }

                $pdo->prepare('INSERT INTO candidatures (reference, stagiaire_id, offre_id, type_candidature, domaine_id, direction_id, score_tri, statut_global, motivation) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$ref, $sid, $offre_sel ?: null, $type_cand, $dom_id, $dir_id, $score, 'soumise', $motivation]);

                // 8. Notification
                $pdo->prepare('INSERT INTO notifications (utilisateur_id, type_notification, objet, message) VALUES (?,?,?,?)')
                    ->execute([$uid, 'inscription', 'Inscription confirm&eacute;e', 'Bienvenue sur StagIA ! Votre candidature ' . $ref . ' a &eacute;t&eacute; enregistr&eacute;e.']);

                // 9. Send welcome email
                sendMail($email, 'Bienvenue sur StagIA', MailTemplates::bienvenue($prenom, $email, $ref, $score, $offre_sel ? ($ofData['titre'] ?? '') : ''), $prenom . ' ' . $nom);

                $pdo->commit();

                // Auto-login
                $_SESSION['user'] = [
                    'id'           => $uid,
                    'nom'          => $nom,
                    'prenom'       => $prenom,
                    'email'        => $email,
                    'role'         => 'stagiaire',
                    'stagiaire_id' => $sid,
                ];
                flash('Inscription r&eacute;ussie ! Bienvenue sur StagIA. Votre candidature (' . $ref . ') a &eacute;t&eacute; enregistr&eacute;e.', 'success');
                redirect('espace-stagiaire.php');

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Erreur lors de l\'inscription : ' . h($e->getMessage());
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>StagIA – Inscription</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Montserrat',sans-serif;background:#f4f5f7;color:#2c3e50;}
:root{--navy:#1b2a6b;--red:#e8001c;}
.navbar{background:var(--navy);padding:0 40px;height:60px;display:flex;align-items:center;justify-content:space-between;}
.logo{font-size:22px;font-weight:800;color:#fff;text-decoration:none;}
.logo span{color:var(--red);}
.navbar a{color:rgba(255,255,255,.8);text-decoration:none;font-size:13px;}
.container{max-width:900px;margin:40px auto;padding:0 20px;}
.progress{display:flex;justify-content:center;gap:0;margin-bottom:36px;}
.step-item{display:flex;align-items:center;gap:0;}
.step-circle{width:34px;height:34px;border-radius:50%;border:2px solid #d1d5db;background:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#9ca3af;transition:all .3s;}
.step-circle.active{border-color:var(--navy);background:var(--navy);color:#fff;}
.step-circle.done{border-color:#10b981;background:#10b981;color:#fff;}
.step-line{width:60px;height:2px;background:#d1d5db;}
.step-line.done{background:#10b981;}
.card{background:#fff;border-radius:12px;padding:32px;box-shadow:0 4px 20px rgba(0,0,0,.08);border:1px solid #e0e3ed;}
.card h2{font-size:20px;font-weight:800;color:var(--navy);margin-bottom:6px;}
.card p.subtitle{color:#6b7280;font-size:13px;margin-bottom:24px;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group label{font-size:12px;font-weight:700;color:var(--navy);}
.form-control{width:100%;padding:10px 13px;border:1.5px solid #e0e3ed;border-radius:7px;font-size:13px;font-family:inherit;transition:border-color .2s;}
.form-control:focus{outline:none;border-color:var(--navy);}
textarea.form-control{min-height:120px;resize:vertical;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:11px 22px;border-radius:7px;font-size:14px;font-weight:700;border:none;cursor:pointer;font-family:inherit;transition:all .2s;text-decoration:none;}
.btn-primary{background:var(--navy);color:#fff;}
.btn-primary:hover{background:#132050;}
.btn-secondary{background:#e5e7eb;color:#374151;}
.btn-secondary:hover{background:#d1d5db;}
.btn-danger{background:var(--red);color:#fff;}
.btn-danger:hover{background:#c0001a;}
.form-actions{display:flex;gap:12px;margin-top:24px;justify-content:space-between;align-items:center;}
.errors{background:rgba(232,0,28,.06);border:1px solid rgba(232,0,28,.2);border-radius:8px;padding:14px 18px;margin-bottom:20px;}
.errors ul{list-style:none;padding:0;}
.errors li{font-size:13px;color:#991b1b;padding:2px 0;}
.errors li::before{content:'• ';}
.section-sep{border-top:1px solid #e0e3ed;margin:24px 0;padding-top:20px;}
.section-sep h3{font-size:15px;font-weight:700;color:var(--navy);margin-bottom:16px;}
.col-full{grid-column:1/-1;}
.hint{font-size:11px;color:#9ca3af;margin-top:3px;}
.required::after{content:' *';color:var(--red);}
.radio-group{display:flex;gap:16px;margin-top:4px;}
.radio-option{display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;}
.radio-option input{accent-color:var(--navy);}
@media(max-width:700px){.form-grid{grid-template-columns:1fr;}.step-line{width:30px;}}
</style>
</head>
<body>

<nav class="navbar">
  <a href="index.php" class="logo">Stag<span>IA</span></a>
  <a href="index.php">← Retour aux offres</a>
</nav>

<div class="container">
  <div class="card">
    <h2>Inscription &amp; D&eacute;p&ocirc;t de candidature</h2>
    <p class="subtitle">Cr&eacute;ez votre compte et soumettez votre candidature en quelques &eacute;tapes.</p>

    <?php if (!empty($errors)): ?>
    <div class="errors">
      <ul><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="register">

      <!-- Personal info -->
      <div class="section-sep"><h3>👤 Informations personnelles</h3></div>
      <div class="form-grid">
        <div class="form-group">
          <label class="required">Civilit&eacute;</label>
          <select name="civilite" class="form-control">
            <option value="M." <?= (($_POST['civilite']??'M.'))==='M.'?'selected':'' ?>>M.</option>
            <option value="Mme" <?= (($_POST['civilite']??''))==='Mme'?'selected':'' ?>>Mme</option>
          </select>
        </div>
        <div class="form-group">
          <label class="required">Sexe</label>
          <select name="sexe" class="form-control">
            <option value="M" <?= (($_POST['sexe']??'M'))==='M'?'selected':'' ?>>Masculin</option>
            <option value="F" <?= (($_POST['sexe']??''))==='F'?'selected':'' ?>>F&eacute;minin</option>
          </select>
        </div>
        <div class="form-group">
          <label class="required">Nom</label>
          <input type="text" name="nom" class="form-control" value="<?= h($_POST['nom'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="required">Pr&eacute;nom</label>
          <input type="text" name="prenom" class="form-control" value="<?= h($_POST['prenom'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="required">Email</label>
          <input type="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>T&eacute;l&eacute;phone</label>
          <input type="tel" name="telephone" class="form-control" value="<?= h($_POST['telephone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="required">Mot de passe</label>
          <input type="password" name="password" class="form-control" minlength="8" required>
          <span class="hint">Au moins 8 caract&egrave;res</span>
        </div>
        <div class="form-group">
          <label class="required">Confirmer le mot de passe</label>
          <input type="password" name="password2" class="form-control" minlength="8" required>
        </div>
        <div class="form-group">
          <label>Date de naissance</label>
          <input type="date" name="date_naissance" class="form-control" value="<?= h($_POST['date_naissance'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Nationalit&eacute;</label>
          <input type="text" name="nationalite" class="form-control" value="<?= h($_POST['nationalite'] ?? 'Guineenne') ?>">
        </div>
        <div class="form-group">
          <label>Ville</label>
          <input type="text" name="ville" class="form-control" value="<?= h($_POST['ville'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Adresse</label>
          <input type="text" name="adresse" class="form-control" value="<?= h($_POST['adresse'] ?? '') ?>">
        </div>
      </div>

      <!-- Formation -->
      <div class="section-sep"><h3>🎓 Formation principale</h3></div>
      <div class="form-grid">
        <div class="form-group">
          <label class="required">Niveau d'&eacute;tudes</label>
          <select name="niveau_etude" class="form-control">
            <?php foreach (['bac','bac+2','bac+3','bac+4','bac+5','doctorat'] as $niv): ?>
            <option value="<?= $niv ?>" <?= (($_POST['niveau_etude']??'bac+3')===$niv)?'selected':'' ?>><?= strtoupper($niv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Domaine principal</label>
          <select name="domaine_id" class="form-control">
            <option value="">-- S&eacute;lectionnez --</option>
            <?php foreach ($domaines as $d): ?>
            <option value="<?= $d['id'] ?>" <?= (($_POST['domaine_id']??'')==(string)$d['id'])?'selected':'' ?>><?= h($d['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Dipl&ocirc;me</label>
          <input type="text" name="diplome" class="form-control" placeholder="Licence, Master..." value="<?= h($_POST['diplome'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Sp&eacute;cialit&eacute;</label>
          <input type="text" name="specialite" class="form-control" value="<?= h($_POST['specialite'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Etablissement</label>
          <input type="text" name="etablissement" class="form-control" value="<?= h($_POST['etablissement'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Ann&eacute;e de fin</label>
          <input type="number" name="annee_fin" class="form-control" value="<?= h($_POST['annee_fin'] ?? date('Y')) ?>" min="2000" max="2030">
        </div>
      </div>

      <!-- Competences -->
      <div class="section-sep"><h3>⚡ Comp&eacute;tences</h3></div>
      <div class="form-group">
        <label>Comp&eacute;tences (s&eacute;par&eacute;es par des virgules)</label>
        <input type="text" name="competences" class="form-control" placeholder="PHP, JavaScript, Excel, ..." value="<?= h($_POST['competences'] ?? '') ?>">
        <span class="hint">Exemple : PHP, MySQL, JavaScript, Microsoft Office</span>
      </div>

      <!-- Documents -->
      <div class="section-sep"><h3>📄 Documents &agrave; joindre</h3></div>
      <div class="form-grid">
        <div class="form-group">
          <label>CV (Curriculum Vitae)</label>
          <input type="file" name="cv" class="form-control" accept=".pdf,.doc,.docx">
          <span class="hint">PDF, DOC, DOCX — max 5 Mo</span>
        </div>
        <div class="form-group">
          <label>Pi&egrave;ce d'identit&eacute;</label>
          <input type="file" name="pi" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
          <span class="hint">PDF ou image (JPG, PNG) — max 5 Mo</span>
        </div>
        <div class="form-group col-full">
          <label>Dernier dipl&ocirc;me ou attestation</label>
          <input type="file" name="diplome" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
          <span class="hint">PDF, image ou document Word — max 5 Mo</span>
        </div>
      </div>

      <!-- Candidature -->
      <div class="section-sep"><h3>📋 Ma candidature</h3></div>
      <div class="form-grid">
        <div class="form-group">
          <label>Type de candidature</label>
          <div class="radio-group">
            <label class="radio-option">
              <input type="radio" name="type_candidature" value="offre" <?= (($_POST['type_candidature']??'offre'))==='offre'?'checked':'' ?> onchange="toggleOffre(this)"> R&eacute;pondre &agrave; une offre
            </label>
            <label class="radio-option">
              <input type="radio" name="type_candidature" value="spontanee" <?= (($_POST['type_candidature']??''))==='spontanee'?'checked':'' ?> onchange="toggleOffre(this)"> Candidature spontan&eacute;e
            </label>
          </div>
        </div>
        <div class="form-group" id="offre-select" style="<?= (($_POST['type_candidature']??'offre'))==='spontanee'?'display:none':'' ?>">
          <label>Offre de stage</label>
          <select name="offre_id" class="form-control">
            <option value="">-- S&eacute;lectionnez une offre --</option>
            <?php foreach ($offres as $o): ?>
            <option value="<?= $o['id'] ?>" <?= (($_POST['offre_id']??'')==(string)$o['id'] || $offre_id===$o['id'])?'selected':'' ?>><?= h($o['reference'] . ' – ' . $o['titre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" id="direction-select" style="<?= (($_POST['type_candidature']??'offre')!=='spontanee'?'display:none':'') ?>">
          <label>Direction souhait&eacute;e</label>
          <select name="direction_id" class="form-control">
            <option value="">-- S&eacute;lectionnez --</option>
            <?php foreach ($directions as $d): ?>
            <option value="<?= $d['id'] ?>" <?= (($_POST['direction_id']??'')==(string)$d['id'])?'selected':'' ?>><?= h($d['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-full">
          <label class="required">Lettre de motivation</label>
          <textarea name="motivation" class="form-control" rows="6" required placeholder="Expliquez votre motivation pour ce stage..."><?= h($_POST['motivation'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-actions">
        <a href="index.php" class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-primary">Soumettre ma candidature →</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleOffre(el) {
    var offre = document.getElementById('offre-select');
    var dir = document.getElementById('direction-select');
    if (el.value === 'offre') {
        offre.style.display = '';
        dir.style.display = 'none';
    } else {
        offre.style.display = 'none';
        dir.style.display = '';
    }
}
</script>
</body>
</html>
