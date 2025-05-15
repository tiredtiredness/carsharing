<?php
// admin/cars.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection (one level up)
require '../db.php';

// Define the directory for storing car photos
$photo_upload_dir = __DIR__ . '/../cars/'; // One level up from admin folder

// Ensure upload directory exists and is writable
if (!is_dir($photo_upload_dir)) {
    // Attempt to create the directory; in production, handle errors more gracefully
    if (!mkdir($photo_upload_dir, 0775, true)) { // Use 0775 or less permissive in production
        // Log error and potentially stop execution or show message
        $error = "Ошибка: Не удалось создать директорию для фото " . htmlspecialchars($photo_upload_dir);
        // You might want to exit here or disable photo upload functionality
    }
}

// Function to handle file upload
// Added $max_size parameter for more flexibility
function handleFileUpload($file_input_name, $upload_dir, $max_size, &$error) {
    // Check if the specific file input exists and a file was uploaded without errors
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES[$file_input_name]['tmp_name'];
        $file_name = $_FILES[$file_input_name]['name'];
        $file_size = $_FILES[$file_input_name]['size'];
        $file_type = mime_content_type($file_tmp_path); // Get MIME type

        // Basic validation
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];


        if (!in_array($file_type, $allowed_types)) {
            $error = "Разрешены только файлы JPG, PNG, GIF.";
            return false; // Indicate failure
        }

        if ($file_size > $max_size) {
            $error = "Размер файла не должен превышать " . ($max_size / 1024 / 1024) . "MB.";
            return false; // Indicate failure
        }

        // Generate a unique filename to prevent conflicts
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        // Using microtime and crc32 for higher uniqueness chance than uniqid
        $new_file_name = hash('crc32', microtime() . $file_name) . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
        $dest_path = $upload_dir . $new_file_name;

        // Move the file
        if (move_uploaded_file($file_tmp_path, $dest_path)) {
            return $new_file_name; // Return the new filename on success
        } else {
            $error = "Ошибка при сохранении загруженного файла.";
            return false; // Indicate failure
        }
    } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors
        switch ($_FILES[$file_input_name]['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = 'Загруженный файл превышает максимально допустимый размер загрузки.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error = 'Загруженный файл был загружен только частично.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error = 'Отсутствует временная папка для загрузки.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error = 'Ошибка записи файла на диск.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error = 'Расширение PHP остановило загрузку файла.';
                break;
            default:
                $error = 'Неизвестная ошибка загрузки файла.';
                break;
        }
        return false; // Indicate failure
    }
    // No file uploaded for this input or input not present
    return null; // Indicate no file operation needed
}

// Define max photo size (e.g., 5MB)
$max_photo_size = 5 * 1024 * 1024;

// Variable to store success messages
$success_message = '';

// --- CRUD Operations ---

