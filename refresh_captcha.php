<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

function generateCaptcha() {
    $captchaText = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
    $_SESSION['captcha'] = $captchaText;

    $width = 200;
    $height = 30;
    $image = imagecreatetruecolor($width, $height);

    $bgColor = imagecolorallocate($image, 255, 255, 255);
    $textColor = imagecolorallocate($image, 0, 0, 0);
    $lineColor = imagecolorallocate($image, 200, 200, 200);

    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

    for ($i = 0; $i < 5; $i++) {
        imageline($image, 0, rand() % $height, $width, rand() % $height, $lineColor);
    }

    $font = 5; // Встроенный шрифт GD
    $x = 30;
    $y = 10;
    imagestring($image, $font, $x, $y, $captchaText, $textColor);

    ob_start();
    imagepng($image);
    $imageData = ob_get_clean();
    imagedestroy($image);

    return 'data:image/png;base64,' . base64_encode($imageData);
}

echo json_encode(['image' => generateCaptcha()]);
?>