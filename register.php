<?php
session_start();
require_once 'db.php'; // Убедитесь, что этот файл существует и настроен

// Включение отчета об ошибках (лучше для разработки)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Если пользователь уже авторизован, перенаправляем в профиль
if (!empty($_SESSION['user'])) {
    header("Location: profile.php");
    exit();
}

// --- Функция генерации CAPTCHA (с исправлением Deprecated) ---
function generateCaptcha() {
    $captchaText = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
    $_SESSION['captcha'] = $captchaText;

    $width = 200;
    $height = 30;
    $image = imagecreatetruecolor($width, $height);

    $bgColor = imagecolorallocate($image, 255, 255, 255);
    $textColor = imagecolorallocate($image, 0, 0, 0);
    $lineColor = imagecolorallocate($image, 200, 200, 200);

    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

    for ($i = 0; $i < 5; $i++) {
        // Координаты для линий должны быть целыми, rand() и % дают целые числа
        imageline($image, 0, rand(0, $height - 1), $width, rand(0, $height - 1), $lineColor);
    }
    for ($i = 0; $i < 50; $i++) {
        imagesetpixel($image, rand(0, $width - 1), rand(0, $height - 1), $lineColor);
    }

    $font = 5; // Встроенный шрифт GD
    // Вычисляем ширину текста
    $textWidth = imagefontwidth($font) * strlen($captchaText);
    // Вычисляем высоту текста
    $textHeight = imagefontheight($font);

    // --- ИСПРАВЛЕНИЕ Deprecated ---
    // Явно преобразуем результат деления (float) в integer перед использованием в imagestring
    $x = (int)(($width - $textWidth) / 2);
    $y = (int)(($height - $textHeight) / 2);
    // --- Конец ИСПРАВЛЕНИЯ ---

    // Убедимся что координаты не отрицательные (на всякий случай)
    $x = max(0, $x);
    $y = max(0, $y);

    imagestring($image, $font, $x, $y, $captchaText, $textColor);

    ob_start();
    imagepng($image);
    $imageData = ob_get_clean();
    imagedestroy($image);

    return 'data:image/png;base64,' . base64_encode($imageData);
}
// --- Конец функции CAPTCHA ---


$errors = [];
$captchaImage= '';

