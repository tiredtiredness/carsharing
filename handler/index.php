<?php
require_once 'math_errors.php';

echo "<h2>Тестирование математических функций</h2>";

// Тест 1: Корректные векторы
$vec1 = [1, 2, 3];
$vec2 = [4, 5, 6];
echo "<p>Расстояние между [1,2,3] и [4,5,6]: " . vectorDistance($vec1, $vec2) . "</p>";

// Тест 2: Разная размерность векторов
$vec3 = [1, 2];
echo "<p>Попытка сравнить векторы разной размерности:</p>";
$result = vectorDistance($vec1, $vec3);
echo "<p>Результат: " . (is_nan($result) ? 'NaN' : $result) . "</p>";

// Тест 3: Нечисловые элементы в векторах
$vec4 = [1, 'two', 3];
echo "<p>Попытка сравнить векторы с нечисловыми элементами:</p>";
$result = vectorDistance($vec1, $vec4);
echo "<p>Результат: " . $result . "</p>";

// Тест 4: Деление чисел
echo "<p>10 / 2 = " . safeDivide(10, 2) . "</p>";

// Тест 5: Деление на ноль
echo "<p>Попытка деления на ноль:</p>";
$result = safeDivide(10, 0);
echo "<p>Результат: " . ($result === INF ? 'INF' : $result) . "</p>";

?>