<?php
session_start();
require_once 'db.php';

// Настройка отображения ошибок (только для разработки)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Если пользователь уже авторизован - перенаправляем
if (!empty($_SESSION['user'])) {
    header("Location: profile.php");
    exit();
}

// Инициализация переменных
$fieldErrors = [
    'fullName' => '',
    'birthdate' => '',
    'phone' => '',
    'email' => '',
    'license' => '',
    'password' => '',
    'photo' => '',
    'captcha' => '' // Ошибка для поля CAPTCHA
];
$inputValues = [
    'fullName' => '',
    'birthdate' => '',
    'phone' => '',
    'email' => '',
    'license' => '',
    'password' => ''
];
$generalErrors = [];
// CAPTCHA изображение теперь будет загружаться отдельным скриптом captcha.php
// $captchaImage = generateCaptcha(); // Эту строку больше не нужно вызывать здесь

// Обработка POST-запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Проверка CSRF-токена (если нужно)
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     $generalErrors[] = "Ошибка безопасности. Пожалуйста, попробуйте еще раз.";
    // }

    // 2. Проверка CAPTCHA
    // Проверяем, что сессия с CAPTCHA существует и коды совпадают (регистронезависимо, без лишних пробелов)
    // ИСПРАВЛЕНО: $_SESSION['SESSION']['captcha'] на $_SESSION['captcha']
    if (empty($_POST['captcha']) || !isset($_SESSION['captcha']) ||
        strtolower(trim($_POST['captcha'])) !== strtolower($_SESSION['captcha'])) {
        $fieldErrors['captcha'] = "Неверно введен код CAPTCHA. Пожалуйста, попробуйте еще раз.";
        // Важно! После неудачной попытки CAPTCHA, сбрасываем сессионную переменную,
        // чтобы старый код нельзя было использовать повторно.
        unset($_SESSION['captcha']);
    } else {
        // Если CAPTCHA верна, очищаем ее из сессии, чтобы нельзя было использовать повторно
        unset($_SESSION['captcha']);
    }


    // 3. Очистка и валидация данных
    $inputValues['fullName'] = trim(htmlspecialchars($_POST['fullName'] ?? ''));
    $inputValues['birthdate'] = $_POST['birthdate'] ?? '';
    $inputValues['email'] = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $inputValues['license'] = trim(htmlspecialchars($_POST['license'] ?? ''));
    $inputValues['password'] = $_POST['password'] ?? '';
    $photo = $_FILES['photo'] ?? [];

    // Валидация ФИО - только буквы, пробелы и дефисы
    if (!empty($inputValues['fullName'])) {
        if (!preg_match('/^[\p{Cyrillic}\p{L}\s\-]+$/u', $inputValues['fullName'])) {
            $fieldErrors['fullName'] = "ФИО может содержать только буквы, пробелы и дефисы.";
        }
    }

    // Обработка телефона
    $phoneInput = trim($_POST['phone'] ?? '');
    $phoneDigits = preg_replace('/[^0-9]/', '', $phoneInput);

    // Валидация телефона - только цифры
    // Валидация телефона - только цифры и символы +-()
    if (!empty($phoneInput) && !preg_match('/^[\d\s\-\(\)\+]+$/', $phoneInput)) {
        $fieldErrors['phone'] = "Номер телефона может содержать только цифры и символы +-()";
    }


    // Нормализация номера (для России)
    // Проверяем, начинается ли с 7, 8, или +7 и имеет ли 11 цифр
    if (preg_match('/^(\+?7|8)\d{10}$/', $phoneDigits)) {
        // Удаляем +7, 8 или 7 в начале, оставляем только 10 цифр после кода страны
        $phoneDigits = substr($phoneDigits, -10);
    } else {
        // Если формат не соответствует российскому 11-значному или 10-значному после кода
        // Дополнительная проверка на 10-значный номер, если не начинается с кода страны
        if (strlen($phoneDigits) === 10 && $phoneDigits[0] === '9') {
            // Оставляем как есть, если уже 10 цифр и начинается с 9
        } else {
            $fieldErrors['phone'] = "Некорректный формат номера телефона.";
        }
    }
    $inputValues['phone'] = $phoneDigits; // Сохраняем нормализованный или исходный номер для валидации

    // Валидация водительского удостоверения - только буквы и цифры
    if (!empty($inputValues['license'])) {
        if (!preg_match('/^[A-Za-z0-9]+$/', $inputValues['license'])) {
            $fieldErrors['license'] = "Водительское удостоверение может содержать только латинские буквы и цифры.";
        }
    }

    // 4. Проверка обязательных полей (теперь учитывает потенциальные ошибки нормализации телефона)
    $requiredFields = [
        'fullName' => 'ФИО',
        'birthdate' => 'Дата рождения',
        'phone' => 'Телефон',
        'email' => 'Email',
        'license' => 'Водительское удостоверение',
        'password' => 'Пароль'
    ];

    foreach ($requiredFields as $field => $name) {
        // Проверяем на пустоту, но только если еще нет специфической ошибки для этого поля
        if (empty($inputValues[$field]) && empty($fieldErrors[$field])) {
            $fieldErrors[$field] = "Поле '$name' обязательно для заполнения.";
        }
    }


    // 5. Дополнительные валидации
    if (!empty($inputValues['email']) && !filter_var($inputValues['email'], FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = "Некорректный формат email.";
    }

    // Проверка номера телефона только после нормализации/очистки
    if (!empty($inputValues['phone']) && (strlen($inputValues['phone']) !== 10 || $inputValues['phone'][0] !== '9') && empty($fieldErrors['phone'])) {
        $fieldErrors['phone'] = "Номер телефона должен содержать 10 цифр и начинаться с 9.";
    }


    if (!empty($inputValues['password']) && strlen($inputValues['password']) < 8) {
        $fieldErrors['password'] = "Пароль должен содержать минимум 8 символов.";
    }

    // 6. Проверка уникальности email и телефона - выполняем только если нет других ошибок валидации
    if (empty(array_filter($fieldErrors)) && empty($generalErrors)) { // Проверяем только fieldErrors
        try {
            // Проверяем и email, и phone в одном запросе
            $stmt = $pdo->prepare("SELECT id, email, phone, birthdate, phone, license, balance FROM user WHERE email = ? OR phone = ? LIMIT 1");
            $stmt->execute([$inputValues['email'], $inputValues['phone']]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC); // Получаем ассоциативный массив

            if ($existingUser) {
                if ($existingUser['email'] === $inputValues['email']) {
                    $fieldErrors['email'] = "Пользователь с таким email уже зарегистрирован.";
                }
                // Проверяем нормализованный номер телефона
                if ($existingUser['phone'] === $inputValues['phone']) {
                    $fieldErrors['phone'] = "Пользователь с таким номером телефона уже зарегистрирован.";
                }
            }
        } catch (PDOException $e) {
            error_log("Database error (uniqueness check): " . $e->getMessage());
            $generalErrors[] = "Произошла ошибка при проверке данных. Пожалуйста, попробуйте позже.";
        }
    }


    // 7. Обработка загрузки фото - выполняем только если нет других ошибок валидации, кроме фото
    // Не обрабатываем фото, если есть ошибки в других обязательных полях
    $photoName = null;
    if (empty(array_filter(array_diff_key($fieldErrors, array_flip(['photo'])))) && empty($generalErrors)) { // Проверяем все ошибки, кроме фото
        if (!empty($photo['name']) && $photo['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $maxFileSize = 2 * 1024 * 1024; // 2MB

            if (!array_key_exists($photo['type'], $allowedTypes)) {
                $fieldErrors['photo'] = "Допустимы только изображения в форматах: JPEG, PNG, GIF или WebP.";
            } elseif ($photo['size'] > $maxFileSize) {
                $fieldErrors['photo'] = "Размер изображения не должен превышать 2MB.";
            } else {
                $photoName = uniqid('avatar_', true) . '.' . $allowedTypes[$photo['type']];
                $uploadDir = __DIR__ . '/uploads/';

                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    error_log("Failed to create upload directory: " . $uploadDir);
                    $fieldErrors['photo'] = "Ошибка сервера при создании папки для загрузки.";
                    $photoName = null;
                } elseif (!move_uploaded_file($photo['tmp_name'], $uploadDir . $photoName)) {
                    error_log("Failed to move uploaded file: " . $photo['tmp_name']);
                    $fieldErrors['photo'] = "Не удалось сохранить фото. Пожалуйста, попробуйте еще раз.";
                    $photoName = null;
                }
            }
        } elseif ($photo['error'] !== UPLOAD_ERR_NO_FILE && $photo['error'] !== UPLOAD_ERR_OK) {
            // Обрабатываем другие ошибки загрузки, кроме отсутствия файла
            $phpFileUploadErrors = array(
                UPLOAD_ERR_INI_SIZE => 'Размер принятого файла превысил максимально допустимый размер, указанный в php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'Размер загружаемого файла превысил значение MAX_FILE_SIZE в HTML-форме.',
                UPLOAD_ERR_PARTIAL => 'Загружаемый файл был получен только частично.',
                UPLOAD_ERR_NO_FILE => 'Файл не был загружен.', // Этот случай мы уже обрабатываем выше
                UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка.',
                UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
                UPLOAD_ERR_EXTENSION => 'PHP-расширение остановило загрузку файла.',
            );
            $fieldErrors['photo'] = $phpFileUploadErrors[$photo['error']] ?? "Ошибка при загрузке фото (код: {$photo['error']}).";
        }
    }


    // 8. Регистрация пользователя (если нет ошибок)
    if (empty($generalErrors) && empty(array_filter($fieldErrors))) {
        try {
            $passwordHash = password_hash($inputValues['password'], PASSWORD_DEFAULT);
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO user (fullName, birthdate, phone, email, license, passwordHash, photo, reg_date)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $success = $stmt->execute([
                $inputValues['fullName'],
                $inputValues['birthdate'],
                $inputValues['phone'],
                $inputValues['email'],
                $inputValues['license'],
                $passwordHash,
                $photoName
            ]);

            if (!$success) {
                throw new PDOException($stmt->errorInfo()[2] ?? "Ошибка выполнения запроса INSERT");
            }

            $userId = $pdo->lastInsertId();
            // После успешной регистрации получаем данные пользователя для сохранения в сессию
            $stmt = $pdo->prepare("SELECT id, fullName, email, photo FROM user WHERE id = ?"); // Выбирайте только необходимые данные
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC); // Получаем ассоциативный массив

            if ($user) {
                $pdo->commit();
                // unset($_SESSION['captcha']); // CAPTCHA уже сброшена при успешной проверке или неудачной попытке выше
                $_SESSION['user'] = $user; // Сохраняем данные пользователя в сессию
                header("Location: profile.php");
                exit();
            } else {
                // Если пользователя по id не нашли после вставки (очень маловероятно)
                $pdo->rollBack();
                $generalErrors[] = "Ошибка получения данных пользователя после регистрации.";
                // Удаляем загруженное фото, если регистрация не удалась
                if ($photoName && file_exists(__DIR__ . '/uploads/' . $photoName)) {
                    unlink(__DIR__ . '/uploads/' . $photoName);
                }
            }


        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error during registration: " . $e->getMessage());
            $generalErrors[] = "Произошла ошибка при регистрации. Пожалуйста, попробуйте позже.";

            // Удаляем загруженное фото, если регистрация не удалась
            if ($photoName && file_exists(__DIR__ . '/uploads/' . $photoName)) {
                unlink(__DIR__ . '/uploads/' . $photoName);
            }
        }
    }

}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
<?php include 'header.php'; ?>

