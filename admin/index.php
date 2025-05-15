<?php
// admin/index.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Now the user is authenticated, display the admin index
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админпанель</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: auto; }
        h1 { text-align: center; margin-bottom: 20px; }
        .admin-links a { display: inline-block; margin: 10px; padding: 10px 15px; border: 1px solid #ccc; text-decoration: none; color: #333; border-radius: 4px; }
        .admin-links a:hover { background-color: #f0f0f0; }
        .header-links { text-align: right; margin-bottom: 20px; }
        .header-links a { margin-left: 15px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header-links">
        <a href="../index.php">На сайт</a>
        <a href="logout.php">Выход</a>
    </div>

    <h1>Админпанель</h1>

    <p>Добро пожаловать в административную панель.</p>

    <h2>Управление данными</h2>
    <div class="admin-links">
        <a href="cars.php">Автомобили</a>
        <a href="users.php">Пользователи</a>
        <a href="inspections.php">Техосмотры</a>
        <a href="news.php">Новости</a>
    </div>
</div>
</body>
</html>