// Handle BULK Actions (This form is submitted when bulk action buttons are clicked)
// Note: Bulk action buttons and checkboxes will have form="bulkActionsForm"
if (isset($_POST['bulk_action'])) {
    $selected_ids = $_POST['selected_cars'] ?? []; // Get selected IDs from checkboxes associated via form attribute
    $bulk_action = $_POST['bulk_action'];

    // Ensure selected_cars is an array, even if empty
    if (!is_array($selected_ids)) {
        $selected_ids = [];
    }

    // Sanitize IDs to ensure they are integers
    $selected_ids = array_map('intval', $selected_ids);
    // Filter out any IDs that might have become 0 or invalid after intval
    $selected_ids = array_filter($selected_ids, function($id) { return $id > 0; });


    if (empty($selected_ids)) {
        $error = "Не выбрано ни одного автомобиля для выполнения действия.";
    } else {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));

        try {
            switch ($bulk_action) {
                case 'delete':
                    // For deletion, we need to get photo filenames first before deleting
                    $stmt_photos = $pdo->prepare("SELECT id, photo FROM car WHERE id IN ($placeholders)");
                    $stmt_photos->execute($selected_ids);
                    $cars_to_delete = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);

                    // Delete records from database
                    $pdo->beginTransaction();
                    $stmt_delete = $pdo->prepare("DELETE FROM car WHERE id IN ($placeholders)");
                    $stmt_delete->execute($selected_ids);

                    // Delete corresponding photo files
                    foreach ($cars_to_delete as $car) {
                        if ($car['photo']) {
                            $photo_path = $photo_upload_dir . $car['photo'];
                            if (file_exists($photo_path)) {
                                @unlink($photo_path);
                            }
                        }
                    }
                    $pdo->commit();
                    $success_message = "Выбранные автомобили (" . count($selected_ids) . ") успешно удалены.";
                    break;

                case 'update_rate':
                    $new_rate = trim($_POST['bulk_rate'] ?? '');
                    if ($new_rate === '' || !is_numeric($new_rate) || $new_rate < 0) {
                        $error = "Укажите корректный тариф для обновления.";
                    } else {
                        $pdo->beginTransaction();
                        $stmt_update_rate = $pdo->prepare("UPDATE car SET rate = ? WHERE id IN ($placeholders)");
                        $params = array_merge([$new_rate], $selected_ids);
                        $stmt_update_rate->execute($params);
                        $pdo->commit();
                        $success_message = "Тариф выбранных автомобилей (" . count($selected_ids) . ") успешно обновлен на " . htmlspecialchars($new_rate) . ".";
                    }
                    break;

                case 'update_status':
                    $new_status = trim($_POST['bulk_status'] ?? '');
                    $statuses = ["доступна", "ремонт", "арендована"];
                    if (!in_array($new_status, $statuses)) {
                        $error = "Выберите корректный статус для обновления.";
                    } else {
                        $pdo->beginTransaction();
                        $stmt_update_status = $pdo->prepare("UPDATE car SET status = ? WHERE id IN ($placeholders)");
                        $params = array_merge([$new_status], $selected_ids);
                        $stmt_update_status->execute($params);
                        $pdo->commit();
                        $success_message = "Статус выбранных автомобилей (" . count($selected_ids) . ") успешно изменен на '" . htmlspecialchars($new_status) . "'.";
                    }
                    break;

                default:
                    $error = "Неизвестное массовое действие.";
                    break;
            }

            // Redirect only if no validation error occurred within the switch
            if (!isset($error)) {
                $_SESSION['success_message'] = $success_message;
                header("Location: cars.php");
                exit();
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Ошибка базы данных при выполнении массового действия: " . $e->getMessage();
            // error_log(...)
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Произошла ошибка: " . $e->getMessage();
            // error_log(...)
        }
    }
}


// Handle SINGLE DELETE (keep existing single delete for convenience)
if (isset($_GET['delete'])) { // This is a GET request, so it's always a single delete
    $id_to_delete = $_GET['delete'];
    // Sanitize ID
    $id_to_delete = intval($id_to_delete);

    if ($id_to_delete > 0) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT photo FROM car WHERE id = ?");
            $stmt->execute([$id_to_delete]);
            $car_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("DELETE FROM car WHERE id = ?");
            $stmt->execute([$id_to_delete]);
            $pdo->commit();

            if ($car_to_delete && $car_to_delete['photo']) {
                $photo_path = $photo_upload_dir . $car_to_delete['photo'];
                if (file_exists($photo_path)) {
                    @unlink($photo_path);
                }
            }

            $_SESSION['success_message'] = "Автомобиль ID: " . htmlspecialchars($id_to_delete) . " успешно удален.";
            header("Location: cars.php");
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Ошибка при удалении автомобиля: " . $e->getMessage();
        }
    } else {
        $error = "Некорректный ID автомобиля для удаления.";
    }
}

// Handle SINGLE ADD (This form is submitted by the separate add form)
if (isset($_POST['add'])) {
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $number = trim($_POST['number'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $rate = trim($_POST['rate'] ?? '');

    if (empty($brand) || empty($model) || empty($number) || empty($status) || $rate === '' || !is_numeric($rate) || $rate < 0) {
        $error = "Пожалуйста, заполните все обязательные поля для добавления (включая корректный тариф).";
    } else {
        $statuses = ["доступна", "ремонт", "арендована"];
        if (!in_array($status, $statuses)) {
            $error = "Выбран некорректный статус для добавления.";
        } else {
            $new_photo_filename = handleFileUpload('photo', $photo_upload_dir, $max_photo_size, $error);

            if ($new_photo_filename === false) {
                // Error message is already set
            } else {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO car (brand, model, number, status, rate, photo) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$brand, $model, $number, $status, $rate, $new_photo_filename]);
                    $pdo->commit();

                    $_SESSION['success_message'] = "Новый автомобиль '" . htmlspecialchars($brand) . " " . htmlspecialchars($model) . "' успешно добавлен.";
                    header("Location: cars.php");
                    exit();
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if ($new_photo_filename && file_exists($photo_upload_dir . $new_photo_filename)) {
                        @unlink($photo_upload_dir . $new_photo_filename);
                    }
                    $error = "Ошибка при добавлении автомобиля: " . $e->getMessage();
                }
            }
        }
    }
}


