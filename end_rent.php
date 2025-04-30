<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$rentId = $_POST['rent_id'];
$userId = $_SESSION['user']['id'];

// Получаем данные аренды и машины
$stmt = $pdo->prepare("SELECT rent.*, car.rate FROM rent JOIN car ON rent.carId = car.id WHERE rent.id = ? AND rent.userId = ?");
$stmt->execute([$rentId, $userId]);
$rent = $stmt->fetch();

if ($rent && $rent['endTime'] === null) {
    // Убедимся, что время сервера правильное
    date_default_timezone_set('Europe/Moscow');

    $start = new DateTime($rent['startTime']);
    $end = new DateTime();

    // Проверка, что конечное время больше начального
    if ($end < $start) {
        // Если время неправильное, используем текущее время сервера
        $end = new DateTime();
    }

    // Рассчитываем точное количество минут
    $totalSeconds = $end->getTimestamp() - $start->getTimestamp();
    $totalMinutes = max(0, floor($totalSeconds / 60)); // Гарантируем неотрицательное значение

    $amount = $totalMinutes * $rent['rate'];

    // Для отладки
    error_log("Calculated amount: $amount, Minutes: $totalMinutes, Rate: {$rent['rate']}, Start: {$rent['startTime']}, End: " . $end->format('Y-m-d H:i:s'));

    // Обновляем аренду
    $stmt = $pdo->prepare("UPDATE rent SET endTime = NOW() WHERE id = ?");
    $stmt->execute([$rentId]);

    // Обновляем баланс пользователя (уменьшаем баланс)
    $stmt = $pdo->prepare("UPDATE user SET balance = balance - ? WHERE id = ?");
    $stmt->execute([$amount, $userId]);

    // Обновляем статус машины
    $stmt = $pdo->prepare("UPDATE car SET status = 'доступна' WHERE id = ?");
    $stmt->execute([$rent['carId']]);

    // Обновляем данные пользователя в сессии
    $stmt = $pdo->prepare("SELECT balance FROM user WHERE id = ?");
    $stmt->execute([$userId]);
    $_SESSION['user']['balance'] = $stmt->fetchColumn();
}

header("Location: profile.php");
exit();
