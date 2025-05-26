<?php
// 1. db.php démarre la session
require_once __DIR__.'/includes/db.php';
// 2. auth.php peut maintenant utiliser la session
require_once __DIR__.'/includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$current_user = current_user();
if (!$current_user || !isset($current_user['id']) || !isset($current_user['name'])) {
    echo json_encode(['success' => false, 'error' => 'User session data is corrupted.']);
    error_log("AJAX Error: User session data corrupted. Session: " . print_r($_SESSION, true));
    exit;
}
$user_id = $current_user['id'];
$user_name = $current_user['name'];

// --- Input retrieval ---
$map_id_from_post = $_POST['map_id'] ?? null; // Can be int or 'random'
$map_type = $_POST['map_type'] ?? null;
$time_taken = filter_input(INPUT_POST, 'time_taken', FILTER_VALIDATE_INT);
$moves_made = filter_input(INPUT_POST, 'moves_made', FILTER_VALIDATE_INT);
$is_victory_str = $_POST['is_victory'] ?? null;
$is_victory = ($is_victory_str === '1');
$save_random_map_flag = $_POST['save_random_map'] ?? '0';

$map_id_to_update = null; // This will hold the actual map ID to use for updates

// --- Validation ---
$validation_errors = [];
if (!in_array($map_type, ['base', 'custom', 'random'])) $validation_errors[] = "Invalid Map Type ($map_type).";
if ($time_taken === false || $time_taken < 0) $validation_errors[] = "Invalid Time Taken ($time_taken).";
if ($moves_made === false || $moves_made < 0) $validation_errors[] = "Invalid Moves Made ($moves_made).";
if ($is_victory_str === null || !in_array($is_victory_str, ['0', '1'])) $validation_errors[] = "Invalid Victory Status ($is_victory_str).";

if ($save_random_map_flag === '1') {
    if ($map_type !== 'random' || !$is_victory) {
        $validation_errors[] = "Random map saving is only allowed for 'random' type maps upon victory.";
    }
} else {
    // If not saving a random map, map_id_from_post must be a valid integer
    $map_id_to_update = filter_var($map_id_from_post, FILTER_VALIDATE_INT);
    if ($map_id_to_update === false || $map_id_to_update <= 0) {
        $validation_errors[] = "Invalid Map ID ($map_id_from_post) when not saving a random map.";
    }
}

if (!empty($validation_errors)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data: ' . implode(' ', $validation_errors), 'details' => $_POST]);
    exit;
}

$response_data = ['success' => false]; 

