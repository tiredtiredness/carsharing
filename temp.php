<?php
require 'db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS temp_cars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    number VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    rate DECIMAL(10,2) NOT NULL,
    inspection_date DATE,
    description VARCHAR(500)
) ENGINE=MEMORY");

// Копируем данные из основных таблиц во временную
$count = $pdo->query("SELECT COUNT(*) FROM temp_cars")->fetchColumn();
if ($count == 0) {
    // Используем ваш базовый запрос
    $sql = "SELECT car.id, car.brand, car.model, car.number, car.status, car.rate, 
                   inspection.date AS inspection_date, inspection.description 
            FROM car
            LEFT JOIN inspection ON car.id = inspection.carId";

    $stmt = $pdo->query($sql);
    $carsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Вставляем данные во временную таблицу
    $insertStmt = $pdo->prepare("INSERT INTO temp_cars 
                                (brand, model, number, status, rate, inspection_date, description) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($carsData as $car) {
        $insertStmt->execute([
            $car['brand'],
            $car['model'],
            $car['number'],
            $car['status'],
            $car['rate'],
            $car['inspection_date'],
            $car['description']
        ]);
    }
}

// Получение всех машин
$cars = $pdo->query("SELECT * FROM temp_cars")->fetchAll(PDO::FETCH_ASSOC);

// Функция для форматирования даты
function formatDate($date)
{
    return $date ? date('d.m.Y', strtotime($date)) : '';
}

// Список возможных статусов
$statuses = ["доступна", "ремонт", "арендована"];

// Если нажата кнопка "Редактировать", загружаем данные
$editCar = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM temp_cars WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Обновление данных
if (isset($_POST['update'])) {
    $stmt = $pdo->prepare("UPDATE temp_cars SET 
                          brand = ?, model = ?, number = ?, status = ?, 
                          rate = ?, inspection_date = ?, description = ? 
                          WHERE id = ?");
    $stmt->execute([
        $_POST['brand'],
        $_POST['model'],
        $_POST['number'],
        $_POST['status'],
        $_POST['rate'],
        $_POST['inspection_date'],
        $_POST['description'],
        $_POST['id']
    ]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Удаление машины
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM temp_cars WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Добавление новой машины
if (isset($_POST['add'])) {
    $stmt = $pdo->prepare("INSERT INTO temp_cars (brand, model, number, status, rate, inspection_date, description) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['brand'],
        $_POST['model'],
        $_POST['number'],
        $_POST['status'],
        $_POST['rate'],
        $_POST['inspection_date'],
        $_POST['description']
    ]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление автомобилями</title>
    <style>
        :root {
            --primary-blue: #1E90FF;
            --primary-green: #4CAF50;
            --primary-gray: #F5F5F5;
            --primary-orange: #FF8C00;
            --primary-red: #FF5252;
            --text-dark: #212121;
            --text-gray: #616161;
            --white: #FFFFFF;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--primary-gray);
            margin: 0;
            padding: 20px;
        }

        h1 {
            color: var(--text-gray);
            text-align: center;
            margin-bottom: 30px;
        }

        a {
            text-decoration: none;

        }

        .controls {
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .controls button {
            background-color: transparent;
            font-size: 16px;
            line-height: 1.6;
            padding: 0;
            transition: transform 0.2s;
        }

        .controls button:hover,
        .controls a:hover {
            transform: scale(1.1);
            transition: transform 0.2s;
        }

        .main-page-link {
            position: absolute;
            top: 20px;
            right: 20px;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: var(--text-gray);
            color: var(--white);
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .main-page-link:hover {
            background-color: var(--text-dark);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px auto;
            background-color: var(--white);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #007BFF;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #e9e9e9;
        }

        input,
        select,
        textarea {
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        button,
        .btn {
            padding: 8px 12px;
            cursor: pointer;
            border: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .btn-edit {
            background-color: #FFC107;
            color: #212529;
        }

        .btn-delete {
            background-color: #DC3545;
            color: white;
        }

        .btn-save {
            background-color: #28A745;
            color: white;
        }

        .btn-cancel {
            background-color: #6C757D;
            color: white;
        }

        .btn-add {
            background-color: #17A2B8;
            color: white;
        }

        .edit-row input,
        .edit-row select,
        .edit-row textarea {
            width: 90%;
        }
    </style>
</head>

<body>

    <h1>Управление автомобилями</h1>
    <a class="main-page-link" href="index.php">На главную</a>
    <!-- Таблица -->
    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>Марка</th>
                <th>Модель</th>
                <th>Номер</th>
                <th>Статус</th>
                <th>Тариф</th>
                <th>Дата проверки</th>
                <th>Описание</th>
                <th style="width: 80px;">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cars as $car): ?>
                <?php if ($editCar && $editCar['id'] == $car['id']): ?>
                    <!-- Форма редактирования -->
                    <tr>
                        <form method="post">
                            <input type="hidden" name="id" value="<?= $editCar['id'] ?>">
                            <td><?= $editCar['id'] ?></td>
                            <td><input type="text" name="brand" value="<?= htmlspecialchars($editCar['brand']) ?>" required></td>
                            <td><input type="text" name="model" value="<?= htmlspecialchars($editCar['model']) ?>" required></td>
                            <td><input type="text" name="number" value="<?= htmlspecialchars($editCar['number']) ?>" required></td>
                            <td>
                                <select name="status" required>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= $status ?>" <?= ($editCar['status'] == $status) ? 'selected' : '' ?>>
                                            <?= $status ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" name="rate" value="<?= $editCar['rate'] ?>" required></td>
                            <td><input type="date" name="inspection_date" value="<?= $editCar['inspection_date'] ?>"></td>
                            <td><textarea name="description"><?= htmlspecialchars($editCar['description']) ?></textarea></td>
                            <td>
                                <div class="controls">
                                    <button type="submit" name="update" title="Сохранить">💾</button>
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>" title="Отмена">❌</a>
                                </div>
                            </td>
                        </form>
                    </tr>
                <?php else: ?>
                    <!-- Обычная строка -->
                    <tr>
                        <td><?= $car['id'] ?></td>
                        <td><?= htmlspecialchars($car['brand']) ?></td>
                        <td><?= htmlspecialchars($car['model']) ?></td>
                        <td><?= htmlspecialchars($car['number']) ?></td>
                        <td><?= htmlspecialchars($car['status']) ?></td>
                        <td><?= number_format($car['rate'], 2) ?></td>
                        <td><?= formatDate($car['inspection_date']) ?></td>
                        <td><?= htmlspecialchars($car['description']) ?></td>
                        <td>
                            <div class="controls">
                                <a href="?edit=<?= $car['id'] ?>" title="Редактировать">✏️</a>
                                <a href="?delete=<?= $car['id'] ?>" onclick="return confirm('Удалить этот автомобиль?')" title="Удалить">🗑️</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Форма добавления новой машины -->
            <tr>
                <form method="post">
                    <td>#</td>
                    <td><input type="text" name="brand" required></td>
                    <td><input type="text" name="model" required></td>
                    <td><input type="text" name="number" placeholder="a000aa" required></td>
                    <td>
                        <select name="status" required>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= $status ?>"><?= $status ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" step="0.01" name="rate" required></td>
                    <td><input type="date" name="inspection_date" value="<?= date('Y-m-d') ?>"></td>
                    <td><textarea name="description"></textarea></td>
                    <td>
                        <div class="controls"><button type="submit" name="add" title="Добавить">➕</button></div>
                    </td>
                </form>
            </tr>
        </tbody>
    </table>

</body>

</html>