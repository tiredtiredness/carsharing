<?php
session_start();
require_once 'db.php';
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Главная | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="body">

    <?php include 'header.php'; ?>

    <main class="main">
        <section class="hero">
            <h1 class="hero__title">Добро пожаловать в CarShare</h1>
            <p class="hero__description">Умный способ арендовать автомобиль в любое время и в любом месте.</p>

            <!-- Если пользователь не авторизован, показываем кнопки регистрации и каталога -->
            <?php if (!isset($_SESSION['user'])): ?>
                <a href="register.php" class="btn hero__btn">Зарегистрироваться</a>
                <a href="products.php" class="btn hero__btn">Каталог автомобилей</a>
            <?php else: ?>
                <!-- Приветствие и ссылка на личный кабинет для авторизованного пользователя -->
                <a href="profile.php" class="btn hero__btn">Перейти в личный кабинет</a>
            <?php endif; ?>
        </section>

        <section class="about">
            <h2 class="about__title">Что такое каршеринг?</h2>
            <p class="about__text">Каршеринг — это краткосрочная аренда автомобилей. Вы можете арендовать автомобиль на несколько минут, часов или дней прямо с телефона или компьютера.</p>
            <ul class="about__list">
                <li class="about__item">Доступ 24/7</li>
                <li class="about__item">Без лишних документов</li>
                <li class="about__item">Оплата только за время аренды</li>
            </ul>
        </section>

        <section class="video">
            <video class="video__player" controls width="100%" muted autoplay>
                <source src="demo-video.mp4" type="video/mp4">
                Ваш браузер не поддерживает видео.
            </video>
        </section>
    </main>

    <?php include 'footer.php'; ?>

</body>

</html>