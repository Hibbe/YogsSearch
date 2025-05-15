<?php
session_start();
require_once 'config.php';

$dsn = "sqlite:$db";
$dsrep = "sqlite:$dbrep";

define('CARDS_PER_PAGE', 20);        // Number of cards (playlist or video) to display per page
define('MAX_DISPLAY_PAGES', 20);     // Maximum number of pages accessible to the user
define('MAX_VIDEOS_TO_SCAN_FOR_CARDS', 1500); // Max raw videos to fetch from DB to build the card list

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION)) { // Ensure session is started if not already
        session_start();
    }

    // Differentiate POST type: Is it a report submission or a filter submission?
    if (isset($_POST['RepYtId']) && isset($_POST['repimp'])) {
        // --- HANDLE REPORT SUBMISSION ---
        $RepYtId = htmlspecialchars($_POST['RepYtId']);
        $repimp = htmlspecialchars($_POST['repimp']);

        // Get iid[] and page from the POSTed hidden fields from the report form
        // These are crucial for redirecting back to the correct search state.
        $current_iids_from_form = $_POST['iid'] ?? []; 
        $current_page_from_form = $_POST['page'] ?? 1;

        $pdo_rep = null; 
        $report_success = false;
        try {
            $pdo_rep = new PDO($dsrep); // Use your database connection for reports
            $pdo_rep->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Pass only the necessary data to insertreport
            if (insertreport($pdo_rep, ['RepYtId' => $RepYtId, 'repimp' => $repimp])) {
                $report_success = true;
            }
        } catch (PDOException $e) {
            error_log("Report Database error: " . $e->getMessage());
            // $report_success remains false
        } finally {
            $pdo_rep = null;
        }

        // Construct the redirect URL to go back to the search results page,
        // showing the success/fail message and staying on the correct video.
        $redirect_query_parts = [];
        if (!empty($current_iids_from_form) && is_array($current_iids_from_form)) {
            // Use http_build_query for arrays to correctly form iid[]=...&iid[]=...
            $redirect_query_parts[] = http_build_query(['iid' => $current_iids_from_form]);
        }
        $redirect_query_parts[] = 'page=' . (int)$current_page_from_form;
        $redirect_query_parts[] = 'repid=' . urlencode($RepYtId); // To keep the report section open/highlighted
        $redirect_query_parts[] = 'rep=' . ($report_success ? 'success' : 'fail');

        $redirect_url = 'search.php?' . implode('&', array_filter($redirect_query_parts));
        // Add the anchor to jump to the video
        $redirect_url .= '#TI' . urlencode($RepYtId); 

        header("Location: " . $redirect_url, true, 303); // Use 303 See Other for POST-redirect-GET
        exit;

    } else {
        // --- HANDLE FILTER SUBMISSION (from index.php or a general filter form) ---
        // This is your existing filter handling logic (original lines 8-57)
        $selected_iids = $_POST['iid'] ?? [];
        $filter_channel = $_POST['filter_channel'] ?? '';
        $filter_title = trim($_POST['filter_title'] ?? '');
        $filter_date_start = $_POST['filter_date_start'] ?? '';
        $filter_date_end = $_POST['filter_date_end'] ?? '';
        $filter_exclude_live = isset($_POST['filter_exclude_live']) ? 1 : 0;
        $filter_exclusive_cast = isset($_POST['filter_exclusive_cast']) ? 1 : 0;

        $sanitized_iids = [];
        if (is_array($selected_iids)) {
            foreach ($selected_iids as $iid) {
                if (is_string($iid) && preg_match('/^[a-zA-Z0-9\s\-]+$/', $iid)) {
                     $sanitized_iids[] = $iid;
                }
            }
        }

        // Basic date validation (YYYY-MM-DD format)
        $date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
        if (!empty($filter_date_start) && !preg_match($date_pattern, $filter_date_start)) $filter_date_start = '';
        if (!empty($filter_date_end) && !preg_match($date_pattern, $filter_date_end)) $filter_date_end = '';


        $_SESSION['search_filter_channel'] = htmlspecialchars($filter_channel);
        $_SESSION['search_filter_title'] = htmlspecialchars($filter_title);
        $_SESSION['search_filter_date_start'] = $filter_date_start;
        $_SESSION['search_filter_date_end'] = $filter_date_end;
        $_SESSION['search_filter_exclude_live'] = $filter_exclude_live;
        $_SESSION['search_filter_exclusive_cast'] = $filter_exclusive_cast;
        $_SESSION['search_page'] = 1; // Reset to page 1 for new filter search

        $redirect_url = 'search.php';
        if (!empty($sanitized_iids)) {
            $redirect_url .= '?' . http_build_query(['iid' => $sanitized_iids]);
        }
        // If no iids, it will redirect to search.php, which will then show the "Please select" message.

        header("Location: " . $redirect_url);
        exit;
    }
} // END OF if ($_SERVER['REQUEST_METHOD'] === 'POST')

