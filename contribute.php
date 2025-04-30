<?php
session_start();
require_once 'config.php';
$maxVideoFields = 10;
$dsn = "sqlite:$dbc";
#region Functions

function getDbConnection($dsn) {
    try {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Recommended
        return $pdo;
    } catch (PDOException $e) {
        // Log error properly in a real application
        error_log("Database Connection Error: " . $e->getMessage());
        // Display a generic error to the user or handle gracefully
        die("Database connection failed. Please try again later.");
    }
}

function get_youtube_details($ref, $detail) {
    // If details for this video ($ref) haven't been fetched in this request yet...
    if (!isset($GLOBALS['youtube_details'][$ref])) {
        // WARNING: This endpoint is non-standard. Consider YouTube Data API v3.
        $apiUrl = 'https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' . $ref . '&format=json'; // Your endpoint
        $json = @file_get_contents($apiUrl); // Use @ to suppress errors, check result below

        if ($json === false) {
            // Handle error: Log it, maybe return false or throw exception
            error_log("Failed to fetch details from: " . $apiUrl);
            $GLOBALS['youtube_details'][$ref] = false; // Mark as failed
            return false; // Indicate failure
        }

        $data = json_decode($json, true); // Decode JSON to array

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            // Handle JSON decoding error
             error_log("Failed to decode JSON for video ref $ref: " . json_last_error_msg());
             $GLOBALS['youtube_details'][$ref] = false;
             return false;
        }
         // Store the decoded data (or failure mark) globally for this request
        $GLOBALS['youtube_details'][$ref] = $data;
    }

    // Retrieve the specific detail if the fetch was successful and the detail exists
    if (is_array($GLOBALS['youtube_details'][$ref]) && isset($GLOBALS['youtube_details'][$ref][$detail])) {
         // Use htmlspecialchars here ONLY if you intend to directly echo the output.
         // It's often better to return the raw data and sanitize at the point of display/insertion.
         // Let's return raw data and handle escaping later.
        // return htmlspecialchars($GLOBALS['youtube_details'][$ref][$detail], ENT_QUOTES);
        return $GLOBALS['youtube_details'][$ref][$detail];
    } else {
        // Detail not found or fetch failed earlier
        error_log("Detail '$detail' not found or fetch failed for video ref $ref.");
        return false; // Indicate failure or missing detail
    }
}

/**
 * Inserts a single video and its associated cast members into the database.
 * Uses Parameterized Queries for security.
 *
 * @param PDO $pdo The active PDO database connection.
 * @param string $youtubeId The 11-character YouTube Video ID.
 * @param string $title The video title.
 * @param array $castNames An array of cast member names (e.g., ['Creator1', 'Creator2']).
 * @param int $lobbyBool The value for the LobbyBool column (default 0).
 * @param int $playlistBool The value for the PlaylistBool column (default 0).
 * @return bool True on success, false on failure.
 * @throws PDOException If a database query fails.
 */
