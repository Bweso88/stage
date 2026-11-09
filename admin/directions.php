<?php
session_start();
if (empty($_SESSION['user'])) { header("Location: login.php"); exit; }
require_once __DIR__ . '/db.php';

// Création des tables si elles n'existent pas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_directions (
        id   INT AUTO_INCREMENT PRIMARY KEY,
        nom  VARCHAR(255) NOT NULL,
        type ENUM('centrale','regionale') NOT NULL DEFAULT 'centrale',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_services (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        direction_id INT NOT NULL,
        nom          VARCHAR(255) NOT NULL,
        FOREIGN KEY (direction_id) REFERENCES org_directions(id) ON DELETE CASCADE
    ) DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_agences (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        direction_id INT NOT NULL,
        nom          VARCHAR(255) NOT NULL,
        FOREIGN KEY (direction_id) REFERENCES org_directions(id) ON DELETE CASCADE
    ) DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_postes (
        id  INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {}

// ── ACTIONS POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ok = ''; $err = '';

    try {
        switch ($action) {

            case 'add_direction':
                $nom  = trim($_POST['nom'] ?? '');
                $type = in_array($_POST['type']??'', ['centrale','regionale']) ? $_POST['type'] : 'centrale';
                if ($nom === '') throw new RuntimeException("Le nom est requis.");
                $pdo->prepare("INSERT INTO org_directions (nom,type) VALUES (?,?)")->execute([$nom,$type]);
                $ok = "Direction « ".htmlspecialchars($nom)." » ajoutée.";
                break;

            case 'del_direction':
                $id = (int)($_POST['id']??0);
                $pdo->prepare("DELETE FROM org_directions WHERE id=?")->execute([$id]);
                $ok = "Direction supprimée (services et agences associés supprimés).";
                break;

            case 'add_service':
                $did = (int)($_POST['direction_id']??0);
                $nom = trim($_POST['nom'] ?? '');
                if (!$did || $nom === '') throw new RuntimeException("Sélectionnez une direction et entrez un nom.");
                $pdo->prepare("INSERT INTO org_services (direction_id,nom) VALUES (?,?)")->execute([$did,$nom]);
                $ok = "Service « ".htmlspecialchars($nom)." » ajouté.";
                break;

            case 'del_service':
                $id = (int)($_POST['id']??0);
                $pdo->prepare("DELETE FROM org_services WHERE id=?")->execute([$id]);
                $ok = "Service supprimé.";
                break;

            case 'add_agence':
                $did = (int)($_POST['direction_id']??0);
                $nom = trim($_POST['nom'] ?? '');
                if (!$did || $nom === '') throw new RuntimeException("Sélectionnez une direction régionale et entrez un nom.");
                $pdo->prepare("INSERT INTO org_agences (direction_id,nom) VALUES (?,?)")->execute([$did,$nom]);
                $ok = "Agence « ".htmlspecialchars($nom)." » ajoutée.";
                break;

            case 'del_agence':
                $id = (int)($_POST['id']??0);
                $pdo->prepare("DELETE FROM org_agences WHERE id=?")->execute([$id]);
                $ok = "Agence supprimée.";
                break;

            case 'add_poste':
                $nom = trim($_POST['nom'] ?? '');
                if ($nom === '') throw new RuntimeException("Le nom du poste est requis.");
                $pdo->prepare("INSERT IGNORE INTO org_postes (nom) VALUES (?)")->execute([$nom]);
                $ok = "Poste « ".htmlspecialchars($nom)." » ajouté.";
                break;

            case 'del_poste':
                $id = (int)($_POST['id']??0);
                $pdo->prepare("DELETE FROM org_postes WHERE id=?")->execute([$id]);
                $ok = "Poste supprimé.";
                break;
        }
    } catch(Exception $e) {
        $err = $e->getMessage();
    }

    $q = http_build_query(['ok'=>$ok,'err'=>$err,'tab'=>($_POST['tab']??'directions')]);
    header("Location: directions.php?$q"); exit;
}

