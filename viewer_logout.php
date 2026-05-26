<?php
session_start();
unset($_SESSION['viewer'], $_SESSION['viewer_session_logged'], $_SESSION['pending_viewer_login']);
header("Location: viewer_login.php");
exit;
