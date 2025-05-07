<?php
session_start();
require_once 'config.php';

$dsn = "sqlite:" . $dbc;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, null, null, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- Cast Whitelist ---
$castWhiteList = array("@yogscast","@YogsLive","@yogscastshorts","@HoneydewLive","@Sips","@SipsLive","@doubledragon","@duncan","@YogsCiv","@Rythian","@DXPhoenix","@GamesNight","@LewisAndBen","@AngoryTom", "@Mystery_Quest","@Zylus","@zoey","@nilesy","@Ravs_","@SquidGame","@Bouphe","@Osiefish","@brionykay_","@KirstyYT","@boba69","@Pedguin","@hatfilms","@HatFilmsGaming","@HatFilmsLive","@SherlockHulmesDM","@SherlockHulmes","@HighRollersDnD","@pyrionflax","@Daltos","@AlextheRambler","@thespiffingbrit","@inthelittlewood","@martyn","@InTheLittleWoodLive","@inthelittleshorts","@yogscastkim","@Geestargames","@Wilsonator","@BryanCentralYT","@Vadact","@BekkiBooom","@conquest","@Mousie","@MousieAfterDark","@shadowatnoon","@Lolipopgi",);

// --- Fetch Data & Create Mappings ---
// Fetch all cast members and create a Name -> ID map
$stmtCast = $pdo->query("SELECT CastID, CastName FROM CastList");
$allCastMembers = $stmtCast->fetchAll();
$castNameToIdMap = [];
foreach ($allCastMembers as $member) {
    $mapKey = $member['CastName'];
    $castNameToIdMap[$mapKey] = $member['CastID'];
}

// Fetch all channel data and create a Name -> channelId map
$stmtChannels = $pdo->query("SELECT channelId, channelName FROM ChannelData");
$allChannels = $stmtChannels->fetchAll();
$channelNameToIdMap = [];
foreach ($allChannels as $channel) {
    $channelNameToIdMap[$channel['channelName']] = $channel['channelId'];
}


// --- Function to Get Next Video ---
function getNextVideoId($pdo, $filters = []) {
    // Columns to be finally selected by the main query
    $finalSelectColumns = "vl.YoutubeID, vl.VideoID, vl.Title";

    // Base parts of the query
    $fromClause = " FROM VideoList vl LEFT JOIN VideoCast vc ON vl.VideoID = vc.VideoID";
    
    // Base WHERE conditions (video not in VideoCast)
    $baseWhereConditions = ["vc.VideoID IS NULL"];
    $params = []; // For SQL parameters

    // Condition 1: Exclude skipped videos (applies in all cases)
    if (!empty($_SESSION['skipped_videos'])) {
        // Create placeholders for each skipped video ID
        $skippedPlaceholders = implode(',', array_fill(0, count($_SESSION['skipped_videos']), '?'));
        $baseWhereConditions[] = "vl.YoutubeID NOT IN ($skippedPlaceholders)";
        // Add skipped video IDs to the parameters array
        $params = array_merge($params, $_SESSION['skipped_videos']);
    }

    // Determine if any filters are currently active
    $areFiltersActive = !empty($filters['channelId']) ||
                        !empty($filters['titleSearch']) ||
                        !empty($filters['dateStart']) ||
                        !empty($filters['dateEnd']);

    if ($areFiltersActive) {
        // --- Logic for when filters ARE active ---
        $filterWhereConditions = []; // To hold conditions specific to active filters
        
        if (!empty($filters['channelId'])) {
            $filterWhereConditions[] = "vl.channelId = ?";
            $params[] = $filters['channelId'];
        }
        if (!empty($filters['titleSearch'])) {
            $filterWhereConditions[] = "vl.Title LIKE ?";
            $params[] = '%' . $filters['titleSearch'] . '%'; // Add wildcards for LIKE search
        }
        if (!empty($filters['dateStart'])) {
            $filterWhereConditions[] = "DATE(vl.publishedAt) >= ?";
            $params[] = $filters['dateStart'];
        }
        if (!empty($filters['dateEnd'])) {
            $filterWhereConditions[] = "DATE(vl.publishedAt) <= ?";
            $params[] = $filters['dateEnd'];
        }
        
        // Combine base conditions with filter conditions
        $allWhereConditions = array_merge($baseWhereConditions, $filterWhereConditions);
        
        // Construct the SQL query: select latest video matching all conditions
        $sql = "SELECT " . $finalSelectColumns
             . $fromClause
             . " WHERE " . implode(" AND ", $allWhereConditions)
             . " ORDER BY vl.publishedAt DESC LIMIT 1";

    } else {
        // --- Logic for when filters are NOT active ---
        // Fetch 1 random video from the latest 100 eligible videos
        
        // Construct the WHERE clause for the inner query (base conditions only)
        $innerWhereClause = " WHERE " . implode(" AND ", $baseWhereConditions);

        // Subquery to get the latest 100 eligible videos.
        // It must select the columns needed by the outer query, plus 'publishedAt' for ordering.
        $subQuerySelectColumns = "vl.YoutubeID, vl.VideoID, vl.Title, vl.publishedAt";
        $subQuery = "SELECT " . $subQuerySelectColumns
                  . $fromClause // FROM VideoList vl LEFT JOIN VideoCast vc ...
                  . $innerWhereClause // WHERE vc.VideoID IS NULL AND (skipped video conditions)
                  . " ORDER BY vl.publishedAt DESC LIMIT 100";

        // Outer query selects the desired columns from the subquery's results (aliased as 't')
        // and then picks one randomly.
        $outerSelectColumns = "t.YoutubeID, t.VideoID, t.Title";
        $sql = "SELECT " . $outerSelectColumns
             . " FROM (" . $subQuery . ") AS t"  // 't' is the alias for the subquery result set
             . " ORDER BY RANDOM() LIMIT 1";     // RANDOM() is SQLite specific for random ordering
        
        // The $params array already contains parameters for skipped videos,
        // which are used in $innerWhereClause of the $subQuery.
    }

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Fetch the result (PDO::FETCH_ASSOC is likely your default but good to be explicit if needed)
    return $stmt->fetch(PDO::FETCH_ASSOC); 
}

