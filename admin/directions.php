<?php
session_start();
if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }
require "db.php";

$pageTitle = "Directions & Services";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_directions (
        id   INT AUTO_INCREMENT PRIMARY KEY,
        nom  VARCHAR(150) NOT NULL,
        type ENUM('centrale','regionale') NOT NULL DEFAULT 'centrale',
        UNIQUE KEY uq_dir_nom (nom)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_services (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        direction_id INT NOT NULL,
        nom          VARCHAR(150) NOT NULL,
        FOREIGN KEY (direction_id) REFERENCES org_directions(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS org_agences (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        direction_id INT NOT NULL,
        nom          VARCHAR(150) NOT NULL,
        FOREIGN KEY (direction_id) REFERENCES org_directions(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_direction') {
        $nom  = trim($_POST['nom']  ?? '');
        $type = in_array($_POST['type'] ?? '', ['centrale','regionale']) ? $_POST['type'] : 'centrale';
        if (empty($nom)) { $err = "Le nom de la direction est requis."; }
        else {
            try {
                $pdo->prepare("INSERT INTO org_directions (nom, type) VALUES (?,?)")->execute([$nom, $type]);
                $msg = "Direction « $nom » ajoutée.";
            } catch (Exception $e) { $err = "Cette direction existe déjà."; }
        }
    }

    if ($action === 'delete_direction') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM org_directions WHERE id=?")->execute([$id]);
        $msg = "Direction supprimée.";
    }

    if ($action === 'add_service') {
        $dir_id = (int)($_POST['direction_id'] ?? 0);
        $nom    = trim($_POST['nom'] ?? '');
        if (empty($nom) || !$dir_id) { $err = "Nom et direction requis."; }
        else {
            $pdo->prepare("INSERT INTO org_services (direction_id, nom) VALUES (?,?)")->execute([$dir_id, $nom]);
            $msg = "Service « $nom » ajouté.";
        }
    }

    if ($action === 'delete_service') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM org_services WHERE id=?")->execute([$id]);
        $msg = "Service supprimé.";
    }

    if ($action === 'add_agence') {
        $dir_id = (int)($_POST['direction_id'] ?? 0);
        $nom    = trim($_POST['nom'] ?? '');
        if (empty($nom) || !$dir_id) { $err = "Nom et direction requis."; }
        else {
            $pdo->prepare("INSERT INTO org_agences (direction_id, nom) VALUES (?,?)")->execute([$dir_id, $nom]);
            $msg = "Agence « $nom » ajoutée.";
        }
    }

    if ($action === 'delete_agence') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM org_agences WHERE id=?")->execute([$id]);
        $msg = "Agence supprimée.";
    }

    header("Location: directions.php" . ($msg ? "?ok=".urlencode($msg) : ($err ? "?err=".urlencode($err) : '')));
    exit;
}

if (isset($_GET['ok']))  $msg = $_GET['ok'];
if (isset($_GET['err'])) $err = $_GET['err'];

$directions = $pdo->query("SELECT * FROM org_directions ORDER BY type, nom")->fetchAll();
$dirData = [];
foreach ($directions as $d) {
    $svcs = $pdo->prepare("SELECT * FROM org_services WHERE direction_id=? ORDER BY nom");
    $svcs->execute([$d['id']]);
    $ages = $pdo->prepare("SELECT * FROM org_agences WHERE direction_id=? ORDER BY nom");
    $ages->execute([$d['id']]);
    $dirData[] = [
        'dir'     => $d,
        'services'=> $svcs->fetchAll(),
        'agences' => $ages->fetchAll(),
    ];
}

require "_nav.php";
?>

<style>
.dir-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:20px;margin-bottom:32px;}
.dir-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;}
.dir-card-head{padding:14px 18px;display:flex;align-items:center;justify-content:space-between;
  background:var(--surface2);border-bottom:1px solid var(--border);}
