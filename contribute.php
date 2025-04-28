<?php
session_start();
require_once 'config.php';
$maxVideoFields = 10; // Limit set previously


#region Sticky castmembers 
$selectedcast = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['iid'])) {
        $selectedcast = $_POST['iid'];
        $_SESSION['selected_cast'] = $selectedcast;
    } else {
        // All checkboxes were unchecked
        $selectedcast = [];
        $_SESSION['selected_cast'] = $selectedcast;
    }
} elseif (isset($_SESSION['selected_cast'])) {
    $selectedcast = $_SESSION['selected_cast'];
}
if (!is_array($selectedcast)) {
    $selectedcast = [];
}
#endregion

#region Sticky Adding and removing video fields 

// Initialize video URLs array
if (!isset($_SESSION['video_urls']) || empty($_SESSION['video_urls'])) {
    $_SESSION['video_urls'] = [''];
}
$currentVideoUrls = $_SESSION['video_urls']; // Start with session
$submittedUrls = isset($_POST['VidId']) ? $_POST['VidId'] : $currentVideoUrls; // Capture POST data if available

$redirectUrl = $_SERVER['PHP_SELF']; // Base URL for redirection

// ADD 
if (isset($_POST['add_video'])) {
    $currentVideoUrls = $submittedUrls; // Use submitted values
    $new_index = count($currentVideoUrls); // Index of the item *about* to be added

    if ($new_index < $maxVideoFields) {
        $currentVideoUrls[] = ''; // Add new field
        $_SESSION['video_urls'] = $currentVideoUrls; // Update session
        // *** PRG Step: Redirect after processing ***
        // Add anchor to scroll near the newly added field
        header("Location: " . $redirectUrl . "#video-entry-" . $new_index);
        exit;
    } else {
         // Limit reached, still update session with potentially edited values
         $_SESSION['video_urls'] = $currentVideoUrls;
         // *** PRG Step: Redirect even if limit reached to clear POST state ***
         // Redirect to the last valid index
         header("Location: " . $redirectUrl . "#video-entry-" . ($maxVideoFields - 1));
         exit;
    }
}

// REMOVE
elseif (isset($_POST['remove_video'])) {
    $indexToRemove = (int)$_POST['remove_video'];
    $currentVideoUrls = $submittedUrls; // Use submitted values

    if (isset($currentVideoUrls[$indexToRemove])) {
        array_splice($currentVideoUrls, $indexToRemove, 1);
        if (empty($currentVideoUrls)) {
            $currentVideoUrls = [''];
        }
        $_SESSION['video_urls'] = $currentVideoUrls; // Update session

        // *** PRG Step: Redirect after processing ***
        // Add anchor to scroll near the field *before* the removed one (or top if first was removed)
        $anchor_index = max(0, $indexToRemove - 1); // Scroll to previous or first element
        header("Location: " . $redirectUrl . "#video-entry-" . $anchor_index);
        exit;
    } else {
         $_SESSION['video_urls'] = $_SESSION['video_urls']; // Ensure session is persisted if needed
         header("Location: " . $redirectUrl);
         exit;
    }
}
#endregion 

#region FINAL SUBMIT 
elseif (isset($_POST['final_submit'])) {
    // Use $submittedUrls and $selectedcast which were determined earlier

    $validUrls = array_filter($submittedUrls); // Use submittedUrls captured earlier
    $submittedCast = $selectedcast; // Use selectedcast determined earlier

    // --> Add your database insertion logic here <--
    echo "Processing...<br>"; // Don't echo before redirect!
    echo "Cast: " . implode(', ', $submittedCast) . "<br>";
    echo "URLs: " . implode('<br>', $validUrls);

    // Example: Clear session after successful final submission
    //unset($_SESSION['video_urls']);
    //unset($_SESSION['selected_cast']);

    // For now, keep session sticky after submit for testing, but still use PRG
    $_SESSION['video_urls'] = $submittedUrls;
    $_SESSION['selected_cast'] = $submittedCast;


    // *** PRG Step: Redirect after processing final submit ***
    // Redirect back to the form (or to a success page like "success.php")
    // No specific anchor needed usually after final submit, could redirect to top/default
    header("Location: " . $redirectUrl); // Or header("Location: success.php");
    exit;

} else {
   // --- Handle General Page Load (GET request, or POST without specific action buttons) ---
   // If it was a POST but didn't match add/remove/submit, maybe just update session and redirect?
   // This handles the case where only cast members might have changed without other actions.
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update sessions just in case something was submitted without specific buttons
        // (This assumes $selectedcast and $submittedUrls captured the latest POST)
        $_SESSION['selected_cast'] = $selectedcast;
        $_SESSION['video_urls'] = $submittedUrls;
        // *** PRG Step: Redirect even for general POST to clear state ***
        header("Location: " . $redirectUrl);
        exit;
   } else {
       // GET request: Load from session (already done by initializing $currentVideoUrls and $selectedcast earlier)
       $currentVideoUrls = $_SESSION['video_urls']; // Ensure we use session data for GET
   }

}
#endregion

