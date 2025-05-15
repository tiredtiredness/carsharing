<?php
// admin/news.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Define JSON file path (one level up from admin folder)
$json_file_path = __DIR__ . '/../news.json';

// --- JSON File Handling Functions ---

function readNews($jsonFilePath, &$error) {
    if (!file_exists($jsonFilePath)) {
        // If file doesn't exist, return an empty array (assume no news yet)
        // In a real app, you might want to create the file or show a warning
        error_log("News JSON file not found: " . $jsonFilePath);
        return [];
    }

    $json_data = file_get_contents($jsonFilePath);
    if ($json_data === false) {
        $error = "Ошибка чтения файла новостей.";
        error_log("Failed to read news JSON file: " . $jsonFilePath);
        return [];
    }

    // Allow empty file or empty JSON array []
    if (trim($json_data) === '' || trim($json_data) === '[]') {
        return [];
    }

    $newsArray = json_decode($json_data, true);
    if ($newsArray === null && json_last_error() !== JSON_ERROR_NONE) {
        $error = "Ошибка декодирования JSON файла новостей: " . json_last_error_msg();
        error_log("JSON decode error in " . $jsonFilePath . ": " . json_last_error_msg());
        return [];
    }

    // Ensure it's an array
    if (!is_array($newsArray)) {
        $error = "Некорректная структура данных в файле новостей (ожидался массив).";
        error_log("Invalid data structure in news JSON file: " . $jsonFilePath);
        return [];
    }

    return $newsArray;
}

function writeNews($jsonFilePath, $newsArray, &$error) {
    $json_data = json_encode($newsArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json_data === false) {
        $error = "Ошибка кодирования данных новостей в JSON: " . json_last_error_msg();
        error_log("JSON encode error: " . json_last_error_msg());
        return false;
    }

    // Use LOCK_EX to prevent race conditions during writing
    // Check if directory is writable
    $dir = dirname($jsonFilePath);
    if (!is_writable($dir)) {
        $error = "Ошибка записи файла: Директория недоступна для записи.";
        error_log("Directory not writable for news JSON: " . $dir);
        return false;
    }

    if (file_put_contents($jsonFilePath, $json_data, LOCK_EX) === false) {
        $error = "Ошибка записи файла новостей. Проверьте права доступа.";
        error_log("Failed to write news JSON file: " . $jsonFilePath);
        return false;
    }

    return true; // Success
}

function getNextNewsId($newsArray) {
    $maxId = 0;
    foreach ($newsArray as $item) {
        if (isset($item['id']) && is_numeric($item['id'])) {
            $maxId = max($maxId, $item['id']);
        }
    }
    return $maxId + 1;
}

// Variable to store errors
$error = null;

// Load existing news data
$news = readNews($json_file_path, $error);

// Variable to store success messages
$success_message = '';

// --- CRUD Operations ---

// Handle ADD
if (isset($_POST['add'])) {
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($date) || empty($content)) {
        $error = "Пожалуйста, заполните все обязательные поля для добавления новости.";
    } elseif (!strtotime($date)) { // Basic date format validation
        $error = "Некорректный формат даты.";
    }
    else {
        // Get the next ID
        $nextId = getNextNewsId($news);

        // Create the new news item
        $newNewsItem = [
            'id' => $nextId,
            'title' => $title,
            'date' => $date, // Store date as provided (YYYY-MM-DD)
            'content' => $content,
        ];

        // Add to the array
        $news[] = $newNewsItem;

        // Write the updated array back to the file
        if (writeNews($json_file_path, $news, $error)) {
            $_SESSION['success_message'] = "Новость успешно добавлена (ID: " . $nextId . ").";
            header("Location: news.php"); // Redirect
            exit();
        }
        // If write failed, $error is set by writeNews function
    }
}

