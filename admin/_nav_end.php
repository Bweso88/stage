  </main>
</div><!-- /wrapper -->

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  var t = localStorage.getItem("fpTheme") || "light";
  document.documentElement.dataset.theme = t;
})();
function toggleTheme(){
  var h = document.documentElement;
  h.dataset.theme = h.dataset.theme==="dark" ? "light" : "dark";
  localStorage.setItem("fpTheme", h.dataset.theme);
}
</script>
</body>
</html>