function insertVideoData(PDO $pdo, string $youtubeId, string $title, array $castNames, int $lobbyBool = 0, int $playlistBool = 0): bool
{
    // Basic validation
    if (empty($youtubeId) || empty($title) || empty($castNames)) {
        error_log("Missing required data for insertVideoData.");
        return false;
    }

    // Start transaction for atomicity (all or nothing)
    $pdo->beginTransaction();

    try {
        // 1. Insert into VideoList
        $sqlVideo = "INSERT INTO VideoList (YoutubeID, Title, LobbyBool, PlaylistBool) VALUES (:youtubeId, :title, :lobbyBool, :playlistBool)";
        $stmtVideo = $pdo->prepare($sqlVideo);
        $stmtVideo->bindParam(':youtubeId', $youtubeId, PDO::PARAM_STR);
        $stmtVideo->bindParam(':title', $title, PDO::PARAM_STR);
        $stmtVideo->bindParam(':lobbyBool', $lobbyBool, PDO::PARAM_INT);
        $stmtVideo->bindParam(':playlistBool', $playlistBool, PDO::PARAM_INT);

        if (!$stmtVideo->execute()) {
             // Execution failed before throwing PDOException (less common with ERRMODE_EXCEPTION)
             $pdo->rollBack();
             error_log("Failed to execute VideoList insert for ID: $youtubeId. ErrorInfo: " . implode(", ", $stmtVideo->errorInfo()));
             return false;
        }

        $videoId = $pdo->lastInsertId(); // Get the auto-incremented ID of the inserted video

        if (!$videoId) {
            $pdo->rollBack();
            error_log("Failed to get lastInsertId after inserting VideoList for ID: $youtubeId.");
            return false;
        }


        // 2. Get Cast IDs from CastNames
        // Create placeholders for the IN clause (e.g., ?,?,?)
        $placeholders = rtrim(str_repeat('?,', count($castNames)), ',');
        $sqlGetCastIds = "SELECT CastName, CastID FROM CastList WHERE CastName IN ($placeholders)";
        $stmtGetCastIds = $pdo->prepare($sqlGetCastIds);
        $stmtGetCastIds->execute($castNames);
        $castIdMap = $stmtGetCastIds->fetchAll(PDO::FETCH_KEY_PAIR); // Creates [CastName => CastID] map

        // Check if all selected cast members were found
        if (count($castIdMap) !== count($castNames)) {
             $foundNames = array_keys($castIdMap);
             $notFound = array_diff($castNames, $foundNames);
             error_log("Could not find CastIDs for the following CastNames: " . implode(', ', $notFound));
             // Decide if this is fatal: rollback or proceed with found cast? Let's rollback for safety.
             $pdo->rollBack();
             return false;
        }


        // 3. Insert into VideoCast (linking table)
        $sqlCast = "INSERT INTO VideoCast (VideoID, CastID) VALUES (:videoId, :castId)";
        $stmtCast = $pdo->prepare($sqlCast);
        $stmtCast->bindParam(':videoId', $videoId, PDO::PARAM_INT);

        foreach ($castIdMap as $castName => $currentCastId) {
            $params = [
                ':videoId' => $videoId, // VideoID is the same for all cast members of this video
                ':castId' => $currentCastId // Use the correct CastID for this iteration
            ];
             // Pass the parameters array directly to execute()
            if (!$stmtCast->execute($params)) {
            // Execution failed
                $pdo->rollBack();
                error_log("Failed to execute VideoCast insert for VideoID: $videoId, CastID: $currentCastId. ErrorInfo: " . implode(", ", $stmtCast->errorInfo()));
            return false;
             }
        }

        // If all queries succeeded, commit the transaction
        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        // An error occurred, rollback the transaction
        $pdo->rollBack();
        // Log the error message
        error_log("Database transaction failed in insertVideoData: " . $e->getMessage());
        // Re-throw the exception to be caught by the calling code if needed
        throw $e;
        // Or return false: return false;
    }
}


