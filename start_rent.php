<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$carId = $_POST['car_id'];
$userId = $_SESSION['user']['id'];

// Проверка доступности машины
$stmt = $pdo->prepare("SELECT * FROM car WHERE id = ? AND status = 'доступна'");
$stmt->execute([$carId]);
$car = $stmt->fetch();

if ($car) {
    // Создаем аренду
    $stmt = $pdo->prepare("INSERT INTO rent (userId, carId, startTime, endTime) VALUES (?, ?, NOW(), null)");
    $stmt->execute([$userId, $carId]);

    // Обновляем статус машины
    $stmt = $pdo->prepare("UPDATE car SET status = 'арендована' WHERE id = ?");
    $stmt->execute([$carId]);
}

header("Location: profile.php");
exit();
