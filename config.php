<?php
/**
 * StagIA - Configuration principale
 */
define('BASE', '/stage2');

require_once __DIR__ . '/mailer.php';

// Session cookie hardening
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_start();

// HTTP security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'");

// ─── Chargement du fichier .env ───────────────────────────────────────────────
function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // Retirer les guillemets éventuels
        if (strlen($val) >= 2 && $val[0] === '"' && $val[-1] === '"') {
            $val = substr($val, 1, -1);
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
}

loadEnv(__DIR__ . '/.env');

// ─── PDO Singleton ────────────────────────────────────────────────────────────
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $name = $_ENV['DB_NAME'] ?? 'stagia';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        die('<div style="font-family:monospace;background:#fee;border:1px solid #c00;padding:20px;margin:20px;border-radius:8px">
            <strong>Erreur de connexion BDD :</strong><br>' . htmlspecialchars($e->getMessage()) . '
            <br><br>Vérifiez votre fichier <code>.env</code> (DB_HOST, DB_NAME, DB_USER, DB_PASS).
        </div>');
    }
    return $pdo;
}

// ─── Sécurité & helpers HTML ──────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
        $url = '/';
    }
    header('Location: ' . BASE . $url);
    exit;
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
}

function csrfVerify(): void {
    $token    = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!$expected || !hash_equals($expected, $token)) {
        http_response_code(403);
        die('<h1>403 — Requête refusée (jeton CSRF invalide)</h1>');
    }
}

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ─── Helpers Auth ─────────────────────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user']);
}

function isAdmin(): bool {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] !== 'stagiaire';
}

function isStagiaire(): bool {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'stagiaire';
}

function hasRole(string ...$roles): bool {
    if (!isset($_SESSION['user'])) return false;
    return in_array($_SESSION['user']['role'], $roles, true);
}

function requireAdmin(): void {
    if (!isLoggedIn() || !isAdmin()) {
        flash('Accès refusé. Veuillez vous connecter.', 'error');
        redirect('/admin.php');
    }
}

// ─── Rate limiting (brute-force protection) ───────────────────────────────────
function checkLoginRateLimit(string $key): bool {
    $maxAttempts = 5;
    $windowSecs  = 300; // 5 minutes
    $now         = time();
    $sessKey     = 'login_attempts_' . md5($key);

    if (!isset($_SESSION[$sessKey])) {
        $_SESSION[$sessKey] = ['count' => 0, 'since' => $now];
    }

    if ($now - $_SESSION[$sessKey]['since'] > $windowSecs) {
        $_SESSION[$sessKey] = ['count' => 0, 'since' => $now];
    }

    $_SESSION[$sessKey]['count']++;

    return $_SESSION[$sessKey]['count'] <= $maxAttempts;
}

function resetLoginRateLimit(string $key): void {
    unset($_SESSION['login_attempts_' . md5($key)]);
}

function requireStagiaire(): void {
    if (!isLoggedIn() || !isStagiaire()) {
        flash('Veuillez vous connecter à votre espace stagiaire.', 'error');
        redirect('/index.php');
    }
}

