<?php
require_once __DIR__ . '/config.php';

// Redirige si déjà connecté
if (isStagiaire())  redirect('/espace-stagiaire.php');
if (isAdmin())      redirect('/backoffice.php');

// Connexion stagiaire
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_stagiaire'])) {
    $email = trim($_POST['email'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    if (!checkLoginRateLimit('stagiaire:' . $email)) {
        $error = 'Trop de tentatives. Réessayez dans 5 minutes.';
    } else {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT u.*, s.id AS stagiaire_id FROM utilisateurs u JOIN stagiaires s ON s.utilisateur_id = u.id WHERE u.email = ? AND u.actif = 1 AND u.role = 'stagiaire'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($mdp, $user['mot_de_passe'])) {
            resetLoginRateLimit('stagiaire:' . $email);
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'           => $user['id'],
                'nom'          => $user['nom'],
                'prenom'       => $user['prenom'],
                'email'        => $user['email'],
                'role'         => $user['role'],
                'stagiaire_id' => $user['stagiaire_id'],
            ];
            redirect('/espace-stagiaire.php');
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}

$flash = getFlash();
$pdo   = getPDO();

// Offres ouvertes
$offres = $pdo->query("
    SELECT o.*, d.libelle AS direction, dom.libelle AS domaine
    FROM offres_stage o
    LEFT JOIN directions d ON d.id = o.direction_id
    LEFT JOIN domaines dom ON dom.id = o.domaine_id
    WHERE o.statut = 'ouverte'
    ORDER BY o.date_publication DESC, o.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StagIA — Offres de stage</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Montserrat', sans-serif; background: #f4f5f7; color: #111827; }
        .navbar {
            background: #1b2a6b;
            padding: 0 32px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand { font-size: 22px; font-weight: 700; color: #fff; }
        .brand span { color: #e8001c; }
        .nav-links { display: flex; gap: 12px; align-items: center; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: none; border-radius: 8px; font-family: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; text-decoration: none; transition: opacity .15s; }
        .btn:hover { opacity: .88; }
        .btn-white { background: #fff; color: #1b2a6b; }
        .btn-red   { background: #e8001c; color: #fff; }
        .btn-ghost { background: rgba(255,255,255,.15); color: #fff; }

        .hero {
            background: linear-gradient(135deg, #1b2a6b 0%, #2d3c8a 100%);
            padding: 60px 32px;
            text-align: center;
            color: #fff;
        }
        .hero h1 { font-size: 36px; font-weight: 700; margin-bottom: 12px; }
        .hero p  { font-size: 16px; opacity: .8; max-width: 600px; margin: 0 auto 24px; }

        .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }
        .section { padding: 40px 0; }

        .offres-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .offre-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e4ea;
            overflow: hidden;
            transition: box-shadow .2s;
        }
        .offre-card:hover { box-shadow: 0 4px 20px rgba(27,42,107,.12); }
        .offre-card-header {
            background: #1b2a6b;
            padding: 18px 20px;
            color: #fff;
        }
        .offre-card-header h3 { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
        .offre-card-header .ref { font-size: 11px; opacity: .6; font-family: monospace; }
        .offre-card-body { padding: 16px 20px; }
        .offre-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; }
        .badge-blue  { background: #eff6ff; color: #1d4ed8; }
        .badge-teal  { background: #f0fdfa; color: #0f766e; }
        .badge-amber { background: #fffbeb; color: #b45309; }
        .offre-desc { font-size: 13.5px; color: #6b7280; line-height: 1.6; margin-bottom: 14px; }
        .offre-footer { display: flex; align-items: center; justify-content: space-between; }
        .offre-deadline { font-size: 12px; color: #9ca3af; }

        /* Login panel */
        .login-section {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e4ea;
            padding: 28px;
            max-width: 400px;
            margin: 0 auto;
        }
        .login-section h2 { font-size: 17px; font-weight: 600; color: #1b2a6b; margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 5px; }
        input { width: 100%; padding: 9px 12px; border: 1.5px solid #e2e4ea; border-radius: 8px; font-family: inherit; font-size: 13.5px; outline: none; transition: border-color .2s; }
        input:focus { border-color: #1b2a6b; }
        .alert-error { background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #16a34a; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .btn-full { width: 100%; justify-content: center; padding: 11px; font-size: 14px; }

        .tabs { display: flex; gap: 0; border-bottom: 2px solid #e2e4ea; margin-bottom: 24px; }
        .tab-btn { padding: 10px 20px; font-family: inherit; font-size: 14px; font-weight: 600; border: none; background: none; cursor: pointer; color: #6b7280; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: color .2s, border-color .2s; }
        .tab-btn.active { color: #1b2a6b; border-bottom-color: #1b2a6b; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        footer { background: #1b2a6b; color: rgba(255,255,255,.5); text-align: center; padding: 24px; font-size: 13px; margin-top: 40px; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="brand">Stag<span>IA</span></div>
    <div class="nav-links">
        <a href="/admin.php" class="btn btn-ghost btn-sm">Administration</a>
        <a href="/inscription.php" class="btn btn-red btn-sm">Postuler</a>
    </div>
</nav>

<div class="hero">
    <h1>Offres de stage</h1>
    <p>Découvrez nos opportunités de stage et rejoignez notre organisation.</p>
    <a href="/inscription.php" class="btn btn-red" style="font-size:15px;padding:12px 28px">Déposer ma candidature →</a>
</div>

<div class="container section">
    <div style="display:grid;grid-template-columns:1fr 380px;gap:32px;align-items:start">
        <!-- Offres -->
        <div>
            <h2 style="font-size:20px;font-weight:700;color:#1b2a6b;margin-bottom:20px">
                Offres disponibles <span style="color:#e8001c">(<?= count($offres) ?>)</span>
            </h2>

            <?php if (!$offres): ?>
            <div style="text-align:center;padding:40px;background:#fff;border-radius:12px;border:1px solid #e2e4ea">
                <div style="font-size:40px;margin-bottom:12px">📭</div>
                <p style="color:#6b7280">Aucune offre disponible pour le moment.</p>
                <a href="/inscription.php" class="btn btn-red" style="margin-top:16px">Candidature spontanée</a>
            </div>
            <?php else: ?>
            <div class="offres-grid">
                <?php foreach ($offres as $o): ?>
                <div class="offre-card">
                    <div class="offre-card-header">
                        <div class="ref"><?= h($o['reference']) ?></div>
                        <h3><?= h($o['titre']) ?></h3>
                    </div>
                    <div class="offre-card-body">
                        <div class="offre-meta">
                            <?php if ($o['direction']): ?>
                            <span class="badge badge-blue"><?= h($o['direction']) ?></span>
                            <?php endif; ?>
                            <span class="badge badge-teal"><?= h($o['niveau_minimum']) ?> min.</span>
                            <span class="badge badge-amber"><?= $o['nb_places'] ?> place(s)</span>
                        </div>
                        <?php if ($o['description']): ?>
                        <div class="offre-desc"><?= h(substr($o['description'], 0, 120)) . (strlen($o['description']) > 120 ? '…' : '') ?></div>
                        <?php endif; ?>
                        <div class="offre-footer">
                            <div class="offre-deadline">
                                <?php if ($o['date_limite']): ?>
                                Limite : <?= date('d/m/Y', strtotime($o['date_limite'])) ?>
                                <?php endif; ?>
                            </div>
                            <a href="/inscription.php?offre_id=<?= $o['id'] ?>" class="btn btn-red btn-sm">Postuler</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Connexion / Espace stagiaire -->
        <div>
            <div class="login-section">
                <div class="tabs">
                    <button class="tab-btn active" onclick="showTab('tab-login')">Se connecter</button>
                    <button class="tab-btn" onclick="showTab('tab-inscrit')">Nouveau ?</button>
                </div>

                <div id="tab-login" class="tab-content active">
                    <h2>Espace stagiaire</h2>

                    <?php if ($flash): ?>
                    <div class="alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= h($flash['msg']) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                    <div class="alert-error"><?= h($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
            <?= csrfField() ?>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required placeholder="votre@email.com" value="<?= h($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <input type="password" name="mot_de_passe" required placeholder="••••••••">
                        </div>
                        <button type="submit" name="login_stagiaire" value="1" class="btn btn-red btn-full">Se connecter</button>
                    </form>
                </div>

                <div id="tab-inscrit" class="tab-content">
                    <h2>Première visite ?</h2>
                    <p style="font-size:13.5px;color:#6b7280;margin-bottom:20px;line-height:1.6">
                        Créez votre compte et soumettez votre candidature en quelques minutes.
                    </p>
                    <a href="/inscription.php" class="btn btn-red btn-full">Créer mon compte →</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/widget-amina.php'; ?>

<footer>StagIA &mdash; Gestion des stages &copy; <?= date('Y') ?></footer>

<script>
function showTab(id) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    event.target.classList.add('active');
}
</script>
</body>
</html>
