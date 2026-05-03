<?php
$pdo = getPDO();
$stagiaireId  = (int)($_GET['stagiaire_id'] ?? 0);
$candidatureId = (int)($_GET['candidature_id'] ?? 0);

$stag = null;
$cand = null;

if ($stagiaireId) {
    $stmt = $pdo->prepare("SELECT s.*, u.nom, u.prenom, u.email FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id WHERE s.id = ?");
    $stmt->execute([$stagiaireId]);
    $stag = $stmt->fetch();
}

if ($candidatureId) {
    $stmt = $pdo->prepare("SELECT c.*, o.titre AS offre_titre, o.description AS offre_desc, o.profil_recherche FROM candidatures c LEFT JOIN offres_stage o ON o.id = c.offre_id WHERE c.id = ?");
    $stmt->execute([$candidatureId]);
    $cand = $stmt->fetch();
    if ($cand && !$stag) {
        $stmt2 = $pdo->prepare("SELECT s.*, u.nom, u.prenom, u.email FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id WHERE s.id = ?");
        $stmt2->execute([$cand['stagiaire_id']]);
        $stag = $stmt2->fetch();
        $stagiaireId = $stag['id'] ?? 0;
    }
}

if (!$stag) {
    // Sélecteur de stagiaire
    $stagiaires = $pdo->query("SELECT s.id, u.nom, u.prenom FROM stagiaires s JOIN utilisateurs u ON u.id = s.utilisateur_id ORDER BY u.nom")->fetchAll();
    echo '<div class="page-header"><h1>Analyse IA du CV</h1></div>';
    echo '<div class="card"><div class="card-body">';
    echo '<p style="margin-bottom:16px">Sélectionnez un stagiaire pour analyser son CV :</p>';
    echo '<div style="display:flex;flex-wrap:wrap;gap:10px">';
    foreach ($stagiaires as $s) {
        echo '<a href="backoffice.php?page=analyse-cv&stagiaire_id=' . $s['id'] . '" class="btn btn-ghost">' . h($s['prenom'] . ' ' . $s['nom']) . '</a>';
    }
    echo '</div></div></div>';
    return;
}

// Construire profil textuel
$formations  = $pdo->prepare("SELECT * FROM formations WHERE stagiaire_id = ? ORDER BY annee_fin DESC");
$formations->execute([$stagiaireId]);
$formations  = $formations->fetchAll();

$competences = $pdo->prepare("SELECT libelle FROM competences WHERE stagiaire_id = ?");
$competences->execute([$stagiaireId]);
$competences = $competences->fetchAll(PDO::FETCH_COLUMN);

$experiences = $pdo->prepare("SELECT * FROM experiences WHERE stagiaire_id = ? ORDER BY date_debut DESC");
$experiences->execute([$stagiaireId]);
$experiences = $experiences->fetchAll();

$cvTexte = "Nom: {$stag['prenom']} {$stag['nom']}\n";
$cvTexte .= "Email: {$stag['email']}\n";
$cvTexte .= "Niveau d'étude: {$stag['niveau_etude']}\n\n";
$cvTexte .= "FORMATIONS:\n";
foreach ($formations as $f) {
    $cvTexte .= "- {$f['diplome']} en {$f['specialite']} — {$f['etablissement']} ({$f['annee_fin']})\n";
}
$cvTexte .= "\nCOMPÉTENCES:\n- " . implode("\n- ", $competences) . "\n";
$cvTexte .= "\nEXPÉRIENCES:\n";
foreach ($experiences as $e) {
    $cvTexte .= "- {$e['poste']} chez {$e['entreprise']} ({$e['date_debut']} → {$e['date_fin']}): {$e['description']}\n";
}
if ($stag['cv_analyse']) {
    $existingAnalyse = json_decode($stag['cv_analyse'], true);
}

$torTexte = '';
if ($cand && $cand['offre_titre']) {
    $torTexte  = "Titre: {$cand['offre_titre']}\n";
    $torTexte .= "Description: {$cand['offre_desc']}\n";
    $torTexte .= "Profil: {$cand['profil_recherche']}\n";
}

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
$apiConfigured = $apiKey && $apiKey !== 'sk-ant-api03-YOUR_KEY_HERE';
?>

<div class="page-header">
    <div>
        <a href="backoffice.php?page=stagiaire_detail&id=<?= $stagiaireId ?>" class="btn btn-ghost btn-sm" style="margin-bottom:8px">← Retour</a>
        <h1>Analyse IA — <?= h($stag['prenom'] . ' ' . $stag['nom']) ?></h1>
    </div>
</div>

