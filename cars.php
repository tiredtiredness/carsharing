<?php
session_start();
require_once 'db.php';

// Проверяем, есть ли у пользователя активная аренда
$hasActiveRent = false;
if (isset($_SESSION['user']['id'])) {
    $stmt = $pdo->prepare("SELECT id FROM rent WHERE userId = ? AND endTime IS NULL");
    $stmt->execute([$_SESSION['user']['id']]);
    $hasActiveRent = (bool)$stmt->fetch();
}

$userBalance = 0;
$stmt = $pdo->prepare("SELECT balance FROM user WHERE id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$userBalance = $stmt->fetchColumn();

$filterName = $_GET['name'] ?? '';
$sortRate = $_GET['sort'] ?? '';

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
$cars = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Каталог автомобилей | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/cars.css">
</head>

<body>

    <?php include 'header.php'; ?>

    <main class="main">
        <section class="about cars">
            <h2 class="about__title cars__title">Каталог автомобилей</h2>

            <!-- Форма фильтра -->
            <form method="GET" class="filter-form">
                <div class="filter-form__group">
                    <label class="filter-form__label">Поиск:</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($filterName) ?>" placeholder="Марка или модель" class="filter-form__input">
                </div>
                <div class="filter-form__group">
                    <label class="filter-form__label">Сортировка по тарифу:</label>
                    <select name="sort" class="filter-form__select">
                        <option value="">Без сортировки</option>
                        <option value="asc" <?= $sortRate === 'asc' ? 'selected' : '' ?>>По возрастанию</option>
                        <option value="desc" <?= $sortRate === 'desc' ? 'selected' : '' ?>>По убыванию</option>
                    </select>
                </div>
                <button type="submit" class="btn filter-form__btn">Найти</button>
            </form>

            <!-- Каталог -->
            <?php if ($cars): ?>
                <div class="car-list">
                    <?php foreach ($cars as $car): ?>
                        <?php if ($car['status'] !== 'доступна') continue; ?>

                        <form method="POST" action="start_rent.php" class="car-card__form">
                            <div class="car-card">
                                <!-- Добавляем изображение автомобиля -->
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

                                <?php if (isset($_SESSION['user'])): ?>
                                    <input type="hidden" name="car_id" value="<?= $car['id'] ?>">

                                    <?php if ($userBalance <= 0): ?>
                                        <button type="button" class="btn car-card__btn car-card__btn--disabled" disabled>Недостаточно средств</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn car-card__btn">Забронировать</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-cars-message">Автомобили не найдены.</p>
            <?php endif; ?>
        </section>
    </main>

    <?php include 'footer.php'; ?>

</body>

</html>