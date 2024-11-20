<?php
require_once 'config.php';

function getVideosByCreators(PDO $pdo, array $creators) {

    $creator_list = str_repeat('?, ', count($creators) - 1) . '?';
    $creator_count = count($creators);    
    $sql = "SELECT vl.YoutubeID, vl.Title FROM VideoList vl INNER JOIN VideoCast vc ON vl.VideoID = vc.VideoID INNER JOIN CastList cl ON cl.CastID = vc.CastID WHERE cl.CastName IN ($creator_list) GROUP BY vl.VideoID HAVING COUNT(*) = $creator_count LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($creators);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


$dsn = "sqlite:$db";

if (isset($_GET['iid']) and (count($_GET['iid'])) != 1 and count($_GET['iid']) <= 12){

    try {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $creators = array_map('htmlspecialchars', $_GET['iid']);
        $videos = getVideosByCreators($pdo, $creators);
        /*if ($videos) {
            // Process the results
            foreach ($videos as $video) {
                $ytID = $video['YoutubeID'];
                echo get_youtube_details($ytID, 'title');
            }
        } else {
            echo "No videos found.";
        } */

    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    } finally {
        $pdo = null;
    }
}
else {$creators = 1;}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Yogsearch | <?php if (!isset($videos)) { echo "No creators selected";} else if(empty($videos)) { echo "No videos found";} else echo("Found ".count($videos)." videos with ".join(' & ', array_filter(array_merge(array(join(', ', array_slice($_GET['iid'], 0, -1))), array_slice($_GET['iid'], -1)), 'strlen')));  ?> </title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <link rel="preload" href="/yogsearch.webp" as="image">
        <!-- <link rel="preload" href="/yogthumbs.webp" as="image"> -->
        <link rel="stylesheet" href="index.css">
        <!-- Favico region -->
        <link rel="icon" type="image/png" href="/favico/favicon-96x96.png" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="/favico/favicon.svg" />
        <link rel="shortcut icon" href="/favico/favicon.ico" />
        <link rel="apple-touch-icon" sizes="180x180" href="/favico/apple-touch-icon.png" />
        <meta name="apple-mobile-web-app-title" content="Yogsearch" />
        <link rel="manifest" href="/favico/site.webmanifest" />
    </head>
    <body>
        <header> 
            <img width="800" height="289" src="yogsearch.webp" alt="The unofficial, unlicensed Yogsearch" class="yogart">
        </header>
        <main>
        <a href='/'> << Go back</a>
            <article class="searchresults">  <!-- Here go the results of the search-->

                <?php  
                    if (!isset($videos) and $creators != 1) { echo "<br>"."No creators selected";}
                    else if($creators == 1 ) { echo "<br>"."Please select between 2 and 12 creators";}
                    else if(empty($videos)) { echo "<br>"."No videos found";}
                    else foreach ($videos as $video): ?>
                        <div class="card">
                            <h4><a alt="<?= $video['YoutubeID']; ?>" href="https://youtube.com/watch?v=<?= $video['YoutubeID']; ?>" target="_blank" rel="noopener noreferrer"> <span class="TitleWidth"><?= $video['Title']; ?></span></h4>
                            <p><img width="640" height="360" class="ytimg" alt="Thumbnail" src="https://i.ytimg.com/vi/<?= $video['YoutubeID']; ?>/maxresdefault.jpg"></a></p>
                        </div>
                <?php endforeach; ?>
            </article>
        <a href='/'> << Go back</a>
        </main>
        <footer style="text-align:center;">
            <a href='/faq.php' class=folinks>About</a> | <a href='https://github.com/Hibbe/Yogsearch' class=folinks target="_blank">Github</a> | <a href='/faq.php#faq' class=folinks>Report error</a>
        </footer>
    </body>
</html>
