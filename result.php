<?php
error_reporting(E_ALL); // отображение всех ошибок
ini_set('display_errors', 1); // отоображение ошибок прямо в браузере

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // проверяем метод отправки
    $url = filter_var($_POST['url'], FILTER_VALIDATE_URL); // проверка на url

    if ($url) {
        // Инициализация cURL для получения HTML содержимого
        $ch = curl_init(); // инициализация запроса
        curl_setopt($ch, CURLOPT_URL, $url); // указываем url по которому будет осуществляться запрос
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // получение результата в переменную
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // отслеживание переадресаций
        $response = curl_exec($ch); // осуществление запроса
        if (curl_errno($ch)) { // ловим ошибки при осуществление запроса
            echo 'Ошибка cURL: ' . curl_error($ch);
            curl_close($ch);
            exit;
        }
        curl_close($ch); // обязательно закрываем запрос, осовбождая ресурсы

        $dom = new DOMDocument(); // создание DOMDocument для полученного http ответа через curl
        @$dom->loadHTML($response); // загрузка html из полученного ответа, @ - костыль не все html объекты грузятся
        $xpath = new DOMXPath($dom); // создание DOMXPath для осуществления запросов к DOMDocument через XPath
        $nodes = $xpath->query('//img'); // поиск любых картинок в дереве html


        $images = []; // массив изображений с метаданными
        $totalSize = 0; // суммарный вес картинок в байтах
        foreach ($nodes as $node) {
            $src = $node->getAttribute('src'); // получаем ссылку на изображение

            // Преобразование относительных URL в абсолютные
            if (parse_url($src, PHP_URL_SCHEME) === null) { // проверка если в src относительная ссылка
                $base = rtrim($url, '/') . '/'; // удаляем лишие / в конце базовой url
                $src = $base . ltrim($src, '/'); // получаем абсолютную ссылку и убираем лишние / в начале относительной ссылки
            }

            // Получение размера изображения
            $headers = @get_headers($src, 1); // получаем только заголовки найденных изображений
            if ($headers && isset($headers['Content-Length'])) { // проверка на наличие заголовков и веса
                $size = (int)$headers['Content-Length']; // получаем все изображения в байтах
                $totalSize += $size; // суммируем итоговый вес изображений в байтах
                $images[] = [ // добавляем изображение с метаданными
                    'src' => $src,
                    'size' => $size
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты поиска изображений</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Результаты поиска изображений</h1>
    
    <?php if (isset($images) && count($images) > 0): ?>
        <table class="image-table">
            <?php
            $count = 0; // отслеживание каретки
            foreach ($images as $image): ?>
                <?php if ($count % 4 == 0): ?> <!-- проверка на первый элемент в строке -->
                    <tr>
                <?php endif; ?>
                <td class="image-item">
                    <img src="<?php echo htmlspecialchars($image['src']); ?>" alt="Image"> <!-- преобразование в html элемент и замещающий текст в случае не возможности загрузить картинку -->
                    <p><?php echo number_format($image['size'] / 1024, 2); ?> KB</p> <!-- Преобразование в килобайты и округление до сотых и вывод веса изображения -->
                    <p><?php echo htmlspecialchars($image['src']); ?></p> <!-- преобразование в html элемент - текст содержащий абсолютную ссылку на изображение -->
                </td>
                <?php if ($count % 4 == 3 || $count == count($images) - 1): ?> <!-- проверка на последний элемент в строке таблицы и последний элемент в массиве изображений -->
                    </tr>
                <?php endif; ?>
                <?php $count++; ?>
            <?php endforeach; ?>
        </table>
        
        <p>На странице обнаружено <?php echo count($images); ?> изображений. Суммарный размер: <?php echo number_format($totalSize / (1024 * 1024), 2); ?> MB</p> <!-- Преобразование в мегабайты и округление до сотых -->
    <?php else: ?>
        <p>Изображения не найдены или возникла ошибка при обработке URL.</p>
    <?php endif; ?>

    <a href="index.php">Вернуться</a>
</body>
</html>