<main class="main">
    <section class="register">
        <h2 class="register__title">Регистрация</h2>

        <?php if (!empty($generalErrors)): ?>
            <ul class="register__errors">
                <?php foreach ($generalErrors as $error): ?>
                    <li class="register__error"><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="register__form" novalidate>
            <div class="register__input-group full-width">
                <label for="fullName" class="register__label">ФИО:</label>
                <input type="text" id="fullName" name="fullName" class="register__input <?= !empty($fieldErrors['fullName']) ? 'invalid' : '' ?>" required
                       value="<?= htmlspecialchars($inputValues['fullName']) ?>">
                <?php if (!empty($fieldErrors['fullName'])): ?>
                    <div class="field-error"><?= htmlspecialchars($fieldErrors['fullName']) ?></div>
                <?php endif; ?>
            </div>

            <div class="register__input-group">
                <label for="birthdate" class="register__label">Дата рождения:</label>
                <input type="date" id="birthdate" name="birthdate" class="register__input <?= !empty($fieldErrors['birthdate']) ? 'invalid' : '' ?>" required
                       value="<?= htmlspecialchars($inputValues['birthdate']) ?>">
                <?php if (!empty($fieldErrors['birthdate'])): ?>
                    <div class="field-error"><?= htmlspecialchars($fieldErrors['birthdate']) ?></div>
                <?php endif; ?>
            </div>

            <div class="register__input-group">
                <label for="phone" class="register__label">Телефон (только цифры):</label>
                <input type="tel" id="phone" name="phone" class="register__input <?= !empty($fieldErrors['phone']) ? 'invalid' : '' ?>" required
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       placeholder="Пример: 9123456789">
                <?php if (!empty($fieldErrors['phone'])): ?>
                    <div class="field-error"><?= htmlspecialchars($fieldErrors['phone']) ?></div>
                <?php endif; ?>
            </div>

            <div class="register__input-group">
                <label for="email" class="register__label">Email:</label>
                <input type="email" id="email" name="email" class="register__input <?= !empty($fieldErrors['email']) ? 'invalid' : '' ?>" required
                       value="<?= htmlspecialchars($inputValues['email']) ?>">
                <?php if (!empty($fieldErrors['email'])): ?>
                    <div class="field-error"><?= htmlspecialchars($fieldErrors['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="register__input-group">
                <label for="license" class="register__label">Водительское удостоверение:</label>
                <input type="text" id="license" name="license" class="register__input <?= !empty($fieldErrors['license']) ? 'invalid' : '' ?>" required
                       value="<?= htmlspecialchars($inputValues['license']) ?>">
                <?php if (!empty($fieldErrors['license'])): ?>
                    <div class="field-error"><?= htmlspecialchars($fieldErrors['license']) ?></div>
                <?php endif; ?>
            </div>

            <div class="register__input-group">
                <label for="password" class="register__label">Пароль (минимум 8 символов):</label>
                <input type="password" id="password" name="password" class="register__input <?= !empty($fieldErrors['password']) ? 'invalid' : '' ?>" required
                       minlength="8">
                <?php if (!empty($fieldErrors['password'])): ?>
                    <div class="field-error"><?= htmlspecialchars($fieldErrors['password']) ?></div>
                <?php endif; ?>
            </div>

            <div class="register__input-group full-width">
                <label for="photo" class="register__label">Фото (опционально, до 2MB):</label>
                <input type="file" id="photo" name="photo" class="register__input <?= !empty($fieldErrors['photo']) ? 'invalid' : '' ?>"
                       accept="image/jpeg, image/png, image/gif, image/webp">
                <?php if (!empty($fieldErrors['photo'])): ?>
                    <div class="field-error"><?= htmlspecialchars($fieldErrors['photo']) ?></div>
                <?php endif; ?>
                <div id="photo-preview" class="photo-preview"></div>
            </div>
            <div class="register__input-group full-width">
                <label for="captcha" class="register__label">Введите код с картинки:</label>
                <div class="captcha-container">
                    <img src="captcha.php" alt="CAPTCHA" class="captcha-image" id="captcha-img">
                    <button type="button" class="captcha-refresh" onclick="refreshCaptcha()"
                            title="Обновить картинку">⟳</button>
                </div>
                <input type="text" id="captcha" name="captcha" class="register__input <?= !empty($fieldErrors['captcha']) ? 'invalid' : '' ?>" required
                       autocomplete="off" placeholder="Введите код" value="<?= htmlspecialchars($_POST['captcha'] ?? '') ?>">
                <?php if (!empty($fieldErrors['captcha'])): ?>
                    <div class="field-error"><?= htmlspecialchars($fieldErrors['captcha']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="register__button">Зарегистрироваться</button>

            <p class="register__login-link">
                Уже есть аккаунт? <a href="login.php" class="login__link">Войти</a>
            </p>
        </form>
    </section>
</main>

<?php include 'footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Предпросмотр имени файла фото
        const photoInput = document.getElementById('photo');
        const photoPreview = document.getElementById('photo-preview');

        if (photoInput && photoPreview) {
            photoInput.addEventListener('change', function() {
                // Очищаем предыдущий предпросмотр
                photoPreview.innerHTML = '';

                if (this.files && this.files[0]) {
                    // Проверка размера файла на клиенте
                    const maxFileSize = 2 * 1024 * 1024; // 2MB
                    if (this.files[0].size > maxFileSize) {
                        //alert('Файл слишком большой. Максимальный размер - 2MB.'); // Можно заменить на показ ошибки под полем
                        photoPreview.innerHTML = '<div class="field-error">Размер изображения не должен превышать 2MB.</div>';
                        this.value = ''; // Сбрасываем выбор файла
                        return;
                    }

                    // Проверка типа файла на клиенте (более строгая)
                    const allowedTypesRegex = /^image\/(jpeg|png|gif|webp)$/i;
                    if (!allowedTypesRegex.test(this.files[0].type)) {
                        photoPreview.innerHTML = '<div class="field-error">Допустимы только изображения в форматах: JPEG, PNG, GIF или WebP.</div>';
                        this.value = ''; // Сбрасываем выбор файла
                        return;
                    }


                    // Показываем имя файла
                    const fileNameSpan = document.createElement('span');
                    fileNameSpan.textContent = `Выбран файл: ${this.files[0].name}`;
                    photoPreview.appendChild(fileNameSpan);


                    // Если нужно показать превью изображения
                    if (this.files[0].type.match('image.*')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.style.maxWidth = '100px'; // Ограничиваем размер превью
                            img.style.maxHeight = '100px';
                            img.style.marginTop = '10px';
                            photoPreview.appendChild(img);
                        };
                        reader.readAsDataURL(this.files[0]);
                    }
                }
            });
        }
    });

    // Обновление CAPTCHA
    function refreshCaptcha() {
        const captchaImage = document.getElementById('captcha-img');
        // Добавляем временную метку к URL изображения, чтобы браузер не брал его из кеша
        captchaImage.src = 'captcha.php?' + new Date().getTime();
        // Очищаем поле ввода CAPTCHA для новой попытки
        document.getElementById('captcha').value = '';
    }
</script>
</body>
</html>