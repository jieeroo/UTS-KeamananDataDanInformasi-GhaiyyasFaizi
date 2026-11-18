<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (!empty($_SESSION['admin_id'])) { header('Location: admin/dashboard.php'); }
else { header('Location: admin/login.php'); }
exit;