// --- Обработка POST-запроса ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Проверка CAPTCHA
    if (empty($_POST['captcha']) || !isset($_SESSION['captcha']) ||
        strtolower(trim($_POST['captcha'])) !== strtolower($_SESSION['captcha'])) {
        $errors[] = "Неверно введена капча. Пожалуйста, попробуйте еще раз.";
    }

    // 2. Очистка и получение данных
    $fullName = trim(htmlspecialchars($_POST['fullName'] ?? ''));
    $birthdate = $_POST['birthdate'] ?? '';
    $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $license = trim(htmlspecialchars($_POST['license'] ?? ''));
    $password = $_POST['password'] ?? '';
    $photo = $_FILES['photo'] ?? [];

    // 3. Обработка и валидация номера телефона (БЕЗ МАСКИ)
    // Пользователь может ввести что угодно, включая "+7", скобки и т.д.
    $phoneInputRaw = trim($_POST['phone'] ?? '');
    // Убираем всё, кроме цифр
    $phone = preg_replace('/[^0-9]/', '', $phoneInputRaw);

    // Нормализация номера: если 11 цифр и начинается с 7 или 8, убираем первый символ
    if (strlen($phone) === 11 && ($phone[0] === '7' || $phone[0] === '8')) {
        $phone = substr($phone, 1); // Оставляем 10 цифр
    }
    // Теперь $phone должен содержать 10 цифр для валидного номера

    // 4. Проверка на пустые ОБЯЗАТЕЛЬНЫЕ поля
    $requiredFields = [
        'fullName' => 'ФИО',
        'birthdate' => 'Дата рождения',
        'phone' => 'Телефон', // Проверяем исходное значение из POST
        'email' => 'Email',
        'license' => 'Водительское удостоверение',
        'password' => 'Пароль',
        'captcha' => 'Код капчи'
    ];

    foreach ($requiredFields as $field => $name) {
        if (empty($_POST[$field])) {
            $errors[] = "Поле '$name' обязательно для заполнения.";
        }
    }
    // Доп. проверка, что телефон не стал пустым ПОСЛЕ очистки от не-цифр
    if (isset($_POST['phone']) && empty($phone) && !in_array("Поле 'Телефон' обязательно для заполнения.", $errors)) {
        $errors[] = "Некорректный формат введенного телефона.";
    }


    // 5. Дополнительные валидации
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Некорректный формат email.";
    }

    // Валидация Телефона (длина и первая цифра) - после очистки
    if (!empty($phone)) { // Проверяем только если телефон не пустой после очистки
        if (strlen($phone) !== 10) {
            $errors[] = "Некорректный формат номера телефона. Ожидается 10 цифр после +7.";
        } elseif (substr($phone, 0, 1) !== '9') {
            $errors[] = "Номер телефона должен начинаться с 9.";
        }
    }

    // 6. Проверка уникальности (только если нет других ошибок)
    // Используем $phone (10 цифр)
    $canCheckUniqueness = empty(array_filter($errors, function($err) {
        return strpos($err, 'Телефо') !== false || strpos($err, 'email') !== false;
    }));

    if (empty($errors) || $canCheckUniqueness) { // Проверяем если совсем нет ошибок, или хотя бы нет ошибок телефона/email
        try {
            $checkPhone = (strlen($phone) === 10 && substr($phone, 0, 1) === '9');
            $checkEmail = filter_var($email, FILTER_VALIDATE_EMAIL);

            if ($checkPhone || $checkEmail) { // Только если есть что проверять
                $sql = "SELECT phone, email FROM user WHERE";
                $params = [];
                $conditions = [];
                if ($checkPhone) {
                    $conditions[] = "phone = ?";
                    $params[] = $phone;
                }
                if ($checkEmail) {
                    $conditions[] = "email = ?";
                    $params[] = $email;
                }
                $sql .= " " . implode(" OR ", $conditions);

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $existingUser = $stmt->fetch();

                if ($existingUser) {
                    if ($checkPhone && $existingUser['phone'] === $phone) {
                        $errors[] = "Пользователь с таким номером телефона уже зарегистрирован.";
                    }
                    if ($checkEmail && $existingUser['email'] === $email) {
                        $errors[] = "Пользователь с таким email уже зарегистрирован.";
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Database error (check uniqueness): " . $e->getMessage());
            $errors[] = "Произошла ошибка при проверке данных. Пожалуйста, попробуйте позже.";
        }
    }

    // 7. Обработка загрузки фото
    $photoName = null;
    if (!empty($photo['name']) && $photo['error'] === UPLOAD_ERR_OK) {
        // ... (код обработки фото остается таким же) ...
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($photo['type'], $allowedTypes)) {
            $errors[] = "Допустимы только изображения (jpeg, png, gif, webp).";
        } elseif ($photo['size'] > $maxFileSize) {
            $errors[] = "Размер изображения не должен превышать 2MB.";
        } else {
            $ext = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
            $photoName = uniqid('avatar_', true) . '.' . $ext;
            $uploadDir = __DIR__ . "/uploads/";
            $uploadPath = $uploadDir . $photoName;

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0775, true)) {
                    error_log("Failed to create upload directory: " . $uploadDir);
                    $errors[] = "Ошибка сервера при создании папки для загрузки.";
                    $photoName = null; // Сбрасываем имя, если не удалось создать папку
                }
            }

            if ($photoName && !move_uploaded_file($photo['tmp_name'], $uploadPath)) {
                error_log("Failed to move uploaded file: " . $photo['tmp_name'] . " to " . $uploadPath);
                $errors[] = "Не удалось сохранить фото. Попробуйте другое изображение.";
                $photoName = null;
            }
        }
    } elseif ($photo['error'] !== UPLOAD_ERR_NO_FILE && $photo['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Произошла ошибка при загрузке фото (код: {$photo['error']}).";
    }


    // 8. Если ОШИБОК НЕТ - регистрация пользователя
    if (empty($errors)) {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO user (fullName, birthdate, phone, email, license, passwordHash, photo, reg_date)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            // Используем $phone (10 цифр)
            if (!$stmt->execute([$fullName, $birthdate, $phone, $email, $license, $passwordHash, $photoName])) {
                throw new PDOException($stmt->errorInfo()[2] ?? "Ошибка выполнения INSERT запроса");
            }

            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            $pdo->commit();
            unset($_SESSION['captcha']);
            $_SESSION['user'] = $user;
            header("Location: index.php");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error during registration: " . $e->getMessage());
            $errors[] = "Произошла ошибка при регистрации. Пожалуйста, попробуйте позже.";
        }
    }
} // --- Конец обработки POST ---