#endregion

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

    #region 1. Basic Validation (Cast Selection) - Keep your existing check
    if (count($selectedcast) < 1 || count($selectedcast) > 12) {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Please select 1-12 creators.'];
        // Keep current form state in session before redirecting
        $_SESSION['video_urls'] = $submittedUrls; // Keep entered URLs
        $_SESSION['selected_cast'] = $selectedcast; // Keep selected cast
        header("Location: " . $redirectUrl);
        exit;
    }
    #endregion
    #region 2. Filter out empty URL fields *before* processing
    $validSubmittedUrls = array_filter($submittedUrls); // Removes empty strings

    if (empty($validSubmittedUrls)) {
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Please enter at least one valid YouTube URL.'];
        $_SESSION['video_urls'] = $submittedUrls;
        $_SESSION['selected_cast'] = $selectedcast;
        header("Location: " . $redirectUrl);
        exit;
    }
    #endregion
    #region 3. Establish Database Connection
       $pdo = getDbConnection($dsn);
       if (!$pdo) {
           // Error is handled within getDbConnection, script would die
           // Or you could set a session message and redirect if preferred
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Database connection failed. Cannot submit videos.'];
            $_SESSION['video_urls'] = $submittedUrls;
            $_SESSION['selected_cast'] = $selectedcast;
            header("Location: " . $redirectUrl);
            exit;
       }
    #endregion
    #region 4. Process Each Submitted URL
    $successCount = 0;
    $failCount = 0;
    $skipCount = 0;
    $duplicateCount = 0;
    $processedUrls = []; // Keep track of URLs successfully added

    foreach ($validSubmittedUrls as $index => $url) {
        $url = trim($url); // Clean whitespace

        // Extract Video ID
        if (!preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=|live/)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
            // Invalid YouTube URL format
            error_log("Invalid YouTube URL format skipped: " . $url);
            $skipCount++;
            continue; // Skip to the next URL
        }
        $videoId = $match[1];

        // Get Author URL using your function
        // Add error handling: Check if the function returns false or throws error
        $authorUrl = false;
        try {
             // IMPORTANT: Ensure your function can return 'author_url'
            $authorUrl = get_youtube_details($videoId, 'author_url');
        } catch (Exception $e) {
             error_log("Error fetching details for video ID $videoId: " . $e->getMessage());
             $failCount++;
             continue; // Skip this video if details fail
        }

        if ($authorUrl === false || $authorUrl === null || $authorUrl === '') {
             error_log("Could not retrieve author_url for video ID $videoId (URL: $url)");
             $failCount++;
             continue; // Skip if author URL couldn't be fetched
        }

        // Check Author against Whitelist
        // Extract the last component of the author URL
        $authorIdentifier = basename($authorUrl);

        // Check if the extracted identifier is in the whitelist
        if (empty($authorIdentifier) || !in_array($authorIdentifier, $castWhiteList)) {
            // Log both the full URL and the identifier for easier debugging
            error_log("Author identifier not whitelisted: Identifier='" . $authorIdentifier . "', FullURL='" . $authorUrl . "' (Video ID: $videoId, URL: $url)");
            $skipCount++;
            continue; // Skip to the next URL if identifier is empty or not allowed
        }

        // Get Video Title (only if author is whitelisted)
        $title = false;
         try {
            $title = get_youtube_details($videoId, 'title');
         } catch (Exception $e) {
             error_log("Error fetching title for video ID $videoId: " . $e->getMessage());
             $failCount++;
             continue; // Skip this video if title fails
         }

         if ($title === false || $title === null || $title === '') {
            error_log("Could not retrieve title for video ID $videoId (URL: $url)");
            $failCount++;
            continue; // Skip if title couldn't be fetched
        }

       // ***** START PRE-INSERTION CHECK *****
       try {
           $sqlCheck = "SELECT 1 FROM VideoList WHERE YoutubeID = :youtubeId LIMIT 1";
           $stmtCheck = $pdo->prepare($sqlCheck);
           $stmtCheck->bindParam(':youtubeId', $videoId, PDO::PARAM_STR);
           $stmtCheck->execute();

           if ($stmtCheck->fetchColumn()) {
               // Video already exists! Increment duplicate counter and skip insertion.
               $duplicateCount++; // <-- Make sure to initialize $duplicateCount = 0; before the loop!
               error_log("Video already exists in DB, skipping insertion: Video ID $videoId");
               continue; // Skip to the next URL in the foreach loop
           }
       } catch (PDOException $e) {
           // Handle error during the check itself
           error_log("Database error checking for existing video ID $videoId: " . $e->getMessage());
           $failCount++;
           continue; // Skip this video if the check fails
       }
       // ***** END PRE-INSERTION CHECK *****


       // Insert into Database (only if check above passed)
       try { // <--- TRY BLOCK FOR INSERTION
           // Assuming LobbyBool is always 0 for these submissions, adjust if needed
           if (insertVideoData($pdo, $videoId, $title, $selectedcast, 0)) { // <-- Actual Insertion Call
               $successCount++;
               $processedUrls[] = $url; // Add to list of successfully processed URLs
           } else {
               // This 'else' might not be reached if insertVideoData throws exceptions on failure
               $failCount++;
               error_log("insertVideoData function returned false for Video ID: $videoId");
           }
       } catch (PDOException $e) {
           // Catch UNIQUE constraint here specifically? Optional, pre-check is better.
           // if ($e->getCode() == 23000) { ... } else { ... }
           $failCount++;
           error_log("Database insertion error for Video ID $videoId: " . $e->getMessage());
       } catch (Exception $e) { // Catch other potential exceptions from insertVideoData
           $failCount++;
           error_log("General error during insertion for Video ID $videoId: " . $e->getMessage());
       }
   } // End foreach loop
   #endregion
    #region 5. Set Feedback Message
    $message = "";
    if ($successCount > 0) {
        $message .= "$successCount video(s) submitted successfully. ";
    }
    if ($duplicateCount > 0) { 
        $message .= "$duplicateCount video(s) were skipped because they have already been submitted. ";
    }
    if ($failCount > 0) {
        $message .= "$failCount video(s) failed during processing. ";
    }
    if ($skipCount > 0) {
        $message .= "$skipCount video(s) were skipped (invalid URL or non-whitelisted author).";
    }
    if (empty($message) && $duplicateCount == 0) {
        $message = "No new videos were processed."; // Should only happen if all inputs were invalid/skipped
    }
    elseif (empty($message) && $duplicateCount > 0) {

        $message = "All videos were skipped because they have already been submitted.";
   }
   
    // Determine message type based on outcome
    $messageType = 'warning'; // Default if nothing succeeded
    if ($successCount > 0) {
        $messageType = 'success'; // Success if at least one was added
    } 
    
    $_SESSION['flash_message'] = ['type' => $messageType, 'text' => trim($message)];
    #endregion

   #region 6. Clear or Update Session State for PRG
    // Option A: Clear form on successful submission
    // unset($_SESSION['video_urls']);
    // unset($_SESSION['selected_cast']);

    // Option B: Keep form state (as in your original code) - useful if there were failures/skips
    // Filter out the successfully processed URLs from the session array if desired
    // $_SESSION['video_urls'] = array_diff($submittedUrls, $processedUrls); // Show only remaining/failed ones
    // For simplicity, let's just keep the last submitted state for now:
        $_SESSION['video_urls'] = $submittedUrls;
        $_SESSION['selected_cast'] = $selectedcast;
   
   
       // 7. PRG Redirect
       header("Location: " . $redirectUrl);
       exit;
    #endregion

} else {
   // --- Handle General Page Load (GET request, or POST without specific action buttons) ---
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

$pdo = null;

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
                <div class="card">
                    <h2 style="margin: 0em">Add videos to Yogsearch</h2> <br>
                    <h4 style="margin: 0em; margin-bottom: 0.2em">How it works</h4>
                    Select who is in the video and paste the link. You can add more videos featuring the same cast in one go (for example if several videos are a part of a series).<br>
                    When you submit a video it will be manually approved before it is added to Yogsearch. <br>
                    If you are unable to submit a video make sure that you have pasted the full URL and that the video is from an official yogscast channel. 
                </div>
                <section class="noselect">
                <form action="contribute.php" method="post">
                    <div class="card card-contribute">
                            <?php
                                foreach ($castmember as $icast){
                                    $castcheck = (is_array($selectedcast) && in_array($icast, $selectedcast)) ? 'checked' : '';
                            ?>
                                <input type="checkbox" class="hiddencb" id="<?= htmlspecialchars($icast) ?>" name="iid[]" value="<?= htmlspecialchars($icast) ?>" <?= $castcheck ?>> <label for="<?= htmlspecialchars($icast) ?>" class="castcb castcb-contribute <?= htmlspecialchars($icast) ?>"><?= htmlspecialchars($icast) ?></label>
                            <?php }; ?>
                    </div>
                </section>
                            <?php
                                if (isset($_SESSION['flash_message'])) {
                                    $messageData = $_SESSION['flash_message'];
                                    // Use different CSS classes based on message type (e.g., 'success', 'error', 'warning')
                                    echo '<div class="message ' . htmlspecialchars($messageData['type']) . '">' . htmlspecialchars($messageData['text']) . '</div>';
                                    unset($_SESSION['flash_message']); // Clear the message after displaying it
                                }
                            ?>
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
                    </form> 
            </article>
        <a href='/'> << Go back</a>
        </main>
        </body>
</html>