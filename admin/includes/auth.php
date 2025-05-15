<?php
session_start();

// Проверка авторизации
function checkAuth() {
    if (!isset($_SESSION['admin_logged_in']))
    {
        header('Location: login.php');
        exit();
    }
}

// Учетные данные администратора (в реальном проекте хранить в БД)
$adminCredentials = [
    'login' => 'admin',
    'password' => '123'
];
?>