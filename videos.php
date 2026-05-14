<?php
$videoDir = "videos/";
$viewsDir = "views/";

if (!file_exists($viewsDir)) {
    mkdir($viewsDir, 0777, true);
}

if (isset($_GET['view'])) {
    $video = basename($_GET['view']);
    $viewFile = $viewsDir . pathinfo($video, PATHINFO_FILENAME) . ".txt";

    $count = file_exists($viewFile) ? (int)file_get_contents($viewFile) + 1 : 1;
    file_put_contents($viewFile, $count);
    exit;
}

$theme = isset($_GET['theme']) ? basename($_GET['theme']) : '';
$themePath = $videoDir . $theme . "/";
$videos = [];

if (!empty($theme) && is_dir($themePath)) {
    $videos = glob($themePath . "*.mp4");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Formation - <?php echo htmlspecialchars($theme ?: 'Vidéos'); ?></title>
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

        .back-link {
            text-decoration: none;
            font-weight: 600;
        }

        .card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .card:hover {
            transform: scale(1.03);
            box-shadow: 0 8px 18px rgba(0,0,0,0.15);
        }

        video {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-bottom: 3px solid #007bff;
        }

        .card-body {
            text-align: center;
            padding: 12px;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1b3b6f;
        }

        .views {
            color: #666;
            font-size: 0.9rem;
        }

        footer {
            margin-top: 60px;
            text-align: center;
            padding: 20px;
            background: #1b3b6f;
            color: #fff;
        }

        .modal-content {
            border-radius: 12px;
            overflow: hidden;
        }

        .modal-body video {
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
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
    <div class="mb-3">
        <a href="index.php" class="back-link">⬅ Retour aux thèmes</a>
    </div>

    <h2 class="text-primary mb-3 text-center">🎓 Tutoriels vidéo</h2>
    <h3 class="text-secondary mb-2 text-center">
        <strong>Thème : <?php echo htmlspecialchars($theme ?: 'Non défini'); ?></strong>
    </h3>
    <h4 class="text-center mb-4">DRH - Service Formation</h4>
    <hr><br>

    <?php if (empty($theme) || !is_dir($themePath)): ?>
        <div class="alert alert-danger text-center">
            Thème introuvable.
        </div>
    <?php elseif (empty($videos)): ?>
        <div class="alert alert-warning text-center">
            Aucune vidéo trouvée dans le thème <strong><?php echo htmlspecialchars($theme); ?></strong>.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($videos as $video): ?>
                <?php
                    $filename = pathinfo($video, PATHINFO_FILENAME);
                    $viewFile = $viewsDir . $filename . ".txt";
                    $views = file_exists($viewFile) ? (int)file_get_contents($viewFile) : 0;
                    $displayName = ucfirst(str_replace('_', ' ', $filename));
                ?>
                <div class="col-md-4 col-sm-6">
                    <div class="card"
                         data-bs-toggle="modal"
                         data-bs-target="#videoModal"
                         onclick="openVideo('<?php echo htmlspecialchars($video); ?>', '<?php echo addslashes($displayName); ?>')">
                        <video preload="metadata" loading="lazy">
                            <source src="<?php echo htmlspecialchars($video); ?>#t=0.1" type="video/mp4">
                        </video>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($displayName); ?></h5>
                            <p class="views">👁️ <?php echo $views; ?> vues</p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="videoModalLabel">Lecture</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body text-center">
                <video id="modalVideo" controls autoplay>
                    <source src="" type="video/mp4">
                    Votre navigateur ne supporte pas la lecture vidéo.
                </video>
            </div>
        </div>
    </div>
</div>

<footer>
    &copy; <?php echo date('Y'); ?> DIDSI - Tous droits réservés.
</footer>

<script>
function openVideo(videoSrc, title) {
    const modalVideo = document.getElementById('modalVideo');
    const modalTitle = document.getElementById('videoModalLabel');

    modalTitle.innerText = title;
    modalVideo.src = videoSrc;

    fetch("videos.php?theme=<?php echo urlencode($theme); ?>&view=" + encodeURIComponent(videoSrc))
        .then(() => console.log("Vue enregistrée"))
        .catch(e => console.error("Erreur :", e));
}

document.getElementById('videoModal').addEventListener('hidden.bs.modal', function () {
    const modalVideo = document.getElementById('modalVideo');
    modalVideo.pause();
    modalVideo.src = "";
});
</script>

</body>
</html>