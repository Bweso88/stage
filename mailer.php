<?php
/**
 * StagIA - Mailer SMTP + Templates
 */

class SmtpMailer {
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $fromEmail;
    private string $fromName;
    private string $secure;

    public function __construct() {
        $this->host      = $_ENV['SMTP_HOST']       ?? 'smtp.gmail.com';
        $this->port      = (int)($_ENV['SMTP_PORT'] ?? 587);
        $this->user      = $_ENV['SMTP_USER']       ?? '';
        $this->pass      = $_ENV['SMTP_PASS']       ?? '';
        $this->fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? $this->user;
        $this->fromName  = $_ENV['SMTP_FROM_NAME']  ?? 'StagIA';
        $this->secure    = $_ENV['SMTP_SECURE']     ?? 'tls';
    }

    public function send(string $to, string $toName, string $subject, string $html): bool {
        $socket = @fsockopen(
            ($this->secure === 'ssl' ? 'ssl://' : '') . $this->host,
            $this->port,
            $errno,
            $errstr,
            10
        );

        if (!$socket) {
            error_log("SMTP connect failed: $errstr ($errno)");
            return false;
        }

        $read = fgets($socket, 515);

        if ($this->secure === 'tls') {
            $this->smtpCmd($socket, "EHLO localhost");
            $this->smtpCmd($socket, "STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }

        $this->smtpCmd($socket, "EHLO localhost");
        $this->smtpCmd($socket, "AUTH LOGIN");
        $this->smtpCmd($socket, base64_encode($this->user));
        $this->smtpCmd($socket, base64_encode($this->pass));
        $this->smtpCmd($socket, "MAIL FROM:<{$this->fromEmail}>");
        $this->smtpCmd($socket, "RCPT TO:<$to>");
        $this->smtpCmd($socket, "DATA");

        $boundary = md5(uniqid());
        $headers  = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "To: $toName <$to>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";
        $headers .= "\r\n";
        $headers .= chunk_split(base64_encode($html));
        $headers .= "\r\n.\r\n";

        fputs($socket, $headers);
        fgets($socket, 515);

        $this->smtpCmd($socket, "QUIT");
        fclose($socket);

        return true;
    }

    private function smtpCmd($socket, string $cmd): string {
        fputs($socket, $cmd . "\r\n");
        return fgets($socket, 515);
    }
}

// ─── Templates email ───────────────────────────────────────────────────────────
class MailTemplates {

    private static function base(string $title, string $body): string {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
            body{font-family:Montserrat,Arial,sans-serif;background:#f4f5f7;margin:0;padding:0}
            .wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
            .header{background:#1b2a6b;padding:28px 32px;color:#fff}
            .header h1{margin:0;font-size:22px;font-weight:700}
            .header p{margin:4px 0 0;font-size:13px;opacity:.8}
            .body{padding:32px}
            .body p{color:#374151;font-size:14px;line-height:1.7;margin:0 0 14px}
            .btn{display:inline-block;background:#e8001c;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;margin:8px 0}
            .info-box{background:#f0f4ff;border-left:4px solid #1b2a6b;padding:14px 18px;border-radius:0 8px 8px 0;margin:16px 0}
            .info-box p{margin:4px 0;font-size:13px}
            .footer{background:#f4f5f7;padding:18px 32px;text-align:center;font-size:12px;color:#9ca3af}
        </style></head><body>
        <div class="wrap">
            <div class="header"><h1>StagIA</h1><p>' . $title . '</p></div>
            <div class="body">' . $body . '</div>
            <div class="footer">Système de gestion des stages &mdash; StagIA &bull; Cet email est automatique, ne pas répondre.</div>
        </div></body></html>';
    }

    public static function bienvenue(string $prenom, string $email, string $ref, float $score, string $offre): string {
        $body = '<p>Bonjour <strong>' . htmlspecialchars($prenom) . '</strong>,</p>
        <p>Votre candidature a bien été reçue et enregistrée dans notre système.</p>
        <div class="info-box">
            <p><strong>Référence :</strong> ' . htmlspecialchars($ref) . '</p>
            <p><strong>Score initial :</strong> ' . number_format($score, 1) . '/100</p>
            <p><strong>Offre :</strong> ' . htmlspecialchars($offre) . '</p>
        </div>
        <p>Notre équipe va examiner votre candidature. Vous serez notifié(e) par email à chaque étape du processus.</p>
        <p>Vous pouvez suivre l\'état de votre candidature en vous connectant à votre espace stagiaire.</p>';
        return self::base('Candidature reçue', $body);
    }

    public static function decision(string $prenom, string $ref, string $statut, string $commentaire, string $niveau): string {
        $statuts = [
            'valide'     => ['Validée ✓', '#16a34a'],
            'rejete'     => ['Rejetée ✗', '#dc2626'],
            'complement' => ['Complément requis', '#d97706'],
        ];
        [$label, $color] = $statuts[$statut] ?? ['Mise à jour', '#1b2a6b'];
        $niv = $niveau === 'niv1' ? 'Niveau 1 (Habilité)' : 'Niveau 2 (Superviseur)';

        $body = '<p>Bonjour <strong>' . htmlspecialchars($prenom) . '</strong>,</p>
        <p>Une décision a été prise concernant votre candidature <strong>' . htmlspecialchars($ref) . '</strong>.</p>
        <div class="info-box">
            <p><strong>Étape :</strong> ' . $niv . '</p>
            <p><strong>Décision :</strong> <span style="color:' . $color . ';font-weight:600">' . $label . '</span></p>
            ' . ($commentaire ? '<p><strong>Commentaire :</strong> ' . htmlspecialchars($commentaire) . '</p>' : '') . '
        </div>
        <p>Connectez-vous à votre espace pour plus d\'informations.</p>';
        return self::base('Décision sur votre candidature', $body);
    }

    public static function renouvellement(string $prenom, string $refStage, string $statut, string $dateFin): string {
        $statuts = [
            'valide' => ['Approuvé ✓', '#16a34a'],
            'rejete' => ['Refusé ✗', '#dc2626'],
            'precisions' => ['Précisions requises', '#d97706'],
        ];
        [$label, $color] = $statuts[$statut] ?? ['Mise à jour', '#1b2a6b'];

        $body = '<p>Bonjour <strong>' . htmlspecialchars($prenom) . '</strong>,</p>
        <p>Une décision a été prise concernant votre demande de renouvellement pour le stage <strong>' . htmlspecialchars($refStage) . '</strong>.</p>
        <div class="info-box">
            <p><strong>Décision :</strong> <span style="color:' . $color . ';font-weight:600">' . $label . '</span></p>
            <p><strong>Nouvelle date de fin :</strong> ' . htmlspecialchars($dateFin) . '</p>
        </div>
        <p>Connectez-vous à votre espace pour consulter les détails.</p>';
        return self::base('Renouvellement de stage', $body);
    }

    public static function lettreStage(string $prenom, string $nom, string $email, string $refStage, string $dateDebut, string $dateFin, float $mois, string $refCand): string {
        $body = '<p>Bonjour <strong>' . htmlspecialchars($prenom . ' ' . $nom) . '</strong>,</p>
        <p>Nous avons le plaisir de vous confirmer votre stage au sein de notre organisation.</p>
        <div class="info-box">
            <p><strong>Référence stage :</strong> ' . htmlspecialchars($refStage) . '</p>
            <p><strong>Référence candidature :</strong> ' . htmlspecialchars($refCand) . '</p>
            <p><strong>Date de début :</strong> ' . htmlspecialchars($dateDebut) . '</p>
            <p><strong>Date de fin :</strong> ' . htmlspecialchars($dateFin) . '</p>
            <p><strong>Durée :</strong> ' . number_format($mois, 1) . ' mois</p>
        </div>
        <p>Votre lettre de stage officielle est disponible dans votre espace stagiaire.</p>
        <p>Nous vous souhaitons la bienvenue et espérons que ce stage sera enrichissant pour vous.</p>';
        return self::base('Votre lettre de stage', $body);
    }
}
