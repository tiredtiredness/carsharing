<?php
session_start();
date_default_timezone_set('Europe/Moscow'); // Установка часового пояса

require_once 'db.php';

$errors = [];

// Функция очистки входных данных
function sanitizeInput($input) {
    // Используем ?? '' для обработки null, если ключ отсутствует
    return trim(htmlspecialchars($input ?? ''));
}

// Получение и валидация параметров фильтрации
$filterName = sanitizeInput($_GET['name'] ?? '');
$sortRate = sanitizeInput($_GET['sort'] ?? '');
// $notRobot = isset($_GET['not_robot']); // УДАЛЕНО: Больше не проверяем "Я не робот"

// Проверка параметров
if (!in_array($sortRate, ['', 'asc', 'desc'], true)) {
    $errors[] = "Неверное значение сортировки.";
}

if (strlen($filterName) > 100) {
    $errors[] = "Слишком длинный поисковый запрос.";
}

// УДАЛЕНО: Проверка "Я не робот"
// if (!$notRobot) {
//     $errors[] = "Пожалуйста, подтвердите, что вы не робот.";
// }

// Значения по умолчанию
$hasActiveRent = false;
$userBalance = 0; // Инициализируем баланс
$cars = []; // Инициализируем массив автомобилей

try {
    // Проверяем авторизацию пользователя перед запросами к базе, связанными с ним
    if (isset($_SESSION['user']['id'])) {
        $userId = $_SESSION['user']['id'];

        // Проверка активной аренды
        $stmt = $pdo->prepare("SELECT id FROM rent WHERE userId = ? AND endTime IS NULL LIMIT 1"); // добавил LIMIT 1
        $stmt->execute([$userId]);
        $hasActiveRent = (bool)$stmt->fetch();

        // Получение баланса
        $stmt = $pdo->prepare("SELECT balance FROM user WHERE id = ? LIMIT 1"); // добавил LIMIT 1
        $stmt->execute([$userId]);
        $balanceResult = $stmt->fetchColumn(); // Получаем только значение баланса
        if ($balanceResult !== false) { // Проверяем, что запрос вернул результат
            $userBalance = $balanceResult;
        } else {
            // Если пользователя по ID не найдено (что маловероятно, если он в сессии)
            // Можно добавить обработку или ошибку, но пока просто оставляем баланс 0
            error_log("User ID " . $userId . " not found in database but present in session.");
        }

    }

    // Выполняем запрос на выборку автомобилей только если нет ошибок в параметрах фильтра
    if (empty($errors)) {
        // Формирование SQL-запроса
        $sql = "SELECT car.id, car.brand, car.model, car.number, car.status, car.rate, car.photo
                FROM car
                WHERE (:name = '' OR car.brand LIKE :name_like OR car.model LIKE :name_like)";

        if ($sortRate === 'asc') {
            $sql .= " ORDER BY car.rate ASC";
        } elseif ($sortRate === 'desc') {
            $sql .= " ORDER BY car.rate DESC";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $filterName,
            ':name_like' => '%' . $filterName . '%'
        ]);
        $cars = $stmt->fetchAll(PDO::FETCH_ASSOC); // Используем FETCH_ASSOC для удобства
    } else {
        // Если есть ошибки фильтрации, список автомобилей остается пустым
        $cars = [];
    }

} catch (PDOException $e) {
    // В случае ошибки базы данных, добавляем ее в ошибки и очищаем список автомобилей
    $errors[] = "Ошибка при работе с базой данных: " . $e->getMessage();
    $cars = [];
    // Логирование критических ошибок
    error_log("Database error in cars.php: " . $e->getMessage());
}

// Проверка, авторизован ли пользователь для отображения кнопок аренды
$isLoggedIn = isset($_SESSION['user']);

?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Каталог автомобилей | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/cars.css">
    <style>
        .error-messages {
            margin: 20px auto; /* Центрирование */
            padding: 15px;
            border-left: 5px solid #e74c3c;
            background-color: #fcecec;
            color: #c0392b;
            border-radius: 5px;
            font-size: 1rem;
            max-width: 800px; /* Ограничение по ширине */
        }

        .error-messages ul {
            margin: 0;
            padding-left: 20px;
            list-style: disc; /* Маркеры списка */
        }

        .car-card__btn--disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        /* УДАЛЕНО: Стили для .captcha-box больше не нужны */
        /* .captcha-box {
            margin-top: 1rem;
        } */
    </style>
