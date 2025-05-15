<?php
// admin/inspections.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection (one level up)
require '../db.php';

// --- REMOVE PHOTO RELATED CODE ---
// Removed $photo_upload_dir, mkdir check, handleFileUpload function.


// Function to format date
function formatDate($date)
{
    return $date && $date !== '0000-00-00' ? date('d.m.Y', strtotime($date)) : '';
}

// Define possible inspection statuses
$inspection_statuses = ['Завершена', 'В процессе', 'Не пройдена']; // Adjust based on your needs

// Define max price for validation (optional)
$max_price = 99999.99; // Match your database DECIMAL(10,2) limits


// Variable to store success messages
$success_message = '';

// --- CRUD Operations ---

// Handle BULK Actions
if (isset($_POST['bulk_action'])) {
    $selected_ids = $_POST['selected_inspections'] ?? []; // Changed name
    $bulk_action = $_POST['bulk_action'];

    if (!is_array($selected_ids)) {
        $selected_ids = [];
    }

    $selected_ids = array_map('intval', $selected_ids);
    $selected_ids = array_filter($selected_ids, function($id) { return $id > 0; });

    if (empty($selected_ids)) {
        $error = "Не выбрано ни одного техосмотра для выполнения действия.";
        error_log("Bulk action failed: No inspections selected."); // Added logging
    } else {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));

        try {
            $pdo->beginTransaction(); // Start transaction for bulk operations

            switch ($bulk_action) {
                case 'delete':
                    $stmt_delete = $pdo->prepare("DELETE FROM inspection WHERE id IN ($placeholders)"); // Table inspection
                    $stmt_delete->execute($selected_ids);

                    $success_message = "Выбранные техосмотры (" . count($selected_ids) . ") успешно удалены.";
                    error_log("Bulk delete inspections success for IDs: " . implode(',', $selected_ids)); // Added logging
                    break;

                case 'update_price': // Bulk update for price
                    $new_price = trim($_POST['bulk_price'] ?? '');
                    if ($new_price === '' || !is_numeric($new_price) || $new_price < 0 || $new_price > $max_price) {
                        $error = "Укажите корректную цену для обновления.";
                        error_log("Bulk update price failed: Invalid price value '" . $new_price . "'"); // Added logging
                    } else {
                        $stmt_update_price = $pdo->prepare("UPDATE inspection SET price = ? WHERE id IN ($placeholders)"); // Table inspection, column price
                        $params = array_merge([$new_price], $selected_ids);
                        $stmt_update_price->execute($params);
                        $success_message = "Цена выбранных техосмотров (" . count($selected_ids) . ") успешно обновлена на " . htmlspecialchars($new_price) . ".";
                        error_log("Bulk update price success for IDs: " . implode(',', $selected_ids) . " to " . $new_price); // Added logging
                    }
                    break;

                case 'update_status': // Bulk update for status
                    $new_status = trim($_POST['bulk_status'] ?? '');
                    if (!in_array($new_status, $inspection_statuses)) { // Validate against allowed statuses
                        $error = "Выберите корректный статус для обновления.";
                        error_log("Bulk update status failed: Invalid status value '" . $new_status . "'"); // Added logging
                    } else {
                        $stmt_update_status = $pdo->prepare("UPDATE inspection SET status = ? WHERE id IN ($placeholders)"); // Table inspection, column status
                        $params = array_merge([$new_status], $selected_ids);
                        $stmt_update_status->execute($params);
                        $success_message = "Статус выбранных техосмотров (" . count($selected_ids) . ") успешно изменен на '" . htmlspecialchars($new_status) . "'.";
                        error_log("Bulk update status success for IDs: " . implode(',', $selected_ids) . " to " . $new_status); // Added logging
                    }
                    break;

                default:
                    $error = "Неизвестное массовое действие.";
                    error_log("Bulk action failed: Unknown action '" . htmlspecialchars($bulk_action) . "'"); // Added logging
                    break;
            }

            if (!isset($error)) {
                $pdo->commit(); // Commit transaction on success
                $_SESSION['success_message'] = $success_message;
                header("Location: inspections.php"); // Redirect to inspections.php
                exit();
            } else {
                $pdo->rollBack(); // Rollback transaction on validation error
            }


        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Ошибка базы данных при выполнении массового действия: " . $e->getMessage();
            error_log("Bulk inspection DB error: " . $e->getMessage()); // Added logging
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Произошла ошибка: " . $e->getMessage();
            error_log("Bulk inspection Exception: " . $e->getMessage()); // Added logging
        }
    }
}


