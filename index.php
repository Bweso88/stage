<?php
$videoDir = "videos/";
$themes = array_filter(glob($videoDir . "*"), 'is_dir');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>🎓 Espace Formation - Thèmes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="css/bootstrap.min.css" rel="stylesheet">
    <script src="js/bootstrap.bundle.min.js"></script>

    <style>
        body {
            background: #f7f9fc;
            font-family: "Segoe UI", Roboto, sans-serif;
        }

        .navbar {
            background: linear-gradient(90deg, #1b3b6f, #2d8adb);
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .navbar-brand {
            font-weight: bold;
            color: #fff !important;
            font-size: 1.2rem;
        }

        .navbar img {
            background: #fff;
            border-radius: 50%;
            padding: 3px;
            margin-right: 10px;
        }

        .theme-card {
            border: none;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform .2s ease, box-shadow .2s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }

        .theme-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.15);
        }

        .theme-icon {
            font-size: 3rem;
        }

        .theme-title {
            color: #1b3b6f;
            font-weight: 700;
            margin-top: 12px;
        }

        .theme-count {
            color: #666;
            font-size: 0.95rem;
        }

        footer {
            margin-top: 60px;
            text-align: center;
            padding: 20px;
            background: #1b3b6f;
            color: #fff;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="images/MUCODEC.gif" alt="Logo MUCODEC" width="45" height="45">
            ESPACE FORMATION - LA MINUTE AMPLITUDE
        </a>
    </div>
</nav>

<div class="container mt-5">
    <h2 class="text-primary text-center mb-3">📚 Thèmes de formation</h2>
    <h3 class="text-secondary text-center mb-4"><strong>DRH - Service Formation</strong></h3>
    <hr><br>

    <?php if (empty($themes)): ?>
        <div class="alert alert-warning text-center">
            Aucun thème trouvé dans le dossier <strong>videos/</strong>.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($themes as $theme): ?>
                <?php
                    $themeName = basename($theme);
                    $videoCount = count(glob($theme . "/*.mp4"));
                ?>
                <div class="col-md-4 col-sm-6">
                    <a href="videos.php?theme=<?php echo urlencode($themeName); ?>" class="theme-card p-4 text-center">
                        <div class="theme-icon">🎬</div>
                        <h4 class="theme-title"><?php echo htmlspecialchars($themeName); ?></h4>
                        <p class="theme-count"><?php echo $videoCount; ?> vidéo(s)</p>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer>
    &copy; <?php echo date('Y'); ?> DIDSI - Tous droits réservés.
    &nbsp;|&nbsp;
    <a href="admin/login.php" style="color:#aac4e8;font-size:.85rem;">Administration</a>
</footer>

</body>
</html>