// ── CHARGEMENT ────────────────────────────────────────────────────────────────
$directions = $pdo->query("SELECT * FROM org_directions ORDER BY type DESC, nom")->fetchAll(PDO::FETCH_ASSOC);
$services   = $pdo->query("SELECT s.*,d.nom dir_nom FROM org_services s JOIN org_directions d ON d.id=s.direction_id ORDER BY d.nom,s.nom")->fetchAll(PDO::FETCH_ASSOC);
$agences    = $pdo->query("SELECT a.*,d.nom dir_nom FROM org_agences a JOIN org_directions d ON d.id=a.direction_id ORDER BY d.nom,a.nom")->fetchAll(PDO::FETCH_ASSOC);
$postes     = $pdo->query("SELECT * FROM org_postes ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$regDirs    = array_values(array_filter($directions, fn($d) => $d['type']==='regionale'));

$ok  = $_GET['ok']  ?? '';
$err = $_GET['err'] ?? '';
$activeTab = $_GET['tab'] ?? 'directions';

$pageTitle = "Organigramme";
require __DIR__ . '/_nav.php';
?>

<div style="max-width:1000px;">

<?php if ($ok): ?>
  <div class="alert alert-ok" style="margin-bottom:20px;">✓ <?php echo htmlspecialchars($ok); ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-err" style="margin-bottom:20px;">✗ <?php echo htmlspecialchars($err); ?></div>
<?php endif; ?>

<!-- ── ONGLETS ────────────────────────────────────────────────────────────── -->
<div style="display:flex;gap:4px;margin-bottom:24px;border-bottom:2px solid #e2e7ff;padding-bottom:0;">
  <?php
  $tabs = [
    'directions' => ['🏢','Directions','',count($directions)],
    'services'   => ['📂','Services','',count($services)],
    'agences'    => ['📍','Agences','',count($agences)],
    'postes'     => ['💼','Postes','',count($postes)],
  ];
  foreach ($tabs as $key => [$ico,$lbl,$sub,$cnt]):
    $active = ($activeTab === $key);
  ?>
  <a href="?tab=<?php echo $key; ?>"
     style="display:flex;align-items:center;gap:7px;padding:10px 18px;border-radius:8px 8px 0 0;
            font-size:.88rem;font-weight:700;text-decoration:none;margin-bottom:-2px;border:2px solid transparent;
            <?php echo $active
              ? 'background:#fff;border-color:#e2e7ff #e2e7ff #fff;color:#0037b0;'
              : 'color:#747686;border-color:transparent;'; ?>">
    <span><?php echo $ico; ?></span>
    <span><?php echo $lbl; ?></span>
    <span style="background:<?php echo $active?'#eaedff':'#f2f3ff';?>;color:<?php echo $active?'#0037b0':'#747686';?>;
                 font-size:.7rem;padding:1px 7px;border-radius:10px;"><?php echo $cnt; ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- TAB : DIRECTIONS                                                          -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'directions'): ?>

<div class="card" style="padding:0;overflow:hidden;">
  <div style="padding:18px 20px;border-bottom:1px solid #e2e7ff;display:flex;align-items:center;justify-content:space-between;">
    <strong style="font-size:1rem;color:#131b2e;">Directions</strong>
    <button onclick="toggleForm('add-dir-form')" class="btn btn-primary btn-sm">+ Ajouter</button>
  </div>

  <!-- Formulaire d'ajout -->
  <div id="add-dir-form" style="display:none;padding:16px 20px;background:#f8f9ff;border-bottom:1px solid #e2e7ff;">
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="add_direction">
      <input type="hidden" name="tab"    value="directions">
      <div class="field" style="flex:1;min-width:180px;margin:0;">
        <label>Nom de la direction</label>
        <input type="text" name="nom" placeholder="Ex : Direction des Ressources Humaines" required>
      </div>
      <div class="field" style="width:180px;margin:0;">
        <label>Type</label>
        <select name="type">
          <option value="centrale">Centrale</option>
          <option value="regionale">Régionale</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="toggleForm('add-dir-form')">Annuler</button>
    </form>
  </div>

  <?php if (empty($directions)): ?>
    <p style="padding:32px;text-align:center;color:#747686;">Aucune direction enregistrée.</p>
  <?php else: ?>
    <table>
      <thead><tr>
        <th>Direction</th>
        <th>Type</th>
        <th style="width:80px;text-align:center;">Services</th>
        <th style="width:80px;text-align:center;">Agences</th>
        <th style="width:60px;"></th>
      </tr></thead>
      <tbody>
        <?php foreach ($directions as $dir):
          $nbSvc = count(array_filter($services, fn($s)=>$s['direction_id']==$dir['id']));
          $nbAge = count(array_filter($agences,  fn($a)=>$a['direction_id']==$dir['id']));
        ?>
        <tr>
          <td style="font-weight:600;"><?php echo htmlspecialchars($dir['nom']); ?></td>
          <td>
            <span class="badge <?php echo $dir['type']==='centrale'?'badge-centrale':'badge-regionale'; ?>">
              <?php echo ucfirst($dir['type']); ?>
            </span>
          </td>
          <td style="text-align:center;"><?php echo $nbSvc; ?></td>
          <td style="text-align:center;"><?php echo $dir['type']==='regionale'?$nbAge:'—'; ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Supprimer cette direction et tous ses services/agences ?');">
              <input type="hidden" name="action" value="del_direction">
              <input type="hidden" name="id"     value="<?php echo $dir['id']; ?>">
              <input type="hidden" name="tab"    value="directions">
              <button type="submit" class="btn btn-danger btn-sm">✕</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- TAB : SERVICES                                                            -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'services'): ?>

