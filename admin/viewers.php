<?php
session_start();
if (!isset($_SESSION["user"])) { header("Location: login.php"); exit; }
require "db.php";

$pageTitle = "Apprenants";
$totalViewers  = $pdo->query("SELECT COUNT(*) FROM viewers")->fetchColumn();
$todaySessions = $pdo->query("SELECT COUNT(*) FROM viewer_sessions WHERE DATE(started_at)=CURDATE()")->fetchColumn();
$todayWatched  = $pdo->query("SELECT COUNT(*) FROM video_watch_log WHERE DATE(watched_at)=CURDATE()")->fetchColumn();
$totalWatched  = $pdo->query("SELECT COUNT(*) FROM video_watch_log")->fetchColumn();
$viewers = $pdo->query("SELECT v.*, COUNT(vs.id) AS nb_sessions, COALESCE(SUM(wl.watch_seconds),0) AS total_secs FROM viewers v LEFT JOIN viewer_sessions vs ON vs.viewer_id = v.id LEFT JOIN video_watch_log wl ON wl.viewer_id = v.id GROUP BY v.id ORDER BY v.last_seen DESC")->fetchAll();
$recent = $pdo->query("SELECT wl.*, v.windows_login, v.direction, v.service FROM video_watch_log wl JOIN viewers v ON v.id = wl.viewer_id ORDER BY wl.watched_at DESC LIMIT 50")->fetchAll();
require "_nav.php";
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
  <h1 class="adm-title" style="margin:0;">👁 Apprenants & Activité</h1>
</div>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:28px;">
  <div class="kpi-card"><div class="kpi-icon" style="background:rgba(26,46,110,.15);">👥</div><div><div class="kpi-val"><?php echo $totalViewers; ?></div><div class="kpi-label">Apprenants inscrits</div></div></div>
  <div class="kpi-card"><div class="kpi-icon" style="background:rgba(16,185,129,.1);">📅</div><div><div class="kpi-val"><?php echo $todaySessions; ?></div><div class="kpi-label">Connexions aujourd'hui</div></div></div>
  <div class="kpi-card"><div class="kpi-icon" style="background:rgba(245,158,11,.1);">🎬</div><div><div class="kpi-val"><?php echo $todayWatched; ?></div><div class="kpi-label">Vidéos vues aujourd'hui</div></div></div>
  <div class="kpi-card"><div class="kpi-icon" style="background:rgba(14,165,233,.1);">📊</div><div><div class="kpi-val"><?php echo $totalWatched; ?></div><div class="kpi-label">Vues totales</div></div></div>
</div>
<div class="card" style="margin-bottom:28px;">
  <div style="padding:18px 20px;border-bottom:1px solid var(--border);font-weight:700;">Liste des apprenants</div>
  <div style="overflow-x:auto;"><table class="adm-table"><thead><tr><th>Identifiant</th><th>Nom complet</th><th>Direction</th><th>Service / Agence</th><th>Sessions</th><th>Temps total</th><th>Dernière activité</th></tr></thead><tbody>
  <?php if (empty($viewers)): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px;">Aucun apprenant enregistré pour l'instant.</td></tr>
  <?php else: foreach ($viewers as $vw): $secs=(int)$vw['total_secs'];$hours=floor($secs/3600);$mins=floor(($secs%3600)/60);$timeStr=$hours>0?"{$hours}h {$mins}m":"{$mins}m"; ?>
  <tr><td><span style="font-weight:600;"><?php echo htmlspecialchars($vw['windows_login']); ?></span></td><td><?php echo htmlspecialchars($vw['fullname']?:'—'); ?></td><td><span class="badge badge-indigo"><?php echo htmlspecialchars($vw['direction']?:'—'); ?></span></td><td><?php echo htmlspecialchars($vw['service']?:'—'); ?></td><td style="text-align:center;"><?php echo $vw['nb_sessions']; ?></td><td style="text-align:center;"><?php echo $timeStr; ?></td><td style="color:var(--muted);font-size:.82rem;"><?php echo $vw['last_seen']?date('d/m/Y H:i',strtotime($vw['last_seen'])):'—'; ?></td></tr>
  <?php endforeach; endif; ?></tbody></table></div>
</div>
<div class="card">
  <div style="padding:18px 20px;border-bottom:1px solid var(--border);font-weight:700;">Activité récente (50 dernières vues)</div>
  <div style="overflow-x:auto;"><table class="adm-table"><thead><tr><th>Date / Heure</th><th>Apprenant</th><th>Direction</th><th>Vidéo</th><th>Thème</th><th>Durée visionnée</th></tr></thead><tbody>
  <?php if (empty($recent)): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:40px;">Aucune activité enregistrée.</td></tr>
  <?php else: foreach ($recent as $row): $secs=(int)$row['watch_seconds'];$dur=$secs>=60?floor($secs/60).'m '.($secs%60).'s':$secs.'s';$videoLabel=ucfirst(str_replace(['_','-'],' ',pathinfo($row['video'],PATHINFO_FILENAME))); ?>
  <tr><td style="color:var(--muted);font-size:.82rem;white-space:nowrap;"><?php echo date('d/m/Y H:i',strtotime($row['watched_at'])); ?></td><td><span style="font-weight:600;"><?php echo htmlspecialchars($row['windows_login']); ?></span></td><td><span class="badge badge-indigo"><?php echo htmlspecialchars($row['direction']); ?></span></td><td style="font-size:.85rem;"><?php echo htmlspecialchars($videoLabel); ?></td><td><span class="badge badge-gray"><?php echo htmlspecialchars($row['theme']); ?></span></td><td style="text-align:center;color:var(--muted);font-size:.82rem;"><?php echo $secs>0?$dur:'<span style="color:var(--subtle)">—</span>'; ?></td></tr>
  <?php endforeach; endif; ?></tbody></table></div>
</div>
<?php require "_nav_end.php"; ?>
