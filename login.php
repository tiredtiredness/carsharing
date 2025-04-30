<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = trim($_POST['phone']);
    $phone = substr($phone, 2);
    $password = $_POST['password'];

    // Запрос по номеру телефона
    $stmt = $pdo->prepare("SELECT * FROM user WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['passwordHash'])) {
        $_SESSION['user'] = $user;
        header("Location: index.php");
        exit();
    } else {
        $error = "Неверный номер телефона или пароль.";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Вход | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">
</head>

<body>

    <?php include 'header.php'; ?>

    <main class="main">
        <section class="login">
            <h2 class="login__title">Вход в аккаунт</h2>

            <?php if ($error): ?>
                <p class="login__error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form method="POST" class="login__form">
                <div class="login__input-group">
                    <label for="phone" class="login__label">Телефон:</label><br>
                    <input type="text" id="phone" name="phone" class="login__input" required value="+7">
                </div>
                <div class="login__input-group">
                    <label for="password" class="login__label">Пароль:</label><br>
                    <input type="password" id="password" name="password" class="login__input" required>
                </div>
                <button type="submit" class="login__button">Войти</button>
            </form>

            <p class="login__register-link" style="margin-top: 20px;">Нет аккаунта? <a href="register.php" class="login__link">Зарегистрироваться</a></p>
        </section>
    </main>


    <?php include 'footer.php'; ?>

</body>

</html>