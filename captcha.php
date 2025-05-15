<?php
session_start();

// Функция генерации CAPTCHA (скопирована из вашего кода)
function generateCaptchaImage() {
    $captchaText = substr(str_shuffle("0123456789"), 0, 6);
    $_SESSION['captcha'] = $captchaText; // Сохраняем код в сессию

    $width = 200;
    $height = 50;
    $image = imagecreatetruecolor($width, $height);

    $bgColor = imagecolorallocate($image, 245, 245, 245);
    $textColor = imagecolorallocate($image, 30, 30, 30);
    $lineColor = imagecolorallocate($image, 100, 100, 100);
    $noiseColor = imagecolorallocate($image, 150, 150, 150);

    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

    // Добавляем линии
    for ($i = 0; $i < 5; $i++) {
        imageline($image, 0, rand(0, $height), $width, rand(0, $height), $lineColor);
    }

    // Добавляем шум
    for ($i = 0; $i < 100; $i++) {
        imagesetpixel($image, rand(0, $width), rand(0, $height), $noiseColor);
    }

    // Используем встроенный шрифт GD
    $font = 5;
    $textWidth = imagefontwidth($font) * strlen($captchaText);
    $textHeight = imagefontheight($font);
    $x = (int)(($width - $textWidth) / 2);
    $y = (int)(($height - $textHeight) / 2);

    imagestring($image, $font, $x, $y, $captchaText, $textColor);

    // Отправляем заголовок, указывающий, что это изображение PNG
    header('Content-type: image/png');

    // Выводим изображение в браузер
    imagepng($image);

    // Очищаем память
    imagedestroy($image);

    exit(); // Обязательно завершаем скрипт после вывода изображения
}

// Генерируем и выводим изображение CAPTCHA
generateCaptchaImage();
?>