// Handle UPDATE
if (isset($_POST['update'])) {
    $id_to_update = $_POST['id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $content = trim($_POST['content'] ?? '');

    // Validate ID
    $id_to_update = is_numeric($id_to_update) ? (int)$id_to_update : null;

    if ($id_to_update === null || empty($title) || empty($date) || empty($content)) {
        $error = "Пожалуйста, заполните все обязательные поля для обновления новости и убедитесь, что ID корректен.";
    } elseif (!strtotime($date)) {
        $error = "Некорректный формат даты.";
    }
    else {
        $found_key = null;
        foreach ($news as $key => $item) {
            if (isset($item['id']) && $item['id'] === $id_to_update) {
                $found_key = $key;
                break;
            }
        }

        if ($found_key === null) {
            $error = "Новость с ID " . htmlspecialchars($id_to_update) . " не найдена.";
        } else {
            // Update the item in the array
            $news[$found_key]['title'] = $title;
            $news[$found_key]['date'] = $date; // Store date as provided
            $news[$found_key]['content'] = $content;

            // Write the updated array back to the file
            if (writeNews($json_file_path, $news, $error)) {
                $_SESSION['success_message'] = "Новость ID: " . htmlspecialchars($id_to_update) . " успешно обновлена.";
                header("Location: news.php"); // Redirect
                exit();
            }
            // If write failed, $error is set by writeNews function
        }
    }
}

// Handle DELETE
if (isset($_GET['delete'])) {
    $id_to_delete = $_GET['delete'] ?? null;

    // Validate ID
    $id_to_delete = is_numeric($id_to_delete) ? (int)$id_to_delete : null;

    if ($id_to_delete === null) {
        $error = "Некорректный ID новости для удаления.";
    } else {
        $initial_count = count($news);
        // Filter out the item to delete
        $news = array_filter($news, function($item) use ($id_to_delete) {
            return !isset($item['id']) || $item['id'] !== $id_to_delete;
        });

        // Re-index the array if desired (keeps sequential numerical keys)
        // $news = array_values($news); // Keep original keys if you prefer

        if (count($news) === $initial_count) {
            $error = "Новость с ID " . htmlspecialchars($id_to_delete) . " не найдена для удаления.";
        } else {
            // Write the updated array back to the file
            if (writeNews($json_file_path, $news, $error)) {
                $_SESSION['success_message'] = "Новость ID: " . htmlspecialchars($id_to_delete) . " успешно удалена.";
                header("Location: news.php"); // Redirect
                exit();
            }
            // If write failed, $error is set by writeNews function
        }
    }
}


// --- Handle messages after redirect ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get the ID of the news item being edited from the URL
$editNewsId = $_GET['edit'] ?? null;
// Ensure edit ID is an integer if set
$editNewsId = is_numeric($editNewsId) ? (int)$editNewsId : null;


?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление новостями</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .container { max-width: 900px; margin: auto; }
        h1 { text-align: center; margin-bottom: 20px; }
        .header-links { text-align: right; margin-bottom: 20px; }
        .header-links a { margin-left: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        input[type="text"], input[type="date"], textarea { width: 100%; padding: 5px; box-sizing: border-box; }
        textarea { height: 100px; resize: vertical; } /* Make textarea larger for content */
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
        /* Specific styles for news table columns if needed */
        .news-description-cell { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; } /* Prevent long text wrapping */
        .news-content-cell { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .news-date-cell { width: 100px; }


        /* Style for the separate add row form/table */
        .add-row-form-table { margin-top: 20px; width: 100%; border-collapse: collapse; border: 1px solid #ccc; } /* Added border */
        .add-row-form-table th, .add-row-form-table td { border: 1px solid #ddd; padding: 8px; vertical-align: top;}
        .add-row-form-table th { background-color: #f2f2f2; text-align: left;}
        .add-row-form-table input[type="text"],
        .add-row-form-table input[type="date"],
        .add-row-form-table textarea {
            width: 100%;
            padding: 5px;
            box-sizing: border-box;
        }
        .add-row-form-table textarea { height: 100px;}
        .add-row-form-table .controls { display: flex; gap: 5px; }
        .add-row-form-table button[type="submit"] {
            padding: 5px 10px;
            background-color: #5cb85c;
            color: white;
            border: 1px solid #4cae4c;
            border-radius: 4pt;
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

    <h1>Управление новостями</h1>

    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if (isset($success_message)): ?>
        <p class="success"><?= htmlspecialchars($success_message) ?></p>
    <?php endif; ?>


    <!-- News List Table -->
    <table border="1">
        <thead>
        <tr>
            <th>ID</th>
            <th>Дата</th>
            <th>Заголовок</th>
            <th>Содержание</th>
            <th style="width: 100px;">Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($news)): ?>
            <tr>
                <td colspan="5" style="text-align: center;">Новостей пока нет.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($news as $item): ?>
                <?php
                // Ensure item has required keys and valid ID
                if (!isset($item['id'], $item['title'], $item['date'], $item['content']) || !is_numeric($item['id'])) {
                    // Skip malformed items or log a warning
                    continue;
                }
                $item['id'] = (int)$item['id']; // Ensure ID is integer
                ?>
                <?php if ($editNewsId === $item['id']): // Use === for strict comparison ?>
                    <!-- Edit Row Form -->
                    <tr>
                        <form method="post" action="news.php">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                            <td><?= htmlspecialchars($item['id']) ?></td>
                            <td><input type="date" name="date" value="<?= htmlspecialchars($item['date']) ?>" required></td>
                            <td><input type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>" required></td>
                            <td><textarea name="content" required><?= htmlspecialchars($item['content']) ?></textarea></td>
                            <td>
                                <div class="controls">
                                    <button type="submit" name="update" title="Сохранить">💾</button>
                                    <a href="news.php" title="Отмена">❌</a>
                                </div>
                            </td>
                        </form>
                    </tr>
                <?php else: ?>
                    <!-- Display Row -->
                    <tr>
                        <td><?= htmlspecialchars($item['id']) ?></td>
                        <td class="news-date-cell"><?= htmlspecialchars($item['date']) ?></td>
                        <td class="news-description-cell"><?= htmlspecialchars($item['title']) ?></td>
                        <td class="news-content-cell"><?= htmlspecialchars($item['content']) ?></td>
                        <td>
                            <div class="controls">
                                <a href="?edit=<?= htmlspecialchars($item['id']) ?>" title="Редактировать">✏️</a>
                                <a href="?delete=<?= htmlspecialchars($item['id']) ?>" onclick="return confirm('Вы уверены, что хотите удалить эту новость (ID: <?= htmlspecialchars($item['id']) ?>)?')" title="Удалить">🗑️</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>


    <!-- Form for Adding a New Row (Separate Form) -->
    <form method="post" action="news.php" class="add-row-form">
        <table class="add-row-form-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Дата</th>
                <th>Заголовок</th>
                <th>Содержание</th>
                <th>Действие</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>#</td>
                <td><input type="date" name="date" value="<?= date('Y-m-DD') ?>" required></td> <!-- Default to today -->
                <td><input type="text" name="title" placeholder="Заголовок новости" required></td>
                <td><textarea name="content" placeholder="Содержание новости" required></textarea></td>
                <td style="width: 100px;">
                    <div class="controls">
                        <button type="submit" name="add" title="Добавить">➕</button>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </form>


    <!-- No bulk actions requested for news -->


</div>
</body>
</html>