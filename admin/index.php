<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
if (is_admin()) redirect('/admin/dashboard.php');
redirect('/admin/login.php');
