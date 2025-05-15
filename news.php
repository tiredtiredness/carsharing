<?php
session_start();
date_default_timezone_set('Europe/Moscow'); // Установите вашу временную зону, если нужно

$newsFile = 'news.json';
$news = [];

if (file_exists($newsFile)) {
    $json = file_get_contents($newsFile);
    // Убедимся, что файл не пустой перед декодированием
    if ($json !== false && trim($json) !== '') {
        $news = json_decode($json, true);
        // Проверим, что результат декодирования - массив
        if (!is_array($news)) {
            $news = []; // Если JSON некорректен, начинаем с пустого массива
            // Можно добавить логирование ошибки json_last_error_msg()
        }
    } else {
        $news = []; // Файл пустой или ошибка чтения
    }
} else {
    $news = []; // Файл не существует
}

// --- Сортировка новостей по дате в убывающем порядке (сначала новые) ---
if (!empty($news)) {
    usort($news, function($a, $b) {
        // Преобразуем даты в метки времени для сравнения
        $timestampA = strtotime($a['date']);
        $timestampB = strtotime($b['date']);

        if ($timestampA == $timestampB) {
            return 0; // Даты одинаковые, порядок не важен (или можно добавить сортировку по ID)
        }

        // Для убывающего порядка (сначала новые), если дата A позже даты B,
        // A должно идти перед B, значит, возвращаем отрицательное значение.
        // Если дата A раньше даты B, A должно идти после B, возвращаем положительное.
        return ($timestampA > $timestampB) ? -1 : 1;

        // Альтернатива с использованием оператора сравнения <=> (PHP 7+)
        // return $timestampB <=> $timestampA; // Сравниваем B с A для убывающего порядка
    });
}
// --- Конец сортировки ---

?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Новости | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/news.css">
</head>

<body>

<?php include 'header.php'; ?>

<main class="main">
    <section class="about news">
        <h2 class="news__title">Последние новости</h2>

        <?php if (!empty($news)): ?>
            <div class="news__list">
                <?php foreach ($news as $item): ?>
                    <?php
                    // Проверка, что элемент содержит необходимые ключи перед отображением
                    if (!isset($item['id'], $item['title'], $item['date'], $item['content'])) {
                        // Пропускаем некорректный элемент или логируем ошибку
                        continue;
                    }
                    ?>
                    <a href="news_item.php?id=<?= htmlspecialchars($item['id']) ?>" class="news__card-link">
                        <article class="news__card">
                            <h3 class="news__card-title"><?= htmlspecialchars($item['title']) ?></h3>
                            <time datetime="<?= htmlspecialchars($item['date']) ?>" class="news__card-date"><?= date('d.m.Y', strtotime($item['date'])) ?></time>
                            <p class="news__card-content"><?= mb_strimwidth(htmlspecialchars($item['content']), 0, 120, "...", 'UTF-8') ?></p>
                        </article>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="news__empty">На данный момент новостей нет.</p>
        <?php endif; ?>
    </section>
</main>

<?php include 'footer.php'; ?>

</body>

</html>