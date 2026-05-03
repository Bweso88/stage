<?php
// pages/admin/analyse-cv.php — Analyse IA des CV
requireAdmin();
define('STAGIA_PAGE', 'Analyse IA CV');
$pdo = getPDO();

$candidatureId = (int) ($_GET['candidature_id'] ?? 0);
$candidature   = null;
$stagiaire     = null;
$offre         = null;

if ($candidatureId) {
    $stmt = $pdo->prepare('
        SELECT c.*, s.cv_analyse, s.id AS stag_id,
               u.nom, u.prenom, u.email,
               o.titre AS offre_titre, o.description AS offre_desc, o.profil_recherche
        FROM candidatures c
        JOIN stagiaires s ON c.stagiaire_id = s.id
        JOIN utilisateurs u ON s.utilisateur_id = u.id
        LEFT JOIN offres_stage o ON c.offre_id = o.id
        WHERE c.id = ?
    ');
    $stmt->execute([$candidatureId]);
    $candidature = $stmt->fetch();
}

// Récupère formations + compétences + expériences du stagiaire pour constituer le "CV texte"
$cvText = '';
if ($candidature) {
    $forms = $pdo->prepare('SELECT * FROM formations WHERE stagiaire_id = ?');
    $forms->execute([$candidature['stag_id']]);
    $forms = $forms->fetchAll();

    $comps = $pdo->prepare('SELECT libelle FROM competences WHERE stagiaire_id = ?');
    $comps->execute([$candidature['stag_id']]);
    $comps = $comps->fetchAll(PDO::FETCH_COLUMN);

    $exps = $pdo->prepare('SELECT * FROM experiences WHERE stagiaire_id = ?');
    $exps->execute([$candidature['stag_id']]);
    $exps = $exps->fetchAll();

    $cvText  = "NOM : {$candidature['prenom']} {$candidature['nom']}\n";
    $cvText .= "CANDIDATURE : {$candidature['reference']}\n\n";
    $cvText .= "FORMATIONS :\n";
    foreach ($forms as $f) {
        $cvText .= "- {$f['diplome']} en {$f['specialite']} ({$f['etablissement']}, {$f['annee_fin']})\n";
    }
    $cvText .= "\nCOMPÉTENCES :\n" . implode(', ', $comps) . "\n";
    $cvText .= "\nEXPÉRIENCES :\n";
    foreach ($exps as $e) {
        $cvText .= "- {$e['poste']} chez {$e['entreprise']} ({$e['date_debut']} → " . ($e['date_fin'] ?: 'En cours') . ")\n";
        if ($e['description']) $cvText .= "  {$e['description']}\n";
    }
}

// Check existing analysis
$analyse = null;
if ($candidature && $candidature['cv_analyse']) {
    $analyse = json_decode($candidature['cv_analyse'], true);
}

// Recent candidatures list
$recentes = $pdo->query('
    SELECT c.id, c.reference, u.nom, u.prenom, c.statut_global
    FROM candidatures c
    JOIN stagiaires s ON c.stagiaire_id = s.id
    JOIN utilisateurs u ON s.utilisateur_id = u.id
    ORDER BY c.date_candidature DESC LIMIT 20
')->fetchAll();

$apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
$apiConfigured = !empty($apiKey) && $apiKey !== 'sk-ant-api03-YOUR_KEY_HERE';

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <h1>Analyse IA des CV</h1>
  <?php if (!$apiConfigured): ?>
  <span class="badge badge-danger">⚠ Clé API non configurée</span>
  <?php endif ?>
</div>

<?php if (!$apiConfigured): ?>
<div class="alert alert-warning">
  La clé API Anthropic n'est pas configurée. Ajoutez <code>ANTHROPIC_API_KEY=sk-ant-...</code> dans votre fichier <code>.env</code>.
</div>
<?php endif ?>

<div class="section-grid" style="grid-template-columns:280px 1fr">
  <!-- Liste candidatures -->
  <div class="card" style="max-height:600px;overflow-y:auto">
    <div class="card-header"><h3 class="card-title">Candidatures récentes</h3></div>
    <?php foreach ($recentes as $c): ?>
    <a href="backoffice.php?page=analyse-cv&candidature_id=<?= $c['id'] ?>"
       style="display:block;padding:10px 16px;border-bottom:1px solid var(--gray-border);text-decoration:none;color:inherit;<?= $candidatureId == $c['id'] ? 'background:#f0f4ff;border-left:3px solid var(--navy)' : '' ?>">
      <div style="font-weight:600;font-size:.82rem"><?= h($c['prenom'] . ' ' . $c['nom']) ?></div>
      <div style="font-size:.74rem;color:#9ca3af"><?= h($c['reference']) ?></div>
      <div style="margin-top:2px"><?= statusBadge($c['statut_global']) ?></div>
    </a>
    <?php endforeach ?>
  </div>

  <!-- Zone d'analyse -->
  <div>
    <?php if (!$candidature): ?>
    <div class="card">
      <div class="card-body" style="text-align:center;padding:60px;color:#9ca3af">
        <div style="font-size:48px;margin-bottom:12px">🤖</div>
        <p>Sélectionnez une candidature pour analyser son CV.</p>
      </div>
    </div>
    <?php else: ?>

    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <h3 class="card-title">CV de <?= h($candidature['prenom'] . ' ' . $candidature['nom']) ?></h3>
        <code class="mono"><?= h($candidature['reference']) ?></code>
      </div>
      <div class="card-body">
        <pre style="font-size:.75rem;background:#f8f9fa;padding:12px;border-radius:6px;white-space:pre-wrap;max-height:200px;overflow-y:auto"><?= h($cvText) ?></pre>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><h3 class="card-title">Lancer l'analyse IA</h3></div>
      <div class="card-body">
        <?php if ($candidature['offre_titre']): ?>
        <div class="alert alert-success" style="margin-bottom:12px">
          L'analyse sera comparée avec l'offre : <strong><?= h($candidature['offre_titre']) ?></strong>
        </div>
        <?php endif ?>
        <button class="btn btn-red" id="btn-analyser" onclick="lancerAnalyse()" <?= !$apiConfigured ? 'disabled' : '' ?>>
          🤖 Analyser avec Claude IA
        </button>
        <div id="loader" style="display:none;margin-top:12px;color:#6b7280">Analyse en cours…</div>
      </div>
    </div>

    <div id="result-zone">
    <?php if ($analyse): ?>
    <?php include_once __DIR__ . '/../../includes/cv_analyse_render.php'; renderAnalyse($analyse); ?>
    <?php endif ?>
    </div>

    <script>
    function lancerAnalyse() {
      document.getElementById('btn-analyser').disabled = true;
      document.getElementById('loader').style.display = 'block';
      document.getElementById('result-zone').innerHTML = '';

      fetch('<?= BASE_URL ?? '' ?>api/analyse-cv.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          cv_text: <?= json_encode($cvText) ?>,
          tor_text: <?= json_encode($candidature['offre_desc'] . "\n" . $candidature['profil_recherche']) ?>,
          candidature_id: <?= $candidatureId ?>
        })
      })
      .then(r => r.json())
      .then(data => {
        document.getElementById('loader').style.display = 'none';
        document.getElementById('btn-analyser').disabled = false;
        if (data.error) {
          document.getElementById('result-zone').innerHTML =
            '<div class="alert alert-danger">Erreur : ' + data.error + '</div>';
          return;
        }
        const a = data.analyse;
        let html = '<div class="card"><div class="card-header"><h3 class="card-title">Résultat de l\'analyse</h3></div><div class="card-body">';
        html += '<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">';
        html += '<div class="stat-card"><div class="stat-value">' + (a.score_global || '—') + '</div><div class="stat-label">Score global /100</div></div>';
        html += '<div class="stat-card"><div class="stat-value">' + (a.niveau_estime || '—') + '</div><div class="stat-label">Niveau estimé</div></div>';
        if (a.correspondance_tor !== undefined)
          html += '<div class="stat-card"><div class="stat-value">' + a.correspondance_tor + '%</div><div class="stat-label">Correspondance TOR</div></div>';
        html += '</div>';
        if (a.resume) html += '<p style="margin-bottom:12px">' + a.resume + '</p>';
        if (a.points_forts?.length) {
          html += '<h4 style="color:#059669;margin-bottom:6px">✔ Points forts</h4><ul style="margin-bottom:12px">';
          a.points_forts.forEach(p => html += '<li>' + p + '</li>');
          html += '</ul>';
        }
        if (a.points_amelioration?.length) {
          html += '<h4 style="color:#d97706;margin-bottom:6px">⚠ Axes d\'amélioration</h4><ul style="margin-bottom:12px">';
          a.points_amelioration.forEach(p => html += '<li>' + p + '</li>');
          html += '</ul>';
        }
        if (a.recommandation) html += '<div class="alert alert-success"><strong>Recommandation :</strong> ' + a.recommandation + '</div>';
        if (a.analyse_tor) html += '<div class="alert alert-warning" style="margin-top:8px"><strong>Analyse TOR :</strong> ' + a.analyse_tor + '</div>';
        html += '</div></div>';
        document.getElementById('result-zone').innerHTML = html;
      })
      .catch(err => {
        document.getElementById('loader').style.display = 'none';
        document.getElementById('btn-analyser').disabled = false;
        document.getElementById('result-zone').innerHTML =
          '<div class="alert alert-danger">Erreur réseau : ' + err.message + '</div>';
      });
    }
    </script>
    <?php endif ?>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
