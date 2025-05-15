<?php
require_once 'auth.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель CarShare</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        header { background: #333; color: white; padding: 10px 20px; }
        nav ul { list-style: none; padding: 0; margin: 0; display: flex; }
        nav ul li { margin-right: 20px; }
        nav ul li a { color: white; text-decoration: none; }
        nav ul li a:hover { text-decoration: underline; }
        .container { padding: 20px; }
        .main-link { float: right; }
    </style>
</head>
<body>
<header>
    <nav>
        <ul style="display: flex;">
            <li style="flex-grow: 1 "><a href="index.php">Главная</a></li>
            <li><a href="cars.php">Автомобили</a></li>
            <li><a href="logout.php">Выйти</a></li>
        </ul>
        <a href="/index.php" class="main-link">На основной сайт</a>
    </nav>
</header>
<div class="container">