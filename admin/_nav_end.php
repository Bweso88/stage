  </div><!-- /main-content -->
</div><!-- /wrapper -->

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  var saved = localStorage.getItem("theme") || "light";
  document.documentElement.dataset.theme = saved;
})();
function toggleTheme(){
  var html = document.documentElement;
  html.dataset.theme = html.dataset.theme === "dark" ? "light" : "dark";
  localStorage.setItem("theme", html.dataset.theme);
}
</script>
</body>
</html>
