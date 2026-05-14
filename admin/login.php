<?php
session_start();
if (isset($_SESSION["user"])) {
    header("Location: dashboard.php");
    exit;
}
require "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? AND active=1");
    $stmt->execute([$_POST["username"]]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST["password"], $user["password"])) {
        session_regenerate_id(true);
        $_SESSION["user"] = [
            "id"       => $user["id"],
            "username" => $user["username"],
            "fullname" => $user["fullname"],
            "role"     => $user["role"]
        ];
        $pdo->prepare("INSERT INTO user_logins (user_id) VALUES (?)")->execute([$user["id"]]);
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Identifiants incorrects ou compte désactivé.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Connexion – Espace Formation</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:linear-gradient(135deg,#1b3b6f 0%,#2d8adb 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;}
.login-card{background:#fff;border-radius:16px;padding:40px 36px;width:100%;max-width:380px;box-shadow:0 16px 48px rgba(0,0,0,.25);}
.login-logo{display:block;margin:0 auto 16px;background:#fff;border-radius:50%;padding:4px;box-shadow:0 2px 8px rgba(0,0,0,.15);}
.btn-primary{background:linear-gradient(90deg,#1b3b6f,#2d8adb);border:none;}
.btn-primary:hover{background:linear-gradient(90deg,#163263,#2577c2);}
</style>
</head>
<body>

<div class="login-card">
  <img src="../images/MUCODEC.gif" alt="Logo MUCODEC" width="64" height="64" class="login-logo">
  <h4 class="text-center fw-bold text-primary mb-1">Espace Formation</h4>
  <p class="text-center text-muted small mb-4">Administration – Connexion</p>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2 text-center small"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="mb-3">
      <label class="form-label fw-semibold">Identifiant</label>
      <input type="text" name="username" class="form-control" autofocus required
             value="<?php echo htmlspecialchars($_POST["username"] ?? ""); ?>">
    </div>
    <div class="mb-4">
      <label class="form-label fw-semibold">Mot de passe</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100 py-2 fw-semibold">Se connecter</button>
  </form>

  <div class="text-center mt-3">
    <a href="../index.php" class="text-muted small">⬅ Retour à l'espace formation</a>
  </div>
</div>

</body>
</html>
