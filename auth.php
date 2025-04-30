<?php
// auth.php - Функции для аутентификации и управления сессиями

// Важно: session_start() должна вызываться *до* любого вывода в браузер.
// Поэтому ее лучше всего разместить в header.php, который подключается в самом начале каждой страницы.
// session_start(); // Перенесено в header.php

// Функция для проверки, авторизован ли пользователь
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

// Функция для регистрации нового пользователя
function register_user($pdo, $login, $password, $email, $reg_date, $photo_path = null)
{
    // Хеширование пароля перед сохранением в БД
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $sql = "INSERT INTO users (login, password, email, registration_date, photo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$login, $hashed_password, $email, $reg_date, $photo_path]);
        return true; // Успешная регистрация
    } catch (PDOException $e) {
        // Обработка ошибки (например, если логин или email уже существуют, если есть UNIQUE constraint)
        // Можно логировать $e->getMessage()
        return false; // Ошибка регистрации
    }
}

// Функция для проверки логина и пароля пользователя
function check_login($pdo, $login, $password)
{
    try {
        $sql = "SELECT id, login, password FROM users WHERE login = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$login]);
        $user = $stmt->fetch(); // Получаем данные пользователя

        if ($user && password_verify($password, $user['password'])) {
            // Пароль верный
            // Сохраняем ID пользователя в сессию
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_login'] = $user['login']; // Можно сохранить и логин для удобства
            return true;
        } else {
            // Неверный логин или пароль
            return false;
        }
    } catch (PDOException $e) {
        // Обработка ошибки запроса к БД
        // Можно логировать $e->getMessage()
        return false;
    }
}

// Функция для получения данных пользователя по ID
function get_user_data($pdo, $user_id)
{
    try {
        $sql = "SELECT id, login, email, registration_date, photo FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        // Обработка ошибки
        return null;
    }
}

// Функция выхода пользователя (очистка сессии)
function logout_user()
{
    // Удаляем все переменные сессии
    $_SESSION = array();

    // Если используется сессионная cookie, удаляем и ее
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

    // Уничтожаем сессию
    session_destroy();
}
