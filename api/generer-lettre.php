<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$stage_id = (int)($_GET['stage_id'] ?? 0);
if (!$stage_id) {
    flash('Stage non spécifié.', 'error');
    redirect('/backoffice.php?page=stages');
}

$pdo = getPDO();
$stmt = $pdo->prepare("
    SELECT s.*,
           u.nom, u.prenom, u.email, u.civilite,
           st.niveau_etude, st.telephone,
           d.libelle AS direction_libelle, d.abreviation AS direction_abr,
           dom.libelle AS domaine_libelle,
           eu.nom AS enc_nom, eu.prenom AS enc_prenom,
           c.reference AS ref_cand
    FROM stages s
    JOIN stagiaires st ON st.id = s.stagiaire_id
    JOIN utilisateurs u ON u.id = st.utilisateur_id
    LEFT JOIN directions d ON d.id = s.direction_id
    LEFT JOIN domaines dom ON dom.id = s.domaine_id
    LEFT JOIN utilisateurs eu ON eu.id = s.encadrant_id
    LEFT JOIN candidatures c ON c.id = s.candidature_id
    WHERE s.id = ?
");
$stmt->execute([$stage_id]);
$stage = $stmt->fetch();

if (!$stage) {
    flash('Stage introuvable.', 'error');
    redirect('/backoffice.php?page=stages');
}

// Appel Node.js pour générer le DOCX
$scriptPath = __DIR__ . '/generer-lettre.js';
$data = json_encode([
    'ref_stage'   => $stage['reference'],
    'ref_cand'    => $stage['ref_cand'] ?? '',
    'civilite'    => $stage['civilite'],
    'prenom'      => $stage['prenom'],
    'nom'         => $stage['nom'],
    'email'       => $stage['email'],
    'telephone'   => $stage['telephone'] ?? '',
    'niveau'      => $stage['niveau_etude'],
    'direction'   => $stage['direction_libelle'] ?? 'Non définie',
    'domaine'     => $stage['domaine_libelle'] ?? 'Non défini',
    'date_debut'  => $stage['date_debut'],
    'date_fin'    => $stage['date_fin'],
    'enc_prenom'  => $stage['enc_prenom'] ?? '',
    'enc_nom'     => $stage['enc_nom'] ?? '',
    'date_lettre' => date('d/m/Y'),
]);

$dataEscaped = escapeshellarg($data);
$output = shell_exec("node " . escapeshellarg($scriptPath) . " $dataEscaped 2>&1");

if (!$output) {
    flash('Erreur lors de la génération du DOCX. Node.js est-il installé ?', 'error');
    redirect('/backoffice.php?page=stage_detail&id=' . $stage_id);
}

$decoded = json_decode($output, true);
if (!$decoded || !isset($decoded['file'])) {
    flash('Erreur de génération : ' . ($output ?: 'réponse vide'), 'error');
    redirect('/backoffice.php?page=stage_detail&id=' . $stage_id);
}

$filePath = $decoded['file'];
if (!file_exists($filePath)) {
    flash('Fichier DOCX introuvable après génération.', 'error');
    redirect('/backoffice.php?page=stage_detail&id=' . $stage_id);
}

$filename = 'lettre-stage-' . $stage['reference'] . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
unlink($filePath);
exit;
