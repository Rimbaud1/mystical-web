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

$map_id = filter_input(INPUT_POST, 'map_id', FILTER_VALIDATE_INT);
$map_type = $_POST['map_type'] ?? null;
$time_taken = filter_input(INPUT_POST, 'time_taken', FILTER_VALIDATE_INT);
$moves_made = filter_input(INPUT_POST, 'moves_made', FILTER_VALIDATE_INT);
$is_victory_str = $_POST['is_victory'] ?? null;
$is_victory = ($is_victory_str === '1');

$validation_errors = [];
if ($map_id === false || $map_id <= 0) $validation_errors[] = "Invalid Map ID ($map_id).";
if (!in_array($map_type, ['base', 'custom'])) $validation_errors[] = "Invalid Map Type ($map_type).";
if ($time_taken === false || $time_taken < 0) $validation_errors[] = "Invalid Time Taken ($time_taken).";
if ($moves_made === false || $moves_made < 0) $validation_errors[] = "Invalid Moves Made ($moves_made).";
if ($is_victory_str === null || !in_array($is_victory_str, ['0', '1'])) $validation_errors[] = "Invalid Victory Status ($is_victory_str).";

if (!empty($validation_errors)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data: ' . implode(' ', $validation_errors), 'details' => $_POST]);
    exit;
}

$response_data = ['success' => false]; 

try {
    $pdo->beginTransaction();

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

        if ($map_type === 'base' && $map_id >= 1 && $map_id <= 4) { 
            $stmt_current_level = $pdo->prepare('SELECT current_level FROM stats WHERE user_id = ?');
            $stmt_current_level->execute([$user_id]);
            $current_level_db = (int)$stmt_current_level->fetchColumn();
            if ($map_id > $current_level_db) { 
                 $stmt_level_up = $pdo->prepare('UPDATE stats SET current_level = :new_level WHERE user_id = :user_id');
                 $stmt_level_up->execute([':new_level' => $map_id, ':user_id' => $user_id]);
            }
        }
    }

    $stmt_map_game_count = $pdo->prepare('UPDATE Map SET map_game_count = map_game_count + 1 WHERE id = :map_id');
    $stmt_map_game_count->execute([':map_id' => $map_id]);

    if ($is_victory) {
        $stmt_map_scores = $pdo->prepare('SELECT best_player_time, best_player_moves FROM Map WHERE id = :map_id');
        $stmt_map_scores->execute([':map_id' => $map_id]);
        $map_current_bests = $stmt_map_scores->fetch();

        if ($map_current_bests) {
            $new_best_time = false;
            $new_best_moves = false;

            if ($map_current_bests['best_player_time'] === null || $map_current_bests['best_player_time'] == 0 || $time_taken < $map_current_bests['best_player_time']) {
                $new_best_time = true;
                $pdo->prepare('UPDATE Map SET best_player_time = :time, best_player_time_name = :name WHERE id = :map_id')
                    ->execute([':time' => $time_taken, ':name' => $user_name, ':map_id' => $map_id]);
            }
            if ($map_current_bests['best_player_moves'] === null || $map_current_bests['best_player_moves'] == 999 || $map_current_bests['best_player_moves'] == 0 || $moves_made < $map_current_bests['best_player_moves']) {
                 $new_best_moves = true;
                 $pdo->prepare('UPDATE Map SET best_player_moves = :moves, best_player_moves_name = :name WHERE id = :map_id')
                    ->execute([':moves' => $moves_made, ':name' => $user_name, ':map_id' => $map_id]);
            }
            if ($new_best_time) $response_data['new_best_time'] = true;
            if ($new_best_moves) $response_data['new_best_moves'] = true;
        }
    }

    $pdo->commit();
    $response_data['success'] = true;
    $response_data['message'] = 'Game progress saved successfully.';
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