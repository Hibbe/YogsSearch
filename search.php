<?php
require_once 'config.php';

function getVideosByCreators(PDO $pdo, array $creators, int $page = 1, int $limit = 20) { // Search function query

    $creator_list = str_repeat('?, ', count($creators) - 1) . '?';
    $creator_count = count($creators);
    $offset = ($page - 1) * $limit; // Calculate the offset

    $sql = "SELECT vl.YoutubeID, vl.Title
            FROM VideoList vl
            INNER JOIN VideoCast vc ON vl.VideoID = vc.VideoID
            INNER JOIN CastList cl ON cl.CastID = vc.CastID
            WHERE cl.CastName IN ($creator_list)
            GROUP BY vl.VideoID
            HAVING COUNT(*) = :creator_count
            LIMIT :limit OFFSET :offset"; // Use LIMIT and OFFSET with placeholders

    $stmt = $pdo->prepare($sql);

    // Bind creator names (string type)
    foreach ($creators as $key => $creator) {
        $stmt->bindValue($key + 1, $creator, PDO::PARAM_STR);
    }
    // Bind creator count, limit and offset (integer type)
    $stmt->bindValue(':creator_count', $creator_count, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function countTotalVideosByCreators(PDO $pdo, array $creators): int { // Function to count total results

    $creator_list = str_repeat('?, ', count($creators) - 1) . '?';
    $creator_count = count($creators);

    // SQL to count the total number of videos (using a subquery for correctness with GROUP BY/HAVING)
    $sql = "SELECT COUNT(*) FROM (
                SELECT vl.VideoID
                FROM VideoList vl
                INNER JOIN VideoCast vc ON vl.VideoID = vc.VideoID
                INNER JOIN CastList cl ON cl.CastID = vc.CastID
                WHERE cl.CastName IN ($creator_list)
                GROUP BY vl.VideoID
                HAVING COUNT(*) = :creator_count
            ) AS TotalCount";

    $stmt = $pdo->prepare($sql);

    // Bind creator names (string type)
    foreach ($creators as $key => $creator) {
        $stmt->bindValue($key + 1, $creator, PDO::PARAM_STR);
    }
    // Bind creator count (integer type)
     $stmt->bindValue(':creator_count', $creator_count, PDO::PARAM_INT);

    $stmt->execute();
    return (int)$stmt->fetchColumn(); // Fetch the single count value
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

$videos = []; // Initialize videos array
$total_videos = 0; // Initialize total count
$current_page = 1; // Default page
$results_per_page = 20; // Set results per page
$total_pages = 0; // Initialize total pages
$creators = []; // Initialize creators array
$urlrep = ''; // Initialize URL rep string
$base_query_string = ''; // To store iid parameters for pagination links

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

        $redirect_query_parts = [];
        // Prioritize POST data if you add hidden fields, otherwise fallback to GET
        $current_iids = $_POST['iid'] ?? $_GET['iid'] ?? []; // Example: Get iid from hidden field if exists
        $current_pg = $_POST['page'] ?? $_GET['page'] ?? 1; // Example: Get page from hidden field if exists
        if (is_array($current_iids)) {
            foreach ($current_iids as $c) {
                if (!empty($c)) { // Ensure creator ID is not empty
                    $redirect_query_parts[] = 'iid%5B%5D=' . urlencode($c);
                }
            }
        }
        $redirect_query_parts[] = 'page=' . (int)$current_pg;
        // Use the submitted video ID for repid in the redirect URL
        if (isset($_SESSION['RepYtId'])) {
            $redirect_query_parts[] = 'repid=' . urlencode($_SESSION['RepYtId']);
        }
        $redirect_base_url = 'search.php?' . implode('&', $redirect_query_parts);
        try {
            if (insertreport($pdo, $_SESSION)) {
                header( "Location: {$redirect_base_url}&rep=success#TI" . urlencode($_SESSION['RepYtId']), true, 303 );
                exit();
            } else {
                header( "Location: {$redirect_base_url}&rep=fail#TI" . urlencode($_SESSION['RepYtId']), true, 303 );
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


else if (isset($_GET['iid']) && is_array($_GET['iid']) && count($_GET['iid']) >= 1 && count($_GET['iid']) <= 12) {
    try {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $creators = array_map('htmlspecialchars', $_GET['iid']);

        // Build base query string for pagination links (excluding page param)
        $query_params = [];
        foreach ($creators as $c) {
            $query_params[] = 'iid%5B%5D=' . urlencode($c); 
        }
        $base_query_string = implode('&', $query_params);
        if ($urlrep === '') { // Build urlrep only if not set by POST logic earlier
             $urlrep = implode("&iid%5B%5D=", $creators); // Keep original urlrep logic if needed elsewhere
        }


        // Get the current page number from URL, default to 1
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($current_page < 1) {
            $current_page = 1; 
        }

        // Get the total count of videos FIRST
        $total_videos = countTotalVideosByCreators($pdo, $creators); 

        if ($total_videos > 0) {
            $total_pages = (int)ceil($total_videos / $results_per_page);

            // Ensure current page is not out of bounds
            if ($current_page > $total_pages) {
                $current_page = $total_pages;
            }

             // Fetch only the videos for the current page
            $videos = getVideosByCreators($pdo, $creators, $current_page, $results_per_page); 
        } else {
             // No videos found, reset pages
             $total_pages = 0;
             $current_page = 1; // Or 0, depending on preference
        }

    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
        // Reset variables on error
        $videos = [];
        $total_videos = 0;
        $total_pages = 0;
        $creators = []; // Ensure creators is an array
    } finally {
        $pdo = null;
    }
} else {
    // Handle case where no valid creators are selected
     if (!($_SERVER['REQUEST_METHOD'] == 'POST')) { // Avoid resetting if it's a POST request
          $creators = []; // Ensure creators is an empty array unless handling POST
          // Set a message or handle appropriately
     }
}
// This line might be removed or adjusted depending on where $urlrep is truly needed
// if ($creators != 1) {$urlrep = implode("&iid%5B%5D=", $creators);} // <<< Review if still needed here




?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Yogsearch | <?php if (empty($creators)) { echo "Please select between 1 and 12 creators";} else if($total_videos === 0) { echo "No videos found";} else echo("Found ".$total_videos." videos with ".join(' & ', array_filter(array_merge(array(join(', ', array_slice($creators, 0, -1))), array_slice($creators, -1)), 'strlen'))); ?> </title>
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
        <style>
            .pagination {
                margin-top: 25px;
                margin-bottom: 15px;
                text-align: center;
            }

            .pagination ul {
                list-style: none;
                padding: 0;
                margin: 0;
                display: inline-block;
            }

            .pagination li {
                display: inline;
                margin: 0 5px; /* Spacing between pills */
            }

            /* Base style for both Links (<a>) and Disabled Spans (<span>) */
            .pagination li a,
            .pagination li span {
                display: inline-block;
                padding: 8px 18px; /* Adjust padding for size */
                border: 1px solid #161D70; /* Slightly darker blue border */
                background-color: #2c349e; /* Report submit button blue */
                color: white; /* White text */
                text-decoration: none;
                border-radius: 50px; /* Pill shape */
                transition: background-color 0.2s, color 0.2s, border-color 0.2s; /* Smooth hover effect */
                font-weight: 500;
            }

            /* Hover effect ONLY for clickable links (<a>) */
            .pagination li a:hover {
                background-color: #EC8305; /* Primary Orange background */
                color: #091057; /* Deep Blue text (like main submit button) */
                border-color: #be6b05; /* Darker orange border */
            }

            /* Style for Disabled Spans (<span>) */
            .pagination li.disabled span {
                background-color: #232a88; /* Card blue (less prominent) */
                color: #8a8e91; /* Muted text color (like report link) */
                border-color: #161D70; /* Keep the dark border or make it match bg */
                cursor: default;
            }
        </style>
    </head>
    <body>
        <header> 
            <img width="800" height="289" src="yogsearch.webp" alt="Yogsearch logo" class="yogart">
        </header>
        <main>
        <?php // --- Pagination Links ---
            // Show nav container if a search was done (e.g., creators selected)
            if (!empty($creators)): ?>
            <nav class="pagination">
                <ul>
                    <?php // Previous Page Link (Shows "Back to Search" on Page 1)
                        if ($current_page > 1): ?>
                        <li><a href="?<?= htmlspecialchars($base_query_string); ?>&page=<?= $current_page - 1; ?><?= isset($_GET['repid']) ? '&repid='.urlencode($_GET['repid']) : ''; ?>">« Previous</a></li>
                    <?php else: // When current_page is 1 ?>
                        <li><a href="/index.php">« Back to Search</a></li>
                    <?php endif; ?>


                    <?php // Conditionally show the Next button block only if multiple pages exist
                        if ($total_pages > 1):
                    ?>
                        <?php // --- Next Page Link --- (Handles enabled/disabled state internally)
                            if ($current_page < $total_pages): ?>
                            <li><a href="?<?= htmlspecialchars($base_query_string); ?>&page=<?= $current_page + 1; ?><?= isset($_GET['repid']) ? '&repid='.urlencode($_GET['repid']) : ''; ?>">Next »</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>Next »</span></li>
                        <?php endif; // End of inner if/else for Next link content ?>

                    <?php endif; // End of wrapper condition for showing Next button block ?>

                </ul>
            </nav>
        <?php endif; // End of outer condition: if (!empty($creators)) ?>
        <?php // Display messages or results
                        if (empty($creators) && !($_SERVER['REQUEST_METHOD'] == 'POST')) { // Show message only if not a POST result
                            echo "<p>Please select between 1 and 12 creators using the search on the main page.</p>";
                        } elseif (!empty($creators) && $total_videos === 0) { // Check creators is not empty before saying no videos found
                            echo "<p>No videos found matching the selected creators.</p>";
                        } elseif ($total_videos > 0) {
                            // Display the total count and page info
                            echo "<p>Found $total_videos results. Showing page $current_page of $total_pages.</p>";
                        }
                ?>
            <article class="searchresults">  <!-- Here go the results of the search-->
                <?php  
                foreach ($videos as $video): {  //Search result cards
                    $report_base_url = "?{$base_query_string}&page={$current_page}";
                    $report_url_with_repid = "{$report_base_url}&repid=" . urlencode($video['YoutubeID']) . "#TI" . urlencode($video['YoutubeID']);
                    $report_url_without_repid = "{$report_base_url}#TI" . urlencode($video['YoutubeID']); 
                ?>
                        <div class="card">
                        <label for="vidTITLE" id="TI<?= htmlspecialchars($video['YoutubeID']); ?>"><h4><a alt="<?= htmlspecialchars($video['YoutubeID']); ?>" id="TI<?= htmlspecialchars($video['YoutubeID']); ?>" href="https://youtube.com/watch?v=<?= htmlspecialchars($video['YoutubeID']); ?>" target="_blank" rel="noopener noreferrer"> <span class="TitleWidth"><?= htmlspecialchars($video['Title']); ?></span></h4></label>
                            <div class="ytimg"><img alt="Thumbnail" src="https://i.ytimg.com/vi/<?= htmlspecialchars($video['YoutubeID']); ?>/hqdefault.jpg"></a></div>
                            <?php if (isset($_GET['repid']) and $_GET['repid'] == $video['YoutubeID']) { //RepID is set in cardReport(href)?>
                            <a class="cardReport" href="<?= htmlspecialchars($report_url_without_repid); ?>">Report issue</a>
                            <?php } else { ?>
                            <a class="cardReport" href="<?= htmlspecialchars($report_url_with_repid); ?>">Report issue</a>
                            <?php } ?>
                        </div>
                    <?php  if (isset($_GET['repid']) and $_GET['repid'] == $video['YoutubeID'] ) { //RepID is set in cardReport(href)?>
                        <div class="card repcard">
                            <?php if (!isset($_GET['rep'])) { //report function popout?>
                                <form action="?<?= htmlspecialchars($base_query_string); ?>&page=<?= $current_page; ?><?= isset($_GET['repid']) ? '&repid='.urlencode($_GET['repid']) : ''; ?>#RepForm" method="POST" id="RepForm">
                                    <input type="hidden" id="RepYtId" name="RepYtId" value="<?= htmlspecialchars($video['YoutubeID']); ?>">
                                    <input type="hidden" name="page" value="<?= $current_page; ?>">
                                    <?php foreach ($creators as $creator_id): ?><input type="hidden" name="iid[]" value="<?= htmlspecialchars($creator_id); ?>"><?php endforeach; ?>
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
            <?php // --- Pagination Links ---
                // Show nav container if a search was done (e.g., creators selected)
                if (!empty($creators)): ?>
                <nav class="pagination">
                    <ul>
                        <?php // Previous Page Link (Shows "Back to Search" on Page 1)
                            if ($current_page > 1): ?>
                            <li><a href="?<?= htmlspecialchars($base_query_string); ?>&page=<?= $current_page - 1; ?><?= isset($_GET['repid']) ? '&repid='.urlencode($_GET['repid']) : ''; ?>">« Previous</a></li>
                        <?php else: // When current_page is 1 ?>
                            <li><a href="/index.php">« Back to Search</a></li>
                        <?php endif; ?>


                        <?php // Conditionally show the Next button block only if multiple pages exist
                            if ($total_pages > 1):
                        ?>
                            <?php // --- Next Page Link --- (Handles enabled/disabled state internally)
                                if ($current_page < $total_pages): ?>
                                <li><a href="?<?= htmlspecialchars($base_query_string); ?>&page=<?= $current_page + 1; ?><?= isset($_GET['repid']) ? '&repid='.urlencode($_GET['repid']) : ''; ?>">Next »</a></li>
                            <?php else: ?>
                                <li class="disabled"><span>Next »</span></li>
                            <?php endif; // End of inner if/else for Next link content ?>

                        <?php endif; // End of wrapper condition for showing Next button block ?>

                    </ul>
                </nav>
            <?php endif; // End of outer condition: if (!empty($creators)) ?>
        </main>
        <footer>
            <a href='/faq.php' class=folinks>About</a> | <a href='https://github.com/Hibbe/YogsSearch' class=folinks target="_blank">Github</a> | <a href='/faq.php#faq' class=folinks>Contribute</a>
            <p class="fodisc">Yogsearch is a <strong>fanpage</strong> and is <strong>not associated with or endorsed</strong> by the Yogscast</p>
        </footer>
    </body>
</html>