// --- PRG Pattern: Handle POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Rate Limiting Check (Part 1) ---
    $minimumSubmissionInterval = 4; // seconds
    $submissionRateLimitApplies = false; // Flag to indicate if this POST action type should be rate-limited

    // Determine if the current action is a "submit cast to video" action
    // This means 'submit_action' is set, AND it's NOT a ban action.
    if (isset($_POST['submit_action'])) {
        $submissionRateLimitApplies = true;
    }

    if ($submissionRateLimitApplies && isset($_SESSION['last_submission_time'])) {
        $timeSinceLastSubmission = time() - $_SESSION['last_submission_time'];
        if ($timeSinceLastSubmission < $minimumSubmissionInterval) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'text' => 'Please wait a few seconds between submissions.'
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    if (!isset($_SESSION['skipped_videos'])) {
        $_SESSION['skipped_videos'] = [];
    }


    // Store filter selections in session
    $_SESSION['filter_channelId'] = $_POST['filter_channel'] ?? '';
    $_SESSION['filter_titleSearch'] = $_POST['filter_title'] ?? '';
    $_SESSION['filter_date_start'] = $_POST['filter_date_start'] ?? '';
    $_SESSION['filter_date_end'] = $_POST['filter_date_end'] ?? '';

    $currentServedYoutubeId = $_POST['servedYoutubeId'] ?? null;
    $currentServedVideoId = $_POST['servedVideoId'] ?? null;
    $selectedCastNames = $_POST['iid'] ?? []; // Uses simple names from $castmember

    $isSubmit = isset($_POST['submit_action']);
    $submittedChannelFilter = $_POST['filter_channel'] ?? '';
    $submittedTitleFilter = $_POST['filter_title'] ?? '';

    // Handle Persistent Selection Logic
    if ($isSubmit && $submittedChannelFilter !== '' && $submittedTitleFilter !== '') {
        // Persist selection ONLY if Submit is clicked AND both filters are set
        $_SESSION['persistent_cast_selection'] = $selectedCastNames;
    } else {
        // Clear persistence if Skip is clicked, Filters are applied,
        // or Submit is clicked without both filters active.
        unset($_SESSION['persistent_cast_selection']);
    }

// --- Decision: Submit or Skip ---
// $isSubmit is already defined above
$isSkip = isset($_POST['skip_action']);
$isBan = isset($_POST['ban_video']) && $_POST['ban_video'] == '1'; // Check if ban checkbox is checked

// --- Handle POST Actions ---

// Action 1: Ban Video (if Submit was clicked AND Ban checkbox is checked)
if ($isSubmit && $isBan && $currentServedVideoId) {
    $_SESSION['last_submission_time'] = time();
    try {
        $pdo->beginTransaction();

        // 1. Update bannedBool in VideoList
        //$stmtUpdateBan = $pdo->prepare("UPDATE VideoList SET bannedBool = 1 WHERE VideoID = ?");
        //$stmtUpdateBan->execute([$currentServedVideoId]);

        // 2. Insert into VideoCast with CastID 42 (Banned)
        // Check if a record for this VideoID already exists to avoid duplicates if desired,
        // or just insert (assuming primary key/unique constraints handle it).
        // For simplicity, we insert directly here. If VideoCast should only have one entry per VideoID,
        // you might need REPLACE INTO or an UPSERT strategy depending on SQLite version.
        $stmtInsertBanCast = $pdo->prepare("INSERT INTO VideoCast (VideoID, CastID) VALUES (?, 42)");
        $stmtInsertBanCast->execute([$currentServedVideoId]);

        $pdo->commit();
        $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Video marked as banned and associated with forbidden cast.'];

    } catch (\PDOException $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Database error while banning video: ' . $e->getMessage()];
        // Treat DB error during ban as a skip to avoid getting stuck
        if ($currentServedYoutubeId) {
            $_SESSION['skipped_videos'][] = $currentServedYoutubeId;
        }
    }
}
// Action 2: Standard Submit (if Submit was clicked, Ban is NOT checked)
else if ($isSubmit && !$isBan && $currentServedVideoId) {
    $_SESSION['last_submission_time'] = time();
    if (!empty($selectedCastNames)) { // Check if cast members were selected
        try {
            $pdo->beginTransaction();
            $stmtInsert = $pdo->prepare("INSERT INTO VideoCast (VideoID, CastID) VALUES (?, ?)");
            $submittedCount = 0;
            foreach ($selectedCastNames as $castName) {
                if (isset($castNameToIdMap[$castName])) {
                    $castId = $castNameToIdMap[$castName];
                    $stmtInsert->execute([$currentServedVideoId, $castId]);
                    $submittedCount++;
                } else {
                    error_log("Warning: Submitted cast name '{$castName}' not found in CastList.");
                }
            }

            if ($submittedCount > 0) {
                $pdo->commit();
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Video cast submitted successfully!'];
            } else {
                $pdo->rollBack();
                $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'No valid cast members selected or found. Treating as skip.'];
                // Also treat this as a skip if no valid cast were submitted
                if ($currentServedYoutubeId) {
                    $_SESSION['skipped_videos'][] = $currentServedYoutubeId;
                }
            }

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Database error: ' . $e->getMessage()];
            // Also treat this as a skip on DB error to avoid getting stuck
            if ($currentServedYoutubeId) {
                $_SESSION['skipped_videos'][] = $currentServedYoutubeId;
            }
        }
    } else {
        // Case: Submit clicked, Ban not checked, BUT no cast selected
         $_SESSION['flash_message'] = ['type' => 'info', 'text' => 'No cast selected. Video skipped.'];
         if ($currentServedYoutubeId) {
            $_SESSION['skipped_videos'][] = $currentServedYoutubeId; // Add to session skip list
         }
    }
}
// Action 3: Skip Action (Skip button OR error cases treated as skip)
else if ($isSkip) {
    if ($currentServedYoutubeId) {
        $_SESSION['skipped_videos'][] = $currentServedYoutubeId; // Add to session skip list
         $_SESSION['flash_message'] = ['type' => 'info', 'text' => 'Video skipped.'];
    }
}
// Note: Cases where $currentServedVideoId is null should implicitly do nothing or could be handled explicitly if needed.

