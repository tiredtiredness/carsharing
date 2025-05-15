<?php
// admin/users.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection (one level up)
require '../db.php';

// Define the directory for storing user photos
$photo_upload_dir = __DIR__ . '/../uploads/';

// Ensure upload directory exists and is writable
if (!is_dir($photo_upload_dir)) {
    if (!mkdir($photo_upload_dir, 0775, true)) {
        $error = "Ошибка: Не удалось создать директорию для фото пользователей " . htmlspecialchars($photo_upload_dir);
        error_log("Failed to create upload directory: " . $photo_upload_dir);
    } else {
        error_log("Created upload directory: " . $photo_upload_dir);
    }
}

// Function to format date
function formatDate($date)
{
    return $date && $date !== '0000-00-00' ? date('d.m.Y', strtotime($date)) : '';
}

// Function to handle file upload
function handleFileUpload($file_input_name, $upload_dir, $max_size, &$error) {
    error_log("handleFileUpload called for input: " . $file_input_name);
    // Check if the specific file input exists and a file was uploaded
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] === UPLOAD_ERR_NO_FILE) {
        error_log("No file selected for input: " . $file_input_name);
        return null; // No file uploaded
    }

    // Check for upload errors
    if ($_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        $file_error_code = $_FILES[$file_input_name]['error'];
        $upload_error_message = 'Неизвестная ошибка загрузки файла.';
        switch ($file_error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $upload_error_message = 'Загруженный файл превышает максимально допустимый размер загрузки.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $upload_error_message = 'Загруженный файл был загружен только частично.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $upload_error_message = 'Отсутствует временная папка для загрузки.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $upload_error_message = 'Ошибка записи файла на диск.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $upload_error_message = 'Расширение PHP остановило загрузку файла.';
                break;
        }
        $error = $upload_error_message;
        error_log("File upload error for input " . $file_input_name . ". Code: " . $file_error_code . ". Message: " . $upload_error_message);
        return false; // Indicate failure
    }

    $file_tmp_path = $_FILES[$file_input_name]['tmp_name'];
    $file_name = $_FILES[$file_input_name]['name'];
    $file_size = $_FILES[$file_input_name]['size'];
    $file_type = null;

    if (function_exists('mime_content_type')) {
        $file_type = mime_content_type($file_tmp_path);
        error_log("Detected MIME type: " . $file_type);
    } else {
        error_log("mime_content_type function not available.");
        $error = "Ошибка сервера: Недоступна функция mime_content_type.";
        return false;
    }


    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

    if (!in_array($file_type, $allowed_types)) {
        $error = "Разрешены только файлы JPG, PNG, GIF. Обнаружен: " . htmlspecialchars($file_type);
        error_log("Disallowed file type: " . $file_type);
        return false;
    }

    if ($file_size > $max_size) {
        $error = "Размер файла (" . number_format($file_size/1024/1024, 2) . "MB) превышает максимально допустимый размер (" . ($max_size / 1024 / 1024) . "MB).";
        error_log("File size exceeded limit. Size: " . $file_size . ", Limit: " . $max_size);
        return false;
    }

    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
    $new_file_name = hash('crc32', microtime() . $file_name) . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
    $dest_path = $upload_dir . $new_file_name;

    error_log("Attempting to move file from " . $file_tmp_path . " to " . $dest_path);
    error_log("Target directory exists: " . (is_dir($upload_dir) ? 'Yes' : 'No'));
    error_log("Target directory is writable: " . (is_writable($upload_dir) ? 'Yes' : 'No'));

    if (move_uploaded_file($file_tmp_path, $dest_path)) {
        error_log("File move SUCCESS. New filename: " . $new_file_name);
        return $new_file_name;
    } else {
        $error = "Ошибка при сохранении загруженного файла. Возможно, нет прав на запись в папку.";
        error_log("File move FAILED from " . $file_tmp_path . " to " . $dest_path);
        return false;
    }
}