</head>

<body>

<?php include 'header.php'; ?>

<main class="main">
    <section class="about cars">
        <h2 class="about__title cars__title">Каталог автомобилей</h2>

        <form method="GET" class="filter-form">
            <div class="filter-form__group">
                <label for="filter-name" class="filter-form__label">Поиск:</label>
                <input type="text" id="filter-name" name="name" value="<?= htmlspecialchars($filterName) ?>" placeholder="Марка или модель" class="filter-form__input">
            </div>
            <div class="filter-form__group">
                <label for="sort-rate" class="filter-form__label">Сортировка по тарифу:</label>
                <select id="sort-rate" name="sort" class="filter-form__select">
                    <option value="">Без сортировки</option>
                    <option value="asc" <?= $sortRate === 'asc' ? 'selected' : '' ?>>По возрастанию</option>
                    <option value="desc" <?= $sortRate === 'desc' ? 'selected' : '' ?>>По убыванию</option>
                </select>
            </div>
            <button type="submit" class="btn filter-form__btn">Найти</button>
        </form>

        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php
        // Проверяем, есть ли автомобили для отображения только если нет ошибок, препятствующих выборке
        if (empty($errors) && !empty($cars)): ?>
            <div class="car-list">
                <?php foreach ($cars as $car): ?>
                    <?php
                    // Пропускаем автомобили со статусом, отличным от 'доступна'
                    if ($car['status'] !== 'доступна') continue;

                    // Проверяем, может ли пользователь арендовать этот автомобиль
                    $canRent = $isLoggedIn && !$hasActiveRent && $userBalance > 0;
                    ?>

                    <form method="POST" action="start_rent.php" class="car-card__form">
                        <div class="car-card">
                            <div class="car-card__image-container">
                                <?php if (!empty($car['photo']) && file_exists("cars/{$car['photo']}")): ?>
                                    <img src="cars/<?= htmlspecialchars($car['photo']) ?>" alt="<?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>" class="car-card__image">
                                <?php else: ?>
                                    <div class="car-card__image-placeholder">
                                        <span>Фото отсутствует</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h3 class="car-card__title"><?= htmlspecialchars($car['brand']) . ' ' . htmlspecialchars($car['model']) ?></h3>
                            <p class="car-card__detail"><strong class="car-card__label">Госномер:</strong> <span class="car-card__value"><?= htmlspecialchars($car['number']) ?></span></p>
                            <p class="car-card__detail"><strong class="car-card__label">Статус:</strong>
                                <span class="car-card__status car-card__status--available">Доступен</span>
                            </p>
                            <p class="car-card__detail"><strong class="car-card__label">Тариф:</strong> <span class="car-card__rate"><?= htmlspecialchars($car['rate']) ?> ₽/час</span></p>

                            <?php if ($isLoggedIn): // Показываем кнопку только авторизованным ?>
                                <input type="hidden" name="car_id" value="<?= $car['id'] ?>">

                                <?php if ($hasActiveRent): ?>
                                    <button type="button" class="btn car-card__btn car-card__btn--disabled" disabled title="У вас уже есть активная аренда">У вас активная аренда</button>
                                <?php elseif ($userBalance <= 0): ?>
                                    <button type="button" class="btn car-card__btn car-card__btn--disabled" disabled title="Пополните баланс">Недостаточно средств</button>
                                <?php else: ?>
                                    <button type="submit" class="btn car-card__btn">Забронировать</button>
                                <?php endif; ?>

                            <?php else: // Неавторизованным предлагаем войти ?>
<!--                                <a href="login.php" class="btn car-card__btn">Войдите для аренды</a>-->
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php
        // Сообщение, если нет автомобилей или есть ошибки выборки
        elseif (empty($errors)): ?>
            <p class="no-cars-message">Автомобили не найдены по вашему запросу или пока недоступны.</p>
        <?php endif; ?>
    </section>
</main>

<?php include 'footer.php'; ?>

</body>
</html>