// Генерируем НОВУЮ капчу для отображения
$captchaImage = generateCaptcha();

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Регистрация | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/register.css">
    <?php /* Ссылка на IMask удалена */ ?>
</head>

<body>

<?php include 'header.php'; ?>

<main class="main">
    <section class="register">
        <h2 class="register__title">Регистрация</h2>

        <?php if (!empty($errors)): ?>
            <ul class="register__errors">
                <?php foreach ($errors as $error): ?>
                    <li class="register__error"><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="register__form" novalidate>
            <div class="register__input-group">
                <label for="fullName" class="register__label">ФИО:</label>
                <input type="text" id="fullName" name="fullName" class="register__input" required
                       value="<?= htmlspecialchars($_POST['fullName'] ?? '') ?>">
            </div>

            <div class="register__input-group">
                <label for="birthdate" class="register__label">Дата рождения:</label>
                <input type="date" id="birthdate" name="birthdate" class="register__input" required
                       value="<?= htmlspecialchars($_POST['birthdate'] ?? '') ?>">
            </div>

            <div class="register__input-group">
                <label for="phone" class="register__label">Телефон:</label>
                <?php // Используем исходное введенное значение, т.к. нет маски ?>
                <input type="tel" id="phone" name="phone" class="register__input" required
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       placeholder="+7 9XX XXX XX XX"> <?php // Плейсхолдер как подсказка ?>
            </div>

            <div class="register__input-group">
                <label for="email" class="register__label">Email:</label>
                <input type="email" id="email" name="email" class="register__input" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="register__input-group">
                <label for="license" class="register__label">Водительское удостоверение:</label>
                <input type="text" id="license" name="license" class="register__input" required
                       value="<?= htmlspecialchars($_POST['license'] ?? '') ?>">
            </div>

            <div class="register__input-group">
                <label for="password" class="register__label">Пароль:</label>
                <input type="password" id="password" name="password" class="register__input" required>
            </div>

            <div class="register__input-group">
                <label for="photo" class="register__label">Фото (опционально, до 2MB):</label>
                <input type="file" id="photo" name="photo" class="register__input" accept="image/jpeg, image/png, image/gif, image/webp">
                <div id="photo-preview" class="photo-preview"></div>
            </div>

            <div class="register__input-group">
                <label for="captcha" class="register__label">Введите код с картинки:</label>
                <div class="captcha-container">
                    <img src="<?= $captchaImage ?>" alt="CAPTCHA" class="captcha-image" id="captcha-img">
                    <button type="button" class="captcha-refresh" onclick="refreshCaptcha()" title="Обновить картинку">⟳</button>
                </div>
                <input type="text" id="captcha" name="captcha" class="register__input" required autocomplete="off">
            </div>

            <button type="submit" class="register__button">Зарегистрироваться</button>
        </form>

        <p class="register__login-link">
            Уже есть аккаунт? <a href="login.php" class="login__link">Войти</a>
        </p>
    </section>
</main>

<?php include 'footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Предпросмотр имени файла фото ---
        const photoInput = document.getElementById('photo');
        const photoPreview = document.getElementById('photo-preview');
        if (photoInput && photoPreview) {
            photoInput.addEventListener('change', function() {
                photoPreview.innerHTML = '';
                if (this.files && this.files[0]) {
                    photoPreview.textContent = `Выбран файл: ${this.files[0].name}`;
                }
            });
        }
        // --- Код для IMask удален ---
    }); // Конец DOMContentLoaded

    // --- Функция обновления CAPTCHA (остается) ---
    function refreshCaptcha() {
        fetch('refresh_captcha.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const captchaImg = document.getElementById('captcha-img');
                if (captchaImg && data.image) {
                    captchaImg.src = data.image;
                }
                const captchaInput = document.getElementById('captcha');
                if (captchaInput) {
                    captchaInput.value = '';
                }
            })
            .catch(error => {
                console.error('Error refreshing captcha:', error);
                alert('Не удалось обновить картинку капчи. Пожалуйста, попробуйте позже.');
            });
    }
</script>

</body>
</html>