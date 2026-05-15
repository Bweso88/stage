</main>
<script>
(function(){
  var t = localStorage.getItem("fpt") || "dark";
  document.getElementById("adminRoot").dataset.theme = t;
  if (t === "light") {
    document.documentElement.style.setProperty("--bg","#f5f7fa");
    document.documentElement.style.setProperty("--surface","#ffffff");
    document.documentElement.style.setProperty("--surface2","#f3f4f6");
    document.documentElement.style.setProperty("--border","#e5e7eb");
    document.documentElement.style.setProperty("--text","#111827");
    document.documentElement.style.setProperty("--muted","#6b7280");
    document.documentElement.style.setProperty("--subtle","#9ca3af");
  }
})();
function toggleTheme() {
  var r = document.documentElement;
  var t = localStorage.getItem("fpt") || "dark";
  if (t === "dark") {
    r.style.setProperty("--bg","#f5f7fa");
    r.style.setProperty("--surface","#ffffff");
    r.style.setProperty("--surface2","#f3f4f6");
    r.style.setProperty("--border","#e5e7eb");
    r.style.setProperty("--text","#111827");
    r.style.setProperty("--muted","#6b7280");
    r.style.setProperty("--subtle","#9ca3af");
    localStorage.setItem("fpt","light");
  } else {
    r.style.setProperty("--bg","#0f1117");
    r.style.setProperty("--surface","#1a1d27");
    r.style.setProperty("--surface2","#222535");
    r.style.setProperty("--border","#2e3148");
    r.style.setProperty("--text","#e8eaf0");
    r.style.setProperty("--muted","#6b7280");
    r.style.setProperty("--subtle","#374151");
    localStorage.setItem("fpt","dark");
  }
}
</script>
</body>
</html>
