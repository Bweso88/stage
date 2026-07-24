<?php
/**
 * Stage - Mailer SMTP
 */

class SmtpMailer {
    private string $host;
    private int $port;
    private string $secure;
    private string $user;
    private string $pass;
    private string $fromEmail;
    private string $fromName;

    public function __construct() {
        $this->host      = $_ENV['SMTP_HOST']       ?? 'localhost';
        $this->port      = (int)($_ENV['SMTP_PORT'] ?? 587);
        $this->secure    = $_ENV['SMTP_SECURE']     ?? 'tls';
        $this->user      = $_ENV['SMTP_USER']       ?? '';
        $this->pass      = $_ENV['SMTP_PASS']       ?? '';
        $this->fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@stagia.org';
        $this->fromName  = $_ENV['SMTP_FROM_NAME']  ?? 'Stage';
    }

    public function send(string $to, string $toName, string $subject, string $htmlBody): bool {
        $boundary = uniqid('boundary_', true);
        $headers  = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Reply-To: ' . $this->fromEmail;
        $headers[] = 'X-Mailer: Stage/1.0';

        $toHeader = $toName ? "$toName <$to>" : $to;

        $textBody = strip_tags($htmlBody);
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= "--{$boundary}--";

        return mail($toHeader, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
    }
}

class MailTemplates {
    private static function layout(string $title, string $content): string {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<style>
body{font-family:Montserrat,Arial,sans-serif;background:#f4f5f7;margin:0;padding:0}
.wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)}
.header{background:#1b2a6b;color:#fff;padding:30px;text-align:center}
.header h1{margin:0;font-size:24px;letter-spacing:2px}
.header span{color:#e8001c}
.body{padding:30px;color:#333;line-height:1.6}
.btn{display:inline-block;background:#e8001c;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:700;margin:20px 0}
.footer{background:#1b2a6b;color:#8899bb;padding:15px;text-align:center;font-size:12px}
.info-box{background:#f4f5f7;border-left:4px solid #1b2a6b;padding:15px;margin:15px 0;border-radius:0 6px 6px 0}
</style></head><body>
<div class="wrap">
<div class="header"><h1>Stage</h1><p style="margin:5px 0;font-size:13px">' . htmlspecialchars($title) . '</p></div>
<div class="body">' . $content . '</div>
<div class="footer">Ce message est envoy&eacute; automatiquement par Stage &mdash; Ne pas r&eacute;pondre</div>
</div></body></html>';
    }

    public static function bienvenue(string $prenom, string $email, string $ref, float $score, string $offre = ''): string {
        $offreLine = $offre ? '<p>Offre concern&eacute;e : <strong>' . htmlspecialchars($offre) . '</strong></p>' : '<p>Candidature spontan&eacute;e</p>';
        $content = '<p>Bonjour <strong>' . htmlspecialchars($prenom) . '</strong>,</p>
<p>Votre candidature a &eacute;t&eacute; re&ccedil;ue et enregistr&eacute;e avec succ&egrave;s sur la plateforme Stage.</p>
<div class="info-box">
  <p><strong>R&eacute;f&eacute;rence :</strong> ' . htmlspecialchars($ref) . '</p>
  <p><strong>Email :</strong> ' . htmlspecialchars($email) . '</p>
  <p><strong>Score de tri :</strong> ' . number_format($score, 1) . ' / 100</p>
  ' . $offreLine . '
</div>
<p>Votre dossier sera examin&eacute; par notre &eacute;quipe dans les meilleurs d&eacute;lais. Vous serez notifi&eacute;(e) de l\'&eacute;volution de votre candidature par email.</p>
<p>Cordialement,<br><strong>L\'&eacute;quipe Stage</strong></p>';
        return self::layout('Confirmation de candidature', $content);
    }

    public static function decision(string $prenom, string $ref, string $statut, string $commentaire, string $niveau): string {
        $isValide = in_array($statut, ['valide', 'validee'], true);
        $statutTxt = $isValide ? 'Valid&eacute;e' : (str_contains($statut, 'complement') ? 'Compl&eacute;ment requis' : 'Rejet&eacute;e');
        $color = $isValide ? '#27ae60' : ($statut === 'complement' ? '#e67e22' : '#e8001c');
        $niv = $niveau === 'niv1' ? 'Niveau 1' : 'Niveau 2 (Final)';
        $content = '<p>Bonjour <strong>' . htmlspecialchars($prenom) . '</strong>,</p>
<p>Une d&eacute;cision a &eacute;t&eacute; prise concernant votre candidature.</p>
<div class="info-box">
  <p><strong>R&eacute;f&eacute;rence :</strong> ' . htmlspecialchars($ref) . '</p>
  <p><strong>Niveau :</strong> ' . $niv . '</p>
  <p><strong>D&eacute;cision :</strong> <span style="color:' . $color . ';font-weight:700">' . $statutTxt . '</span></p>
  ' . ($commentaire ? '<p><strong>Commentaire :</strong> ' . htmlspecialchars($commentaire) . '</p>' : '') . '
</div>
<p>Cordialement,<br><strong>L\'&eacute;quipe Stage</strong></p>';
        return self::layout('D&eacute;cision sur votre candidature', $content);
    }

    public static function renouvellement(string $prenom, string $ref_stage, string $statut, string $date_fin): string {
        $isValide = $statut === 'valide';
        $statutTxt = $isValide ? 'Valid&eacute;' : ($statut === 'precisions' ? 'Pr&eacute;cisions requises' : 'Rejet&eacute;');
        $color = $isValide ? '#27ae60' : ($statut === 'precisions' ? '#e67e22' : '#e8001c');
        $content = '<p>Bonjour <strong>' . htmlspecialchars($prenom) . '</strong>,</p>
<p>Votre demande de renouvellement de stage a &eacute;t&eacute; trait&eacute;e.</p>
<div class="info-box">
  <p><strong>Stage :</strong> ' . htmlspecialchars($ref_stage) . '</p>
  <p><strong>D&eacute;cision :</strong> <span style="color:' . $color . ';font-weight:700">' . $statutTxt . '</span></p>
  ' . ($isValide ? '<p><strong>Nouvelle date de fin :</strong> ' . htmlspecialchars($date_fin) . '</p>' : '') . '
</div>
<p>Cordialement,<br><strong>L\'&eacute;quipe Stage</strong></p>';
        return self::layout('D&eacute;cision renouvellement de stage', $content);
    }

    public static function lettreStage(string $prenom, string $nom, string $email, string $ref_stage, string $date_debut, string $date_fin, int $mois, string $ref_cand): string {
        $content = '<p>Bonjour <strong>' . htmlspecialchars($prenom) . ' ' . htmlspecialchars($nom) . '</strong>,</p>
<p>Nous avons le plaisir de vous informer que votre lettre de stage est disponible.</p>
<div class="info-box">
  <p><strong>Stage :</strong> ' . htmlspecialchars($ref_stage) . '</p>
  <p><strong>Candidature :</strong> ' . htmlspecialchars($ref_cand) . '</p>
  <p><strong>P&eacute;riode :</strong> du ' . htmlspecialchars($date_debut) . ' au ' . htmlspecialchars($date_fin) . ' (' . $mois . ' mois)</p>
  <p><strong>Email :</strong> ' . htmlspecialchars($email) . '</p>
</div>
<p>Veuillez vous connecter &agrave; votre espace stagiaire pour t&eacute;l&eacute;charger votre lettre de stage.</p>
<p>Cordialement,<br><strong>L\'&eacute;quipe Stage</strong></p>';
        return self::layout('Votre lettre de stage', $content);
    }
}
