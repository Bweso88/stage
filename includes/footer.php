  </div><!-- /.page-content -->
</div><!-- /.main -->

<!-- Widget Amina -->
<?php if (file_exists(__DIR__ . '/../widget-amina.php')) include __DIR__ . '/../widget-amina.php'; ?>

<script>
// ── Modal helpers ──
function openModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('open');
}
function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('open');
}

// Click outside to close
document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) el.classList.remove('open');
    });
});

// Sidebar toggle (mobile)
function toggleSidebar() {
    var sb = document.getElementById('sidebar');
    if (sb) sb.classList.toggle('open');
}

// Auto-dismiss flash messages
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(a) {
        a.style.transition = 'opacity .5s';
        a.style.opacity = '0';
        setTimeout(function() { a.remove(); }, 500);
    });
}, 4000);

// Confirm delete links
document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
    });
});
</script>
</body>
</html>
