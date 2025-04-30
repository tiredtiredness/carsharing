<?php
session_start();
date_default_timezone_set('Europe/Moscow');

// Если пользователь не авторизован — редирект
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$userId = $_SESSION['user']['id'];
$user = $_SESSION['user'];

// Обработка загрузки новой фотографии
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Если это загрузка фото
    if (isset($_FILES['new_photo'])) {
        $photo = $_FILES['new_photo'];
        $errors = [];

        // Проверка файла
        if (!empty($photo['name'])) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($photo['type'], $allowedTypes)) {
                $errors[] = "Допустимы только изображения (jpeg, png, gif, webp).";
            } elseif ($photo['size'] > 2 * 1024 * 1024) {
                $errors[] = "Размер изображения не должен превышать 2MB.";
            } else {
                $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
                $photoName = uniqid('avatar_', true) . '.' . $ext;

                // Удаляем старое фото, если оно существует
                if (!empty($user['photo']) && file_exists("uploads/{$user['photo']}")) {
                    unlink("uploads/{$user['photo']}");
                }

                if (move_uploaded_file($photo['tmp_name'], "uploads/$photoName")) {
                    // Обновляем фото в базе данных
                    $stmt = $pdo->prepare("UPDATE user SET photo = ? WHERE id = ?");
                    $stmt->execute([$photoName, $userId]);

                    // Обновляем данные в сессии
                    $_SESSION['user']['photo'] = $photoName;
                    $user = $_SESSION['user'];

                    // Перенаправляем, чтобы избежать повторной отправки формы
                    header("Location: profile.php");
                    exit();
                } else {
                    $errors[] = "Не удалось сохранить фото. Попробуйте другое изображение.";
                }
            }
        } else {
            $errors[] = "Файл не был загружен.";
        }

        // Если были ошибки, сохраняем их в сессию для отображения
        if (!empty($errors)) {
            $_SESSION['photo_errors'] = $errors;
            header("Location: profile.php");
            exit();
        }
    }
    // Если это пополнение баланса
    elseif (isset($_POST['topup_balance'])) {
        $amount = 500; // Сумма пополнения

        // Обновляем баланс в базе данных
        $stmt = $pdo->prepare("UPDATE user SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);

        // Обновляем баланс в сессии
        $_SESSION['user']['balance'] += $amount;
        $user = $_SESSION['user'];

        // Перенаправляем, чтобы избежать повторной отправки формы
        header("Location: profile.php");
        exit();
    }
}