// --- Redirect (Remains the same) ---
header("Location: " . $_SERVER['PHP_SELF']);
exit;
}

// --- PRG Pattern: Handle GET Request (Display Page) ---

// Retrieve filters from session for display and next fetch
$filterChannelId = $_SESSION['filter_channelId'] ?? '';
$filterTitleSearch = $_SESSION['filter_titleSearch'] ?? '';

// Define $filterDateStart from session OR default to empty string
$filterDateStart = $_SESSION['filter_date_start'] ?? '';

// Define $filterDateEnd from session OR default to empty string
$filterDateEnd = $_SESSION['filter_date_end'] ?? '';

// Retrieve Persisted Cast Selection
$persistedCast = $_SESSION['persistent_cast_selection'] ?? [];

// Initialize skipped videos array if needed
if (!isset($_SESSION['skipped_videos'])) {
    $_SESSION['skipped_videos'] = [];
}

// Prepare filters for the fetching function
$currentFilters = [
    'channelId' => $filterChannelId,
    'titleSearch' => $filterTitleSearch,
    'dateStart' => $filterDateStart,
    'dateEnd' => $filterDateEnd,
];

// Fetch the next video ID based on filters and skipped list
$nextVideo = getNextVideoId($pdo, $currentFilters);
$servedYoutubeId = $nextVideo['YoutubeID'] ?? null;
$servedVideoId = $nextVideo['VideoID'] ?? null;
$servedVideoTitle = $nextVideo['Title'] ?? null;

$channelOwnerName = null; // Initialize variable

    if ($servedVideoId) { // Only proceed if we have a video ID
        try {
            // 1. Get the channel ID (TEXT) from VideoList using the VideoID
            $stmtGetChannelId = $pdo->prepare("SELECT channelId FROM VideoList WHERE VideoID = :videoId LIMIT 1");
            $stmtGetChannelId->bindParam(':videoId', $servedVideoId, PDO::PARAM_INT);
            $stmtGetChannelId->execute();
            $videoInfo = $stmtGetChannelId->fetch();

            // Check if channelId was found and is not empty
            if ($videoInfo && !empty($videoInfo['channelId'])) {
                $videoChannelIdText = $videoInfo['channelId']; // This is the 'UC...' string

                // 2. Get the channel owner from ChannelData using the TEXT channel ID
                $stmtGetOwner = $pdo->prepare("SELECT channelOwner FROM ChannelData WHERE channelId = :channelIdText LIMIT 1");
                $stmtGetOwner->bindParam(':channelIdText', $videoChannelIdText, PDO::PARAM_STR);
                $stmtGetOwner->execute();
                $ownerInfo = $stmtGetOwner->fetch();

                // 3. Store the owner name if found and not null/empty
                if ($ownerInfo && !empty($ownerInfo['channelOwner'])) {
                    $channelOwnerName = $ownerInfo['channelOwner']; // Should match names in $castmember
                } else {
                }
            } else {
                 error_log("No channelId found in VideoList for VideoID: " . $servedVideoId); // Optional debug log
            }
        } catch (PDOException $e) {
            // Log potential errors like column not found if case sensitivity is wrong
            error_log("Error fetching channel owner for VideoID $servedVideoId: " . $e->getMessage());
            // $channelOwnerName remains null
        }
    }