// Define max photo size (e.g., 5MB)
$max_photo_size = 5 * 1024 * 1024;

// Variable to store success messages
$success_message = '';

// --- CRUD Operations ---

// Handle BULK Actions (Keep existing logic)
if (isset($_POST['bulk_action'])) {
    $selected_ids = $_POST['selected_users'] ?? [];
    $bulk_action = $_POST['bulk_action'];

    if (!is_array($selected_ids)) {
        $selected_ids = [];
    }

    $selected_ids = array_map('intval', $selected_ids);
    $selected_ids = array_filter($selected_ids, function($id) { return $id > 0; });


    if (empty($selected_ids) && $bulk_action !== 'update_photo') { // Для обновления фото выбор пользователей обязателен
        $error = "Не выбрано ни одного пользователя для выполнения действия.";
        error_log("Bulk action failed: No users selected.");
    } else {
        $placeholders = !empty($selected_ids) ? implode(',', array_fill(0, count($selected_ids), '?')) : ''; // Только если есть выбранные ID

        try {
            switch ($bulk_action) {
                case 'delete':
                    if (empty($selected_ids)) { // Повторная проверка для delete
                        $error = "Не выбрано ни одного пользователя для удаления.";
                        error_log("Bulk delete failed: No users selected (switch check).");
                        break;
                    }
                    $stmt_photos = $pdo->prepare("SELECT id, photo FROM user WHERE id IN ($placeholders)");
                    $stmt_photos->execute($selected_ids);
                    $users_to_delete = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);

                    $pdo->beginTransaction();
                    $stmt_delete = $pdo->prepare("DELETE FROM user WHERE id IN ($placeholders)");
                    $stmt_delete->execute($selected_ids);

                    foreach ($users_to_delete as $user) {
                        if ($user['photo']) {
                            $photo_path = $photo_upload_dir . $user['photo'];
                            error_log("Attempting to delete photo: " . $photo_path);
                            if (file_exists($photo_path)) {
                                if (@unlink($photo_path)) {
                                    error_log("Photo deleted: " . $photo_path);
                                } else {
                                    error_log("Failed to delete photo: " . $photo_path);
                                }
                            } else {
                                error_log("Photo file not found for deletion: " . $photo_path);
                            }
                        }
                    }
                    $pdo->commit();
                    $success_message = "Выбранные пользователи (" . count($selected_ids) . ") успешно удалены.";
                    error_log("Bulk delete success for IDs: " . implode(',', $selected_ids));
                    break;

                case 'update_balance':
                    if (empty($selected_ids)) { // Повторная проверка
                        $error = "Не выбрано ни одного пользователя для обновления баланса.";
                        break;
                    }
                    $new_balance = trim($_POST['bulk_balance'] ?? '');
                    if ($new_balance === '' || !is_numeric($new_balance) || $new_balance < 0) {
                        $error = "Укажите корректный баланс для обновления.";
                        error_log("Bulk update balance failed: Invalid balance value '" . $new_balance . "'");
                    } else {
                        $pdo->beginTransaction();
                        $stmt_update_balance = $pdo->prepare("UPDATE user SET balance = ? WHERE id IN ($placeholders)");
                        $params = array_merge([$new_balance], $selected_ids);
                        $stmt_update_balance->execute($params);
                        $pdo->commit();
                        $success_message = "Баланс выбранных пользователей (" . count($selected_ids) . ") успешно обновлен на " . htmlspecialchars($new_balance) . ".";
                        error_log("Bulk update balance success for IDs: " . implode(',', $selected_ids) . " to " . $new_balance);
                    }
                    break;

                case 'update_photo':
                    error_log("Handling bulk update photo action.");
                    if (empty($selected_ids)) {
                        $error = "Не выбрано ни одного пользователя для обновления фото.";
                        error_log("Bulk update photo failed: No users selected.");
                        break;
                    }

                    error_log("Calling handleFileUpload for 'bulk_photo' input in bulk update.");
                    $uploaded_bulk_photo_filename = handleFileUpload('bulk_photo', $photo_upload_dir, $max_photo_size, $error);

                    if ($uploaded_bulk_photo_filename === false) {
                        error_log("Bulk update photo failed: handleFileUpload returned false.");
                        break;
                    } elseif ($uploaded_bulk_photo_filename === null) {
                        $error = "Выберите файл фотографии для массового обновления.";
                        error_log("Bulk update photo failed: No file selected.");
                        break;
                    } else {
                        error_log("handleFileUpload returned new filename: " . $uploaded_bulk_photo_filename);

                        $pdo->beginTransaction();
                        $stmt_update_photo = $pdo->prepare("UPDATE user SET photo = ? WHERE id = ?");

                        $deleted_old_count = 0;
                        $failed_delete_count = 0;

                        foreach ($selected_ids as $user_id) {
                            $stmt_old_photo = $pdo->prepare("SELECT photo FROM user WHERE id = ?");
                            $stmt_old_photo->execute([$user_id]);
                            $old_photo_result = $stmt_old_photo->fetch(PDO::FETCH_ASSOC);
                            $old_photo_filename = $old_photo_result ? $old_photo_result['photo'] : null;
                            error_log("Processing user ID " . $user_id . ". Old photo: " . ($old_photo_filename ?: 'null'));


                            $stmt_update_photo->execute([$uploaded_bulk_photo_filename, $user_id]);
                            error_log("DB updated for user ID " . $user_id . " with new photo " . $uploaded_bulk_photo_filename);


                            if ($old_photo_filename && $old_photo_filename !== $uploaded_bulk_photo_filename) {
                                $old_photo_path = $photo_upload_dir . $old_photo_filename;
                                error_log("Attempting to delete old photo for user ID " . $user_id . ": " . $old_photo_path);
                                if (file_exists($old_photo_path)) {
                                    if (@unlink($old_photo_path)) {
                                        $deleted_old_count++;
                                        error_log("Old photo deleted successfully for user ID " . $user_id);
                                    } else {
                                        $failed_delete_count++;
                                        error_log("Failed to delete old photo for user ID " . $user_id . ": " . $old_photo_path);
                                    }
                                } else {
                                    error_log("Old photo file not found for deletion for user ID " . $user_id . ": " . $old_photo_path);
                                }
                            }
                        }

                        $pdo->commit();
                        $success_message = "Фотография успешно обновлена для выбранных пользователей (" . count($selected_ids) . ").";
                        if ($deleted_old_count > 0) $success_message .= " Удалено старых фото: " . $deleted_old_count . ".";
                        if ($failed_delete_count > 0) $success_message .= " Не удалось удалить старых фото: " . $failed_delete_count . ".";
                        error_log("Bulk update photo success for IDs: " . implode(',', $selected_ids) . ". New photo: " . $uploaded_bulk_photo_filename);

                    }
                    break;


                default:
                    $error = "Неизвестное массовое действие.";
                    error_log("Bulk action failed: Unknown action '" . htmlspecialchars($bulk_action) . "'");
                    break;
            }

            if (!isset($error)) {
                $_SESSION['success_message'] = $success_message;
                header("Location: users.php");
                exit();
            } else {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                    error_log("Transaction rolled back due to error.");
                }
                if ($bulk_action === 'update_photo' && isset($uploaded_bulk_photo_filename) && $uploaded_bulk_photo_filename !== null && file_exists($photo_upload_dir . $uploaded_bulk_photo_filename)) {
                    error_log("Bulk update photo error, cleaning up NEWLY uploaded file: " . $photo_upload_dir . $uploaded_bulk_photo_filename);
                    @unlink($photo_upload_dir . $uploaded_bulk_photo_filename);
                }
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Ошибка базы данных при выполнении массового действия: " . $e->getMessage();
            error_log("Bulk action DB error: " . $e->getMessage());
            if ($bulk_action === 'update_photo' && isset($uploaded_bulk_photo_filename) && $uploaded_bulk_photo_filename !== null && file_exists($photo_upload_dir . $uploaded_bulk_photo_filename)) {
                error_log("Bulk update photo DB error, cleaning up NEWLY uploaded file: " . $photo_upload_dir . $uploaded_bulk_photo_filename);
                @unlink($photo_upload_dir . $uploaded_bulk_photo_filename);
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Произошла ошибка: " . $e->getMessage();
            error_log("Bulk action Exception: " . $e->getMessage());
            if ($bulk_action === 'update_photo' && isset($uploaded_bulk_photo_filename) && $uploaded_bulk_photo_filename !== null && file_exists($photo_upload_dir . $uploaded_bulk_photo_filename)) {
                error_log("Bulk update photo Exception, cleaning up NEWLY uploaded file: " . $photo_upload_dir . $uploaded_bulk_photo_filename);
                @unlink($photo_upload_dir . $uploaded_bulk_photo_filename);
            }
        }
    }
}


