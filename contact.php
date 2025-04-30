<?php
session_start();
// Обработка отправки формы
$success = '';
$error = '';
// Подключаем PHPMailer вручную
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {


    // Получаем данные
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $message = trim(htmlspecialchars($_POST['message'] ?? ''));

    // Валидация
    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Заполните все поля';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный email';
    } else {
        $mail = new PHPMailer(true);

        try {
            // Настройки SMTP для Gmail
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'kostyacomarov3@gmail.com'; // Ваш Gmail
            $mail->Password = 'xaaj nvrw ujyv rwcm'; // Пароль приложения
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            // От кого
            $mail->setFrom($email, $name);
            $mail->addReplyTo($email, $name);

            // Кому
            $mail->addAddress('kostyacomarov3@gmail.com');

            // Содержимое
            $mail->isHTML(false);
            $mail->Subject = "Сообщение от $name";
            $mail->Body = "Пользователь: $name\nEmail: $email\n\nСообщение:\n$message";

            $mail->send();
            $success = 'Сообщение отправлено!';
            $_POST = [];
        } catch (Exception $e) {
            $error = "Ошибка: {$mail->ErrorInfo}";
            error_log("Mail Error: {$mail->ErrorInfo}");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Контакты | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/contact.css">
</head>

<body>

<?php include 'header.php'; ?>

<main class="main">
    <div class="contact">
        <section class="contact-form">
            <h2 class="contact-form__title">Свяжитесь с нами</h2>

            <?php if ($success): ?>
                <div class="contact-message contact-message--success"><?= $success ?></div>
            <?php elseif ($error): ?>
                <div class="contact-message contact-message--error"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" class="contact-form__form">
                <div class="contact-form__group">
                    <label for="name" class="contact-form__label">Ваше имя:</label>
                    <input type="text" id="name" name="name" required class="contact-form__input"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="contact-form__group">
                    <label for="email" class="contact-form__label">Email для связи:</label>
                    <input type="email" id="email" name="email" required class="contact-form__input"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="contact-form__group">
                    <label for="message" class="contact-form__label">Сообщение:</label>
                    <textarea id="message" name="message" rows="5" required
                              class="contact-form__textarea"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>
                <button type="submit" name="submit" class="btn contact-form__btn">Отправить</button>
            </form>
        </section>

        <hr class="contact__divider">

        <section class="contact-info">
            <h3 class="contact-info__title">Контактная информация</h3>
            <p class="contact-info__item">Email: support@carshare.local</p>
            <p class="contact-info__item">Телефон: +7 (999) 123-45-67</p>
            <p class="contact-info__item">Адрес: г. Санкт-Петербург, ул. Автомобильная, 10</p>
        </section>
    </div>
</main>

<?php include 'footer.php'; ?>

</body>

</html>