// Clear flash message after retrieving it
$flashMessage = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Yogsearch | Help add new videos</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="preload" href="../yogthumbs.webp" as="image">
    <link rel="preload" href="../yogsearch.webp" as="image">
    <link rel="stylesheet" href="../index.css">
     <style>
        /* Basic styling for visibility - Keep or remove as needed */
        .filter-section, .video-section, .cast-section { margin-bottom: 20px; padding: 15px; border-radius: 24px; background-color:rgb(27, 32, 104); }
        .filter-section label, .filter-section input, .filter-section select { display: block; margin-bottom: 10px; }

        /* Dynamic Flash Message Area with Logo - Message Over Logo */
        header {
            margin-top: 0; /* Or your desired value */
            margin-bottom: 0px; /* Or your desired smaller value */
            /* You can also set margin-left and margin-right if needed, though they are often 0 by default for block elements */
        }

        .flash-area-container {
            position: relative;       /* Establishes positioning context for the message */
            display: flex;
            align-items: center;      /* Vertically center the logo */
            justify-content: center;  /* Horizontally center the logo */
            min-height: 150px;        /* Set to desired height of the logo area, e.g., 120px logo + 10px padding */
            margin-bottom: 0px;       /* Space below this entire area (adjust as needed) */
            padding: 5px;             /* Minimal padding for the container */
            overflow: hidden;         /* Good practice for containers */
            /* border: 1px dashed #555; /* For debugging */
        }

        .flash-area-logo {
            height: 150px;            /* Desired consistent height for the logo */
            width: auto;              /* Maintain aspect ratio */
            object-fit: contain;
            opacity: 1;               /* Logo is always fully visible initially */
            /* No transition needed for size, but opacity transition can be kept if message fades logo */
        }

        .flash-area-container > a {
        display: block;
        text-decoration: none; 
        padding: 0;             
        border: 0;              
        font-size: 0;  
        line-height: 0; 
        }

        /* Styling for the message itself when inside the flash-area-container */
        .flash-area-container .message {
                position: absolute;
                bottom: 10px;
                left: 50%;
                transform: translateX(-50%);
                width: 90%;
                max-width: 550px;
                padding: 8px 12px;
                border-radius: 4px;
                text-align: center;
                z-index: 10;
                margin-bottom: 0;
                box-sizing: border-box;
                opacity: 1; /* Start fully visible */

                /* Common animation properties for ALL messages */
                animation-name: fadeOutMessage;       /* Use the renamed keyframe */
                animation-duration: 3.5s;             /* NEW: Fade DURATION (3 to 4 seconds) */
                animation-delay: 1s;                  /* NEW: DELAY before fade starts (e.g., 2 seconds) */
                animation-timing-function: ease-out;
                animation-fill-mode: forwards;        /* Keep opacity: 0 after animation */
            }

            /* Specific background/text colors and borders for each message type */
            /* These will inherit the animation from the rule above */
            .flash-area-container .message.success {
                background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;
                
            }
            .flash-area-container .message.error {
                background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;
            }
            .flash-area-container .message.info {
                background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb;
            }
            .flash-area-container .message.warning {
                background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;
            }

        /* Original Cast Checkbox styles from index.css might be needed if not globally applied */
        /* .castcb { margin: 2px; padding: 5px 8px; border: 1px solid #aaa; border-radius: 4px; display: inline-block; cursor: pointer; background-color: #eee; color: #333;} */
        /* .hiddencb { position: absolute; opacity: 0; pointer-events: none; } */
        /* .hiddencb:checked + .castcb { background-color: #4CAF50; color: white; border-color: #45a049; } */
        .message { padding: 10px; margin-bottom: 0px; border-radius: 4px; }
        .message.success { 
            background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;
        }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .message.warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        iframe { max-width: 100%; }
        
        @keyframes fadeOutMessage { 
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
                /* Optional: You could add visibility: hidden; here if needed */
                /* visibility: hidden; */
            }
        }
            /* --- START BAN BUTTON STYLES --- */

            /* Hide the actual checkbox */
            #ban_video_checkbox {
                display: none;
            }

            /* Style the label to look like a button (default/off state) */
            #ban_video_label {
                display: inline-block; /* Allow padding and centering */
                padding: 8px 15px;
                background-color: #555; /* Light grey background */
                color: #DDD; /* Dark text */
                border: 1px solid #777;
                border-radius: 5px;
                cursor: pointer;
                font-weight: bold;
                transition: background-color 0.3s ease; /* Smooth transition */
                user-select: none; /* Prevent text selection */
            }

            /* Change label appearance when the hidden checkbox is checked (on state) */
            #ban_video_checkbox:checked + #ban_video_label {
                background-color: #d9534f; /* Red background */
                color: white; /* White text */
                border-color: #d43f3a;
            }

            /* Optional: Add a visual indicator like a checkmark or text change */
            #ban_video_checkbox:checked + #ban_video_label::before {
                content: '\2714\00A0'; /* Optional checkmark */
                /* Or change the text completely using JS if needed, but CSS is limited here */
            }

            /* Container styling */
            .ban-button-container {
                text-align: center; /* Center the button */
                margin-top: 20px;
                margin-bottom: 15px;
            }
            /* --- END BAN BUTTON STYLES --- */
            /* --- START TOOLTIP STYLES --- */

            /* Styles for the trigger element (now the label) */
            /* Ensure .tooltip-trigger styles don't conflict badly with #ban_video_label styles */
            /* We added the class, so the existing .tooltip-trigger styles will apply */
            /* Make sure the label (#ban_video_label) already has position: relative; if needed */
            #ban_video_label {
                /* ... existing label styles ... */
                position: relative; /* Ensure this is present for tooltip positioning */
                /* Remove any specific width/height if they conflict with tooltip trigger needs */
            }


            /* Tooltip text box - hidden by default */
            .tooltip-trigger::after {
            content: attr(data-tooltip);
            position: absolute;
            /* Adjusted position slightly higher relative to the taller button */
            bottom: 80%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: normal;
            white-space: nowrap;
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transition-property: opacity, visibility;
            transition-duration: 0.8s; 
            transition-timing-function: ease;
            transition-delay: 0s
            }

            /* Show the tooltip on hover */
            .tooltip-trigger:hover::after {
            opacity: 1;
            visibility: visible;
            transition-delay: 0.6s;
            }

            /* --- END TOOLTIP STYLES --- */
            /* --- START ACTION BUTTON STYLES --- */

            /* Common style for both Submit and Skip buttons */
            .action-button {
            display: inline-block; /* Allow padding */
            padding: 8px 15px; /* Match Ban button padding */
            font-size: 1em; /* Adjust as needed, make slightly larger than default maybe */
            font-weight: bold; /* Match Ban button weight */
            text-align: center;
            border-radius: 5px; /* Match Ban button radius */
            border: 1px solid #ccc; /* Default border */
            cursor: pointer;
            margin: 0 5px; /* Add some space between buttons */
            transition: background-color 0.3s ease, border-color 0.3s ease; /* Smooth transition */
            user-select: none;
            vertical-align: middle; /* Align nicely if on same line as other elements */
            }

            /* Specific style for the Submit button */
            .action-button.submit-button {
            background-color: #5cb85c; /* Green background */
            color: white;
            border-color: #4cae4c;
            }
            .action-button.submit-button:hover {
            background-color: #449d44; /* Darker green on hover */
            border-color: #398439;
            }

            /* Specific style for the Skip button */
            .action-button.skip-button {
            background-color: #f0ad4e; /* Orange background */
            color: white;
            border-color: #eea236;
            }
            .action-button.skip-button:hover {
            background-color: #ec971f; /* Darker orange on hover */
            border-color: #d58512;
            }


            /* --- END ACTION BUTTON STYLES --- */

            /* --- START Keyboard Focus Border Color Styles --- */

            /* 1. Style for FOCUSED + UNCHECKED label */
            /* This applies when the label has focus class but the preceding checkbox is NOT checked */
            .castcb.focused-label {
                border-color: #007bff; /* Blue focus color for unchecked items */
            }

            /* 2. Style for FOCUSED + CHECKED label */
            /* This is more specific and overrides the above rule and the default checked rule */
            /* It applies when the preceding checkbox IS checked AND the label has the focus class */
            .hiddencb:checked + .castcb.focused-label {
                border-color:rgb(255, 187, 0); /* Darker orange for focused+checked items */
                /* You can adjust this color - other options: #D97707, or a brighter orange */
            }

            /* --- END Keyboard Focus Border Color Styles --- */
            /* Video Title Styling */
            .video-title-display {
                /* Default state for smaller screens: allow wrapping */
                white-space: normal; /* Allows text to wrap */
                overflow-wrap: break-word; /* Helps break long words to prevent overflow if needed */
                /* The existing inline styles for text-align, margin, color will still apply */
                /* No explicit max-width here, so it takes available width and wraps */
            }

            /* Apply truncation when the viewport is at or above the page's max-width */
            @media (min-width: 1148px) {
                .video-title-display {
                    /* Ensure the h3 block itself doesn't exceed the video's width and is centered */
                    max-width: 1148px;   /* Assuming video width is 640px, adjust if different */
                    margin-left: auto;  /* Centers the h3 block if it's narrower than its container */
                    margin-right: auto; /* Centers the h3 block */

                    /* Truncation styles */
                    white-space: nowrap;     /* Keep the text on a single line */
                    overflow: hidden;        /* Hide the text that overflows */
                    text-overflow: ellipsis; /* Display "..." for overflowed text */
                }
            }


    </style>
