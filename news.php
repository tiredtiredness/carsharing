<?php
session_start();
$newsFile = 'news.json';
$news = [];

if (file_exists($newsFile)) {
    $json = file_get_contents($newsFile);
    $news = json_decode($json, true);
} else {
    $news = [];
}
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
                        <a href="news_item.php?id=<?= $item['id'] ?>" class="news__card-link">
                            <article class="news__card">
                                <h3 class="news__card-title"><?= htmlspecialchars($item['title']) ?></h3>
                                <time datetime="<?= $item['date'] ?>" class="news__card-date"><?= date('d.m.Y', strtotime($item['date'])) ?></time>
                                <p class="news__card-content"><?= mb_strimwidth(htmlspecialchars($item['content']), 0, 120, "...") ?></p>
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