// Handle SINGLE UPDATE (This form is submitted by the separate edit row form)
if (isset($_POST['update'])) {
    $id_to_update = $_POST['id'] ?? 0;
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $number = trim($_POST['number'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $rate = trim($_POST['rate'] ?? '');

    $id_to_update = intval($id_to_update); // Sanitize ID

    if ($id_to_update <= 0 || empty($brand) || empty($model) || empty($number) || empty($status) || $rate === '' || !is_numeric($rate) || $rate < 0) {
        $error = "Пожалуйста, заполните все обязательные поля для обновления (включая корректный ID и тариф).";
    } else {
        $statuses = ["доступна", "ремонт", "арендована"];
        if (!in_array($status, $statuses)) {
            $error = "Выбран некорректный статус для обновления.";
        } else {

            $uploaded_new_photo_filename = handleFileUpload('photo_new', $photo_upload_dir, $max_photo_size, $error);

            if ($uploaded_new_photo_filename === false) {
                // Error message is already set
            } else {
                try {
                    $pdo->beginTransaction();
                    $sql = "UPDATE car SET brand = ?, model = ?, number = ?, status = ?, rate = ?";
                    $params = [$brand, $model, $number, $status, $rate];

                    $old_photo_filename = null;
                    if ($uploaded_new_photo_filename !== null) {
                        $stmt_old_photo = $pdo->prepare("SELECT photo FROM car WHERE id = ?");
                        $stmt_old_photo->execute([$id_to_update]);
                        $old_photo_result = $stmt_old_photo->fetch(PDO::FETCH_ASSOC);
                        if ($old_photo_result) {
                            $old_photo_filename = $old_photo_result['photo'];
                        }

                        $sql .= ", photo = ?";
                        $params[] = $uploaded_new_photo_filename;
                    }

                    $sql .= " WHERE id = ?";
                    $params[] = $id_to_update;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $pdo->commit();

                    if ($uploaded_new_photo_filename !== null && $old_photo_filename && $old_photo_filename !== $uploaded_new_photo_filename) {
                        $old_photo_path = $photo_upload_dir . $old_photo_filename;
                        if (file_exists($old_photo_path)) {
                            @unlink($old_photo_path);
                        }
                    }

                    $_SESSION['success_message'] = "Данные автомобиля ID: " . htmlspecialchars($id_to_update) . " успешно обновлены.";
                    header("Location: cars.php");
                    exit();
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if ($uploaded_new_photo_filename && file_exists($photo_upload_dir . $uploaded_new_photo_filename)) {
                        @unlink($photo_upload_dir . $uploaded_new_photo_filename);
                    }
                    $error = "Ошибка при обновлении автомобиля: " . $e->getMessage();
                }
            }
        }
    }
}


// --- Handle messages after redirect ---
// Success messages are stored in session after redirect
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the session variable
}
// Error messages are set directly if they occur on the current page load


// --- Data Fetching for Display ---

// Get car data
$sql = "SELECT
            id,
            brand,
            model,
            number,
            status,
            rate,
            photo
        FROM
            car
        ORDER BY
            id";

try {
    $stmt = $pdo->query($sql);
    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Ошибка при загрузке данных автомобилей: " . $e->getMessage();
    $cars = []; // Ensure $cars is an empty array on error
}

// Get the ID of the car being edited from the URL
$editCarId = $_GET['edit'] ?? null;

// List of possible statuses
$statuses = ["доступна", "ремонт", "арендована"]; // Match these to your DB constraints if any
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление автомобилями</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: auto; }
        h1 { text-align: center; margin-bottom: 20px; }
        .header-links { text-align: right; margin-bottom: 20px; display: flex }
        .header-links a { margin-left: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea { width: 100%; padding: 5px; box-sizing: border-box; }
        input[type="file"] { width: auto; display: block; margin-top: 5px;}
        .controls { display: flex; gap: 5px; }
        .controls a, .controls button { text-decoration: none; padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; font-size: 1em; }
        .controls button[type="submit"] { background-color: #5cb85c; color: white; border-color: #4cae4c;}
        .controls button[type="submit"]:hover { background-color: #4cae4c; }
        .controls a { color: #333; }
        .controls a:hover { background-color: #f0f0f0; }
        .controls a[title="Отмена"] { background-color: #d9534f; color: white; border-color: #d43f3a;}
        .controls a[title="Отмена"]:hover { background-color: #c9302c; }
        .error { color: red; margin-top: 10px; text-align: center; }
        .success { color: green; margin-top: 10px; text-align: center; } /* Success message style */
        .car-photo { max-width: 80px; max-height: 60px; height: auto; display: block; margin: 0 auto 5px auto; border: 1px solid #ccc; padding: 2px;}
        .photo-cell-content { min-width: 90px; text-align: center; }
        .photo-cell-content label { font-weight: normal; display: block; margin-bottom: 3px; }

        /* Styles for Bulk Actions Section */
        .bulk-actions { margin-top: 20px; padding: 15px; border: 1px solid #ccc; background-color: #f9f9f9; border-radius: 5pt; }
        .bulk-actions h3 { margin-top: 0; }
        .bulk-actions > div { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #eee; }
        .bulk-actions > div:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .bulk-actions label { margin-right: 10px; font-weight: bold;}
        .bulk-actions button { margin-left: 10px; padding: 5px 10px; cursor: pointer; border-radius: 4px; }
        .bulk-actions button[value="delete"] { background-color: #d9534f; color: white; border: 1px solid #d43f3a; }
        .bulk-actions button[value="delete"]:hover { background-color: #c9302c; }
        .bulk-actions button[value="update_rate"], .bulk-actions button[value="update_status"] { background-color: #0275d8; color: white; border: 1px solid #025aa5; }
        .bulk-actions button[value="update_rate"]:hover, .bulk-actions button[value="update_status"]:hover { background-color: #025aa5; }
        .bulk-actions input[type="number"], .bulk-actions select { padding: 5px; border: 1px solid #ccc; border-radius: 4px; }

        /* Style for the separate add row form/table */
        .add-row-form-table { margin-top: 10px; width: 100%; border-collapse: collapse; }
        .add-row-form-table td { border: 1px solid #ddd; padding: 8px; vertical-align: top;}
        .add-row-form-table input[type="text"],
        .add-row-form-table input[type="number"],
        .add-row-form-table select {
            width: 100%;
            padding: 5px;
            box-sizing: border-box;
        }
        .add-row-form-table .controls { display: flex; gap: 5px; }
        .add-row-form-table button[type="submit"] {
            padding: 5px 10px;
            background-color: #5cb85c;
            color: white;
            border: 1px solid #4cae4c;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }
        .add-row-form-table button[type="submit"]:hover { background-color: #4cae4c; }


    </style>
</head>
<body>
<div class="container">
    <div class="header-links">
        <a style="flex-grow: 1; text-align: left" href="index.php">Назад</a>
        <a href="../index.php">На сайт</a>
        <a href="logout.php">Выход</a>
    </div>

    <h1>Управление автомобилями</h1>

    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post" action="cars.php" id="mainDisplayForm">
        <table border="1">
            <thead>
            <tr>
                <th style="width: 30px;"><input type="checkbox" id="select_all_cars"></th> <th>ID</th>
                <th>Марка</th>
                <th>Модель</th>
                <th>Номер</th>
                <th>Статус</th>
                <th>Тариф (руб/мин)</th>
                <th>Фото</th>
                <th style="width: 100px;">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($cars as $car): ?>
                <?php if ($editCarId == $car['id']): ?>
                    <tr>
                        <td></td>
                        <form method="post" action="cars.php" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?= $car['id'] ?>">
                            <td><?= $car['id'] ?></td>
                            <td><input type="text" name="brand" value="<?= htmlspecialchars($car['brand']) ?>" required></td>
                            <td><input type="text" name="model" value="<?= htmlspecialchars($car['model']) ?>" required></td>
                            <td><input type="text" name="number" value="<?= htmlspecialchars($car['number']) ?>" required></td>
                            <td>
                                <select name="status" required>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= $status ?>" <?= ($car['status'] == $status) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($status) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" name="rate" value="<?= $car['rate'] ?>" required></td>
                            <td class="photo-cell-content">
                                <?php if ($car['photo']): ?>
                                    <img src="../cars/<?= htmlspecialchars($car['photo']) ?>" alt="Фото <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>" class="car-photo">
                                    Текущее<br>
                                <?php endif; ?>
                                <label for="photo_new_<?= $car['id'] ?>">Заменить:</label>
                                <input type="file" name="photo_new" id="photo_new_<?= $car['id'] ?>">
                            </td>
                            <td>
                                <div class="controls">
                                    <button type="submit" name="update" title="Сохранить">💾</button>
                                    <a href="cars.php" title="Отмена">❌</a>
                                </div>
                            </td>
                        </form>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td><input type="checkbox" name="selected_cars[]" value="<?= $car['id'] ?>" form="bulkActionsForm"></td>
                        <td><?= $car['id'] ?></td>
                        <td><?= htmlspecialchars($car['brand']) ?></td>
                        <td><?= htmlspecialchars($car['model']) ?></td>
                        <td><?= htmlspecialchars($car['number']) ?></td>
                        <td><?= htmlspecialchars($car['status']) ?></td>
                        <td><?= number_format($car['rate'], 2) ?></td>
                        <td class="photo-cell-content">
                            <?php if ($car['photo']): ?>
                                <img src="../cars/<?= htmlspecialchars($car['photo']) ?>" alt="Фото <?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>" class="car-photo">
                            <?php else: ?>
                                Нет фото
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="controls">
                                <a href="?edit=<?= $car['id'] ?>" title="Редактировать">✏️</a>
                                <a href="?delete=<?= $car['id'] ?>" onclick="return confirm('Вы уверены, что хотите удалить этот автомобиль (ID: <?= $car['id'] ?>)?')" title="Удалить">🗑️</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </form> <form method="post" action="cars.php" enctype="multipart/form-data" class="add-row-form">
        <table class="add-row-form-table">
            <tbody>
            <tr>
                <td style="width: 30px;"></td> <td>#</td>
                <td><input type="text" name="brand" placeholder="Марка" required></td>
                <td><input type="text" name="model" placeholder="Модель" required></td>
                <td><input type="text" name="number" placeholder="A000AA00" required></td>
                <td>
                    <select name="status" required>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status ?>"><?= htmlspecialchars($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" step="0.01" name="rate" placeholder="0.00" required></td>
                <td class="photo-cell-content">
                    <label for="photo_add">Добавить фото:</label>
                    <input type="file" name="photo" id="photo_add">
                </td>
                <td style="width: 100px;">
                    <div class="controls">
                        <button type="submit" name="add" title="Добавить">➕</button>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </form> <form method="post" action="cars.php" id="bulkActionsForm">
        <div class="bulk-actions">
            <h3>Массовые действия с выбранными:</h3>
            <div>
                <button type="submit" name="bulk_action" value="delete" form="bulkActionsForm" onclick="return confirm('Вы уверены, что хотите удалить выбранные автомобили?');">Удалить выбранные</button>
            </div>
            <div>
                <label for="bulk_rate">Новый тариф:</label>
                <input type="number" step="0.01" name="bulk_rate" id="bulk_rate" placeholder="0.00" form="bulkActionsForm">
                <button type="submit" name="bulk_action" value="update_rate" form="bulkActionsForm">Изменить тариф выбранных</button>
            </div>
            <div>
                <label for="bulk_status">Новый статус:</label>
                <select name="bulk_status" id="bulk_status" form="bulkActionsForm">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= $status ?>"><?= htmlspecialchars($status) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="bulk_action" value="update_status" form="bulkActionsForm">Изменить статус выбранных</button>
            </div>
        </div>
    </form> <?php if (isset($success_message)): ?>
        <p class="success"><?= htmlspecialchars($success_message) ?></p>
    <?php endif; ?>

    <script>
        // JavaScript for "Select All" checkbox
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select_all_cars');
            // Select checkboxes associated with the bulkActionsForm
            const individualCheckboxes = document.querySelectorAll('input[name="selected_cars[]"][form="bulkActionsForm"]');

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    individualCheckboxes.forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                });
            }

            // Optional: If you want the master checkbox to uncheck if any individual is unchecked
            individualCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked) {
                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = false;
                        }
                    } else {
                        if (selectAllCheckbox) {
                            let allChecked = true;
                            individualCheckboxes.forEach(cb => {
                                if (!cb.checked) {
                                    allChecked = false;
                                }
                            });
                            if (allChecked) {
                                selectAllCheckbox.checked = true;
                            }
                        }
                    }
                });
            });

            // --- Optional: Prevent bulk actions if no cars are selected ---
            // Add event listeners to bulk action buttons
            const bulkActionButtons = document.querySelectorAll('#bulkActionsForm button[type="submit"]'); // Select buttons within the bulk actions form
            bulkActionButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    // Checkboxes are associated with this form via 'form="bulkActionsForm"'
                    const selectedCheckboxes = document.querySelectorAll('input[name="selected_cars[]"][form="bulkActionsForm"]:checked');

                    if (selectedCheckboxes.length === 0) {
                        alert('Пожалуйста, выберите хотя бы один автомобиль.');
                        event.preventDefault(); // Stop the form submission
                    }
                    // Additional validation for rate/status fields if needed before submitting
                    if ((button.value === 'update_rate' && document.getElementById('bulk_rate').value.trim() === '') ||
                        (button.value === 'update_status' && document.getElementById('bulk_status').value === '')) {
                        alert('Пожалуйста, укажите значение для выбранного массового действия.');
                        event.preventDefault();
                    }
                });
            });

        });
    </script>

</div>
</body>
</html>