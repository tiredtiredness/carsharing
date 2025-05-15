<?php
// Function to calculate the distance between two vectors
function distance($vect1, $vect2) {
    // Check if both inputs are arrays
    if (!is_array($vect1) || !is_array($vect2)) {
        trigger_error("Некорректные параметры функции, ожидаются массивы в качестве параметров", E_USER_ERROR);
        return null;
    }

    // Check if both vectors have the same size
    if (count($vect1) != count($vect2)) {
        trigger_error("Векторы должны быть одинаковой размерности", E_USER_ERROR);
        return null;
    }

    // Check if all elements in vectors are numeric
    foreach ($vect1 as $value) {
        if (!is_numeric($value)) {
            trigger_error("Координата в одном из векторов не является числом, будет использоваться ноль", E_USER_WARNING);
            return null;
        }
    }

    foreach ($vect2 as $value) {
        if (!is_numeric($value)) {
            trigger_error("Координата в одном из векторов не является числом, будет использоваться ноль", E_USER_WARNING);
            return null;
        }
    }

    // Calculate the distance
    $distance = 0;
    for ($i = 0; $i < count($vect1); $i++) {
        $distance += pow(($vect2[$i] - $vect1[$i]), 2);
    }

    return sqrt($distance);
}

// Custom error handler
function userErrorHandler($errno, $errstr, $filename, $linenum) {
    // Time of the error
    $dt = date("Y-m-d H:i:s (T)");

    // Error types
    $errortype = array(
        E_ERROR => 'Ошибка',
        E_WARNING => 'Предупреждение',
        E_NOTICE => 'Уведомление',
        E_USER_ERROR => 'Пользовательская ошибка',
        E_USER_WARNING => 'Пользовательское предупреждение',
        E_USER_NOTICE => 'Пользовательское уведомление',
    );

    // Save error to log
    $user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);

    if (in_array($errno, $user_errors)) {
        $err = "<errortype>\n";
        $err .= "<datetime>" . $dt . "</datetime>\n";
        $err .= "<errormsg>" . $errstr . "</errormsg>\n";
        $err .= "<errornumber>" . $errno . "</errornumber>\n";
        $err .= "<filename>" . $filename . "</filename>\n";
        $err .= "<scriptname>" . $_SERVER['SCRIPT_NAME'] . "</scriptname>\n";
        $err .= "<scriptlinenum>" . $linenum . "</scriptlinenum>\n";
        $err .= "</errortype>\n";

        file_put_contents("error_log.txt", $err, FILE_APPEND);
    }
}

// Set error handler
set_error_handler("userErrorHandler", E_USER_WARNING);

// Sample vectors
$a = array(2, 3, "foo");
$b = array(5.3, 4.3, -1.6);
$c = array(1, 1, 1);

// Generating user error
echo distance($a, $b) . "\n";
echo distance($a, $c) . "\n";
?>