try {
    $pdo->beginTransaction();

    // --- Handle Random Map Saving if Flagged ---
    if ($save_random_map_flag === '1' && $is_victory) {
        if (!isset($_SESSION['random_map_to_save'])) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'No random map data found in session to save.']);
            exit;
        }
        $map_data_to_save = $_SESSION['random_map_to_save'];

        // Ensure all necessary data is present
        if (empty($map_data_to_save['name']) || empty($map_data_to_save['size']) || empty($map_data_to_save['map_value']) || !isset($map_data_to_save['difficulty']) || !isset($map_data_to_save['user_id'])) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Incomplete random map data in session.']);
            exit;
        }
        
        // Since it's a win on the first play, current stats are the best stats.
        $stmt_insert_map = $pdo->prepare(
            'INSERT INTO Map (user_id, name, size, difficulty, map_value, map_game_count, creation_date, best_player_time, best_player_time_name, best_player_moves, best_player_moves_name) 
             VALUES (:user_id, :name, :size, :difficulty, :map_value, 1, NOW(), :best_time, :best_time_name, :best_moves, :best_moves_name)'
        );
        $insert_success = $stmt_insert_map->execute([
            ':user_id' => $map_data_to_save['user_id'],
            ':name' => $map_data_to_save['name'],
            ':size' => $map_data_to_save['size'],
            ':difficulty' => $map_data_to_save['difficulty'], // This is the 1-5 scale
            ':map_value' => $map_data_to_save['map_value'],
            ':best_time' => $time_taken,
            ':best_time_name' => $user_name,
            ':best_moves' => $moves_made,
            ':best_moves_name' => $user_name
        ]);

        if ($insert_success) {
            $map_id_to_update = $pdo->lastInsertId();
            unset($_SESSION['random_map_to_save']);
            $response_data['map_saved_as_id'] = (int)$map_id_to_update;
            // No need to set new_best_time/moves here as they are set directly
            $response_data['new_best_time'] = true; 
            $response_data['new_best_moves'] = true;
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to save the new random map.']);
            exit;
        }
    }
    // If $map_id_to_update is still null here, it means it wasn't set by saving a random map,
    // so it must be a regular base/custom map, and map_id_from_post should be valid integer.
    if ($map_id_to_update === null) {
         $map_id_to_update = filter_var($map_id_from_post, FILTER_VALIDATE_INT);
         // This check should be redundant due to earlier validation but good for safety.
         if ($map_id_to_update === false || $map_id_to_update <=0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Map ID is invalid after conditional checks.']);
            exit;
         }
    }


    // --- Update User's Global Stats ---
    $stmt_check_stats = $pdo->prepare('SELECT user_id FROM stats WHERE user_id = ?');
    $stmt_check_stats->execute([$user_id]);
    if (!$stmt_check_stats->fetch()) {
        $pdo->prepare('INSERT INTO stats (user_id, played_time, current_level, user_game_count, win_count, money) VALUES (?, 0, 0, 0, 0, 0)')->execute([$user_id]);
    }

    $stmt_stats_update = $pdo->prepare(
        'UPDATE stats SET played_time = played_time + :time_taken, user_game_count = user_game_count + 1 WHERE user_id = :user_id'
    );
    $stmt_stats_update->execute([':time_taken' => $time_taken, ':user_id' => $user_id]);

    if ($is_victory) {
        $stmt_win_update = $pdo->prepare('UPDATE stats SET win_count = win_count + 1 WHERE user_id = :user_id');
        $stmt_win_update->execute([':user_id' => $user_id]);

        if ($map_type === 'base' && $map_id_to_update >= 1 && $map_id_to_update <= 4) { 
            $stmt_current_level = $pdo->prepare('SELECT current_level FROM stats WHERE user_id = ?');
            $stmt_current_level->execute([$user_id]);
            $current_level_db = (int)$stmt_current_level->fetchColumn();
            if ($map_id_to_update > $current_level_db) { 
                 $stmt_level_up = $pdo->prepare('UPDATE stats SET current_level = :new_level WHERE user_id = :user_id');
                 $stmt_level_up->execute([':new_level' => $map_id_to_update, ':user_id' => $user_id]);
            }
        }
    }

    // --- Update Map-Specific Stats ---
    // Increment map_game_count only if it's not a newly saved random map (already set to 1)
    if (!($save_random_map_flag === '1' && isset($response_data['map_saved_as_id']))) {
        $stmt_map_game_count = $pdo->prepare('UPDATE Map SET map_game_count = map_game_count + 1 WHERE id = :map_id');
        $stmt_map_game_count->execute([':map_id' => $map_id_to_update]);
    }
    
    if ($is_victory) {
        // If it's a newly saved random map, best scores are already set.
        // So, only update if it's NOT a newly saved random map.
        if (!($save_random_map_flag === '1' && isset($response_data['map_saved_as_id']))) {
            $stmt_map_scores = $pdo->prepare('SELECT best_player_time, best_player_moves FROM Map WHERE id = :map_id');
            $stmt_map_scores->execute([':map_id' => $map_id_to_update]);
            $map_current_bests = $stmt_map_scores->fetch();

            if ($map_current_bests) {
                $new_best_time_flag = false; // Use a different variable name to avoid conflict
                $new_best_moves_flag = false;

                if ($map_current_bests['best_player_time'] === null || $map_current_bests['best_player_time'] == 0 || $time_taken < $map_current_bests['best_player_time']) {
                    $new_best_time_flag = true;
                    $pdo->prepare('UPDATE Map SET best_player_time = :time, best_player_time_name = :name WHERE id = :map_id')
                        ->execute([':time' => $time_taken, ':name' => $user_name, ':map_id' => $map_id_to_update]);
                }
                if ($map_current_bests['best_player_moves'] === null || $map_current_bests['best_player_moves'] == 999 || $map_current_bests['best_player_moves'] == 0 || $moves_made < $map_current_bests['best_player_moves']) {
                     $new_best_moves_flag = true;
                     $pdo->prepare('UPDATE Map SET best_player_moves = :moves, best_player_moves_name = :name WHERE id = :map_id')
                        ->execute([':moves' => $moves_made, ':name' => $user_name, ':map_id' => $map_id_to_update]);
                }
                if ($new_best_time_flag) $response_data['new_best_time'] = true;
                if ($new_best_moves_flag) $response_data['new_best_moves'] = true;
            }
        }
    }

    $pdo->commit();
    $response_data['success'] = true;
    if (!isset($response_data['message'])) { // Avoid overwriting map saved message
       $response_data['message'] = 'Game progress saved successfully.';
    }
    if (isset($response_data['map_saved_as_id'])) {
        $response_data['message'] = 'Random map saved (ID: '.$response_data['map_saved_as_id'].') and progress recorded.';
    }

    echo json_encode($response_data);

} catch (PDOException $e) { 
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("PDOException in ajax_save_score.php: " . $e->getMessage() . " POST: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) { 
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("General Exception in ajax_save_score.php: " . $e->getMessage() . " POST: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'error' => 'Unexpected error: ' . $e->getMessage()]);
}
?>