// ─── Générateur de référence ──────────────────────────────────────────────────
function genRef(string $prefix, string $table, string $col): string {
    $pdo = getPDO();
    $year = date('Y');
    $pattern = "$prefix-$year-%";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` LIKE ?");
    $stmt->execute([$pattern]);
    $count = (int) $stmt->fetchColumn();
    return sprintf('%s-%s-%03d', $prefix, $year, $count + 1);
}

// ─── Calculateur de score ─────────────────────────────────────────────────────
function calculerScore(int $stag_id): float {
    $pdo = getPDO();
    $score = 0;

    // Niveau d\'étude (max 40 pts)
    $stmt = $pdo->prepare('SELECT niveau_etude FROM stagiaires WHERE id = ?');
    $stmt->execute([$stag_id]);
    $row = $stmt->fetch();
    $niveaux = ['bac' => 10, 'bac+2' => 20, 'bac+3' => 30, 'bac+4' => 35, 'bac+5' => 40, 'doctorat' => 40];
    $score += $niveaux[$row['niveau_etude'] ?? 'bac'] ?? 10;

    // Formations (max 20 pts)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM formations WHERE stagiaire_id = ?');
    $stmt->execute([$stag_id]);
    $score += min(20, (int) $stmt->fetchColumn() * 10);

    // Compétences (max 20 pts)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM competences WHERE stagiaire_id = ?');
    $stmt->execute([$stag_id]);
    $score += min(20, (int) $stmt->fetchColumn() * 4);

    // Expériences (max 20 pts)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM experiences WHERE stagiaire_id = ?');
    $stmt->execute([$stag_id]);
    $score += min(20, (int) $stmt->fetchColumn() * 10);

    return min(100, (float) $score);
}

// ─── Vérificateur de renouvellement ───────────────────────────────────────────
function verifierRenouvellement(int $stage_id, string $date_fin): array {
    $pdo = getPDO();
    $errors = [];

    $stage = $pdo->prepare('SELECT * FROM stages WHERE id = ?');
    $stage->execute([$stage_id]);
    $s = $stage->fetch();

    if (!$s) {
        $errors[] = 'Stage introuvable.';
        return $errors;
    }

    if (in_array($s['statut'], ['termine', 'interrompu', 'annule'], true)) {
        $errors[] = 'Ce stage est terminé ou annulé et ne peut être renouvelé.';
    }

    if ($s['nb_renouvellements'] >= 3) {
        $errors[] = 'Le nombre maximum de renouvellements (3) est atteint.';
    }

    $dateFin = new DateTime($date_fin);
    $dateCurrent = new DateTime($s['date_fin']);
    if ($dateFin <= $dateCurrent) {
        $errors[] = 'La date de fin proposée doit être postérieure à la date de fin actuelle.';
    }

    $diff = $dateCurrent->diff($dateFin);
    $mois = $diff->m + ($diff->y * 12);
    if ($mois > 6) {
        $errors[] = 'La durée de renouvellement ne peut excéder 6 mois.';
    }

    return $errors;
}

// ─── Helpers UI ───────────────────────────────────────────────────────────────
function workflowDots(string $statut): string {
    $steps = [
        'soumise'        => 1,
        'niv1_en_cours'  => 2,
        'niv1_valide'    => 3,
        'validee'        => 4,
        'niv1_rejete'    => -1,
        'rejetee'        => -2,
        'complement'     => 0,
    ];
    $current = $steps[$statut] ?? 1;
    $labels  = ['Soumise', 'Niv.1', 'Niv.2', 'Validée'];

    $html = '<div class="workflow-dots">';
    foreach ($labels as $i => $label) {
        $step = $i + 1;
        if ($current < 0) {
            $cls = $step <= abs($current) ? 'rejected' : 'pending';
        } elseif ($step < $current) {
            $cls = 'done';
        } elseif ($step === $current) {
            $cls = 'active';
        } else {
            $cls = 'pending';
        }
        $html .= "<span class=\"dot $cls\" title=\"$label\"></span>";
        if ($i < count($labels) - 1) $html .= '<span class="dot-line"></span>';
    }
    $html .= '</div>';
    return $html;
}

function statusBadge(string $statut): string {
    $map = [
        'soumise'        => ['Soumise', 'badge-info'],
        'niv1_en_cours'  => ['Niv.1 en cours', 'badge-warning'],
        'niv1_valide'    => ['Niv.1 validé', 'badge-primary'],
        'validee'        => ['Validée', 'badge-success'],
        'niv1_rejete'    => ['Niv.1 rejeté', 'badge-danger'],
        'rejetee'        => ['Rejetée', 'badge-danger'],
        'complement'     => ['Complément requis', 'badge-warning'],
        // stages
        'preparation'    => ['Préparation', 'badge-info'],
        'en_cours'       => ['En cours', 'badge-success'],
        'renouvele'      => ['Renouvelé', 'badge-primary'],
        'termine'        => ['Terminé', 'badge-secondary'],
        'interrompu'     => ['Interrompu', 'badge-warning'],
        'annule'         => ['Annulé', 'badge-danger'],
        // offres
        'ouverte'        => ['Ouverte', 'badge-success'],
        'fermee'         => ['Fermée', 'badge-warning'],
        'archivee'       => ['Archivée', 'badge-secondary'],
        // renouvellements
        'en_attente'     => ['En attente', 'badge-warning'],
        'valide'         => ['Validé', 'badge-success'],
        'rejete'         => ['Rejeté', 'badge-danger'],
        'precisions'     => ['Précisions requises', 'badge-info'],
        // validations
        'valide_v'       => ['Validé', 'badge-success'],
        'rejete_v'       => ['Rejeté', 'badge-danger'],
    ];
    [$label, $cls] = $map[$statut] ?? [ucfirst($statut), 'badge-secondary'];
    return "<span class=\"badge $cls\">" . h($label) . '</span>';
}

function sendMail(string $to, string $sujet, string $html, string $toName = ''): bool {
    try {
        $mailer = new SmtpMailer();
        return $mailer->send($to, $toName, $sujet, $html);
    } catch (Throwable $e) {
        error_log('sendMail error: ' . $e->getMessage());
        return false;
    }
}
