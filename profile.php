<?php
session_start();
date_default_timezone_set('Europe/Moscow');

// Добавляем заголовки для предотвращения кеширования браузером
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.


// Если пользователь не авторизован — редирект
if (!isset($_SESSION['user']['id'])) { // Проверяем наличие ID пользователя в сессии
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$userId = $_SESSION['user']['id'];

// --- Загружаем АКТУАЛЬНЫЕ данные пользователя из базы данных ---
// Эта часть заменяет старую строку $user = $_SESSION['user'];
try {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Если по какой-то причине данные пользователя не найдены (например, удален админом)
    if (!$user) {
        // Уничтожаем сессию и перенаправляем на страницу входа
        session_destroy();
        header('Location: login.php');
        exit();
    }

} catch (PDOException $e) {
    // Обработка ошибки загрузки данных пользователя из БД
    // В продакшене лучше логировать ошибку, а не выводить ее пользователю
    // error_log("Database error fetching user profile: " . $e->getMessage());
    die("Произошла ошибка при загрузке данных пользователя.");
}
// Теперь переменная $user содержит актуальные данные из БД

// --- ОБРАБОТКА POST-ЗАПРОСОВ (загрузка фото, пополнение баланса) ---
// Этот блок остается, но мы должны убедиться, что он обновляет и БД, и сессию,
// чтобы изменения, сделанные НА ЭТОЙ странице, были видны СРАЗУ после редиректа.
// (Код ниже уже это делает правильно)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Если это загрузка новой фотографии
    if (isset($_FILES['new_photo']) && $_FILES['new_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $photo = $_FILES['new_photo'];
        $errors = [];
        $upload_dir = 'uploads/'; // Убедитесь, что эта папка существует и доступна для записи

        // Проверка и обработка файла
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array(mime_content_type($photo['tmp_name']), $allowedTypes)) {
            $errors[] = "Допустимы только изображения (jpeg, png, gif, webp).";
        } elseif ($photo['size'] > $maxSize) {
            $errors[] = "Размер изображения не должен превышать 2MB.";
        } else {
            $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
            $photoName = uniqid('avatar_', true) . '.' . $ext;
            $destPath = $upload_dir . $photoName;

            // Удаляем старое фото, если оно существует и отличается от нового имени
            if (!empty($user['photo']) && $user['photo'] !== $photoName) {
                $oldPhotoPath = $upload_dir . $user['photo'];
                if (file_exists($oldPhotoPath)) {
                    @unlink($oldPhotoPath); // Используем @ для подавления ошибок, если файл уже удален
                }
            }

            if (move_uploaded_file($photo['tmp_name'], $destPath)) {
                // Обновляем фото в базе данных
                $stmt = $pdo->prepare("UPDATE user SET photo = ? WHERE id = ?");
                $stmt->execute([$photoName, $userId]);

                // --- ОБНОВЛЯЕМ ДАННЫЕ В СЕССИИ И ПЕРЕМЕННОЙ $user ПОСЛЕ УСПЕШНОГО ОБНОВЛЕНИЯ ФОТО ---
                // Это нужно, чтобы после редиректа сессия содержала актуальное фото,
                // и переменная $user на следующей загрузке страницы тоже была актуальной.
                $_SESSION['user']['photo'] = $photoName;
                $user['photo'] = $photoName; // Обновляем переменную $user для текущей загрузки страницы (хотя после редиректа она все равно будет перезагружена из БД)


                // Перенаправляем, чтобы избежать повторной отправки формы
                header("Location: profile.php");
                exit();
            } else {
                $errors[] = "Не удалось сохранить фото. Проверьте права доступа к папке 'uploads'.";
            }
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

        try {
            // Обновляем баланс в базе данных
            $stmt = $pdo->prepare("UPDATE user SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$amount, $userId]);

            // --- ОБНОВЛЯЕМ ДАННЫЕ В СЕССИИ И ПЕРЕМЕННОЙ $user ПОСЛЕ УСПЕШНОГО ОБНОВЛЕНИЯ БАЛАНСА ---
            // Это нужно, чтобы после редиректа сессия содержала актуальный баланс.
            $_SESSION['user']['balance'] += $amount;
            // Переменная $user обновится на следующей загрузке страницы при чтении из БД

            // Перенаправляем
            header("Location: profile.php");
            exit();

        } catch (PDOException $e) {
            // Обработка ошибки пополнения баланса
            // error_log("Database error topping up balance: " . $e->getMessage());
            $_SESSION['balance_error'] = "Ошибка при пополнении баланса.";
            header("Location: profile.php"); // Редирект даже при ошибке
            exit();
        }
    }
}

// Получаем ошибки загрузки фото из сессии, если есть
$photo_errors = [];
if (isset($_SESSION['photo_errors'])) {
    $photo_errors = $_SESSION['photo_errors'];
    unset($_SESSION['photo_errors']);
}
// Получаем ошибку пополнения баланса из сессии, если есть
$balance_error = null;
if (isset($_SESSION['balance_error'])) {
    $balance_error = $_SESSION['balance_error'];
    unset($_SESSION['balance_error']);
}


// Получение текущих аренд (используем fetchAll для получения всех)
$stmt = $pdo->prepare("
    SELECT rent.*, car.brand, car.model, car.rate
    FROM rent
    JOIN car ON rent.carId = car.id
    WHERE rent.userId = ? AND rent.endTime IS NULL
    ORDER BY rent.startTime DESC
");
$stmt->execute([$userId]);
$currentRents = $stmt->fetchAll();

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
    <title>Профиль | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/profile.css">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>

<body>
<?php include 'header.php'; ?>

<main class="main">
    <section class="about profile">
        <h2 class="about__title profile__title">Личный кабинет</h2>

        <div class="profile-card profile__header">
            <div class="profile-photo-container">
                <?php
                // Проверяем наличие фото и файла на диске
                $photoPath = 'uploads/' . $user['photo'];
                $photoExists = !empty($user['photo']) && file_exists($photoPath);
                ?>
                <?php if ($photoExists): ?>
                    <img src="<?= htmlspecialchars($photoPath) . '?t=' . time() ?>" alt="Фото профиля" class="profile-photo profile__avatar"> <?php else: ?>
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

                <?php if (!empty($photo_errors)): // Используем переменную, заполненную из сессии ?>
                    <div class="photo-errors">
                        <?php foreach ($photo_errors as $error): ?>
                            <p class="photo-error"><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-info profile__info">
                <div class="profile__section">
                    <h3 class="profile__section-title">Основная информация</h3>
                    <p class="profile__meta-item"><strong class="profile__meta-label">ФИО:</strong> <span class="profile__meta-value"><?= htmlspecialchars($user['fullName']) ?></span></p>
                    <p class="profile__meta-item"><strong class="profile__meta-label">Дата рождения:</strong> <span class="profile__meta-value"><?= !empty($user['birthdate']) && $user['birthdate'] !== '0000-00-00' ? date("d.m.Y", strtotime($user['birthdate'])) : 'Не указана' ?></span></p> <p class="profile__meta-item"><strong class="profile__meta-label">Email:</strong> <span class="profile__meta-value"><?= htmlspecialchars($user['email']) ?></span></p>
                    <p class="profile__meta-item"><strong class="profile__meta-label">Телефон:</strong> <span class="profile__meta-value"><?= !empty($user['phone']) ? htmlspecialchars('+7' . $user['phone']) : 'Не указан' ?></span></p> </div>

                <div class="profile__section">
                    <h3 class="profile__section-title">Данные водителя</h3>
                    <p class="profile__meta-item"><strong class="profile__meta-label">Номер прав:</strong> <span class="profile__meta-value"><?= !empty($user['license']) ? htmlspecialchars($user['license']) : 'Не указан' ?></span></p> <p class="profile__meta-item"><strong class="profile__meta-label">Баланс:</strong>
                        <span class="profile__meta-value"><?= number_format($user['balance'], 2, '.', ' ') ?> ₽</span>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="topup_balance" class="btn balance-btn" title="Пополнить баланс на 500 рублей">
                            +500 ₽
                        </button>
                    </form>
                    </p>
                    <?php if (!empty($balance_error)): // Используем переменную ?>
                        <p class="balance-error photo-error"><?= htmlspecialchars($balance_error) ?></p> <?php endif; ?>
                </div>

                <div class="profile__section">
                    <h3 class="profile__section-title">Аккаунт</h3>
                    <p class="profile__meta-item"><strong class="profile__meta-label">Дата регистрации:</strong> <span class="profile__meta-value"><?= !empty($user['reg_date']) && $user['reg_date'] !== '0000-00-00' ? date("d.m.Y H:i", strtotime($user['reg_date'])) : 'Не указана' ?></span></p> </div>
            </div>
        </div>

        <h3 class="profile-content__title">Текущие аренды</h3>
        <?php if (!empty($currentRents)): ?>
            <div class="current-rents bookings">
                <?php foreach ($currentRents as $currentRent): ?>
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
    // Убедитесь, что папка 'uploads' доступна по этому пути из корня сайта
    // и что админка сохраняет фото именно в эту папку.
    const uploadPath = 'uploads/';

    document.getElementById('new_photo').addEventListener('change', function(e) {
        const fileInfo = document.getElementById('file-info');
        const fileName = document.getElementById('file-name');
        const fileSize = document.getElementById('file-size');
        const submitBtn = document.getElementById('submit-btn');

        if (this.files.length > 0) {
            const file = this.files[0];
            fileName.textContent = file.name;

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

    // Показываем ошибку баланса, если есть
    <?php if (!empty($balance_error)): ?>
    alert('<?= htmlspecialchars($balance_error) ?>');
    <?php endif; ?>

</script>

</body>

</html>