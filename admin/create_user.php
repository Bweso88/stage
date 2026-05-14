<?php
require "auth.php";
require "db.php";

if ($_SESSION["user"]["role"] !== "ADMIN") {
    die("Accès interdit");
}

$msg = $error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
    $check->execute([$_POST["username"]]);

    if ($check->fetchColumn() > 0) {
        $error = "Ce nom d'utilisateur existe déjà";
    } else {
        $hash = password_hash($_POST["password"], PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO users (username,password,fullname,role)
             VALUES (?,?,?,?)"
        );
        $stmt->execute([
            $_POST["username"],
            $hash,
            $_POST["fullname"],
            $_POST["role"]
        ]);

        $msg = "✅ Compte créé avec succès";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Créer utilisateur</title>
<link href="../css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
<div class="container-fluid">
<span class="navbar-brand">Création de compte</span>
<a href="dashboard.php" class="btn btn-light btn-sm">Retour</a>
</div>
</nav>

<div class="container mt-4">
<div class="row justify-content-center">
<div class="col-md-6">

<div class="card shadow">
<div class="card-body">

<h4 class="mb-3 text-center">Nouvel utilisateur</h4>

<?php if ($msg): ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<form method="post">

<div class="mb-3">
    <label class="form-label">Nom complet</label>
    <input type="text" name="fullname" class="form-control" required>
</div>

<div class="mb-3">
    <label class="form-label">Nom d'utilisateur</label>
    <input type="text" name="username" class="form-control" required>
</div>

<div class="mb-3">
    <label class="form-label">Mot de passe</label>
    <input type="password" name="password" class="form-control" required>
</div>

<div class="mb-4">
    <label class="form-label">Rôle</label>
    <select name="role" class="form-select">
        <option value="FORMATEUR">Formateur</option>
        <option value="ADMIN">Administrateur</option>
    </select>
</div>

<button class="btn btn-success w-100">Créer le compte</button>

</form>

</div>
</div>

</div>
</div>
</div>

</body>
</html>