function countTotalVideosByCreators(PDO $pdo, array $creators, string $filter_channel = '', string $filter_title = '', string $filter_date_start = '', string $filter_date_end = '', int $filter_exclude_live = 0, int $filter_exclusive_cast = 0): int {

    $creator_list = str_repeat('?, ', count($creators) - 1) . '?';
    $creator_count = count($creators);

    // --- START: Dynamic SQL Building for Count ---
    $sqlInnerSelect = "SELECT vl.VideoID"; // Select ID for grouping
    $sqlBaseFrom = "FROM VideoList vl
                    INNER JOIN VideoCast vc ON vl.VideoID = vc.VideoID
                    INNER JOIN CastList cl ON cl.CastID = vc.CastID";
    $sqlBaseWhere = "WHERE cl.CastName IN ($creator_list)";

    $sqlConditions = ""; // Additional WHERE conditions
    $filterParams = []; // Parameters for WHERE/HAVING filters

    // Channel Filter
    if (!empty($filter_channel)) {
        $sqlConditions .= " AND vl.channelName = :channelName"; 
        $filterParams[':channelName'] = $filter_channel;       
    }
    // Title Filter
    if (!empty($filter_title)) {
        $sqlConditions .= " AND vl.Title LIKE :titleSearch";
        $filterParams[':titleSearch'] = '%' . $filter_title . '%';
    }
    // Date Start Filter
    if (!empty($filter_date_start)) {
        $sqlConditions .= " AND DATE(vl.publishedAt) >= :dateStart";
        $filterParams[':dateStart'] = $filter_date_start;
    }
    // Date End Filter
    if (!empty($filter_date_end)) {
        $sqlConditions .= " AND DATE(vl.publishedAt) <= :dateEnd";
        $filterParams[':dateEnd'] = $filter_date_end;
    }
    // --- > NEW: Exclude Live Filter <---
    if ($filter_exclude_live === 1) {
        $sqlConditions .= " AND vl.wasLive = 0";
    }

    // Grouping
    $sqlGroupBy = "GROUP BY vl.VideoID";

    // --- > REVISED: Having Clause <---
    $sqlHaving = "HAVING COUNT(DISTINCT cl.CastID) = :creator_count"; // Ensure all selected are present
    if ($filter_exclusive_cast === 1) {
        // If exclusive is checked, add condition for total cast count
        $sqlHaving .= " AND (SELECT COUNT(vc_total.CastID) FROM VideoCast vc_total WHERE vc_total.VideoID = vl.VideoID) = :creator_count";
    }
    // $filterParams[':filter_exclusive_cast'] = $filter_exclusive_cast; // Not needed

    // Combine parts for inner query
    $innerSql = $sqlInnerSelect . " " . $sqlBaseFrom . " " . $sqlBaseWhere . " " . $sqlConditions . " " . $sqlGroupBy . " " . $sqlHaving;
    $innerSqlWithLimit = $innerSql . " LIMIT " . MAX_VIDEOS_TO_SCAN_FOR_CARDS;
    // Wrap in COUNT(*)
    $sql = "SELECT COUNT(*) FROM (" . $innerSqlWithLimit . ") AS LimitedTotalCount";
    // --- END: Dynamic SQL Building for Count ---

    $stmt = $pdo->prepare($sql);

    // Bind IN list parameters (creators)
    $paramIndex = 1;
    foreach ($creators as $creator) {
        $stmt->bindValue($paramIndex++, $creator, PDO::PARAM_STR);
    }

    // Bind HAVING parameter(s)
    $stmt->bindValue(':creator_count', $creator_count, PDO::PARAM_INT);
    // if ($filter_exclusive_cast === 1) {
    //    $stmt->bindValue(':filter_exclusive_cast', $filter_exclusive_cast, PDO::PARAM_INT);
    // } // Not needed

    // Bind Filter parameters
    foreach ($filterParams as $placeholder => $value) {
        $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
    }

    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function generate_card_list(PDO $pdo, array $creators, string $filter_channel, string $filter_title, string $filter_date_start, string $filter_date_end, int $filter_exclude_live, int $filter_exclusive_cast, array $playlist_details_lookup_for_card_generation, bool $can_group_playlists_overall) {
    
    $card_list = [];
    $processed_playlist_ids_in_card_list = []; // Tracks PlaylistIDs already turned into a playlist card
    $temp_filtered_members_by_playlist = []; 

    // --- 1. SQL Query to fetch a broad set of candidate videos ---
    // Limited by MAX_VIDEOS_TO_SCAN_FOR_CARDS.

    $creator_list_sql = str_repeat('?, ', count($creators) - 1) . '?';
    $creator_count_sql = count($creators);

    $sqlBaseSelect = "SELECT vl.VideoID, vl.YoutubeID, vl.Title, vl.PlaylistID, vl.publishedAt, vl.channelName /* Add any other fields needed for card display */";
    $sqlBaseFrom = "FROM VideoList vl
                    INNER JOIN VideoCast vc ON vl.VideoID = vc.VideoID
                    INNER JOIN CastList cl ON cl.CastID = vc.CastID";
    $sqlBaseWhere = "WHERE cl.CastName IN ($creator_list_sql)";
    $sqlConditions = "";
    $filterParams = [];

    if (!empty($filter_channel)) {
        $sqlConditions .= " AND vl.channelName = :channelName"; 
        $filterParams[':channelName'] = $filter_channel;       
    }
    if (!empty($filter_title)) {
        $sqlConditions .= " AND vl.Title LIKE :titleSearch";
        $filterParams[':titleSearch'] = '%' . $filter_title . '%';
    }
    if (!empty($filter_date_start)) {
        $sqlConditions .= " AND DATE(vl.publishedAt) >= :dateStart";
        $filterParams[':dateStart'] = $filter_date_start;
    }
    if (!empty($filter_date_end)) {
        $sqlConditions .= " AND DATE(vl.publishedAt) <= :dateEnd";
        $filterParams[':dateEnd'] = $filter_date_end;
    }
    if ($filter_exclude_live === 1) {
        $sqlConditions .= " AND vl.wasLive = 0";
    }

    $sqlGroupBy = "GROUP BY vl.VideoID, vl.YoutubeID, vl.Title, vl.PlaylistID, vl.publishedAt, vl.channelName"; // Group by all selected non-aggregated columns
    $sqlHaving = "HAVING COUNT(DISTINCT cl.CastID) = :creator_count_sql";
    if ($filter_exclusive_cast === 1) {
        $sqlHaving .= " AND (SELECT COUNT(vc_total.CastID) FROM VideoCast vc_total WHERE vc_total.VideoID = vl.VideoID) = :creator_count_sql";
    }
    $sqlOrderBy = "ORDER BY vl.publishedAt DESC";
    $sqlLimit = "LIMIT " . MAX_VIDEOS_TO_SCAN_FOR_CARDS;

    $sql = $sqlBaseSelect . " " . $sqlBaseFrom . " " . $sqlBaseWhere . " " . $sqlConditions . " " . $sqlGroupBy . " " . $sqlHaving . " " . $sqlOrderBy . " " . $sqlLimit;

    $stmt = $pdo->prepare($sql);
    $paramIndex = 1;
    foreach ($creators as $creator) {
        $stmt->bindValue($paramIndex++, $creator, PDO::PARAM_STR);
    }
    $stmt->bindValue(':creator_count_sql', $creator_count_sql, PDO::PARAM_INT);
    foreach ($filterParams as $placeholder => $value) {
        $stmt->bindValue($placeholder, $value);
    }
    $stmt->execute();
    $scanned_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 2. PHP Processing to build the list of "cards" ---
    if (empty($scanned_videos)) {
        return ['cards' => [], 'scan_limit_hit' => false];
    }
    // Pre-process $scanned_videos to collect filtered member IDs for playlists
    foreach ($scanned_videos as $video_data_for_id_collection) {
        if (!empty($video_data_for_id_collection['PlaylistID']) &&
            !empty($video_data_for_id_collection['YoutubeID'])) {
            // Only add if PlaylistID and YoutubeID are present
            $temp_filtered_members_by_playlist[$video_data_for_id_collection['PlaylistID']][] =
                $video_data_for_id_collection['YoutubeID'];
        }
    }

    // Ensure uniqueness within each playlist's ID list (good practice)
    foreach ($temp_filtered_members_by_playlist as $pl_id => $ids) {
        $temp_filtered_members_by_playlist[$pl_id] = array_unique($ids);
    }

    // This flag determines if we are allowed to group playlists at all.
    // We get this based on a quick check if the scanned videos are more than 1.
    // The original `$total_videos > 1` check for playlist grouping.
    $allow_playlist_grouping = $can_group_playlists_overall;
    $scan_limit_was_hit = (count($scanned_videos) === MAX_VIDEOS_TO_SCAN_FOR_CARDS);

    foreach ($scanned_videos as $video_data) {
        $is_playlist_video = !empty($video_data['PlaylistID']);
        $current_playlist_id = $video_data['PlaylistID'] ?? null;

        if ($allow_playlist_grouping && $is_playlist_video && !in_array($current_playlist_id, $processed_playlist_ids_in_card_list)) {
            // Check if this playlist has details and can be a card
            $playlist_details_for_this_card = $playlist_details_lookup_for_card_generation[$current_playlist_id] ?? null;
            $has_youtube_info_for_card = !empty($playlist_details_for_this_card['YouTubePlaylistID']) && !empty($playlist_details_for_this_card['FirstVideoYoutubeID']);

            if ($has_youtube_info_for_card) {
                // It's a new playlist that can be represented by a card
                $card_list[] = [
                    'type' => 'playlist',
                    'id' => $current_playlist_id, // PlaylistID
                    // Store necessary data from $playlist_details_for_this_card and maybe $video_data for the first video
                    'name' => $playlist_details_for_this_card['PlaylistName'],
                    'youtube_playlist_id' => $playlist_details_for_this_card['YouTubePlaylistID'],
                    'first_video_youtube_id' => $playlist_details_for_this_card['FirstVideoYoutubeID'],
                    'total_video_count_in_playlist' => $playlist_details_for_this_card['VideoCount'] ?? 0, // From your existing simple count
                    'filtered_youtube_ids' => $temp_filtered_members_by_playlist[$current_playlist_id] ?? []
                ];
                $processed_playlist_ids_in_card_list[] = $current_playlist_id;
            } else {
                // It's in a playlist, but we can't make a card (e.g. no details), so treat its first video as an individual video.
                // Or, if you prefer, skip videos from playlists without details entirely.
                // For now, let's add it as an individual video.
                 $card_list[] = [
                    'type' => 'video',
                    'id' => $video_data['VideoID'],
                    'data' => $video_data // Full video data
                ];
            }
        } else if ($is_playlist_video && in_array($current_playlist_id, $processed_playlist_ids_in_card_list)) {
            // This video belongs to a playlist for which a card has ALREADY been added to $card_list.
            // So, we DO NOT add this individual video to the card list. The playlist card covers it.
            continue; 
        } else {
            // It's an individual video (not in a playlist, or its playlist couldn't form a card and we're here by fallback)
            $card_list[] = [
                'type' => 'video',
                'id' => $video_data['VideoID'], // Or YoutubeID if that's your primary key for display
                'data' => $video_data // Full video data
            ];
        }
    }
    return ['cards' => $card_list, 'scan_limit_hit' => $scan_limit_was_hit];
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
// end funct

$current_page = 1; // Default page
$creators = []; // Initialize creators array
$urlrep = ''; // Initialize URL rep string - review if still needed for report form
$base_query_string = ''; // To store iid parameters for pagination links

// --- NEW VARIABLES FOR CARD-BASED PAGINATION ---
$playlist_details_lookup = []; // Will be populated more globally now
$all_possible_cards = [];    // To store the full list of generated cards
$cards_for_this_page = [];   // To store cards for the current page view
$total_available_cards = 0;  // Total number of displayable cards found
$display_total_pages = 0;    // Total pages to show to the user (respecting MAX_DISPLAY_PAGES)


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

        // Retrieve filters from SESSION for this GET request
        $filter_channel = $_SESSION['search_filter_channel'] ?? '';
        $filter_title = $_SESSION['search_filter_title'] ?? '';
        $filter_date_start = $_SESSION['search_filter_date_start'] ?? '';
        $filter_date_end = $_SESSION['search_filter_date_end'] ?? '';
        $filter_exclude_live = $_SESSION['search_filter_exclude_live'] ?? 0;
        $filter_exclusive_cast = $_SESSION['search_filter_exclusive_cast'] ?? 0;

        // Get the current page number from URL, default to 1
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Keep this simple
        if ($current_page < 1) {
            $current_page = 1;
        }

        // Build base query string for pagination links (excluding page param)
        $query_params = [];
        foreach ($creators as $c) {
            $query_params[] = 'iid%5B%5D=' . urlencode($c); 
        }
        $base_query_string = implode('&', $query_params);
        if ($urlrep === '') { // Build urlrep only if not set by POST logic earlier
             $urlrep = implode("&iid%5B%5D=", $creators); // Keep original urlrep logic if needed elsewhere
        }

        // --- Determine if playlist grouping is allowed based on a preliminary count ---
        $preliminary_video_count_for_grouping_check = countTotalVideosByCreators(
            $pdo, $creators, $filter_channel, $filter_title, 
            $filter_date_start, $filter_date_end, $filter_exclude_live, $filter_exclusive_cast
        );
        $can_group_playlists_globally = ($preliminary_video_count_for_grouping_check > 1);

        // --- Pre-populate playlist_details_lookup for all relevant playlists --- START OF THIS PLAYLIST BLOCK
        // This SQL finds PlaylistIDs from videos matching all filters, up to the scan limit.
        $creator_list_sql_for_pl_ids = str_repeat('?, ', count($creators) - 1) . '?';
        $creator_count_sql_for_pl_ids = count($creators);

        $sql_conditions_for_pl_ids = "";
        $filter_params_for_pl_ids = [];
        // Build $sql_conditions_for_pl_ids and $filter_params_for_pl_ids
        // similar to how it's done in generate_card_list or countTotalVideosByCreators
        // (channel, title, date, exclude_live)
        if (!empty($filter_channel)) {
            $sql_conditions_for_pl_ids .= " AND vl.channelName = :channelName_pl"; 
            $filter_params_for_pl_ids[':channelName_pl'] = $filter_channel;       
        }
        if (!empty($filter_title)) {
            $sql_conditions_for_pl_ids .= " AND vl.Title LIKE :titleSearch_pl";
            $filter_params_for_pl_ids[':titleSearch_pl'] = '%' . $filter_title . '%';
        }
        if (!empty($filter_date_start)) {
            $sql_conditions_for_pl_ids .= " AND DATE(vl.publishedAt) >= :dateStart_pl";
            $filter_params_for_pl_ids[':dateStart_pl'] = $filter_date_start;
        }
        if (!empty($filter_date_end)) {
            $sql_conditions_for_pl_ids .= " AND DATE(vl.publishedAt) <= :dateEnd_pl";
            $filter_params_for_pl_ids[':dateEnd_pl'] = $filter_date_end;
        }
        if ($filter_exclude_live === 1) {
            $sql_conditions_for_pl_ids .= " AND vl.wasLive = 0";
        }

        $sql_having_for_pl_ids = "HAVING COUNT(DISTINCT cl.CastID) = :creator_count_sql_for_pl_ids";
        if ($filter_exclusive_cast === 1) {
            $sql_having_for_pl_ids .= " AND (SELECT COUNT(vc_total.CastID) FROM VideoCast vc_total WHERE vc_total.VideoID = vl.VideoID) = :creator_count_sql_for_pl_ids";
        }

        // Query to get distinct PlaylistIDs from filtered videos
        $sql_get_all_playlist_ids_full = "
            SELECT DISTINCT vl.PlaylistID 
            FROM VideoList vl
            INNER JOIN VideoCast vc ON vl.VideoID = vc.VideoID
            INNER JOIN CastList cl ON cl.CastID = vc.CastID
            WHERE cl.CastName IN ($creator_list_sql_for_pl_ids) 
                  {$sql_conditions_for_pl_ids}
                  AND vl.PlaylistID IS NOT NULL AND vl.PlaylistID != ''
            GROUP BY vl.VideoID, vl.PlaylistID -- Group by VideoID first to apply cast HAVING
            ORDER BY vl.publishedAt DESC -- Optional: ordering might not be strictly needed here
            LIMIT " . MAX_VIDEOS_TO_SCAN_FOR_CARDS; // Use the same scan limit

        // Prepare and execute this query to get candidate VideoID/PlaylistID pairs
        // then extract unique PlaylistIDs from that.
        // A more direct way for *just* PlaylistIDs (if subqueries are efficient):
        $sql_get_involved_playlist_ids = "
            SELECT DISTINCT sq.PlaylistID 
            FROM (
                SELECT vl.PlaylistID, vl.VideoID /* Need VideoID for HAVING */
                FROM VideoList vl
                INNER JOIN VideoCast vc ON vl.VideoID = vc.VideoID
                INNER JOIN CastList cl ON cl.CastID = vc.CastID
                WHERE cl.CastName IN ($creator_list_sql_for_pl_ids)
                      {$sql_conditions_for_pl_ids}
                      AND vl.PlaylistID IS NOT NULL AND vl.PlaylistID != ''
                GROUP BY vl.VideoID, vl.PlaylistID
                {$sql_having_for_pl_ids}
                ORDER BY vl.publishedAt DESC
                LIMIT " . MAX_VIDEOS_TO_SCAN_FOR_CARDS . "
            ) AS sq
            WHERE sq.PlaylistID IS NOT NULL AND sq.PlaylistID != ''";

        $stmt_get_pl_ids = $pdo->prepare($sql_get_involved_playlist_ids);
        $paramIndex_pl = 1;
        foreach ($creators as $creator) {
            $stmt_get_pl_ids->bindValue($paramIndex_pl++, $creator, PDO::PARAM_STR);
        }
        $stmt_get_pl_ids->bindValue(':creator_count_sql_for_pl_ids', $creator_count_sql_for_pl_ids, PDO::PARAM_INT);
        foreach ($filter_params_for_pl_ids as $placeholder => $value) {
            $stmt_get_pl_ids->bindValue($placeholder, $value);
        }
        $stmt_get_pl_ids->execute();
        $unique_involved_playlist_ids = $stmt_get_pl_ids->fetchAll(PDO::FETCH_COLUMN, 0);
        $unique_involved_playlist_ids = array_unique(array_filter($unique_involved_playlist_ids)); // Ensure truly unique

        if (!empty($unique_involved_playlist_ids)) {
            $placeholders_involved = implode(',', array_fill(0, count($unique_involved_playlist_ids), '?'));
            // This is your existing query for playlist details, now used more globally
            $sql_playlists_details = "SELECT PlaylistID, PlaylistName, YouTubePlaylistID, FirstVideoYoutubeID, 
                                       (SELECT COUNT(*) FROM VideoList WHERE VideoList.PlaylistID = Playlists.PlaylistID) as VideoCount 
                                     FROM Playlists 
                                     WHERE PlaylistID IN ($placeholders_involved)";
            $stmt_playlists_details = $pdo->prepare($sql_playlists_details);
            $stmt_playlists_details->execute(array_values($unique_involved_playlist_ids)); // Ensure array keys are 0-indexed
            while ($row = $stmt_playlists_details->fetch(PDO::FETCH_ASSOC)) {
                $playlist_details_lookup[$row['PlaylistID']] = $row;
            }
        } //end of this PLAYLIST block

            $card_generation_result = generate_card_list(
            $pdo, $creators, $filter_channel, $filter_title, 
            $filter_date_start, $filter_date_end, $filter_exclude_live, $filter_exclusive_cast,
            $playlist_details_lookup, // Pass the pre-fetched playlist details
            $can_group_playlists_globally 
        );

        $all_possible_cards = $card_generation_result['cards'];

        $total_available_cards = count($all_possible_cards);

        if ($total_available_cards > 0) {
            $actual_total_pages = (int)ceil($total_available_cards / CARDS_PER_PAGE);
            // Apply the MAX_DISPLAY_PAGES limit
            $display_total_pages = min($actual_total_pages, MAX_DISPLAY_PAGES); 

            // Ensure current page is not out of bounds for the displayable pages
            if ($current_page > $display_total_pages && $display_total_pages > 0) {
                $current_page = $display_total_pages;
            }
            // If there are cards, there should be at least 1 page, even if fewer than CARDS_PER_PAGE
            if ($display_total_pages === 0 && $total_available_cards > 0) {
                 $display_total_pages = 1;
            }

        } else {
            // No cards found
            $display_total_pages = 0; 
            // $current_page is already 1 or set from GET, can leave as is or force to 1
            if ($total_available_cards === 0) $current_page = 1; // No results, so "page 1" of 0 pages
        }

        if ($total_available_cards > 0) {
            $offset_cards = ($current_page - 1) * CARDS_PER_PAGE;
            $cards_for_this_page = array_slice($all_possible_cards, $offset_cards, CARDS_PER_PAGE);
        } else {
            $cards_for_this_page = []; // Ensure it's an empty array if no cards
        }
        // --- NEW Page-Specific "Open as Playlist" URL Generation ---
        $playlist_url = null;
        $ids_for_page_playlist_url = []; // To collect YoutubeIDs for the URL
        $current_ids_count_for_url = 0;
        $MAX_URL_IDS_LIMIT = 50; // Max YouTube IDs for the playlist URL

        if (!empty($cards_for_this_page)) { // Only proceed if there are cards on the current page
            foreach ($cards_for_this_page as $card_item) {
                if ($current_ids_count_for_url >= $MAX_URL_IDS_LIMIT) {
                    break; // Stop if we've hit the 50 ID limit for the URL
                }

                if ($card_item['type'] === 'video') {
                    // Ensure 'data' and 'YoutubeID' exist
                    if (isset($card_item['data']['YoutubeID'])) {
                        if (!in_array($card_item['data']['YoutubeID'], $ids_for_page_playlist_url)) {
                            $ids_for_page_playlist_url[] = $card_item['data']['YoutubeID'];
                            $current_ids_count_for_url++;
                        }
                    }
                } elseif ($card_item['type'] === 'playlist') {
                    // Use the 'filtered_youtube_ids' that generate_card_list now provides
                    if (isset($card_item['filtered_youtube_ids']) && is_array($card_item['filtered_youtube_ids'])) {
                        foreach ($card_item['filtered_youtube_ids'] as $yt_id) {
                            if ($current_ids_count_for_url >= $MAX_URL_IDS_LIMIT) {
                                break 2; // Break out of both this inner loop and the outer card loop
                            }
                            if (!in_array($yt_id, $ids_for_page_playlist_url)) {
                                $ids_for_page_playlist_url[] = $yt_id;
                                $current_ids_count_for_url++;
                            }
                        }
                    }
                }
            }
        }

        // Only create a URL if we have at least 2 videos for a meaningful playlist
        if (!empty($ids_for_page_playlist_url) && count($ids_for_page_playlist_url) > 1) {
            $playlist_video_ids_string = implode(",", $ids_for_page_playlist_url);
            // IMPORTANT: Replace 'https://www.youtube.com/watch_videos?video_ids=' with the correct YouTube playlist URL structure.
            // e.g., 'https://www.youtube.com/watch_videos?video_ids=' or 'https://www.youtube.com/playlist?list=' (if creating a temp playlist is possible this way)
            // The PDF used 'http://www.youtube.com/watch_videos?video_ids=', I used '.../4' as a placeholder. This needs verification.
            $playlist_url = 'https://www.youtube.com/watch_videos?video_ids=' . $playlist_video_ids_string; 
        } else {
            $playlist_url = null; // Not enough videos for a meaningful playlist
            // Ensure $ids_for_page_playlist_url is empty if no URL, so button count is 0 or button hides
            $ids_for_page_playlist_url = [];
        }
        // This variable name is what your HTML button part expects for the count (based on the PDF)
        $video_ids_for_url_playlist = $ids_for_page_playlist_url;
        // --- END NEW Page-Specific "Open as Playlist" URL Generation ---

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

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Yogsearch | <?php 
            if (empty($creators) && !($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['RepYtId']))) { // Keep POST for report distinct
                echo "Please select between 1 and 12 creators";
            } else if ($total_available_cards === 0 && !empty($creators)) { // Check if creators were actually part of this search
                echo "No videos found";
            } else if (!empty($creators)) { 
                echo("Found " . $total_available_cards . " videos featuring " . join(' & ', array_filter(array_merge(array(join(', ', array_slice($creators, 0, -1))), array_slice($creators, -1)), 'strlen'))); 
            } else {
                echo "Searching for new videos featuring your favourite creators"; // Default title if no specific state
            }
        ?> </title>
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
            <a href="/"><img width="800" height="289" src="yogsearch.webp" alt="Yogsearch logo" class="yogart"></a>
        </header>
        <main>
            <nav class="pagination">
                <ul>
                    <?php // Previous Page Link (Shows "Back to Search" on Page 1)
                        if ($current_page > 1): ?>
                        <li><a href="?<?= htmlspecialchars($base_query_string); ?>&page=<?= $current_page - 1; ?><?= isset($_GET['repid']) ? '&repid='.urlencode($_GET['repid']) : ''; ?>">« Previous</a></li>
                    <?php else: // When current_page is 1 ?>
                        <li><a href="/">« Back to search</a></li>
                    <?php endif; ?>


                    <?php // Conditionally show the Next button block only if multiple pages exist
                        if ($display_total_pages > 1):
                    ?>
                        <?php // --- Next Page Link --- (Handles enabled/disabled state internally)
                            if ($current_page < $display_total_pages): ?>
                            <li><a href="?<?= htmlspecialchars($base_query_string); ?>&page=<?= $current_page + 1; ?><?= isset($_GET['repid']) ? '&repid='.urlencode($_GET['repid']) : ''; ?>">Next »</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>Next »</span></li>
                        <?php endif; // End of inner if/else for Next link content ?>

                    <?php endif; // End of wrapper condition for showing Next button block ?>
                </ul>
            </nav>
        <?php // Display messages or results
                        if (empty($creators) && !($_SERVER['REQUEST_METHOD'] == 'POST')) { // Show message only if not a POST result
                            echo "<p>Please select between 1 and 12 creators using the search on the main page.</p>";
                        } elseif (!empty($creators) && $total_available_cards === 0) { // Check creators is not empty before saying no videos found
                            echo "<p>No videos found matching the selected creators.</p>"; // CHECKPOINT -- add cool image here, diggy hole. 
                        } elseif ($total_available_cards > 0) {
                            echo '<div class="results-header">';
                            echo '  <div class="flex-spacer-results-header"></div>';
                            $results_message = '';
                            $actual_total_pages_needed_for_all_found_cards = (int)ceil($total_available_cards / CARDS_PER_PAGE);

                            if ($actual_total_pages_needed_for_all_found_cards > MAX_DISPLAY_PAGES) { // If there are more total pages of results than we can fit within our limit
                                $displayable_card_cap = MAX_DISPLAY_PAGES * CARDS_PER_PAGE;
                                $results_message = "Found " . $displayable_card_cap . "+ results. ";
                            } else {
                                $results_message = "Found " . $total_available_cards . " results. ";
                            }
                            $results_message .= "Showing page " . $current_page . " of " . $display_total_pages . ".";
                            echo '  <p class="results-info-text">' . $results_message . '</p>';
                            echo '  <div class="flex-spacer-results-header"></div>';
                        if (isset($playlist_url) && $playlist_url && !empty($video_ids_for_url_playlist)) {
                            $count_for_button_display = count($video_ids_for_url_playlist);
                            if ($count_for_button_display > 1) { 
                            echo '  <div class="export-playlist-container">'; 
                            echo '    <span class="export-playlist-text">Open page as playlist (' . $count_for_button_display . ' videos)</span>';
                            echo '    <a href="' . htmlspecialchars($playlist_url) . '" target="_blank" rel="noopener noreferrer" class="export-playlist-icon-button" title="Open as playlist (' . $count_for_button_display . ' videos)">';
                            echo '      <span class="playlist-icon-char">⎙</span>'; // Ensure this character displays correctly or use an image/SVG
                            echo '    </a>';
                            echo '  </div>';
                            }
                        }
                            echo '</div>'; // End of .results-header
                        }
                ?>
            <article class="searchresults">  <?php
                if (empty($cards_for_this_page) && !empty($creators)) {} 
                elseif (!empty($cards_for_this_page)) { // Only loop if there are cards to display
                    
                    foreach ($cards_for_this_page as $card_item):
                        if ($card_item['type'] === 'playlist') {
                            // --- RENDER PLAYLIST CARD ---
                            // Data for playlist card is directly in $card_item
                            $playlist_id_to_render = $card_item['id']; // PlaylistID
                            $playlist_name = htmlspecialchars($card_item['name']);
                            $youtube_playlist_id_for_link = htmlspecialchars($card_item['youtube_playlist_id']);
                            $first_video_yt_id_for_thumb = htmlspecialchars($card_item['first_video_youtube_id']);
                            // This is the simple total video count IN that playlist (unfiltered by search criteria)
                            $total_vids_in_pl = $card_item['total_video_count_in_playlist']; 

                            $playlist_card_link = 'https://www.youtube.com/watch?v=' . $first_video_yt_id_for_thumb . '&list=' . $youtube_playlist_id_for_link;
                            ?>
                            <div class="card playlist-card"> <h4><a href="<?= $playlist_card_link; ?>" target="_blank" rel="noopener noreferrer">Playlist: <?= $playlist_name; ?></a></h4>
                                <div class="ytimg">
                                    <a href="<?= $playlist_card_link; ?>" target="_blank" rel="noopener noreferrer">
                                    <img loading="lazy" alt="Playlist Thumbnail" src="https://i.ytimg.com/vi/<?= $first_video_yt_id_for_thumb; ?>/hqdefault.jpg">
                                </div>
                                <a class="cardReport" style="color: #8a8e91 !important;"> Total videos: <?= $total_vids_in_pl ?></a>
                            </div>
                            <?php
                        } else { // $card_item['type'] === 'video'
                            // --- RENDER INDIVIDUAL VIDEO CARD ---
                            $video_data = $card_item['data']; // This contains the original video data fields (YoutubeID, Title, etc.)
                            
                            // Ensure $base_query_string and $current_page are correctly available here.
                            // They are set before this loop in the main GET request processing block.
                            $report_base_url = "?{$base_query_string}&page={$current_page}"; 
                            $report_url_with_repid = "{$report_base_url}&repid=" . urlencode($video_data['YoutubeID']) . "#TI" . urlencode($video_data['YoutubeID']);
                            $report_url_without_repid = "{$report_base_url}#TI" . urlencode($video_data['YoutubeID']); 
                            ?>
                            <div class="card">
                                <label for="vidTITLE" id="TI<?= htmlspecialchars($video_data['YoutubeID']); ?>"><h4><a alt="<?= htmlspecialchars($video_data['YoutubeID']); ?>" id="TI<?= htmlspecialchars($video_data['YoutubeID']); ?>" href="https://youtube.com/watch?v=<?= htmlspecialchars($video_data['YoutubeID']); ?>" target="_blank" rel="noopener noreferrer"> <span class="TitleWidth"><?= htmlspecialchars($video_data['Title']); ?></span></h4></label>
                                <div class="ytimg"><img loading="lazy" alt="Thumbnail" src="https://i.ytimg.com/vi/<?= htmlspecialchars($video_data['YoutubeID']); ?>/hqdefault.jpg"></a></div>
                                <?php if (isset($_GET['repid']) && $_GET['repid'] == $video_data['YoutubeID']) { ?>
                                <a class="cardReport" href="<?= htmlspecialchars($report_url_without_repid); ?>">Report issue</a>
                                <?php } else { ?>
                                <a class="cardReport" href="<?= htmlspecialchars($report_url_with_repid); ?>">Report issue</a>
                                <?php } ?>
                            </div>
                            <?php  if (isset($_GET['repid']) && $_GET['repid'] == $video_data['YoutubeID'] ) { ?>
                            <div class="card repcard">
                                <?php if (!isset($_GET['rep'])) { ?>
                                    <form action="?<?= htmlspecialchars($base_query_string); ?>&page=<?= $current_page; ?><?= isset($_GET['repid']) ? '&repid='.urlencode($_GET['repid']) : ''; ?>#RepForm" method="POST" id="RepForm">
                                        <input type="hidden" id="RepYtId" name="RepYtId" value="<?= htmlspecialchars($video_data['YoutubeID']); ?>">
                                        <input type="hidden" name="page" value="<?= $current_page; // Pass current page for POST context ?>">
                                        <?php 
                                        // Ensure $creators is available and correctly passed for POST context
                                        // $creators should be set from the main GET processing block.
                                        if(isset($creators) && is_array($creators)) {
                                            foreach ($creators as $creator_id_form): ?>
                                                <input type="hidden" name="iid[]" value="<?= htmlspecialchars($creator_id_form); ?>">
                                            <?php endforeach; 
                                        }?>
                                        <textarea id="repimp" placeholder="Describe the issue ..." name="repimp" maxlength="5000" required></textarea><br>
                                        <input type="submit" class="RepInlineSubmit" value="Send">
                                    </form>
                                <?php } else if (isset($_GET['rep']) && $_GET['rep'] == "success") { ?>
                                         <p style="margin:-2px; margin-left: 20px;"> Thanks for your report! </p>
                                <?php } else if (isset($_GET['rep']) && $_GET['rep'] == "fail") { ?>
                                         <p style="margin:-2px; margin-left: 20px;"> Your report was not submitted, please try again. </p>
                                <?php } ?>
                            </div>  
                        <?php  } // End of repid check for report form display
                        } // End of card type if/else (playlist vs video)
                    endforeach; // End of $cards_for_this_page loop
                } // End of if !empty($cards_for_this_page)
                ?>
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
                            <li><a href="/">« Back to search</a></li>
                        <?php endif; ?>


                        <?php // Conditionally show the Next button block only if multiple pages exist
                            if ($display_total_pages > 1):
                        ?>
                            <?php // --- Next Page Link --- (Handles enabled/disabled state internally)
                                if ($current_page < $display_total_pages): ?>
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
            <a href='/faq' class=folinks>About</a> | <a href='https://github.com/Hibbe/YogsSearch' class=folinks target="_blank">Github</a> | <a href='/contribute' class=folinks>Contribute</a>
            <p class="fodisc">Yogsearch is a <strong>fanpage</strong> and is <strong>not associated with or endorsed</strong> by the Yogscast</p>
        </footer>
    </body>
</html>