// Handle SINGLE DELETE
if (isset($_GET['delete'])) { // This is a GET request
    $id_to_delete = $_GET['delete'];
    $id_to_delete = intval($id_to_delete);

    if ($id_to_delete > 0) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM inspection WHERE id = ?"); // Table inspection
            $stmt->execute([$id_to_delete]);
            $pdo->commit();

            $_SESSION['success_message'] = "Техосмотр ID: " . htmlspecialchars($id_to_delete) . " успешно удален.";
            error_log("Single delete inspection success ID: " . $id_to_delete); // Added logging
            header("Location: inspections.php"); // Redirect to inspections.php
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Ошибка при удалении техосмотра: " . $e->getMessage();
            error_log("Single delete inspection DB error: " . $e->getMessage()); // Added logging
        }
    } else {
        $error = "Некорректный ID техосмотра для удаления.";
        error_log("Single delete inspection failed: Invalid ID '" . htmlspecialchars($_GET['delete']) . "'"); // Added logging
    }
}

// Handle SINGLE ADD
if (isset($_POST['add'])) {
    error_log("Handling ADD inspection request."); // Added logging
    $date = trim($_POST['date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '0.00');
    $status = trim($_POST['status'] ?? '');
    $carId = trim($_POST['carId'] ?? ''); // Added carId

    // Validate required fields
    if (empty($date) || empty($status) || empty($carId) || $description === '' || $price === '' || !is_numeric($price) || $price < 0 || $price > $max_price) {
        $error = "Пожалуйста, заполните все обязательные поля корректно (Дата, Статус, Автомобиль, Описание, Цена >= 0).";
        error_log("ADD inspection failed: Validation error. POST: " . print_r($_POST, true)); // Added logging
    } elseif (!in_array($status, $inspection_statuses)) { // Validate status
        $error = "Выбран некорректный статус техосмотра.";
        error_log("ADD inspection failed: Invalid status '" . $status . "'"); // Added logging
    } else {
        try {
            $pdo->beginTransaction();
            // Insert query for inspection fields
            $stmt = $pdo->prepare("INSERT INTO inspection (date, description, price, status, carId) VALUES (?, ?, ?, ?, ?)"); // Table inspection
            $stmt->execute([
                $date,
                $description,
                $price,
                $status,
                $carId
            ]);
            $pdo->commit();

            $_SESSION['success_message'] = "Новый техосмотр успешно добавлен.";
            error_log("ADD inspection success."); // Added logging
            header("Location: inspections.php"); // Redirect to inspections.php
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Ошибка при добавлении техосмотра: " . $e->getMessage();
            error_log("ADD inspection DB error: " . $e->getMessage()); // Added logging
        }
    }
}


// Handle SINGLE UPDATE
if (isset($_POST['update'])) {
    error_log("Handling UPDATE inspection request."); // Added logging
    $id_to_update = $_POST['id'] ?? 0;
    $date = trim($_POST['date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '0.00');
    $status = trim($_POST['status'] ?? '');
    $carId = trim($_POST['carId'] ?? ''); // Added carId

    $id_to_update = intval($id_to_update);

    if ($id_to_update <= 0 || empty($date) || empty($status) || empty($carId) || $description === '' || $price === '' || !is_numeric($price) || $price < 0 || $price > $max_price) {
        $error = "Пожалуйста, заполните все обязательные поля корректно для обновления (ID, Дата, Статус, Автомобиль, Описание, Цена >= 0).";
        error_log("UPDATE inspection failed: Validation error. POST: " . print_r($_POST, true)); // Added logging
    } elseif (!in_array($status, $inspection_statuses)) { // Validate status
        $error = "Выбран некорректный статус техосмотра.";
        error_log("UPDATE inspection failed: Invalid status '" . $status . "'"); // Added logging
    } else {
        try {
            $pdo->beginTransaction();
            $sql = "UPDATE inspection SET date = ?, description = ?, price = ?, status = ?, carId = ? WHERE id = ?"; // Table inspection
            $params = [
                $date,
                $description,
                $price,
                $status,
                $carId,
                $id_to_update
            ];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pdo->commit();

            $_SESSION['success_message'] = "Данные техосмотра ID: " . htmlspecialchars($id_to_update) . " успешно обновлены.";
            error_log("UPDATE inspection success ID: " . $id_to_update); // Added logging
            header("Location: inspections.php"); // Redirect to inspections.php
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Ошибка при обновлении техосмотра: " . $e->getMessage();
            error_log("UPDATE inspection DB error: " . $e->getMessage()); // Added logging
        }
    }
}


// --- Handle messages after redirect ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}


// --- Data Fetching for Display ---

// Get inspections data, joining with car info
$sql = "SELECT
            i.id,
            i.date,
            i.description,
            i.price,
            i.status,
            i.carId,
            c.brand,
            c.model,
            c.number
        FROM
            inspection i
        JOIN -- Use JOIN because inspection MUST have a carId
            car c ON i.carId = c.id
        ORDER BY
            i.id ASC,  i.date ASC"; // Order by date, then ID

try {
    $stmt = $pdo->query($sql);
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Ошибка при загрузке данных техосмотров: " . $e->getMessage();
    error_log("Error fetching inspections: " . $e->getMessage()); // Added logging
    $inspections = [];
}

// --- Fetch list of cars for dropdowns ---
$cars_list = [];
try {
    $stmt_cars = $pdo->query("SELECT id, brand, model, number FROM car ORDER BY brand, model, number");
    $cars_list = $stmt_cars->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Ошибка при загрузке списка автомобилей: " . $e->getMessage();
    error_log("Error fetching cars list for inspections: " . $e->getMessage()); // Added logging
    // $cars_list will be empty, which might cause issues in forms, consider handling this
}


// Get the ID of the inspection being edited from the URL
$editInspectionId = $_GET['edit'] ?? null;

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление техосмотрами</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: auto; }
        h1 { text-align: center; margin-bottom: 20px; }
        .header-links { text-align: right; margin-bottom: 20px; }
        .header-links a { margin-left: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        input[type="text"], input[type="number"], input[type="date"], input[type="email"], select, textarea { width: 100%; padding: 5px; box-sizing: border-box; }
        input[type="file"] { width: auto; display: block; margin-top: 5px;}
        textarea { height: 60px; } /* Make textarea taller */
        .controls { display: flex; gap: 5px; }
        .controls a, .controls button { text-decoration: none; padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; font-size: 1em; }
        .controls button[type="submit"] { background-color: #5cb85c; color: white; border-color: #4cae4c;}
        .controls button[type="submit"]:hover { background-color: #4cae4c; }
        .controls a { color: #333; }
        .controls a:hover { background-color: #f0f0f0; }
        .controls a[title="Отмена"] { background-color: #d9534f; color: white; border-color: #d43f3a;}
        .controls a[title="Отмена"]:hover { background-color: #c9302c; }
        .error { color: red; margin-top: 10px; text-align: center; }
        .success { color: green; margin-top: 10px; text-align: center; }


        /* Styles for Bulk Actions Section */
        .bulk-actions { margin-top: 20px; padding: 15px; border: 1px solid #ccc; background-color: #f9f9f9; border-radius: 5pt; }
        .bulk-actions h3 { margin-top: 0; }
        .bulk-actions > div { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #eee; }
        .bulk-actions > div:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .bulk-actions label { margin-right: 10px; font-weight: bold;}
        .bulk-actions button { margin-left: 10px; padding: 5px 10px; cursor: pointer; border-radius: 4px; }
        .bulk-actions button[value="delete"] { background-color: #d9534f; color: white; border: 1px solid #d43f3a; }
        .bulk-actions button[value="delete"]:hover { background-color: #c9302c; }
        .bulk-actions button[value^="update_"] { background-color: #0275d8; color: white; border: 1px solid #025aa5; }
        .bulk-actions button[value^="update_"]:hover { background-color: #025aa5; }
        .bulk-actions input[type="number"], .bulk-actions input[type="text"], .bulk-actions input[type="email"], .bulk-actions select { padding: 5px; border: 1px solid #ccc; border-radius: 4px; }

        /* Style for the separate add row form/table */
        .add-row-form-table { margin-top: 10px; width: 100%; border-collapse: collapse; }
        .add-row-form-table td { border: 1px solid #ddd; padding: 8px; vertical-align: top;}
        .add-row-form-table input[type="text"],
        .add-row-form-table input[type="number"],
        .add-row-form-table input[type="date"],
        .add-row-form-table select,
        .add-row-form-table textarea { /* Added textarea */
            width: 100%;
            padding: 5px;
            box-sizing: border-box;
        }
        .add-row-form-table textarea { height: 60px;} /* Make textarea taller */
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
    <div class="header-links" style="display: flex">
        <a style="flex-grow: 1; text-align: left" href="index.php">Назад</a>
        <a href="../index.php">На сайт</a>
        <a href="logout.php">Выход</a>
    </div>

    <h1>Управление техосмотрами</h1>

    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post" action="inspections.php" id="mainDisplayForm"> <table border="1">
            <thead>
            <tr>
                <th style="width: 30px;"><input type="checkbox" id="select_all_inspections"></th> <th>ID</th>
                <th>Дата</th> <th>Описание</th> <th>Цена</th> <th>Статус</th> <th>Автомобиль</th> <th style="width: 100px;">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($inspections as $inspection): ?>
                <?php if ($editInspectionId == $inspection['id']): ?> <tr>
                    <td></td>
                    <form method="post" action="inspections.php"> <input type="hidden" name="id" value="<?= $inspection['id'] ?>">
                        <td><?= $inspection['id'] ?></td>
                        <td><input type="date" name="date" value="<?= htmlspecialchars($inspection['date']) ?>" required></td> <td><textarea name="description" required><?= htmlspecialchars($inspection['description']) ?></textarea></td> <td><input type="number" step="0.01" name="price" value="<?= $inspection['price'] ?>" required></td> <td>
                            <select name="status" required> <?php foreach ($inspection_statuses as $status_option): ?>
                                    <option value="<?= $status_option ?>" <?= ($inspection['status'] == $status_option) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status_option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="carId" required> <option value="">Выберите автомобиль</option>
                                <?php foreach ($cars_list as $car_item): ?>
                                    <option value="<?= $car_item['id'] ?>" <?= ($inspection['carId'] == $car_item['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($car_item['brand'] . ' ' . $car_item['model'] . ' (' . $car_item['number'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <div class="controls">
                                <button type="submit" name="update" title="Сохранить">💾</button>
                                <a href="inspections.php" title="Отмена">❌</a> </div>
                        </td>
                    </form>
                </tr>
                <?php else: ?>
                    <tr>
                        <td><input type="checkbox" name="selected_inspections[]" value="<?= $inspection['id'] ?>" form="bulkActionsForm"></td> <td><?= $inspection['id'] ?></td>
                        <td><?= formatDate($inspection['date']) ?></td> <td><?= htmlspecialchars($inspection['description']) ?></td> <td><?= number_format($inspection['price'], 2, '.', ' ') ?> ₽</td> <td><?= htmlspecialchars($inspection['status']) ?></td> <td><?= htmlspecialchars($inspection['brand'] . ' ' . $inspection['model'] . ' (' . $inspection['number'] . ')') ?></td> <td>
                            <div class="controls">
                                <a href="?edit=<?= $inspection['id'] ?>" title="Редактировать">✏️</a>
                                <a href="?delete=<?= $inspection['id'] ?>" onclick="return confirm('Выверены, что хотите удалить этот техосмотр (ID: <?= $inspection['id'] ?>)?')" title="Удалить">🗑️</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </form> <form method="post" action="inspections.php" class="add-row-form"> <table class="add-row-form-table">
            <tbody>
            <tr>
                <td style="width: 30px;"></td> <td>#</td>
                <td><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></td> <td><textarea name="description" placeholder="Описание" required></textarea></td> <td><input type="number" step="0.01" name="price" value="0.00" required></td> <td>
                    <select name="status" required> <option value="">Выберите статус</option>
                        <?php foreach ($inspection_statuses as $status_option): ?>
                            <option value="<?= $status_option ?>"><?= htmlspecialchars($status_option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="carId" required> <option value="">Выберите автомобиль</option>
                        <?php foreach ($cars_list as $car_item): ?>
                            <option value="<?= $car_item['id'] ?>">
                                <?= htmlspecialchars($car_item['brand'] . ' ' . $car_item['model'] . ' (' . $car_item['number'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="width: 100px;">
                    <div class="controls">
                        <button type="submit" name="add" title="Добавить">➕</button>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </form> <form method="post" action="inspections.php" id="bulkActionsForm"> <div class="bulk-actions">
            <h3>Массовые действия с выбранными:</h3>
            <div>
                <button type="submit" name="bulk_action" value="delete" form="bulkActionsForm" onclick="return confirm('Выверены, что хотите удалить выбранные техосмотры?');">Удалить выбранные</button>
            </div>
            <div>
                <label for="bulk_price">Новая цена:</label>
                <input type="number" step="0.01" name="bulk_price" id="bulk_price" placeholder="0.00" form="bulkActionsForm">
                <button type="submit" name="bulk_action" value="update_price" form="bulkActionsForm">Изменить цену выбранных</button>
            </div>
            <div>
                <label for="bulk_status">Новый статус:</label>
                <select name="bulk_status" id="bulk_status" form="bulkActionsForm">
                    <option value="">Выберите статус</option>
                    <?php foreach ($inspection_statuses as $status_option): ?>
                        <option value="<?= $status_option ?>"><?= htmlspecialchars($status_option) ?></option>
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
            const selectAllCheckbox = document.getElementById('select_all_inspections'); // Updated ID
            // Select checkboxes associated with the bulkActionsForm
            const individualCheckboxes = document.querySelectorAll('input[name="selected_inspections[]"][form="bulkActionsForm"]'); // Updated name and form attribute selector

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    individualCheckboxes.forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                });
            }

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

            // --- Optional: Prevent bulk actions if no items are selected and validate input ---
            const bulkActionButtons = document.querySelectorAll('#bulkActionsForm button[type="submit"]'); // Select buttons within the bulk actions form
            bulkActionButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    const selectedCheckboxes = document.querySelectorAll('input[name="selected_inspections[]"][form="bulkActionsForm"]:checked');

                    if (selectedCheckboxes.length === 0) {
                        alert('Пожалуйста, выберите хотя бы один техосмотр.');
                        event.preventDefault(); // Stop the form submission
                        return; // Stop further validation
                    }

                    // Additional validation based on the specific bulk action
                    if (button.value === 'update_price') {
                        const priceInput = document.getElementById('bulk_price');
                        if (priceInput.value.trim() === '' || isNaN(parseFloat(priceInput.value)) || parseFloat(priceInput.value) < 0) {
                            alert('Пожалуйста, укажите корректную неотрицательную цену.');
                            event.preventDefault();
                        }
                    } else if (button.value === 'update_status') {
                        const statusSelect = document.getElementById('bulk_status');
                        if (statusSelect.value === '') { // Check if the default "Выберите статус" option is selected
                            alert('Пожалуйста, выберите статус.');
                            event.preventDefault();
                        }
                    }
                    // No validation needed for delete beyond the confirm dialog
                });
            });

        });
    </script>

</body>

</html>