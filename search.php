<?php
require_once 'config.php';

function getVideosByCreators(PDO $pdo, array $creators) { // Search function query

    $creator_list = str_repeat('?, ', count($creators) - 1) . '?';
    $creator_count = count($creators);    
    $sql = "SELECT vl.YoutubeID, vl.Title FROM VideoList vl INNER JOIN VideoCast vc ON vl.VideoID = vc.VideoID INNER JOIN CastList cl ON cl.CastID = vc.CastID WHERE cl.CastName IN ($creator_list) GROUP BY vl.VideoID HAVING COUNT(*) = $creator_count LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($creators);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function insertreport($pdo,array $data) { // Report function input

    $sql = "INSERT INTO videoreports (vidID, repinput) VALUES (:vidID, :repinput)";
    $stmt = $pdo->prepare($sql); 
    $params = [
        ':vidID' => $data['RepYtId'],
        ':repinput' => $data['repimp']
    ];
    $stmt->execute($params);
    return true; // Indicate successful insert
  }

$dsn = "sqlite:$db";
$dsrep = "sqlite:$dbrep";



if ($_SERVER['REQUEST_METHOD'] == 'POST') {   
    if (!isset($_SESSION)) session_start();
    $RepYtId = htmlspecialchars($_POST['RepYtId']);
    $repimp = htmlspecialchars($_POST['repimp']);
    unset($_POST);
    $_SESSION['RepYtId'] = $RepYtId;
    $_SESSION['repimp'] = $repimp;
    if(isset($_SESSION['repimp']) and isset($_SESSION['RepYtId'])){ 
        $pdo = new PDO($dsrep);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            if (insertreport($pdo, $_SESSION)) {
                header( "Location: {$_SERVER['REQUEST_URI']}&rep=success", true, 303 );
                exit();
            } else {
                header( "Location: {$_SERVER['REQUEST_URI']}&rep=fail", true, 303 );
                exit();
            }
        
          } 
          catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
          } 
          finally {
            $pdo = null;
          }

    }
    else {echo "fail";}
$creators = 1; $urlrep = 0;
}


else if (isset($_GET['iid']) and (count($_GET['iid'])) >= 2 and count($_GET['iid']) <= 12){
        try {
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $creators = array_map('htmlspecialchars', $_GET['iid']);
            $videos = getVideosByCreators($pdo, $creators);
        } 
        catch (PDOException $e) {
            echo "Database error: " . $e->getMessage();
        } 
        finally {$pdo = null;}
    }
else {$creators = 1; $urlrep = 0;}
if ($creators != 1) {$urlrep = implode("&iid%5B%5D=", $creators);} //For reportfunction




?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Yogsearch | <?php if (!isset($videos)) { echo "Please select between 2 and 12 creators";} else if(empty($videos)) { echo "No videos found";} else echo("Found ".count($videos)." videos with ".join(' & ', array_filter(array_merge(array(join(', ', array_slice($_GET['iid'], 0, -1))), array_slice($_GET['iid'], -1)), 'strlen')));  ?> </title>
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
                    if(!isset($videos)) { echo "<br>"."Please select between 2 and 12 creators";}
                    else if(empty($videos)) { echo "<br>"."No videos found";}
                    else foreach ($videos as $video): {  //Search result cards?>
                        <div class="card">
                        <label for="vidTITLE" id="TI<?= $video['YoutubeID']; ?>"><h4><a alt="<?= $video['YoutubeID']; ?>" id="TI<?= $video['YoutubeID']; ?>" href="https://youtube.com/watch?v=<?= $video['YoutubeID']; ?>" target="_blank" rel="noopener noreferrer"> <span class="TitleWidth"><?= $video['Title']; ?></span></h4></label>
                            <p class="ytimg"><img width="640" height="360" class="ytimg" alt="Thumbnail" src="https://i.ytimg.com/vi/<?= $video['YoutubeID']; ?>/maxresdefault.jpg"></a></p>
                            <?php if (isset($_GET['repid']) and $_GET['repid'] == $video['YoutubeID']) { //RepID is set in cardReport(href)?>
                            <a class="cardReport" href="?iid%5B%5D=<?= $urlrep; ?>#TI<?=$video['YoutubeID']; ?>">Report issue</a>
                            <?php } else { ?>
                            <a class="cardReport" href="?iid%5B%5D=<?= $urlrep; ?>&repid=<?=$video['YoutubeID']; ?>#TI<?=$video['YoutubeID']; ?>">Report issue</a>
                            <?php } ?>
                        </div>
                    <?php  if (isset($_GET['repid']) and $_GET['repid'] == $video['YoutubeID'] ) { //RepID is set in cardReport(href)?>
                        <div class="card repcard">
                            <?php if (!isset($_GET['rep'])) { //report function popout?>
                            <form action="" method="POST" id="RepForm">
                                <input type="hidden" id="RepYtId" name="RepYtId" value="<?=$video['YoutubeID']; ?>">
                                <textarea id="repimp" placeholder="Describe the issue ..." name="repimp" maxlength="5000" required></textarea><br>
                                <input type="submit" class="RepInlineSubmit" value="Send">
                            </form>
                            <?php } else if (isset($_GET['rep']) and $_GET['rep'] == "success") { // Report confirmation function?>
                                     <p style="margin:-2px; margin-left: 20px;"> Thanks for your report! </p>
                            <?php } else if (isset($_GET['rep'])and $_GET['rep'] == "fail") { ?>
                                     <p style="margin:-2px; margin-left: 20px;"> Your report was not submitted, please try again. </p>
                            <?php } ?>
                        </div>  
                    <?php  } } ?>
                <?php endforeach; ?>
            </article>
        <a href='/'> << Go back</a>
        </main>
        <footer style="text-align:center;">
            <a href='/faq.php' class=folinks>About</a> | <a href='https://github.com/Hibbe/YogsSearch' class=folinks target="_blank">Github</a> | <a href='/faq.php#faq' class=folinks>Report error</a>
        </footer>
    </body>
</html>