.dir-card-head h3{font-size:.95rem;font-weight:700;display:flex;align-items:center;gap:8px;}
.dir-card-body{padding:16px 18px;}
.sub-section{margin-bottom:14px;}
.sub-section h4{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
  color:var(--muted);margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.item-list{display:flex;flex-direction:column;gap:4px;margin-bottom:8px;}
.item-row{display:flex;align-items:center;justify-content:space-between;
  background:var(--bg);border-radius:6px;padding:6px 10px;font-size:.84rem;}
.item-row span{color:var(--text);}
.btn-del{background:none;border:none;cursor:pointer;color:var(--muted);padding:2px 6px;
  border-radius:4px;transition:color .2s,background .2s;font-size:.8rem;line-height:1;}
.btn-del:hover{color:#ef4444;background:rgba(239,68,68,.1);}
.inline-add{display:flex;gap:6px;margin-top:6px;}
.inline-add input{flex:1;background:var(--bg);border:1px solid var(--border);border-radius:6px;
  color:var(--text);padding:6px 10px;font-size:.83rem;outline:none;}
.inline-add input:focus{border-color:var(--indigo);}
.inline-add button{background:var(--indigo);color:#fff;border:none;border-radius:6px;
  padding:6px 12px;font-size:.82rem;font-weight:600;cursor:pointer;white-space:nowrap;}
.inline-add button:hover{background:var(--indigo-h);}
.badge-type{font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:10px;}
.badge-centrale{background:rgba(14,165,233,.15);color:#38bdf8;}
.badge-regionale{background:rgba(245,158,11,.15);color:#fbbf24;}
.add-dir-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:24px;}
.add-dir-card h3{font-size:.95rem;font-weight:700;margin-bottom:16px;}
.form-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;}
.form-row .fg{flex:1;min-width:180px;}
.form-row label{display:block;font-size:.75rem;color:var(--muted);text-transform:uppercase;
  letter-spacing:.05em;margin-bottom:5px;font-weight:600;}
.form-row input,.form-row select{width:100%;background:var(--bg);border:1px solid var(--border);
  border-radius:6px;color:var(--text);padding:8px 12px;font-size:.88rem;outline:none;}
.form-row input:focus,.form-row select:focus{border-color:var(--indigo);}
.form-row select option{background:var(--surface);}
.btn-add{background:var(--indigo);color:#fff;border:none;border-radius:6px;padding:9px 20px;
  font-size:.88rem;font-weight:700;cursor:pointer;white-space:nowrap;}
.btn-add:hover{background:var(--indigo-h);}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
  <h1 class="adm-title" style="margin:0;">🏢 Directions & Services</h1>
</div>

<?php if ($msg): ?>
  <div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);
    color:#34d399;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:.88rem;">
    ✓ <?php echo htmlspecialchars($msg); ?>
  </div>
<?php endif; ?>
<?php if ($err): ?>
  <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);
    color:#f87171;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:.88rem;">
    ✗ <?php echo htmlspecialchars($err); ?>
  </div>
<?php endif; ?>

<div class="add-dir-card" style="margin-bottom:28px;">
  <h3>➕ Ajouter une direction</h3>
  <form method="post">
    <input type="hidden" name="action" value="add_direction">
    <div class="form-row">
      <div class="fg">
        <label>Nom de la direction</label>
        <input type="text" name="nom" placeholder="Ex : Direction des Ressources Humaines" required>
      </div>
      <div class="fg" style="max-width:200px;">
        <label>Type</label>
        <select name="type">
          <option value="centrale">Centrale</option>
          <option value="regionale">Régionale</option>
        </select>
      </div>
      <button type="submit" class="btn-add">Ajouter</button>
    </div>
  </form>
</div>

<?php if (empty($dirData)): ?>
  <div style="text-align:center;padding:60px 20px;color:var(--muted);">
    <div style="font-size:3rem;margin-bottom:12px;">🏢</div>
    <p>Aucune direction configurée. Ajoutez-en une ci-dessus.</p>
  </div>
<?php else: ?>
  <div class="dir-grid">
    <?php foreach ($dirData as $entry):
      $d    = $entry['dir'];
      $svcs = $entry['services'];
      $ages = $entry['agences'];
    ?>
    <div class="dir-card">
      <div class="dir-card-head">
        <h3>
          <?php echo $d['type'] === 'regionale' ? '🌍' : '🏛️'; ?>
          <?php echo htmlspecialchars($d['nom']); ?>
          <span class="badge-type badge-<?php echo $d['type']; ?>">
            <?php echo $d['type'] === 'regionale' ? 'Régionale' : 'Centrale'; ?>
          </span>
        </h3>
        <form method="post" onsubmit="return confirm('Supprimer cette direction et tous ses services/agences ?');">
          <input type="hidden" name="action" value="delete_direction">
          <input type="hidden" name="id"     value="<?php echo $d['id']; ?>">
          <button type="submit" class="btn-del" title="Supprimer la direction">✕</button>
        </form>
      </div>
      <div class="dir-card-body">

        <div class="sub-section">
          <h4>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            Services (<?php echo count($svcs); ?>)
          </h4>
          <?php if (!empty($svcs)): ?>
            <div class="item-list">
              <?php foreach ($svcs as $s): ?>
                <div class="item-row">
                  <span><?php echo htmlspecialchars($s['nom']); ?></span>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="action" value="delete_service">
                    <input type="hidden" name="id"     value="<?php echo $s['id']; ?>">
                    <button type="submit" class="btn-del">✕</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <form method="post" class="inline-add">
            <input type="hidden" name="action"       value="add_service">
            <input type="hidden" name="direction_id" value="<?php echo $d['id']; ?>">
            <input type="text" name="nom" placeholder="Nouveau service…" required>
            <button type="submit">+ Ajouter</button>
          </form>
        </div>

        <?php if ($d['type'] === 'regionale'): ?>
        <div class="sub-section" style="margin-bottom:0;">
          <h4>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            Agences (<?php echo count($ages); ?>)
          </h4>
          <?php if (!empty($ages)): ?>
            <div class="item-list">
              <?php foreach ($ages as $a): ?>
                <div class="item-row">
                  <span><?php echo htmlspecialchars($a['nom']); ?></span>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="action" value="delete_agence">
                    <input type="hidden" name="id"     value="<?php echo $a['id']; ?>">
                    <button type="submit" class="btn-del">✕</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <form method="post" class="inline-add">
            <input type="hidden" name="action"       value="add_agence">
            <input type="hidden" name="direction_id" value="<?php echo $d['id']; ?>">
            <input type="text" name="nom" placeholder="Nouvelle agence…" required>
            <button type="submit">+ Ajouter</button>
          </form>
        </div>
        <?php endif; ?>

      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require "_nav_end.php"; ?>