<div class="card" style="padding:0;overflow:hidden;">
  <div style="padding:18px 20px;border-bottom:1px solid #e2e7ff;display:flex;align-items:center;justify-content:space-between;">
    <strong style="font-size:1rem;color:#131b2e;">Services par direction</strong>
    <button onclick="toggleForm('add-svc-form')" class="btn btn-primary btn-sm">+ Ajouter</button>
  </div>

  <div id="add-svc-form" style="display:none;padding:16px 20px;background:#f8f9ff;border-bottom:1px solid #e2e7ff;">
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="add_service">
      <input type="hidden" name="tab"    value="services">
      <div class="field" style="width:220px;margin:0;">
        <label>Direction</label>
        <select name="direction_id" required>
          <option value="">— Choisir —</option>
          <?php foreach ($directions as $d): ?>
            <option value="<?php echo $d['id']; ?>">
              <?php echo htmlspecialchars($d['nom']); ?>
              (<?php echo $d['type']==='regionale'?'Rég.':'Cen.'; ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="flex:1;min-width:180px;margin:0;">
        <label>Nom du service</label>
        <input type="text" name="nom" placeholder="Ex : Service Formation" required>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="toggleForm('add-svc-form')">Annuler</button>
    </form>
  </div>

  <?php if (empty($services)): ?>
    <p style="padding:32px;text-align:center;color:#747686;">Aucun service enregistré.</p>
  <?php else: ?>
    <?php
    // Grouper les services par direction
    $svcByDir = [];
    foreach ($services as $s) $svcByDir[$s['dir_nom']][] = $s;
    ?>
    <?php foreach ($svcByDir as $dirNom => $svcs): ?>
      <div style="padding:10px 20px 4px;background:#f8f9ff;border-bottom:1px solid #e2e7ff;">
        <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#0037b0;">
          <?php echo htmlspecialchars($dirNom); ?>
        </span>
      </div>
      <table>
        <tbody>
          <?php foreach ($svcs as $svc): ?>
          <tr>
            <td><?php echo htmlspecialchars($svc['nom']); ?></td>
            <td style="width:60px;">
              <form method="post" onsubmit="return confirm('Supprimer ce service ?');">
                <input type="hidden" name="action" value="del_service">
                <input type="hidden" name="id"     value="<?php echo $svc['id']; ?>">
                <input type="hidden" name="tab"    value="services">
                <button type="submit" class="btn btn-danger btn-sm">✕</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- TAB : AGENCES                                                             -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'agences'): ?>

