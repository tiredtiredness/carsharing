<header>
    <nav>
        <a href="index.php">Главная</a>
        <a href="cars.php">Каталог</a>
        <a href="contact.php">Контакты</a>
        <a href="news.php">Новости</a>
        <?php if (isset($_SESSION['user'])): ?>
            <a href="profile.php">Кабинет</a>
            <a href="logout.php">Выход</a>
        <?php else: ?>
            <a href="register.php">Регистрация</a>
            <a href="login.php">Вход</a>
        <?php endif; ?>
    </nav>
</header>