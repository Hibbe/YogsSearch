<?php
session_start();
require_once 'config.php';

#region Sticky castmembers
$castcheck = null;
if (isset($_SESSION['selected_cast']) && !isset($_POST['iid'])) {
    $selectedcast = $_SESSION['selected_cast'];
} elseif (isset($_POST['iid'])) {
    $_SESSION['selected_cast'] = $_POST['iid'];
    $selectedcast = $_POST['iid']; // Keep this for use *within* the current request
} else {
    $selectedcast = [0, 0];
}
#endregion

#region Adding and removing video fields

if (isset($_SESSION['videocard_count']) && isset($_SESSION['video_urls']) && !isset($_POST['VidId'])) {
    // Subsequent load - retrieve from session (but only if NOT a new submission)
    $videocardcount = $_SESSION['videocard_count'];
    $storedVideoUrls = $_SESSION['video_urls'];
} elseif (isset($_POST['VidId'])) { // Form submitted - store and use the new URLs
    $_SESSION['videocard_count'] = count($_POST['VidId']);
    $_SESSION['video_urls'] = $_POST['VidId'];
    $videocardcount = $_SESSION['videocard_count'];
    $storedVideoUrls = $_POST['VidId']; 

} else {
    $videocardcount = 0;
    $storedVideoUrls = []; // Initialize an empty array if no URLs are stored
}

if ($videocardcount >= 10) {
    $videocardcount = 10;
}
if ($videocardcount <= 0) {
    $videocardcount = 0;
}


$videocardbool = 1;
if (isset($_POST['pv'])) {
    //var_dump($_POST['pv']);
}
#endregion


?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Yogsearch | Add new videos featuring your favourite creators </title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <link rel="preload" href="/yogsearch.webp" as="image">
        <link rel="preload" href="/yogthumbs.webp" as="image">
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
            <img width="800" height="289" src="yogsearch.webp" alt="Yogsearch logo" class="yogart">
        </header>
        <main>
        <a href='/'> << Go back</a>
            <article class="searchresults">  <!-- Not actually search results-->
                <section class="noselect"> <!-- No Select --> 
                    <div class="card card-contribute">
        <form action="contribute.php" method="post">
                            <!--#region castmembers-->
                            <?php 
                                foreach ($castmember as $icast){ /* iterates castmember icons */
                                    $castcheck = (isset($_POST['iid']) && in_array($icast, $_POST['iid'])) ? 'checked' : '';
                            ?>
                                <input type="checkbox" class="hiddencb" id="<?= $icast ?>"      name="iid[]" value="<?= $icast ?>" <?= $castcheck ?>>          <label for="<?= $icast ?>"      class="castcb castcb-contribute <?= $icast ?>"><?= $icast ?></label>
                            <?php }; ?>
                                <!--#endregion-->
                    </div>
                </section>
                    <?php   
                        $j = $videocardcount;
                        for($i = 0; $i <= $j; $i++) { 
                        $videocardcount+1; 
                    ?>
                        <div class="card card-contribute">
                        <input type="url" name="VidId[]" id="VidId" placeholder="https://youtube.com/watch?v=" size="25" required title="URL should be a valid Youtube video link" value="<?= isset($storedVideoUrls[$i]) ? htmlspecialchars($storedVideoUrls[$i]) : '' ?>"> 
                        <button formnovalidate class="button" <?php if ($i < $j) { echo 'id="pvr"';}; ?> name="pv" value="<?= $i ?>" type="submit">＋</button>
                        </div> 
                        <br>
                    <?php    };?>
                        





                <p> <!-- Here goes the submit button -->
                    <input type="submit" id="Searchsubmit" value="Submit" />
                </p>
        </form>


            </article>
        <a href='/'> << Go back</a>
        </main>
        <footer>
            <a href='/faq.php' class=folinks>About</a> | <a href='https://github.com/Hibbe/YogsSearch' class=folinks target="_blank">Github</a> | <a href='/faq.php#faq' class=folinks>Contribute</a>
            <p class="fodisc">Yogsearch is a <strong>fanpage</strong> and is <strong>not associated with or endorsed</strong> by the Yogscast</p>
        </footer>
    </body>
</html>