</head>
<body>
    <main>
        <header>
        <div class="flash-area-container">
        <a href="/"><img src="yogsearch.webp" alt="Yogsearch Logo" class="flash-area-logo"></a>
        <?php if ($flashMessage): ?>
            <div class="message <?= htmlspecialchars($flashMessage['type']) ?>">
                <?= htmlspecialchars($flashMessage['text']) ?>
            </div>
        <?php endif; ?>
        </div>
        </header>

        <article class="searchresults" style="padding-bottom: 0px;"> <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post">
        <details class="filter-details" style="margin-top: 0px; margin-right: auto; margin-bottom: 15px; margin-left: auto; max-width: 600px;"> <summary class="filter-summary">Filter Video</summary>
            <div style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 15px; margin-top: 15px; margin-bottom: 15px;">
                <div style="flex: 1; min-width: 200px;"> 
                    <label for="filter_channel" style="display: block; margin-bottom: 5px;">Filter by Channel:</label>
                    <select name="filter_channel" id="filter_channel" style="width: 100%; padding: 5px; border-radius: 4px; background-color:#232a88; color:white; border: 1px solid #091057;">
                        <option value="">-- All Channels --</option>
                        <?php
                        if (isset($castWhiteList) && isset($channelNameToIdMap)) { // Ensure variables are set
                            foreach ($castWhiteList as $channelName) {
                                if (isset($channelNameToIdMap[$channelName])) {
                                    $channelId = $channelNameToIdMap[$channelName];
                                    $selected = (isset($filterChannelId) && $filterChannelId == $channelId) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($channelId) . '" ' . $selected . '>'
                                    . htmlspecialchars($channelName)
                                    . '</option>';
                                }
                            }
                        }
                        ?>
                    </select>
                </div>

                <div style="flex: 1; min-width: 200px;"> 
                    <label for="filter_title" style="display: block; margin-bottom: 5px;">Title Contains:</label>
                    <input type="text" name="filter_title" id="filter_title" placeholder="e.g., TTT, Minecraft" value="<?= isset($filterTitleSearch) ? htmlspecialchars($filterTitleSearch) : '' ?>" style="width: 100%; padding: 5px; border-radius: 4px; background-color:#232a88; color:white; border: 1px solid #091057;">
                </div>

            </div>


            <div class="date-range-container" style="margin-bottom: 15px;">
                <div class="date-inputs-wrapper">
                    <div class="date-input-group">
                        <label for="filter_date_start" class="date-label">Published from:</label>
                        <input type="date" name="filter_date_start" id="filter_date_start" class="date-input" value="<?= isset($filterDateStart) ? htmlspecialchars($filterDateStart) : '' ?>">
                    </div>
                    <span class="date-separator" aria-hidden="true">–</span>
                    <div class="date-input-group">
                        <label for="filter_date_end" class="date-label">Published to:</label>
                        <input type="date" name="filter_date_end" id="filter_date_end" class="date-input" value="<?= isset($filterDateEnd) ? htmlspecialchars($filterDateEnd) : '' ?>">
                    </div>
                </div>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" name="apply_filter" class="filter-apply-button">Apply Filters & Find Video</button>
            </div>
        </details>
                 <?php if ($servedVideoTitle): ?>
                    <h4 class="video-title-display" style="text-align: center; margin-top: 15px; margin-bottom: 10px; color: #ffffff;"><?= htmlspecialchars($servedVideoTitle) ?></h4>
                <?php endif; ?>
                <?php if ($servedYoutubeId): ?>                  
                    <iframe id="youtube-player" width="640" height="360" tabindex="-1"
                        src="https://www.youtube.com/embed/<?= htmlspecialchars($servedYoutubeId) ?>?autoplay=1&mute=1&enablejsapi=1"
                        frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen>
                    </iframe>
                    <input type="hidden" name="servedYoutubeId" value="<?= htmlspecialchars($servedYoutubeId) ?>">
                    <input type="hidden" name="servedVideoId" value="<?= htmlspecialchars($servedVideoId) ?>">

                    <section class="noselect" style="margin-top: 0px;">
                        <div class="card card-contribute" style="margin-top: 0px !important;">
                            <?php
                                foreach ($castmember as $icast){
                                    if (!empty($persistedCast)) {
                                        $isChecked = in_array($icast, $persistedCast) ? 'checked' : '';
                                    } else {
                                        $isChecked = (!empty($channelOwnerName) && $icast === $channelOwnerName) ? 'checked' : '';
                                    }
                            ?>
                                <input type="checkbox" class="hiddencb" id="<?= htmlspecialchars($icast) ?>" name="iid[]" value="<?= htmlspecialchars($icast) ?>" <?= $isChecked ?>>
                                <label for="<?= htmlspecialchars($icast) ?>" class="castcb castcb-contribute <?= htmlspecialchars($icast) ?>"><?= htmlspecialchars($icast) ?></label>
                            <?php }; ?>
                        </div>
                    </section>
                <div class="ban-button-container">
                    <input type="checkbox"
                        id="ban_video_checkbox"
                        name="ban_video"
                        value="1">
                    <label for="ban_video_checkbox" id="ban_video_label" class="tooltip-trigger" data-tooltip="Press if video contains forbidden member"> 
                        Video contains forbidden past member
                    </label>
                    <p style="font-size: 0.9em; color: #A0A8D0; margin-top: 5px;">(Any past member who did not leave the Yogscast on good terms is forbidden)</p>
                </div>
                <section class="action-section">
                    <p>
                        <button type="submit" name="submit_action" id="Searchsubmit" value="submit" class="action-button submit-button">Submit</button>

                        <button type="submit" name="skip_action" value="skip" class="action-button skip-button">Skip Video</button>
                    </p>
                </section>

                <?php else: ?>
                    <section>
                        <p>No more videos found matching the criteria, or all videos have been cast/skipped in this session.</p>
                    </section>
                <?php endif; ?>

            </form>
         </article> 
        </main>
