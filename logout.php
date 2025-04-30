<?php
session_start();

// Очистить все данные сессии
$_SESSION = [];

// Удалить cookie сессии (если используется)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Уничтожить сессию
session_destroy();

// Перенаправить на главную или страницу входа
header("Location: index.php");
exit();