<?php if (!$apiConfigured): ?>
<div class="alert alert-warning">⚠️ Clé API Anthropic non configurée dans le fichier <code>.env</code>. L'analyse IA n'est pas disponible.</div>
<?php endif; ?>

<div class="section-grid">
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">👤 Profil du stagiaire</div></div>
            <div class="card-body">
                <pre style="font-family:'JetBrains Mono',monospace;font-size:12px;white-space:pre-wrap;color:var(--text)"><?= h($cvTexte) ?></pre>
            </div>
        </div>

        <?php if ($torTexte): ?>
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Termes de référence</div></div>
            <div class="card-body">
                <pre style="font-family:'JetBrains Mono',monospace;font-size:12px;white-space:pre-wrap;color:var(--text)"><?= h($torTexte) ?></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <?php if ($apiConfigured): ?>
        <div class="card">
            <div class="card-header"><div class="card-title">🤖 Analyse intelligente</div></div>
            <div class="card-body">
                <button id="btn-analyser" class="btn btn-red" onclick="lancerAnalyse()">▶ Lancer l'analyse IA</button>
                <div id="loading" style="display:none;margin-top:16px;text-align:center">
                    <div style="font-size:24px;animation:spin 1s linear infinite;display:inline-block">⟳</div>
                    <p class="text-muted" style="margin-top:8px">Analyse en cours...</p>
                </div>
                <div id="resultat" style="margin-top:16px"></div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header"><div class="card-title">🤖 Analyse IA</div></div>
            <div class="card-body">
                <div class="alert alert-warning">Configurez la clé API Anthropic pour activer cette fonctionnalité.</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($apiConfigured): ?>
<script>
const cvTexte  = <?= json_encode($cvTexte) ?>;
const torTexte = <?= json_encode($torTexte) ?>;

function lancerAnalyse() {
    document.getElementById('btn-analyser').disabled = true;
    document.getElementById('loading').style.display = 'block';
    document.getElementById('resultat').innerHTML = '';

    const formData = new FormData();
    formData.append('cv_texte', cvTexte);
    if (torTexte) formData.append('tor_texte', torTexte);

    fetch('/api/analyse-cv.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('btn-analyser').disabled = false;

            if (data.error) {
                document.getElementById('resultat').innerHTML =
                    '<div class="alert alert-danger">Erreur : ' + data.error + '</div>';
                return;
            }

            const a = data.analyse;
            let html = '<div class="alert alert-success" style="margin-bottom:16px">Analyse terminée ✓</div>';

            if (a.score_estime !== undefined) {
                const score = a.score_estime;
                const color = score >= 70 ? 'green' : score >= 40 ? 'amber' : 'red';
                html += `<div style="margin-bottom:16px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span class="fw-600">Score estimé</span>
                        <span class="fw-600">${score}/100</span>
                    </div>
                    <div class="progress-wrap"><div class="progress-bar ${color}" style="width:${score}%"></div></div>
                </div>`;
            }

            if (a.resume) {
                html += `<div style="margin-bottom:12px"><div class="text-muted text-sm" style="margin-bottom:4px">Résumé</div><p style="font-size:13.5px">${a.resume}</p></div>`;
            }

            if (a.points_forts && a.points_forts.length) {
                html += '<div style="margin-bottom:12px"><div class="text-muted text-sm" style="margin-bottom:6px">Points forts</div><div style="display:flex;flex-wrap:wrap;gap:6px">';
                a.points_forts.forEach(p => { html += `<span class="badge badge-success">${p}</span>`; });
                html += '</div></div>';
            }

            if (a.adequation_tor !== undefined) {
                html += `<div style="margin-bottom:12px"><div class="fw-600 text-sm" style="margin-bottom:6px">Adéquation avec les TOR : ${a.adequation_tor}%</div>`;
                if (a.points_correspondance && a.points_correspondance.length) {
                    html += '<div class="text-muted text-sm">Correspondances :</div><ul style="margin:4px 0 8px 20px;font-size:13px">';
                    a.points_correspondance.forEach(p => { html += `<li style="color:#15803d">${p}</li>`; });
                    html += '</ul>';
                }
                if (a.points_manquants && a.points_manquants.length) {
                    html += '<div class="text-muted text-sm">Points manquants :</div><ul style="margin:4px 0 0 20px;font-size:13px">';
                    a.points_manquants.forEach(p => { html += `<li style="color:#dc2626">${p}</li>`; });
                    html += '</ul>';
                }
                html += '</div>';
            }

            document.getElementById('resultat').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('btn-analyser').disabled = false;
            document.getElementById('resultat').innerHTML =
                '<div class="alert alert-danger">Erreur réseau : ' + err.message + '</div>';
        });
}

const styleEl = document.createElement('style');
styleEl.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(styleEl);
</script>
<?php endif; ?>
