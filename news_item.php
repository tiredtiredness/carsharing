<?php
session_start();
$newsFile = 'news.json';
$news = [];

if (file_exists($newsFile)) {
    $json = file_get_contents($newsFile);
    $news = json_decode($json, true);
}

$currentNews = null;

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    foreach ($news as $item) {
        if ($item['id'] == $id) {
            $currentNews = $item;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title><?= $currentNews ? htmlspecialchars($currentNews['title']) : "Новость не найдена" ?> | CarShare</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/news_item.css">
</head>

<body>

    <?php include 'header.php'; ?>

    <main class="main">
        <section class="news-item">
            <?php if ($currentNews): ?>
                <article class="news-item__full">
                    <h1 class="news-item__title"><?= htmlspecialchars($currentNews['title']) ?></h1>
                    <time datetime="<?= $currentNews['date'] ?>" class="news-item__date"><?= date('d.m.Y', strtotime($currentNews['date'])) ?></time>
                    <div class="news-item__content"><?= nl2br(htmlspecialchars($currentNews['content'])) ?></div>
                    <p class="news-item__back-wrapper"><a href="news.php" class="news-item__back-link">Вернуться к новостям</a></p>
                </article>
            <?php else: ?>
                <p class="news-item__not-found">Новость не найдена.</p>
            <?php endif; ?>
        </section>
    </main>

    <?php include 'footer.php'; ?>

</body>

</html>