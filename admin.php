<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn() && isAdmin()) {
    redirect('/backoffice.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    if ($email && $mdp) {
        if (!checkLoginRateLimit('admin:' . $email)) {
            $error = 'Trop de tentatives. Réessayez dans 5 minutes.';
        } else {
            $pdo  = getPDO();
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND actif = 1 AND role != 'stagiaire'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($mdp, $user['mot_de_passe'])) {
                resetLoginRateLimit('admin:' . $email);
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'     => $user['id'],
                    'nom'    => $user['nom'],
                    'prenom' => $user['prenom'],
                    'email'  => $user['email'],
                    'role'   => $user['role'],
                ];
                redirect('/backoffice.php');
            } else {
                $error = 'Email ou mot de passe incorrect.';
            }
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StagIA — Connexion administration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f4f5f7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-wrap {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(27,42,107,0.12);
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo .brand {
            font-size: 28px;
            font-weight: 700;
            color: #1b2a6b;
        }
        .login-logo .brand span { color: #e8001c; }
        .login-logo p {
            color: #6b7280;
            font-size: 14px;
            margin-top: 4px;
        }
        h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1b2a6b;
            margin-bottom: 24px;
            text-align: center;
        }
        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e2e4ea;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            color: #111827;
            transition: border-color .2s;
            outline: none;
        }
        input:focus { border-color: #1b2a6b; }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #1b2a6b;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
            margin-top: 8px;
        }
        .btn-submit:hover { background: #142059; }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #16a34a;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #1b2a6b;
            font-size: 13px;
            text-decoration: none;
        }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-logo">
            <div class="brand">Stag<span>IA</span></div>
            <p>Gestion des stages</p>
        </div>
        <h2>Connexion administration</h2>

        <?php if ($flash): ?>
            <div class="alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= h($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" placeholder="votre@email.com"
                       value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="mot_de_passe">Mot de passe</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-submit">Se connecter</button>
        </form>

        <div class="back-link">
            <a href="/stage2/index.php">← Retour au site</a>
        </div>
    </div>
</body>
</html>
