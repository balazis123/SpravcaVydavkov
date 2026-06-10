<?php
require 'includes/auth.php';
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');
header('Location: ' . BASE . '/login.php');
exit;
