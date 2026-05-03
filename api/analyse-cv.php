<?php
/**
 * api/analyse-cv.php — Analyse CV via Anthropic API
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
if (empty($apiKey) || $apiKey === 'sk-ant-api03-YOUR_KEY_HERE') {
    http_response_code(503);
    echo json_encode(['error' => 'Clé API Anthropic non configurée']);
    exit;
}

$cvText  = trim($_POST['cv_text'] ?? '');
$torText = trim($_POST['tor_text'] ?? '');

if (empty($cvText)) {
    http_response_code(400);
    echo json_encode(['error' => 'Texte du CV requis']);
    exit;
}

$systemPrompt = 'Tu es un expert RH spécialisé dans l\'analyse de CV de stagiaires. Analyse le CV fourni et retourne un JSON structuré avec les champs suivants :
- resume (string) : résumé en 2-3 phrases
- points_forts (array of strings) : 3-5 points forts
- points_amelioration (array of strings) : 2-3 axes d\'amélioration
- competences_cles (array of strings) : liste des compétences principales
- niveau_estime (string) : niveau estimé parmi bac, bac+2, bac+3, bac+4, bac+5
- score_global (int) : score de 0 à 100
- recommandation (string) : recommandation pour un stage
Réponds UNIQUEMENT avec le JSON, sans markdown ni explication.';

$userMessage = "Voici le CV à analyser :\n\n$cvText";
if (!empty($torText)) {
    $userMessage .= "\n\nVoici les termes de référence du poste (TOR) :\n\n$torText\n\nEffectue aussi une comparaison CV/TOR et ajoute un champ 'correspondance_tor' (int 0-100) et 'analyse_tor' (string).";
}

$payload = [
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 1024,
    'system'     => $systemPrompt,
    'messages'   => [
        ['role' => 'user', 'content' => $userMessage]
    ],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(503);
    echo json_encode(['error' => 'Erreur réseau : ' . $curlErr]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 || empty($data['content'][0]['text'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Erreur API Anthropic', 'detail' => $data['error']['message'] ?? $response]);
    exit;
}

$text = trim($data['content'][0]['text']);

// Strip potential markdown code fences
$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
$text = preg_replace('/\s*```$/', '', $text);

$result = json_decode($text, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['error' => 'Réponse IA non parseable', 'raw' => $text]);
    exit;
}

echo json_encode(['success' => true, 'analyse' => $result]);