// Handle SINGLE DELETE (keep existing single delete for convenience)
if (isset($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];
    $id_to_delete = intval($id_to_delete);

    if ($id_to_delete > 0) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT photo FROM user WHERE id = ?");
            $stmt->execute([$id_to_delete]);
            $user_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("DELETE FROM user WHERE id = ?");
            $stmt->execute([$id_to_delete]);
            $pdo->commit();

            if ($user_to_delete && $user_to_delete['photo']) {
                $photo_path = $photo_upload_dir . $user_to_delete['photo'];
                error_log("Attempting single delete photo: " . $photo_path);
                if (file_exists($photo_path)) {
                    if(@unlink($photo_path)) {
                        error_log("Single delete photo deleted: " . $photo_path);
                    } else {
                        error_log("Failed single delete photo: " . $photo_path);
                    }
                } else {
                    error_log("Single delete photo file not found: " . $photo_path);
                }
            }

            $_SESSION['success_message'] = "Пользователь ID: " . htmlspecialchars($id_to_delete) . " успешно удален.";
            error_log("Single delete user success ID: " . $id_to_delete);
            header("Location: users.php");
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Ошибка при удалении пользователя: " . $e->getMessage();
            error_log("Single delete user DB error: " . $e->getMessage());
        }
    } else {
        $error = "Некорректный ID пользователя для удаления.";
        error_log("Single delete user failed: Invalid ID '" . htmlspecialchars($_GET['delete']) . "'");
    }
}


