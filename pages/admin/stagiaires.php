<?php
// pages/admin/stagiaires.php
requireAdmin();
define('STAGIA_PAGE', 'Stagiaires');
$pdo = getPDO();

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stagiaires_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Nom','Prénom','Email','Niveau','Téléphone','Ville','Date inscription'], ';');
    $rows = $pdo->query('
        SELECT s.id, u.nom, u.prenom, u.email, s.niveau_etude, s.telephone, s.ville, s.date_inscription
        FROM stagiaires s JOIN utilisateurs u ON s.utilisateur_id = u.id
        ORDER BY s.date_inscription DESC
    ')->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['nom'], $r['prenom'], $r['email'],
            $r['niveau_etude'], $r['telephone'], $r['ville'],
            $r['date_inscription']
        ], ';');
    }
    fclose($out);
    exit;
}

$search = trim($_GET['q'] ?? '');
$params = [];
$where  = '';
if ($search !== '') {
    $where = 'WHERE (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR s.ville LIKE ?)';
    $like  = "%$search%";
    $params = [$like, $like, $like, $like];
}

$stmt = $pdo->prepare("
    SELECT s.id, u.nom, u.prenom, u.email, s.niveau_etude, s.telephone, s.ville,
           s.date_inscription, d.libelle AS domaine,
           (SELECT COUNT(*) FROM candidatures c WHERE c.stagiaire_id = s.id) AS nb_cand,
           (SELECT COUNT(*) FROM stages sg WHERE sg.stagiaire_id = s.id) AS nb_stages,
           u.actif
    FROM stagiaires s
    JOIN utilisateurs u ON s.utilisateur_id = u.id
    LEFT JOIN domaines d ON s.domaine_principal_id = d.id
    $where
    ORDER BY s.date_inscription DESC
");
$stmt->execute($params);
$stagiaires = $stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <h1>Stagiaires</h1>
  <a href="backoffice.php?page=stagiaires&export=csv" class="btn btn-ghost">⬇ Export CSV</a>
</div>

<div class="card">
  <div class="filter-bar">
    <form method="GET" action="backoffice.php" style="display:flex;gap:8px;flex:1">
      <input type="hidden" name="page" value="stagiaires">
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher par nom, email, ville…" style="flex:1">
      <button type="submit" class="btn btn-navy">🔍</button>
      <?php if ($search): ?>
      <a href="backoffice.php?page=stagiaires" class="btn btn-ghost">✕ Effacer</a>
      <?php endif ?>
    </form>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Stagiaire</th>
          <th>Email</th>
          <th>Niveau</th>
          <th>Domaine</th>
          <th>Ville</th>
          <th>Candidatures</th>
          <th>Stages</th>
          <th>Inscrit le</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($stagiaires)): ?>
        <tr><td colspan="9" style="text-align:center;color:#9ca3af;padding:40px">Aucun stagiaire trouvé.</td></tr>
        <?php endif ?>
        <?php foreach ($stagiaires as $s): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar"><?= mb_strtoupper(mb_substr($s['prenom'],0,1)) . mb_strtoupper(mb_substr($s['nom'],0,1)) ?></div>
              <div>
                <strong><?= h($s['prenom'] . ' ' . $s['nom']) ?></strong>
                <?php if (!$s['actif']): ?><span class="badge badge-gray">Inactif</span><?php endif ?>
              </div>
            </div>
          </td>
          <td><?= h($s['email']) ?></td>
          <td><?= h($s['niveau_etude'] ?? '—') ?></td>
          <td><?= h($s['domaine'] ?? '—') ?></td>
          <td><?= h($s['ville'] ?? '—') ?></td>
          <td><span class="badge badge-blue"><?= (int)$s['nb_cand'] ?></span></td>
          <td><span class="badge badge-teal"><?= (int)$s['nb_stages'] ?></span></td>
          <td><?= $s['date_inscription'] ? date('d/m/Y', strtotime($s['date_inscription'])) : '—' ?></td>
          <td>
            <a href="backoffice.php?page=stagiaire_detail&id=<?= $s['id'] ?>" class="btn btn-sm btn-ghost">👁 Voir</a>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