// Recalculate count AFTER potential modifications (for GET request rendering)
$videocardcount = count($currentVideoUrls);



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
        <link rel="icon" type="image/png" href="/favico/favicon-96x96.png" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="/favico/favicon.svg" />
        <link rel="shortcut icon" href="/favico/favicon.ico" />
        <link rel="apple-touch-icon" sizes="180x180" href="/favico/apple-touch-icon.png" />
        <meta name="apple-mobile-web-app-title" content="Yogsearch" />
        <link rel="manifest" href="/favico/site.webmanifest" />
    </head>
    <body>
        <main>
        <header>
            <img width="800" height="289" src="yogsearch.webp" alt="Yogsearch logo" class="yogart">
        </header>
        <a href='/'> << Go back</a>
            <article class="searchresults">
                <section class="noselect">
                    <div class="card card-contribute">
                        <form action="contribute.php" method="post">
                            <?php
                                foreach ($castmember as $icast){
                                    $castcheck = (is_array($selectedcast) && in_array($icast, $selectedcast)) ? 'checked' : '';
                            ?>
                                <input type="checkbox" class="hiddencb" id="<?= htmlspecialchars($icast) ?>" name="iid[]" value="<?= htmlspecialchars($icast) ?>" <?= $castcheck ?>> <label for="<?= htmlspecialchars($icast) ?>" class="castcb castcb-contribute <?= htmlspecialchars($icast) ?>"><?= htmlspecialchars($icast) ?></label>
                            <?php }; ?>
                    </div>
                </section>
                <?php
                foreach ($currentVideoUrls as $index => $url) {
                    $isLastField = ($index === ($videocardcount - 1));
            ?>
                <div class="card card-contribute video-entry" id="video-entry-<?= $index ?>">
                    <input type="url" name="VidId[<?= $index ?>]" placeholder="https://www.youtube.com/watch?v=..." size="25" required title="URL should be a valid Youtube video link" value="<?= htmlspecialchars($url) ?>">

                    <?php
                    // --- Button Logic: Add and Remove on Last Field if count >= 2 ---

                    if ($isLastField) {
                        // --- Processing the LAST field ---

                        // Show ADD (+) button if the count is less than the maximum allowed fields
                        if ($videocardcount < $maxVideoFields) {
                    ?>
                            <button type="submit" name="add_video" value="add" class="button" formnovalidate>＋</button>
                    <?php
                        }

                        // Show REMOVE (-) button if there is more than one field in total
                        // (This ensures it doesn't show when count == 1)
                        if ($videocardcount > 1) {
                    ?>
                            <button type="submit" name="remove_video" value="<?= $index ?>" class="button" formnovalidate>－</button>
                    <?php
                        }

                    } else {
                        // --- Processing fields that are NOT the last one ---
                        // If it's not the last field, it implies count > 1, so always show REMOVE (-)
                    ?>
                        <button type="submit" name="remove_video" value="<?= $index ?>" class="button" formnovalidate>－</button>
                    <?php
                    }
                    ?>
                </div>
                <br>
            <?php } // End foreach loop ?>

            <p>
                <?php
                    // Determine the button text based on the number of video fields
                    $submitButtonText = "Submit Video"; // Default for 1 video
                    if ($videocardcount > 1) {
                        $submitButtonText = "Submit " . $videocardcount . " Videos";
                    }
                ?>
                <input type="submit" name="final_submit" id="Searchsubmit" value="<?= htmlspecialchars($submitButtonText) ?>" />
            </p>
        </form> </article>
        <a href='/'> << Go back</a>
        </main>
        </body>
</html>