// Получение текущих аренд (используем fetchAll для получения всех)
$stmt = $pdo->prepare("
    SELECT rent.*, car.brand, car.model, car.rate
    FROM rent
    JOIN car ON rent.carId = car.id
    WHERE rent.userId = ? AND rent.endTime IS NULL
    ORDER BY rent.startTime DESC -- добавлено для порядка, если несколько аренд
");
$stmt->execute([$userId]);
$currentRents = $stmt->fetchAll(); // Используем fetchAll() для получения всех текущих аренд

// Получение истории аренды
$stmt = $pdo->prepare("
    SELECT rent.*, car.brand, car.model, car.rate, rent.endTime
    FROM rent
    JOIN car ON rent.carId = car.id
    WHERE rent.userId = ? AND rent.endTime IS NOT NULL
    ORDER BY rent.startTime DESC
");
$stmt->execute([$userId]);
$rentHistory = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="30">
    <title>Профиль | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/profile.css">
</head>

<body>
<?php include 'header.php'; ?>

<main class="main">
    <section class="about profile">
        <h2 class="about__title profile__title">Личный кабинет</h2>

        <div class="profile-card profile__header">
            <div class="profile-photo-container">
                <?php if (!empty($user['photo']) && file_exists("uploads/{$user['photo']}")): ?>
                    <img src="uploads/<?= htmlspecialchars($user['photo']) ?>" alt="Фото профиля" class="profile-photo profile__avatar">
                <?php else: ?>
                    <div class="profile-photo profile__avatar placeholder">
                        <p>👤</p>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="change-photo-form" id="photoForm">
                    <label for="new_photo" class="change-photo-label">Изменить фото</label>
                    <input type="file" id="new_photo" name="new_photo" accept="image/*" class="change-photo-input">
                    <div id="file-info" class="file-info" style="display: none;">
                        <span id="file-name"></span>
                        <span id="file-size"></span>
                    </div>
                    <button type="submit" class="change-photo-btn" id="submit-btn" style="display: none;">Обновить</button>
                </form>

                <?php if (!empty($_SESSION['photo_errors'])): ?>
                    <div class="photo-errors">
                        <?php foreach ($_SESSION['photo_errors'] as $error): ?>
                            <p class="photo-error"><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                        <?php unset($_SESSION['photo_errors']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-info profile__info">
                <div class="profile__section">
                    <h3 class="profile__section-title">Основная информация</h3>
                    <p class="profile__meta-item"><strong class="profile__meta-label">ФИО:</strong> <span class="profile__meta-value"><?= htmlspecialchars($user['fullName']) ?></span></p>
                    <p class="profile__meta-item"><strong class="profile__meta-label">Дата рождения:</strong> <span class="profile__meta-value"><?= date("d.m.Y", strtotime($user['birthdate'])) ?></span></p>
                    <p class="profile__meta-item"><strong class="profile__meta-label">Email:</strong> <span class="profile__meta-value"><?= htmlspecialchars($user['email']) ?></span></p>
                    <p class="profile__meta-item"><strong class="profile__meta-label">Телефон:</strong> <span class="profile__meta-value"><?= '+7' . htmlspecialchars($user['phone']) ?></span></p>
                </div>

                <div class="profile__section">
                    <h3 class="profile__section-title">Данные водителя</h3>
                    <p class="profile__meta-item"><strong class="profile__meta-label">Номер прав:</strong> <span class="profile__meta-value"><?= htmlspecialchars($user['license']) ?></span></p>
                    <p class="profile__meta-item"><strong class="profile__meta-label">Баланс:</strong>
                        <span class="profile__meta-value"><?= number_format($user['balance'], 2, '.', ' ') ?> ₽</span>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="topup_balance" class="btn balance-btn" title="Пополнить баланс на 500 рублей">
                            +500 ₽
                        </button>
                    </form>
                    </p>
                </div>

                <div class="profile__section">
                    <h3 class="profile__section-title">Аккаунт</h3>
                    <p class="profile__meta-item"><strong class="profile__meta-label">Дата регистрации:</strong> <span class="profile__meta-value"><?= date("d.m.Y H:i", strtotime($user['reg_date'])) ?></span></p>
                </div>
            </div>
        </div>

        <h3 class="profile-content__title">Текущие аренды</h3>
        <?php if (!empty($currentRents)): ?>
            <div class="current-rents bookings">
                <?php foreach ($currentRents as $currentRent): // Перебираем все текущие аренды ?>
                    <div class="car-card booking-card">
                        <h3 class="booking-card__title"><?= htmlspecialchars($currentRent['brand']) . ' ' . htmlspecialchars($currentRent['model']) ?></h3>
                        <div class="booking-card__details">
                            <p class="booking-card__detail"><strong class="booking-card__label">Начало аренды:</strong> <span class="booking-card__value"><?= date("d.m.Y H:i", strtotime($currentRent['startTime'])) ?></span></p>
                            <p class="booking-card__detail"><strong class="booking-card__label">Тариф:</strong> <span class="booking-card__value"><?= $currentRent['rate'] ?> ₽/мин</span></p>

                            <?php
                            $start = new DateTime($currentRent['startTime']);
                            $end = new DateTime('now');
                            $interval = $start->diff($end);
                            $totalMinutes = max(0, floor(($end->getTimestamp() - $start->getTimestamp()) / 60));
                            $price = round($currentRent['rate'] * $totalMinutes, 2);

                            // Форматирование длительности
                            $durationParts = [];
                            if ($interval->y > 0) $durationParts[] = $interval->y . ' г';
                            if ($interval->m > 0) $durationParts[] = $interval->m . ' мес';
                            if ($interval->d > 0) $durationParts[] = $interval->d . ' дн';
                            if ($interval->h > 0) $durationParts[] = $interval->h . ' ч';
                            if ($interval->i > 0 || empty($durationParts)) $durationParts[] = $interval->i . ' мин'; // Всегда показываем минуты, даже если 0, если нет других частей
                            if ($interval->s > 0 && empty($durationParts) && $interval->i == 0) $durationParts[] = $interval->s . ' сек'; // Показываем секунды, если меньше минуты
                            $formattedDuration = implode(' ', $durationParts);
                            if (empty($formattedDuration)) $formattedDuration = 'меньше минуты'; // Для очень коротких аренд

                            ?>

                            <p class="booking-card__detail"><strong class="booking-card__label">Длительность аренды:</strong> <span class="booking-card__value"><?= $formattedDuration ?></span></p>
                            <p class="booking-card__detail"><strong class="booking-card__label">Текущая стоимость:</strong> <span class="booking-card__value"><?= number_format($price, 2, '.', ' ') ?> ₽</span></p>
                        </div>

                        <form method="POST" action="end_rent.php" class="booking-card__actions">
                            <input type="hidden" name="rent_id" value="<?= $currentRent['id'] ?>">
                            <button type="submit" class="btn booking-card__btn booking-card__btn--primary">Завершить аренду</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="no-cars-message">У вас нет активной аренды.</p>
        <?php endif; ?>

        <h3 class="profile-content__title">История аренд</h3>
        <?php if ($rentHistory): ?>
            <div class="rent-history bookings">
                <?php foreach ($rentHistory as $rent): ?>
                    <div class="car-card booking-card">
                        <h3 class="booking-card__title"><?= htmlspecialchars($rent['brand']) . ' ' . htmlspecialchars($rent['model']) ?></h3>
                        <div class="booking-card__details">
                            <p class="booking-card__detail"><strong class="booking-card__label">Начало аренды:</strong> <span class="booking-card__value"><?= date("d.m.Y H:i", strtotime($rent['startTime'])) ?></span></p>
                            <p class="booking-card__detail"><strong class="booking-card__label">Окончание аренды:</strong> <span class="booking-card__value"><?= date("d.m.Y H:i", strtotime($rent['endTime'])) ?></span></p>
                            <p class="booking-card__detail"><strong class="booking-card__label">Тариф:</strong> <span class="booking-card__value"><?= $rent['rate'] ?> ₽/мин</span></p>

                            <?php
                            $start = new DateTime($rent['startTime']);
                            $end = new DateTime($rent['endTime']);
                            $interval = $start->diff($end);
                            $totalMinutes = floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
                            $pricePerMinute = $rent['rate'];
                            $price = round($pricePerMinute * $totalMinutes, 2);

                            // Форматирование длительности для истории
                            $durationParts = [];
                            if ($interval->y > 0) $durationParts[] = $interval->y . ' г';
                            if ($interval->m > 0) $durationParts[] = $interval->m . ' мес';
                            if ($interval->d > 0) $durationParts[] = $interval->d . ' дн';
                            if ($interval->h > 0) $durationParts[] = $interval->h . ' ч';
                            if ($interval->i > 0 || empty($durationParts)) $durationParts[] = $interval->i . ' мин';
                            if ($interval->s > 0 && empty($durationParts) && $interval->i == 0) $durationParts[] = $interval->s . ' сек';
                            $formattedDuration = implode(' ', $durationParts);
                            if (empty($formattedDuration)) $formattedDuration = 'меньше минуты';
                            ?>

                            <p class="booking-card__detail"><strong class="booking-card__label">Длительность:</strong> <span class="booking-card__value"><?= $formattedDuration ?></span></p>
                            <p class="booking-card__detail"><strong class="booking-card__label">Итоговая стоимость:</strong> <span class="booking-card__value"><?= number_format($price, 2, '.', ' ') ?> ₽</span></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="no-cars-message">Вы ещё не арендовали автомобили.</p>
        <?php endif; ?>
    </section>
</main>

<?php include 'footer.php'; ?>

<script>
    document.getElementById('new_photo').addEventListener('change', function(e) {
        const fileInfo = document.getElementById('file-info');
        const fileName = document.getElementById('file-name');
        const fileSize = document.getElementById('file-size');
        const submitBtn = document.getElementById('submit-btn');

        if (this.files.length > 0) {
            const file = this.files[0];
            fileName.textContent = file.name;

            // Форматирование размера файла
            let size = file.size;
            let sizeText;
            if (size < 1024) {
                sizeText = size + ' байт';
            } else if (size < 1024 * 1024) {
                sizeText = (size / 1024).toFixed(1) + ' КБ';
            } else {
                sizeText = (size / (1024 * 1024)).toFixed(1) + ' МБ';
            }
            fileSize.textContent = ' (' + sizeText + ')';

            fileInfo.style.display = 'block';
            submitBtn.style.display = 'block';
        } else {
            fileInfo.style.display = 'none';
            submitBtn.style.display = 'none';
        }
    });
</script>

</body>

</html>