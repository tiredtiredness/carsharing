<?php
/**
 * Пользовательский обработчик ошибок для математических операций
 */

class MathErrorHandler {
    // Ассоциативный массив типов ошибок
    private static $errorTypes = [
        E_ERROR             => 'Fatal Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parse Error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_ERROR        => 'Math Error',
        E_USER_WARNING      => 'Math Warning',
        E_USER_NOTICE       => 'Math Notice',
        E_STRICT            => 'Strict Standards',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'User Deprecated'
    ];

    // Типы ошибок, для которых будем сохранять стек переменных
    private static $trackedErrors = [E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE];

    /**
     * Обработчик ошибок
     */
    public static function handle($errno, $errmsg, $filename, $linenum, $vars = []) {
        $timestamp = date("Y-m-d H:i:s (T)");

        $errorEntry = "<math_error>\n";
        $errorEntry .= "\t<timestamp>" . htmlspecialchars($timestamp) . "</timestamp>\n";
        $errorEntry .= "\t<type>" . (self::$errorTypes[$errno] ?? 'Unknown') . "</type>\n";
        $errorEntry .= "\t<code>" . $errno . "</code>\n";
        $errorEntry .= "\t<message>" . htmlspecialchars($errmsg) . "</message>\n";
        $errorEntry .= "\t<file>" . htmlspecialchars($filename) . "</file>\n";
        $errorEntry .= "\t<line>" . $linenum . "</line>\n";

        // Для важных ошибок добавляем стек переменных
        if (in_array($errno, self::$trackedErrors)) {
            $errorEntry .= "\t<variables>\n";
            foreach ($vars as $key => $value) {
                $errorEntry .= "\t\t<var name=\"" . htmlspecialchars($key) . "\">" .
                    htmlspecialchars(print_r($value, true)) . "</var>\n";
            }
            $errorEntry .= "\t</variables>\n";
        }

        $errorEntry .= "</math_error>\n\n";

        // Записываем ошибку в лог-файл
        file_put_contents(__DIR__ . '/math_errors.log', $errorEntry, FILE_APPEND);

        // выводим сообщение
        if ($errno === E_USER_ERROR) {
            echo "<div style='color:red;padding:10px;margin:10px;border:1px solid #f00;'>";
            echo "<strong>MATH ERROR:</strong> " . htmlspecialchars($errmsg);
            echo "</div>";
        }
        if ($errno === E_USER_WARNING) {
            echo "<div style='color:red;padding:10px;margin:10px;border:1px solid #f00;'>";
            echo "<strong>MATH WARNING:</strong> " . htmlspecialchars($errmsg);
            echo "</div>";
        }

        // Не выполняем стандартный обработчик PHP
        return true;
    }
}

/**
 * Функция для вычисления расстояния между векторами
 */
function vectorDistance(array $vec1, array $vec2): float {
    // Проверка типов аргументов
    if (!is_array($vec1) || !is_array($vec2)) {
        trigger_error("Оба аргумента должны быть массивами", E_USER_ERROR);
        return NAN;
    }

    // Проверка размерности векторов
    if (count($vec1) !== count($vec2)) {
        trigger_error("Векторы должны иметь одинаковую размерность", E_USER_ERROR);
        return NAN;
    }

    // Проверка числовых значений
    foreach ($vec1 as $i => $value) {
        if (!is_numeric($value)) {
            trigger_error("Элемент вектора 1 с индексом $i не является числом (используется 0)", E_USER_WARNING);
            $vec1[$i] = 0;
        }
    }

    foreach ($vec2 as $i => $value) {
        if (!is_numeric($value)) {
            trigger_error("Элемент вектора 2 с индексом $i не является числом (используется 0)", E_USER_WARNING);
            $vec2[$i] = 0;
        }
    }

    // Вычисление расстояния
    $sum = 0;
    foreach ($vec1 as $i => $value) {
        $sum += pow($value - $vec2[$i], 2);
    }

    return sqrt($sum);
}

/**
 * Функция для деления чисел с проверкой деления на ноль
 */
function safeDivide(float $a, float $b): float {
    if ($b == 0) {
        trigger_error("Деление на ноль невозможно", E_USER_ERROR);
        return INF;
    }
    return $a / $b;
}

// Устанавливаем наш обработчик ошибок
set_error_handler(['MathErrorHandler', 'handle']);
?>