// Handle SINGLE ADD (Keep existing logic)
if (isset($_POST['add'])) {
    error_log("Handling ADD request.");
    $fullName = trim($_POST['fullName'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $license = trim($_POST['license'] ?? '');
    $balance = trim($_POST['balance'] ?? '0.00');

    if (empty($fullName) || empty($phone) || empty($email)) {
        $error = "Пожалуйста, заполните обязательные поля: Полное имя, Телефон, Email.";
        error_log("ADD failed: Required fields empty. POST: " . print_r($_POST, true));
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Укажите корректный формат Email.";
        error_log("ADD failed: Invalid email format '" . $email . "'");
    } elseif ($balance !== '' && (!is_numeric($balance) || $balance < 0)) {
        $error = "Укажите корректный баланс.";
        error_log("ADD failed: Invalid balance value '" . $balance . "'");
    } else {
        error_log("Calling handleFileUpload for 'photo' input in ADD.");
        $new_photo_filename = handleFileUpload('photo', $photo_upload_dir, $max_photo_size, $error);

        if ($new_photo_filename === false) {
            error_log("ADD failed: handleFileUpload returned false.");
        } else {
            error_log("handleFileUpload returned: " . ($new_photo_filename === null ? 'null' : $new_photo_filename));
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO user (fullName, birthdate, phone, email, photo, license, balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $fullName,
                    $birthdate ?: null,
                    $phone,
                    $email,
                    $new_photo_filename,
                    $license,
                    $balance
                ]);
                $pdo->commit();

                $_SESSION['success_message'] = "Новый пользователь '" . htmlspecialchars($fullName) . "' успешно добавлен.";
                error_log("ADD user success: " . $fullName);
                header("Location: users.php");
                exit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($new_photo_filename && file_exists($photo_upload_dir . $new_photo_filename)) {
                    error_log("ADD DB error, cleaning up uploaded file: " . $photo_upload_dir . $new_photo_filename);
                    @unlink($photo_upload_dir . $new_photo_filename);
                }
                $error = "Ошибка при добавлении пользователя: " . $e->getMessage();
                error_log("ADD user DB error: " . $e->getMessage());
            }
        }
    }
}


