<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
if (!$apiKey || $apiKey === 'sk-ant-api03-YOUR_KEY_HERE') {
    echo json_encode(['error' => 'Clé API Anthropic non configurée']);
    exit;
}

$cvTexte = trim($_POST['cv_texte'] ?? '');
$torTexte = trim($_POST['tor_texte'] ?? '');

if (!$cvTexte) {
    echo json_encode(['error' => 'Texte du CV manquant']);
    exit;
}

$prompt = "Analysez ce CV de candidat à un stage et retournez un JSON structuré avec les champs suivants:\n"
    . "- nom: string\n"
    . "- prenom: string\n"
    . "- email: string (si présent)\n"
    . "- telephone: string (si présent)\n"
    . "- niveau_etude: string (bac, bac+2, bac+3, bac+4, bac+5, doctorat)\n"
    . "- formations: array de {diplome, specialite, etablissement, annee_fin}\n"
    . "- competences: array de strings\n"
    . "- experiences: array de {poste, entreprise, date_debut, date_fin, description}\n"
    . "- points_forts: array de strings\n"
    . "- score_estime: number (0-100)\n"
    . "- resume: string (résumé en 2-3 phrases)\n";

if ($torTexte) {
    $prompt .= "\nTermes de référence du poste :\n$torTexte\n";
    $prompt .= "\nAjoutez également :\n"
        . "- adequation_tor: number (0-100, adéquation avec les TOR)\n"
        . "- points_correspondance: array de strings\n"
        . "- points_manquants: array de strings\n";
}

$prompt .= "\n\nCV à analyser :\n$cvTexte\n\nRépondez UNIQUEMENT avec le JSON, sans texte additionnel.";

$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 2048,
    'messages'   => [
        ['role' => 'user', 'content' => $prompt]
    ]
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['error' => 'Erreur API Anthropic (HTTP ' . $httpCode . ')', 'details' => $response]);
    exit;
}

$data = json_decode($response, true);
$content = $data['content'][0]['text'] ?? '';

// Extraire le JSON de la réponse
if (preg_match('/\{.*\}/s', $content, $matches)) {
    $parsed = json_decode($matches[0], true);
    if ($parsed) {
        echo json_encode(['success' => true, 'analyse' => $parsed]);
        exit;
    }
}

echo json_encode(['error' => 'Impossible de parser la réponse IA', 'raw' => $content]);