<div class="card" style="padding:0;overflow:hidden;">
  <div style="padding:18px 20px;border-bottom:1px solid #e2e7ff;display:flex;align-items:center;justify-content:space-between;">
    <strong style="font-size:1rem;color:#131b2e;">Agences (directions régionales)</strong>
    <button onclick="toggleForm('add-age-form')" class="btn btn-primary btn-sm">+ Ajouter</button>
  </div>

  <div id="add-age-form" style="display:none;padding:16px 20px;background:#f8f9ff;border-bottom:1px solid #e2e7ff;">
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="add_agence">
      <input type="hidden" name="tab"    value="agences">
      <div class="field" style="width:220px;margin:0;">
        <label>Direction régionale</label>
        <select name="direction_id" required>
          <option value="">— Choisir —</option>
          <?php foreach ($regDirs as $d): ?>
            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['nom']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="flex:1;min-width:180px;margin:0;">
        <label>Nom de l'agence</label>
        <input type="text" name="nom" placeholder="Ex : Agence de Pointe-Noire" required>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="toggleForm('add-age-form')">Annuler</button>
    </form>
  </div>

  <?php if (empty($regDirs)): ?>
    <p style="padding:32px;text-align:center;color:#747686;">
      Aucune direction régionale. <a href="?tab=directions" style="color:#0037b0;">Créez-en une d'abord.</a>
    </p>
  <?php elseif (empty($agences)): ?>
    <p style="padding:32px;text-align:center;color:#747686;">Aucune agence enregistrée.</p>
  <?php else: ?>
    <?php
    $ageByDir = [];
    foreach ($agences as $a) $ageByDir[$a['dir_nom']][] = $a;
    ?>
    <?php foreach ($ageByDir as $dirNom => $ages): ?>
      <div style="padding:10px 20px 4px;background:#f8f9ff;border-bottom:1px solid #e2e7ff;">
        <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#8b5cf6;">
          <?php echo htmlspecialchars($dirNom); ?>
        </span>
      </div>
      <table>
        <tbody>
          <?php foreach ($ages as $age): ?>
          <tr>
            <td><?php echo htmlspecialchars($age['nom']); ?></td>
            <td style="width:60px;">
              <form method="post" onsubmit="return confirm('Supprimer cette agence ?');">
                <input type="hidden" name="action" value="del_agence">
                <input type="hidden" name="id"     value="<?php echo $age['id']; ?>">
                <input type="hidden" name="tab"    value="agences">
                <button type="submit" class="btn btn-danger btn-sm">✕</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- TAB : POSTES                                                              -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'postes'): ?>

<div class="card" style="padding:0;overflow:hidden;">
  <div style="padding:18px 20px;border-bottom:1px solid #e2e7ff;display:flex;align-items:center;justify-content:space-between;">
    <strong style="font-size:1rem;color:#131b2e;">Postes / Fonctions</strong>
    <button onclick="toggleForm('add-pos-form')" class="btn btn-primary btn-sm">+ Ajouter</button>
  </div>

  <div id="add-pos-form" style="display:none;padding:16px 20px;background:#f8f9ff;border-bottom:1px solid #e2e7ff;">
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="add_poste">
      <input type="hidden" name="tab"    value="postes">
      <div class="field" style="flex:1;min-width:200px;margin:0;">
        <label>Intitulé du poste</label>
        <input type="text" name="nom" placeholder="Ex : Chargé de clientèle, Directeur d'agence…" required>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="toggleForm('add-pos-form')">Annuler</button>
    </form>
  </div>

  <?php if (empty($postes)): ?>
    <p style="padding:32px;text-align:center;color:#747686;">Aucun poste enregistré.</p>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0;">
      <?php foreach ($postes as $pos): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid #f2f3ff;">
        <span style="font-size:.9rem;color:#131b2e;">
          <span style="color:#747686;font-size:.8rem;margin-right:6px;">💼</span>
          <?php echo htmlspecialchars($pos['nom']); ?>
        </span>
        <form method="post" onsubmit="return confirm('Supprimer ce poste ?');" style="margin-left:8px;">
          <input type="hidden" name="action" value="del_poste">
          <input type="hidden" name="id"     value="<?php echo $pos['id']; ?>">
          <input type="hidden" name="tab"    value="postes">
          <button type="submit" class="btn btn-danger btn-sm">✕</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php endif; ?>

</div><!-- /max-width -->

<script>
function toggleForm(id) {
  var el = document.getElementById(id);
  if (el) el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>

<?php require __DIR__ . '/_nav_end.php'; ?>
