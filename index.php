<?php
// index.php - Redirige al dashboard o login
require_once __DIR__ . '/config.php';
if (Auth::check()) {
    redirect(BASE_URL . '/dashboard.php');
} else {
    redirect(BASE_URL . '/login.php');
}
