<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}
session_destroy();
header('Location: login.php');
