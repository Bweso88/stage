<?php
/**
 * Authentification légère des apprenants (pages publiques).
 * Complètement séparée du login administrateur.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($pdo)) require_once __DIR__ . '/admin/db.php';

// Création des tables au premier lancement
if (!file_exists(__DIR__ . '/.viewer_setup_done')) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS viewers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            windows_login VARCHAR(100) UNIQUE NOT NULL,
            fullname      VARCHAR(200) DEFAULT '',
            direction     VARCHAR(200) DEFAULT '',
            service       VARCHAR(200) DEFAULT '',
            agence        VARCHAR(200) DEFAULT '',
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS viewer_sessions (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            viewer_id  INT NOT NULL,
            ip_address VARCHAR(45)  DEFAULT '',
            user_agent VARCHAR(500) DEFAULT '',
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (viewer_id) REFERENCES viewers(id) ON DELETE CASCADE
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS video_watch_log (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            viewer_id     INT NOT NULL,
            theme         VARCHAR(200) NOT NULL,
            video         VARCHAR(200) NOT NULL,
            watched_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            watch_seconds INT DEFAULT 0,
            FOREIGN KEY (viewer_id) REFERENCES viewers(id) ON DELETE CASCADE
        )");
        file_put_contents(__DIR__ . '/.viewer_setup_done', date('Y-m-d H:i:s'));
    } catch (Exception $e) {}
}

// Création des tables organisation (directions / services / agences)
if (!file_exists(__DIR__ . '/.org_setup_done')) {
    try {
        // Ajout colonne agence si table viewers existait déjà sans elle
        try { $pdo->exec("ALTER TABLE viewers ADD COLUMN agence VARCHAR(200) DEFAULT ''"); } catch (Exception $e) {}
        $pdo->exec("CREATE TABLE IF NOT EXISTS org_directions (
            id   INT AUTO_INCREMENT PRIMARY KEY,
            nom  VARCHAR(150) NOT NULL,
            type ENUM('centrale','regionale') NOT NULL DEFAULT 'centrale',
            UNIQUE KEY uq_dir_nom (nom)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS org_services (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            direction_id INT NOT NULL,
            nom          VARCHAR(150) NOT NULL,
            FOREIGN KEY (direction_id) REFERENCES org_directions(id) ON DELETE CASCADE
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS org_agences (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            direction_id INT NOT NULL,
            nom          VARCHAR(150) NOT NULL,
            FOREIGN KEY (direction_id) REFERENCES org_directions(id) ON DELETE CASCADE
        )");
        file_put_contents(__DIR__ . '/.org_setup_done', date('Y-m-d H:i:s'));
    } catch (Exception $e) {}
}

/* Détecte le login Windows via NTLM/Kerberos (si Apache configuré) */
function _viewer_sso_login(): ?string {
    foreach (['REMOTE_USER', 'AUTH_USER', 'HTTP_X_REMOTE_USER'] as $k) {
        if (!empty($_SERVER[$k])) {
            $l = $_SERVER[$k];
            if (strpos($l, '\\') !== false) $l = explode('\\', $l)[1];
            elseif (strpos($l, '@') !== false) $l = explode('@', $l)[0];
            return strtolower(trim($l));
        }
    }
    return null;
}

/* Démarre la session viewer et logue la connexion */
function _viewer_start_session(array $v): void {
    global $pdo;
    $_SESSION['viewer'] = [
        'id'        => $v['id'],
        'login'     => $v['windows_login'],
        'fullname'  => $v['fullname'],
        'direction' => $v['direction'],
        'service'   => $v['service'],
        'agence'    => $v['agence'] ?? '',
    ];
    if (empty($_SESSION['viewer_session_logged'])) {
        try {
            $pdo->prepare("INSERT INTO viewer_sessions (viewer_id, ip_address, user_agent)
                           VALUES (?, ?, ?)")
                ->execute([$v['id'], $_SERVER['REMOTE_ADDR'] ?? '',
                           substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);
            $_SESSION['viewer_session_logged'] = true;
        } catch (Exception $e) {}
    }
}

/* À appeler en haut de chaque page publique */
function requireViewerAuth(): void {
    global $pdo;

    if (!empty($_SESSION['viewer']['id'])) {
        try {
            $pdo->prepare("UPDATE viewers SET last_seen = NOW() WHERE id = ?")
                ->execute([$_SESSION['viewer']['id']]);
        } catch (Exception $e) {}
        return;
    }

    $sso      = _viewer_sso_login();
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');

    if ($sso) {
        $stmt = $pdo->prepare("SELECT * FROM viewers WHERE windows_login = ?");
        $stmt->execute([$sso]);
        $viewer = $stmt->fetch();

        if ($viewer && !empty($viewer['direction']) && !empty($viewer['service'])) {
            _viewer_start_session($viewer);
            return;
        }
        header("Location: viewer_login.php?sso=" . urlencode($sso) . "&redirect=$redirect");
        exit;
    }

    header("Location: viewer_login.php?redirect=$redirect");
    exit;
}