<!-- Avert your eyes, nasty, DESPICABLE !javascript! ahead. Pls give something else DOM access browser gods. -->
<?php /* Only include the JS if the video is being displayed 
        KEYBOARD SHORTCUTS - M(ute) K(Play) H(2x) O & P (fast seek) ARROW KEYS (cast cursor) SPACE (check selected cast)
*/ if ($servedYoutubeId): ?>
    <script>
        // 1. Loads the IFrame Player API code asynchronously.
        var tag = document.createElement('script');
            tag.src = "https://www.youtube.com/iframe_api"; // Use standard HTTPS source
            var firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

            // 2. This function creates an <iframe> (and YouTube player)
            //    after the API code downloads. Player object is global.
            var player;
            function onYouTubeIframeAPIReady() {
                player = new YT.Player('youtube-player', {
                    events: {
                        // Optional: Add callbacks if needed
                        // 'onReady': onPlayerReady,
                        // 'onStateChange': onPlayerStateChange
                    }
                });
                console.log("YouTube IFrame API Ready and player initializing.");
            }

            // --- START: State Variables & Helpers for Keyboard Navigation ---

            const castLabels = document.querySelectorAll('.noselect .castcb'); // Get all labels
            const focusedClass = 'focused-label'; // CSS class for the focus style
            const seekPercentages = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90]; // Percentages for seeking

            let focusedLabelIndex = -1; // Index of the currently focused label (-1 = none)
            let itemsPerRow = calculateItemsPerRow(); // Calculate how many items fit per row (Needs function below)
            let seekPercentageIndex = 0; // Represents 0%, 10%, 20%... (0-9 index) for O/P keys

            // Helper function to calculate items per row based on vertical position
            function calculateItemsPerRow() {
                if (!castLabels || castLabels.length === 0) { return castLabels.length; }
                // Ensure elements are available before calculating offsetTop
                if (castLabels[0].offsetTop === undefined) {
                    console.warn("Cannot calculate itemsPerRow yet, elements might not be fully rendered.");
                    return castLabels.length; // Fallback
                }
                const firstTop = castLabels[0].offsetTop;
                let count = 0;
                for (let i = 0; i < castLabels.length; i++) {
                    if (castLabels[i].offsetTop === firstTop) { count++; } else { break; }
                }
                // Handle case where offsetTop calculation might be unreliable initially or all are same.
                return (count > 0) ? count : (castLabels.length > 0 ? 1 : 0); // Assume at least 1 if labels exist
            }

            // Helper function to update the visual focus on labels
            function updateFocus(newIndex) {
                // Ensure the new index is within bounds (0 to length-1)
                if (newIndex < 0 || newIndex >= castLabels.length) {
                    console.log(`Focus update stopped: Index ${newIndex} out of bounds (0-${castLabels.length - 1})`);
                    return; // Stop if the index is invalid
                }

                // Remove focus from the previously focused label (if any)
                if (focusedLabelIndex !== -1 && castLabels[focusedLabelIndex]) {
                    castLabels[focusedLabelIndex].classList.remove(focusedClass);
                } else if (focusedLabelIndex !== -1) {
                    // This might happen if the label somehow disappeared from the DOM
                    console.warn("Previous focused label at index", focusedLabelIndex, "not found in castLabels list.");
                }


                // Set the new focused index
                focusedLabelIndex = newIndex;

                // Add focus style to the new label
                const currentLabel = castLabels[focusedLabelIndex];
                if (currentLabel) {
                    currentLabel.classList.add(focusedClass);
                    // Scroll the focused label into view if needed
                    currentLabel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    console.log("Focus updated to label index:", focusedLabelIndex);
                } else {
                    // This case should ideally not happen if bounds check is correct
                    console.error("Error: Could not find label to focus at new index:", focusedLabelIndex);
                }
            }

            // Recalculate itemsPerRow if the window is resized (debounced)
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    console.log("Window resized, recalculating items per row.");
                    itemsPerRow = calculateItemsPerRow();
                }, 250); // Wait 250ms after resize stops before recalculating
            });

            // Initial calculation might need slight delay if elements aren't ready immediately
            window.addEventListener('load', () => {
                itemsPerRow = calculateItemsPerRow();
                console.log("Initial items per row calculated:", itemsPerRow);
                // Optional: Focus first element on load
                // if (castLabels.length > 0) { updateFocus(0); }
            });


            // --- END: State Variables & Helpers ---


            // --- START: Combined Keydown Event Listener ---
            document.addEventListener('keydown', function(event) {

                // --- Player Check ---
                // We check for player object *and* specific functions before using them in relevant cases below.
                // If player is generally needed, basic check can happen here:
                if (!player) {
                    console.log("Player object not available yet.");
                    // Depending on desired behavior, you might return or allow non-player keys to function.
                    // Let's allow non-player keys (like arrows) to work even if player is loading.
                }

                // --- Input Focus Check ---
                const activeElement = document.activeElement;
                const isInputFocused = activeElement && (
                    activeElement.tagName === 'INPUT' ||
                    activeElement.tagName === 'TEXTAREA' ||
                    activeElement.isContentEditable
                );

                // --- Only handle keys if NOT focused on an input ---
                if (!isInputFocused) {
                    let handled = false;          // Flag to track if key was processed by our logic
                    let targetIndex = focusedLabelIndex; // Variable specific to arrow key navigation

                    // --- Key Handling Logic ---
                    switch (event.key.toLowerCase()) { // Use lowercase for consistent matching

                        // == Player Controls (M, K, H, O, P) ==
                        case 'm': // Toggle Mute
                            if (player && typeof player.isMuted === 'function' && typeof player.mute === 'function' && typeof player.unMute === 'function') {
                                if (player.isMuted()) {
                                    player.unMute();
                                    console.log("M key: Unmuted");
                                } else {
                                    player.mute();
                                    console.log("M key: Muted");
                                }
                                handled = true;
                            } else { console.log("M key: Player or mute functions not ready."); }
                            break;

                        case 'k': // Toggle Play/Pause
                            // Check player is ready AND YT object is available for PlayerState constants
                            if (player && typeof YT !== 'undefined' && typeof player.getPlayerState === 'function' && typeof player.playVideo === 'function' && typeof player.pauseVideo === 'function') {
                                const playerState = player.getPlayerState();
                                if (playerState === YT.PlayerState.PLAYING) {
                                    player.pauseVideo();
                                    console.log("K key: Paused");
                                } else {
                                    player.playVideo();
                                    console.log("K key: Playing");
                                }
                                handled = true;
                            } else { console.log("K key: Player or YT object not ready."); }
                            break;

                        case 'h': // Toggle Playback Speed (1x / 2x)
                            if (player && typeof player.getPlaybackRate === 'function' && typeof player.setPlaybackRate === 'function') {
                                const currentRate = player.getPlaybackRate();
                                const targetRate = (currentRate === 2) ? 1 : 2;
                                player.setPlaybackRate(targetRate);
                                console.log("H key: Playback rate set to", targetRate + "x");
                                handled = true;
                            } else { console.log("H key: Player or playback rate functions not ready."); }
                            break;

                        case 'p': // Seek Forward +10% (NO WRAP)
                            if (player && typeof player.seekTo === 'function' && typeof player.getDuration === 'function') {
                                if (seekPercentageIndex < seekPercentages.length - 1) { // Check if NOT already at 90% (index 9)
                                    seekPercentageIndex++; // Increment index only if less than max
                                    const targetPercent = seekPercentages[seekPercentageIndex];
                                    const duration = player.getDuration();
                                    if (duration && duration > 0) {
                                        const seekTime = duration * (targetPercent / 100);
                                        player.seekTo(seekTime, true); // allowSeekAhead = true
                                        console.log(`P key: Seeking to ${targetPercent}% (${seekTime.toFixed(1)}s)`);
                                        handled = true;
                                    } else { console.log("P key: Player duration not available yet."); }
                                } else {
                                    console.log("P key: Already at 90%"); // Already at the end
                                    // handled = false; // Optionally don't prevent default if no action taken
                                }
                            } else { console.log("P key: Player or seek/duration functions not ready."); }
                            break;

                        case 'o': // Seek Backward -10% (NO WRAP)
                            if (player && typeof player.seekTo === 'function' && typeof player.getDuration === 'function') {
                                if (seekPercentageIndex > 0) { // Check if NOT already at 0% (index 0)
                                    seekPercentageIndex--; // Decrement index only if greater than 0
                                    const targetPercent = seekPercentages[seekPercentageIndex];
                                    const duration = player.getDuration();
                                    if (duration && duration > 0) {
                                        const seekTime = duration * (targetPercent / 100);
                                        player.seekTo(seekTime, true); // allowSeekAhead = true
                                        console.log(`O key: Seeking to ${targetPercent}% (${seekTime.toFixed(1)}s)`);
                                        handled = true;
                                    } else { console.log("O key: Player duration not available yet."); }
                                } else {
                                    console.log("O key: Already at 0%"); // Already at the beginning
                                    // handled = false; // Optionally don't prevent default if no action taken
                                }
                            } else { console.log("O key: Player or seek/duration functions not ready."); }
                            break;

                        // == Checkbox Navigation/Selection ==
                        case 'arrowup':
                            if (castLabels.length === 0) break; // Skip if no labels
                            if (focusedLabelIndex === -1) targetIndex = 0; // Initial focus moves to first item
                            else targetIndex = focusedLabelIndex - itemsPerRow; // Calculate potential new index
                            handled = true;
                            break;
                        case 'arrowdown':
                            if (castLabels.length === 0) break;
                            if (focusedLabelIndex === -1) targetIndex = 0;
                            else targetIndex = focusedLabelIndex + itemsPerRow;
                            handled = true;
                            break;
                        case 'arrowleft':
                            if (castLabels.length === 0) break;
                            if (focusedLabelIndex === -1) targetIndex = 0;
                            else targetIndex = focusedLabelIndex - 1;
                            handled = true;
                            break;
                        case 'arrowright':
                            if (castLabels.length === 0) break;
                            if (focusedLabelIndex === -1) targetIndex = 0;
                            else targetIndex = focusedLabelIndex + 1;
                            handled = true;
                            break;

                        case ' ': // Space bar
                        case 'enter':
                            if (castLabels.length > 0 && focusedLabelIndex !== -1) {
                                const focusedLabel = castLabels[focusedLabelIndex];
                                if (!focusedLabel) {
                                    console.error("Space/Enter: Focused label at index", focusedLabelIndex, "not found.");
                                    break;
                                }
                                const checkboxId = focusedLabel.getAttribute('for');
                                const checkbox = document.getElementById(checkboxId);
                                if (checkbox) {
                                    checkbox.checked = !checkbox.checked; // Toggle checked state
                                    console.log("Space/Enter: Toggled checkbox for label index", focusedLabelIndex);
                                    // Manually trigger change event if other JS relies on it
                                    // checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                                    handled = true;
                                } else {
                                    console.error("Space/Enter: Checkbox with ID", checkboxId, "not found for label index", focusedLabelIndex);
                                }
                            } else {
                                // No label focused, maybe allow default space/enter behavior (e.g., for buttons)
                                // handled = false; // Ensure default behavior isn't prevented
                            }
                            break;

                    } // --- End Switch ---

                    // --- Post-Handling Actions (After Switch) ---
                    if (handled) {
                        event.preventDefault(); // Prevent default browser action for ALL handled keys

                        // Update visual focus ONLY IF arrow keys potentially changed the target index
                        if (['arrowup', 'arrowdown', 'arrowleft', 'arrowright'].includes(event.key.toLowerCase())) {
                            // Check if the targetIndex is different OR if it's the initial focus action
                            if (targetIndex !== focusedLabelIndex || (focusedLabelIndex === -1 && targetIndex === 0)) {
                                updateFocus(targetIndex); // Call the helper function to move focus visually
                            }
                        }
                    }
                } // --- End if (!isInputFocused) ---
            }); // --- END: Combined Keydown Event Listener ---


            // --- Optional Placeholder Functions for YT Player Events ---

            // 4. The API will call this function when the video player is ready.
            // function onPlayerReady(event) {
            //   // Example: Auto-play or other setup tasks
            //   // event.target.playVideo();
            //   console.log("Player Ready (onPlayerReady callback)");
            //   // Could recalculate itemsPerRow here too if needed after player load potentially shifts layout
            //   // itemsPerRow = calculateItemsPerRow();
            // }

            // 5. The API calls this function when the player's state changes.
            // function onPlayerStateChange(event) {
            //   // Example: Track plays/pauses etc.
            //   if (event.data == YT.PlayerState.PLAYING) {
            //     console.log("Player State: Playing");
            //   }
            //   if (event.data == YT.PlayerState.PAUSED) {
            //       console.log("Player State: Paused");
            //   }
            //   // etc. for other states like BUFFERING, ENDED, CUED
            // }
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterDetails = document.querySelector('details.filter-details');

        if (!filterDetails) {
            console.warn('Filter details element (.filter-details) not found.');
            return;
        }

        function setFilterVisibilityBasedOnFit() {
            const activeElement = document.activeElement;
            // Define the IDs of your filter input elements
            const filterInputIds = ['filter_channel', 'filter_title', 'filter_date_start', 'filter_date_end'];
            const isFilterInputFocused = filterInputIds.some(id => activeElement && activeElement.id === id);

            // If a filter input is focused AND the filter details are currently open,
            // it's likely the resize event is due to the virtual keyboard.
            // In this case, we prevent the function from closing the details.
            if (isFilterInputFocused && filterDetails.open) {
                // console.log('Filter input focused and details open, preventing auto-close on this resize.');
                return; // Exit early, leaving the filter details open
            }

            // Original logic to determine if filter should be open or closed based on content fit.
            // Programmatically open the filterDetails to accurately measure its content's
            // impact on the document's total scrollHeight.
            filterDetails.open = true;

            requestAnimationFrame(function() {
                const documentScrollHeight = document.documentElement.scrollHeight;
                const viewportHeight = window.innerHeight;

                if (documentScrollHeight > viewportHeight) {
                    // If the document's total height (with the filter section open)
                    // is greater than the viewport's height, it means a scrollbar
                    // is present (or would be needed). So, close the filter details section.
                    filterDetails.open = false;
                    // console.log('Content overflows, filter automatically closed.');
                } else {
                    // The document fits within the viewport even with the filter section open.
                    // filterDetails.open was already set to true for measurement, so it remains open.
                    // console.log('Content fits, filter automatically opened/kept open.');
                }
            });
        }

        // Run the check when the page is initially loaded
        setFilterVisibilityBasedOnFit();

        // Optionally, re-run the check if the window is resized
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            // Debounce the resize event to avoid excessive calls
            resizeTimeout = setTimeout(setFilterVisibilityBasedOnFit, 250);
        });
    });
    </script>
<?php endif; /* End the check for $servedYoutubeId */ ?>        
</body>
</html>