// Handle SINGLE UPDATE (MODIFIED to include delete photo logic)
if (isset($_POST['update'])) {
    error_log("Handling UPDATE request.");
    $id_to_update = $_POST['id'] ?? 0;
    $fullName = trim($_POST['fullName'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $license = trim($_POST['license'] ?? '');
    $balance = trim($_POST['balance'] ?? '0.00');
    $delete_photo_requested = isset($_POST['delete_photo']); // Check if delete checkbox was checked

    $id_to_update = intval($id_to_update);

    if ($id_to_update <= 0 || empty($fullName) || empty($phone) || empty($email)) {
        $error = "Пожалуйста, заполните обязательные поля для обновления (включая корректный ID, Полное имя, Телефон, Email).";
        error_log("UPDATE failed: Required fields or ID empty. POST: " . print_r($_POST, true));
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Укажите корректный формат Email.";
        error_log("UPDATE failed: Invalid email format '" . $email . "'");
    } elseif ($balance !== '' && (!is_numeric($balance) || $balance < 0)) {
        $error = "Укажите корректный баланс.";
        error_log("UPDATE failed: Invalid balance value '" . $balance . "'");
    } else {
        error_log("Calling handleFileUpload for 'photo_new' input in UPDATE.");
        $uploaded_new_photo_filename = handleFileUpload('photo_new', $photo_upload_dir, $max_photo_size, $error);

        // Check if file upload failed
        if ($uploaded_new_photo_filename === false) {
            error_log("UPDATE failed: handleFileUpload returned false.");
            // Error is already set by handleFileUpload, just stop processing this request
        } else {
            // If file upload succeeded ($uploaded_new_photo_filename is filename string)
            // OR if no file was selected ($uploaded_new_photo_filename is null)

            error_log("handleFileUpload returned: " . ($uploaded_new_photo_filename === null ? 'null' : $uploaded_new_photo_filename));
            error_log("Delete photo requested: " . ($delete_photo_requested ? 'Yes' : 'No'));

            $pdo->beginTransaction();
            $sql = "UPDATE user SET fullName = ?, birthdate = ?, phone = ?, email = ?, license = ?, balance = ?";
            $params = [
                $fullName,
                $birthdate ?: null,
                $phone,
                $email,
                $license,
                $balance
            ];

            $update_photo_column = false;
            $new_photo_value = null; // Will be new filename or null if deleting/no new upload

            // Determine action based on upload result and delete checkbox
            if ($uploaded_new_photo_filename !== null) {
                // Case 1: New file was uploaded. This overrides delete checkbox.
                $update_photo_column = true;
                $new_photo_value = $uploaded_new_photo_filename;
                error_log("UPDATE logic: New file uploaded. Will replace existing photo.");
            } elseif ($delete_photo_requested) {
                // Case 2: No new file uploaded, but delete checkbox is checked.
                $update_photo_column = true;
                $new_photo_value = null; // Set photo column to NULL in DB
                error_log("UPDATE logic: Delete photo requested and no new file uploaded.");
            }
            // Case 3: No new file, delete checkbox not checked. No photo update needed.
            // $update_photo_column remains false.

            $old_photo_filename = null;
            // Fetch the current photo filename ONLY if we are going to update the photo column
            if ($update_photo_column) {
                $stmt_old_photo = $pdo->prepare("SELECT photo FROM user WHERE id = ?");
                $stmt_old_photo->execute([$id_to_update]);
                $old_photo_result = $stmt_old_photo->fetch(PDO::FETCH_ASSOC);
                if ($old_photo_result) {
                    $old_photo_filename = $old_photo_result['photo'];
                    error_log("Fetched old photo filename for update: " . ($old_photo_filename ?: 'null'));
                }

                $sql .= ", photo = ?"; // Add photo column to update SQL
                $params[] = $new_photo_value; // Add new photo filename (or null) to parameters
            }


            $sql .= " WHERE id = ?";
            $params[] = $id_to_update;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pdo->commit();

            // Delete old photo ONLY if we attempted to update the photo column,
            // an old photo existed, and it's different from the new value (which is null if deleting, or the new filename if replacing)
            if ($update_photo_column && $old_photo_filename && $old_photo_filename !== $new_photo_value) {
                $old_photo_path = $photo_upload_dir . $old_photo_filename;
                error_log("Attempting to delete old photo after update: " . $old_photo_path);
                if (file_exists($old_photo_path)) {
                    if(@unlink($old_photo_path)) {
                        error_log("Old photo deleted successfully: " . $old_photo_path);
                    } else {
                        error_log("Failed to delete old photo: " . $old_photo_path);
                    }
                } else {
                    error_log("Old photo file not found for deletion: " . $old_photo_path);
                }
            }

            $_SESSION['success_message'] = "Данные пользователя ID: " . htmlspecialchars($id_to_update) . " успешно обновлены.";
            error_log("UPDATE user success ID: " . $id_to_update);
            header("Location: users.php");
            exit();

        } // End else (file upload handle was not false)

    } // End else (validation passed)
} // End if (isset($_POST['update']))


// --- Handle messages after redirect ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}


// --- Data Fetching for Display ---

$sql = "SELECT
            id,
            fullName,
            birthdate,
            phone,
            email,
            photo,
            reg_date,
            license,
            balance
        FROM
            user
        ORDER BY
            id";

try {
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Ошибка при загрузке данных пользователей: " . $e->getMessage();
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}

// Get the ID of the user being edited from the URL
$editUserId = $_GET['edit'] ?? null;
// Ensure edit ID is an integer if set
$editUserId = is_numeric($editUserId) ? (int)$editUserId : null;


?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями</title>
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
        .user-photo { max-width: 60px; max-height: 60px; height: auto; display: block; margin: 0 auto 5px auto; border-radius: 50%; border: 1px solid #ccc; padding: 2px; object-fit: cover;}
        .photo-cell-content { min-width: 80px; text-align: center; }
        .photo-cell-content label { font-weight: normal; display: block; margin-bottom: 3px; }
        .photo-cell-content input[type="checkbox"] { margin-top: 5px; display: inline-block; width: auto;} /* Style checkbox */
        .photo-cell-content label[for] { display: inline-block; margin-bottom: 0;} /* Style label for file input */
        .photo-cell-content label { font-weight: normal;} /* Make checkbox label normal weight */


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
        .bulk-actions input[type="file"] { display: inline-block; width: auto; margin: 0 10px 0 0;}

        /* Style for the separate add row form/table */
        .add-row-form-table { margin-top: 10px; width: 100%; border-collapse: collapse; }
        .add-row-form-table td { border: 1px solid #ddd; padding: 8px; vertical-align: top;}
        .add-row-form-table input[type="text"],
        .add-row-form-table input[type="number"],
        .add-row-form-table input[type="email"],
        .add-row-form-table input[type="date"],
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
    <div class="header-links" style="display: flex">
        <a style="flex-grow: 1; text-align: left" href="index.php">Назад</a>
        <a href="../index.php">На сайт</a>
        <a href="logout.php">Выход</a>
    </div>

    <h1>Управление пользователями</h1>

    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post" action="users.php" id="mainDisplayForm"> <table border="1">
            <thead>
            <tr>
                <th style="width: 30px;"><input type="checkbox" id="select_all_users"></th> <th>ID</th>
                <th>Полное имя</th> <th>Дата рождения</th> <th>Телефон</th> <th>Email</th> <th>Фото</th>
                <th>Дата регистрации</th> <th>Водительское удостоверение</th> <th>Баланс (руб.)</th> <th style="width: 100px;">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php if ($editUserId === $user['id']): ?>
                    <?php
                    $userToEdit = null;
                    foreach ($users as $u) {
                        if ($u['id'] === $editUserId) {
                            $userToEdit = $u;
                            break;
                        }
                    }
                    // If somehow the user ID in GET doesn't match a user, show error or redirect
                    if (!$userToEdit) {
                        // Handle error: User not found for editing
                        // $error = "Пользователь с ID " . htmlspecialchars($editUserId) . " не найден для редактирования.";
                        // $editUserId = null; // Exit edit mode
                        // Or redirect: header("Location: users.php"); exit();
                        // For now, let's assume it's always found because it comes from the list
                        $user = $userToEdit; // Use the found user data
                    }
                    ?>
                    <tr>
                        <td></td>
                        <form method="post" action="users.php" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']) ?>">
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><input type="text" name="fullName" value="<?= htmlspecialchars($user['fullName']) ?>" required></td> <td><input type="date" name="birthdate" value="<?= htmlspecialchars($user['birthdate']) ?>"></td> <td><input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required></td> <td><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required></td> <td class="photo-cell-content">
                                <?php
                                $current_photo_filename = $user['photo'];
                                $current_photo_path = $current_photo_filename ? $photo_upload_dir . $current_photo_filename : null;
                                $current_photo_exists = $current_photo_path && file_exists($current_photo_path);
                                ?>
                                <?php if ($current_photo_exists): ?>
                                    <img src="../uploads/<?= htmlspecialchars($current_photo_filename) ?>" alt="Фото <?= htmlspecialchars($user['fullName']) ?>" class="user-photo"> Текущее<br>
                                    <!-- ADD DELETE PHOTO CHECKBOX HERE -->
                                    <label>
                                        <input type="checkbox" name="delete_photo" value="1"> Удалить фото
                                    </label>
                                    <br>
                                <?php endif; ?>
                                <label for="photo_new_<?= htmlspecialchars($user['id']) ?>">Заменить:</label>
                                <input type="file" name="photo_new" id="photo_new_<?= htmlspecialchars($user['id']) ?>">
                            </td>
                            <td><?= formatDate($user['reg_date']) ?></td> <td><input type="text" name="license" value="<?= htmlspecialchars($user['license']) ?>"></td> <td><input type="number" step="0.01" name="balance" value="<?= htmlspecialchars($user['balance']) ?>" required></td> <td>
                                <div class="controls">
                                    <button type="submit" name="update" title="Сохранить">💾</button>
                                    <a href="users.php" title="Отмена">❌</a> </div>
                            </td>
                        </form>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td><input type="checkbox" name="selected_users[]" value="<?= htmlspecialchars($user['id']) ?>" form="bulkActionsForm"></td> <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['fullName']) ?></td> <td><?= formatDate($user['birthdate']) ?></td> <td><?= htmlspecialchars($user['phone']) ?></td> <td><?= htmlspecialchars($user['email']) ?></td> <td class="photo-cell-content">
                            <?php if ($user['photo']): ?>
                                <img src="../uploads/<?= htmlspecialchars($user['photo']) ?>" alt="Фото <?= htmlspecialchars($user['fullName']) ?>" class="user-photo"> <?php else: ?>
                                Нет фото
                            <?php endif; ?>
                        </td>
                        <td><?= formatDate($user['reg_date']) ?></td> <td><?= htmlspecialchars($user['license']) ?></td> <td><?= number_format($user['balance'], 2) ?></td> <td>
                            <div class="controls">
                                <a href="?edit=<?= htmlspecialchars($user['id']) ?>" title="Редактировать">✏️</a>
                                <a href="?delete=<?= htmlspecialchars($user['id']) ?>" onclick="return confirm('Вы уверены, что хотите удалить этого пользователя (ID: <?= htmlspecialchars($user['id'] )?>)?')" title="Удалить">🗑️</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </form> <!-- Add New User Form -->
    <form method="post" action="users.php" enctype="multipart/form-data" class="add-row-form">
        <table class="add-row-form-table">
            <tbody>
            <tr>
                <td style="width: 30px;"></td>
                <td>#</td>
                <td><input type="text" name="fullName" placeholder="Полное имя" required></td>
                <td><input type="date" name="birthdate"></td>
                <td><input type="text" name="phone" placeholder="Телефон" required></td>
                <td><input type="email" name="email" placeholder="Email" required></td>
                <td class="photo-cell-content">
                    <label for="photo_add">Добавить фото:</label>
                    <input type="file" name="photo" id="photo_add">
                </td>
                <td>-</td>
                <td><input type="text" name="license" placeholder="Лицензия"></td>
                <td><input type="number" step="0.01" name="balance" value="0.00"></td>
                <td style="width: 100px;">
                    <div class="controls">
                        <button type="submit" name="add" title="Добавить">➕</button>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </form>

    <!-- Bulk actions form -->
    <form method="post" action="users.php" id="bulkActionsForm" enctype="multipart/form-data">
        <div class="bulk-actions">
            <h3>Массовые действия с выбранными:</h3>
            <div>
                <button type="submit" name="bulk_action" value="delete" form="bulkActionsForm" onclick="return confirm('Выверены, что хотите удалить выбранных пользователей?');">Удалить выбранных</button>
            </div>
            <div>
                <label for="bulk_balance">Новый баланс:</label>
                <input type="number" step="0.01" name="bulk_balance" id="bulk_balance" placeholder="0.00" form="bulkActionsForm">
                <button type="submit" name="bulk_action" value="update_balance" form="bulkActionsForm">Изменить баланс выбранных</button>
            </div>
            <div>
                <label for="bulk_photo">Новое фото:</label>
                <input type="file" name="bulk_photo" id="bulk_photo" form="bulkActionsForm">
                <button type="submit" name="bulk_action" value="update_photo" form="bulkActionsForm" onclick="return confirm('Выверены, что хотите обновить фото для выбранных пользователей?');">Изменить фото выбранных</button>
            </div>
        </div>
    </form>

    <?php if (isset($success_message)): ?>
        <p class="success"><?= htmlspecialchars($success_message) ?></p>
    <?php endif; ?>

    <script>
        // JavaScript for "Select All" checkbox
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select_all_users');
            const individualCheckboxes = document.querySelectorAll('input[name="selected_users[]"][form="bulkActionsForm"]');

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
            const bulkActionButtons = document.querySelectorAll('#bulkActionsForm button[type="submit"]');
            bulkActionButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    const selectedCheckboxes = document.querySelectorAll('input[name="selected_users[]"][form="bulkActionsForm"]:checked');

                    if (selectedCheckboxes.length === 0) {
                        alert('Пожалуйста, выберите хотя бы одного пользователя.');
                        event.preventDefault();
                        return;
                    }

                    // Additional validation based on the specific bulk action
                    if (button.value === 'update_balance') {
                        const balanceInput = document.getElementById('bulk_balance');
                        if (balanceInput.value.trim() === '' || isNaN(parseFloat(balanceInput.value)) || parseFloat(balanceInput.value) < 0) {
                            alert('Пожалуйста, укажите корректный неотрицательный баланс.');
                            event.preventDefault();
                        }
                    } else if (button.value === 'update_photo') {
                        const photoInput = document.getElementById('bulk_photo');
                        if (photoInput.files.length === 0) {
                            alert('Пожалуйста, выберите файл фотографии.');
                            event.preventDefault();
                        }
                    }
                    // No validation needed for delete beyond the confirm dialog
                });
            });

        });
    </script>

</div>
</body>
</html>