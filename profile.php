<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (!is_logged_in()) {
    redirect('login-signup.php');
}

$user = current_user();          // ['id', 'name', 'role']
$userId = $user['id'];

/* ---------- Handle profile POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* --- Username update --- */
    if (isset($_POST['update_name'])) {
        $newName = trim($_POST['username'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_@.-]{3,30}$/', $newName)) {
            flash('error', 'Invalid username. Use 3-30 chars (letters, numbers, _, @, ., -).');
        } else {
            // Uniqueness
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM User WHERE name = :n AND user_id != :id');
            $stmt->execute([':n' => $newName, ':id' => $userId]);
            if ($stmt->fetchColumn() > 0) {
                flash('error', 'Username already taken.');
            } else {
                $pdo->prepare('UPDATE User SET name = :n WHERE user_id = :id')
                    ->execute([':n' => $newName, ':id' => $userId]);
                $_SESSION['user']['name'] = $newName;   // refresh session
                flash('success', 'Username updated!');
            }
        }
        redirect('profile.php');
    }

    /* --- Password update --- */
    if (isset($_POST['update_pass'])) {
        $current = $_POST['current'] ?? '';
        $new     = $_POST['new']     ?? '';
        $confirm = $_POST['confirm'] ?? '';

        if (strlen($new) < 6) {
            flash('error', 'New password must be at least 6 characters long.');
            redirect('profile.php');
        } elseif ($new !== $confirm) {
            flash('error', 'New passwords do not match.');
            redirect('profile.php');
        } else {
            $stmt = $pdo->prepare('SELECT password FROM User WHERE user_id = ?');
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($current, $hash)) {
                flash('error', 'Current password is incorrect.');
            } else {
                $pdo->prepare('UPDATE User SET password = :p WHERE user_id = :id')
                    ->execute([':p' => password_hash($new, PASSWORD_DEFAULT), ':id' => $userId]);
                flash('success', 'Password updated!');
            }
        }
        redirect('profile.php');
    }
}

/* ---------- Gather statistics ---------- */
$stmt = $pdo->prepare('SELECT * FROM Stats WHERE user_id = ?');
$stmt->execute([$userId]);
$stats = $stmt->fetch() ?: [
    'user_game_count' => 0,
    'win_count'       => 0,
    'played_time'     => 0,
    'current_level'   => 0, // This refers to highest story chapter completed
    'money'           => 0
];

$gamesPlayed = (int)$stats['user_game_count'];
$wins        = (int)$stats['win_count'];
$money       = (int)$stats['money'];
$losses      = max(0, $gamesPlayed - $wins);
$winrate     = $gamesPlayed ? round(($wins / $gamesPlayed) * 100) : 0;
$storyLevelCompleted = (int)$stats['current_level']; // Renamed for clarity

$seconds     = (int)$stats['played_time'];
$hours       = floor($seconds / 3600);
$minutes     = floor(($seconds % 3600) / 60);
$playedLabel = sprintf('%dh %02dm', $hours, $minutes);

/* ---------- Player Ranking ---------- */
$rankStmt = $pdo->query(
    "SELECT s.user_id,
           (s.win_count / GREATEST(1, s.user_game_count)) * (1 + LOG10(GREATEST(1,s.user_game_count))) AS ranking_score,
           u.name as user_name
     FROM Stats s JOIN User u ON s.user_id = u.user_id
     ORDER BY ranking_score DESC, s.user_game_count DESC, s.win_count DESC"
);

$rank = 'N/A'; $pos = 0; $totalPlayers = 0; $currentUserScore = null;
$allRankedPlayers = $rankStmt->fetchAll();
$totalPlayers = count($allRankedPlayers);

foreach ($allRankedPlayers as $i => $player) {
    if ($player['user_id'] == $userId) {
        $rank = $i + 1;
        $currentUserScore = $player['ranking_score'];
        break;
    }
}

$rankLabel = is_numeric($rank) ? "#".$rank : 'N/A';
$percentileLabel = 'N/A';
if (is_numeric($rank) && $totalPlayers > 0) {
    // Percentile: ( (Total Players - Rank + 1) / Total Players ) * 100
    // This calculates the percentage of players you are equal to or better than.
    // A rank of 1 in 100 players means you are in the top (100-1+1)/100 * 100 = 100th percentile.
    // A rank of 50 in 100 players means you are in the top (100-50+1)/100 * 100 = 51st percentile.
    // Usually "Top X%" refers to (Rank / Total Players) * 100. Let's use that for clarity.
    $percentile_raw = ($rank / $totalPlayers) * 100;
    $percentileLabel = "Top " . number_format($percentile_raw, 1) . "%";


     if ($percentile_raw <= 1) $rankTitle = 'Living Legend';
     elseif ($percentile_raw <= 5) $rankTitle = 'Grandmaster Delver';
     elseif ($percentile_raw <= 10) $rankTitle = 'Elite Champion';
     elseif ($percentile_raw <= 25) $rankTitle = 'Master Adventurer';
     elseif ($percentile_raw <= 50) $rankTitle = 'Skilled Explorer';
     elseif ($percentile_raw <= 75) $rankTitle = 'Dungeon Crawler';
     else $rankTitle = 'Novice Delver';
} else {
    $rankTitle = 'Unranked';
}


/* ---------- User's Maps ---------- */
$mapsStmt = $pdo->prepare(
    "SELECT id, name, creation_date, difficulty, size
     FROM Map
     WHERE user_id = ?
     ORDER BY creation_date DESC"
);
$mapsStmt->execute([$userId]);
$userMaps = $mapsStmt->fetchAll();


/* ---------- Flash messages ---------- */
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile & Stats | Mystical Dungeons</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=MedievalSharp&display=swap');
:root {
    --primary-orange: #ff8906;
    --primary-pink: #e53170;
    --dark-bg: #0f0e17;
    --panel-bg: rgba(27,27,45,0.9);
    --border-color: rgba(255,137,6,.3);
    --text-light: #e8e8e8;
    --text-muted: #a0aec0;
    --text-amber: #ffc107;
    --text-green: #4ade80;
    --text-red: #f87171;
    --text-blue: #60a5fa;
    --text-purple: #c084fc;
    --text-yellow: #facc15;
}
body {
    font-family: 'MedievalSharp', cursive;
    background-color: var(--dark-bg);
    color: var(--text-light);
    background-image: radial-gradient(circle at 10% 20%, rgba(255,137,6,.08) 0%, transparent 20%),
                      radial-gradient(circle at 90% 80%, rgba(229,49,112,.08) 0%, transparent 20%);
    line-height: 1.6;
}
.text-gradient {
    background: linear-gradient(135deg, var(--primary-orange) 0%, var(--primary-pink) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.btn-primary {
    background: linear-gradient(135deg, var(--primary-orange) 0%, var(--primary-pink) 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: bold;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(229,49,112,.3);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}
.btn-primary:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 8px 25px rgba(229,49,112,.45);
}
.btn-secondary {
    background: rgba(74,74,90,0.6);
    border: 1px solid var(--border-color);
    color: var(--text-light);
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    transition: all 0.3s ease;
}
.btn-secondary:hover {
    background: rgba(90,90,110,0.7);
    border-color: rgba(255,137,6,0.5);
    transform: translateY(-2px);
}

.input-field {
    background-color: rgba(15,14,23,0.7);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    width: 100%;
    color: var(--text-light);
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
.input-field:focus {
    outline: none;
    border-color: var(--primary-orange);
    box-shadow: 0 0 0 3px rgba(255,137,6,.25);
}
.card {
    background: var(--panel-bg);
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 25px rgba(0,0,0,.35);
    backdrop-filter: blur(5px);
    border-radius: 0.75rem;
    padding: 1.5rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,.45);
}
.stat-item {
    background-color: rgba(15,14,23,0.5);
    border: 1px solid rgba(255,137,6,.15);
    padding: 1rem;
    border-radius: 0.5rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.stat-item .icon-bg {
    width: 40px; height: 40px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem auto; /* Center icon */
    background: linear-gradient(135deg, rgba(255,137,6,.2) 0%, rgba(229,49,112,.2) 100%);
}
.stat-item .icon-bg i { color: var(--primary-orange); }
.stat-item p.label { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.25rem; }
.stat-item p.value { font-size: 1.75rem; font-weight: bold; }

.progress-bar-bg {
    height: 8px; background-color: rgba(255,255,255,0.1);
    border-radius: 4px; overflow: hidden; margin-top:0.75rem;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary-orange), var(--primary-pink));
    border-radius: 4px;
    transition: width 0.5s ease-in-out;
}
.map-card {
    background: rgba(30,30,50,0.8);
    border: 1px solid rgba(255,137,6,.25);
    border-radius: 0.5rem;
    padding: 1rem;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.map-card:hover {
    transform: scale(1.03);
    box-shadow: 0 5px 20px rgba(255,137,6,.2);
    border-color: rgba(255,137,6,.4);
}
.map-actions a, .map-actions button { /* Apply to button as well if used */
    color: var(--text-muted);
    background-color: transparent; /* Ensure no default button bg */
    border: none; /* Ensure no default button border */
    padding: 0.5rem;
    border-radius: 0.25rem;
    transition: all 0.2s ease;
    cursor: pointer;
}
.map-actions a:hover, .map-actions button:hover { background-color: rgba(255,255,255,0.1); }
.map-actions .fa-edit:hover { color: var(--text-blue); }
.map-actions .fa-play:hover { color: var(--text-green); }
.map-actions .fa-trash-alt:hover { color: var(--text-red); }

.tooltip { position: relative; display: inline-block; }
.tooltip .tooltiptext {
    visibility: hidden; min-width: 80px; background-color: #333; color: #fff;
    text-align: center; border-radius: 6px; padding: 5px 8px;
    position: absolute; z-index: 10; bottom: 125%; left: 50%;
    transform: translateX(-50%);
    opacity: 0; transition: opacity 0.3s; font-size: 0.8rem; white-space: nowrap;
}
.tooltip:hover .tooltiptext { visibility: visible; opacity: 1; }

.difficulty-dots span {
    display: inline-block; width: 10px; height: 10px;
    border-radius: 50%; background-color: rgba(255,255,255,0.2);
    margin-right: 3px;
}
.difficulty-dots span.filled {
    background-color: var(--primary-orange);
}

.flash-message-container {
    position: fixed;
    top: 80px; /* Adjust if navbar height changes */
    right: 20px;
    z-index: 1000;
    width: 100%;
    max-width: 350px;
}
.flash-message {
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    border-radius: 0.5rem;
    font-size: 0.9rem;
    border-left-width: 5px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    opacity: 0;
    transform: translateX(20px);
    animation: flash-slide-in 0.5s forwards;
}
@keyframes flash-slide-in {
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
.flash-message.fade-out {
    animation: flash-fade-out 0.5s forwards;
}
@keyframes flash-fade-out {
    to {
        opacity: 0;
        transform: translateX(20px) translateY(-10px);
    }
}
.flash-error {
    background-color: rgba(229, 49, 112, 0.15);
    color: #fbcfe8;
    border-color: var(--primary-pink);
}
.flash-success {
    background-color: rgba(56, 161, 105, 0.15);
    color: #a7f3d0;
    border-color: var(--text-green);
}
</style>
</head>
<body class="min-h-screen">

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="flash-message-container">
    <?php if ($flashes): ?>
        <?php foreach ($flashes as $type => $msgs): ?>
            <?php foreach ($msgs as $msg): ?>
            <div class="flash-message <?= $type === 'error' ? 'flash-error' : 'flash-success' ?>">
                <i class="fas <?= $type === 'error' ? 'fa-times-circle' : 'fa-check-circle' ?> mr-2"></i><?= h($msg) ?>
            </div>
            <?php endforeach ?>
        <?php endforeach ?>
    <?php endif; ?>
</div>


<div class="container mx-auto px-4 py-8">

    <header class="mb-12 text-center">
        <h1 class="text-5xl font-bold text-gradient mb-2">Hero's Profile</h1>
        <p class="text-xl text-gray-400">Oversee your legend and manage your account.</p>
        <div class="mt-4">
            <a href="logout.php" class="btn-primary text-sm px-6 py-2">
                <i class="fas fa-sign-out-alt mr-2"></i>Logout
            </a>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
        <!-- Left Column – Profile settings -->
        <div class="lg:col-span-1 card">
            <h2 class="text-3xl font-bold mb-6 text-gradient flex items-center">
                <i class="fas fa-user-shield mr-3 text-2xl"></i>Account
            </h2>

            <form method="POST" action="" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-amber-400 mb-1">Username</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-[var(--border-color)] bg-[rgba(15,14,23,0.5)] text-gray-400 text-sm">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="username" id="username" value="<?= h($user['name']) ?>" class="input-field rounded-l-none flex-1">
                    </div>
                </div>
                <button type="submit" name="update_name" class="btn-primary w-full">
                    <i class="fas fa-save mr-2"></i>Update Username
                </button>
            </form>

            <hr class="my-8 border-gray-700/50">

            <form method="POST" action="" class="space-y-4">
                 <h3 class="text-xl font-semibold text-amber-400 mb-3">Change Password</h3>
                <div>
                    <label for="current_pass" class="block text-sm font-medium text-amber-400 mb-1">Current Password</label>
                     <div class="flex">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-[var(--border-color)] bg-[rgba(15,14,23,0.5)] text-gray-400 text-sm">
                            <i class="fas fa-lock-open"></i>
                        </span>
                        <input type="password" name="current" id="current_pass" placeholder="••••••••" class="input-field rounded-l-none flex-1">
                    </div>
                </div>
                <div>
                    <label for="new_pass" class="block text-sm font-medium text-amber-400 mb-1">New Password</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-[var(--border-color)] bg-[rgba(15,14,23,0.5)] text-gray-400 text-sm">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="new" id="new_pass" placeholder="Min. 6 characters" class="input-field rounded-l-none flex-1">
                    </div>
                </div>
                <div>
                    <label for="confirm_pass" class="block text-sm font-medium text-amber-400 mb-1">Confirm New Password</label>
                     <div class="flex">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-[var(--border-color)] bg-[rgba(15,14,23,0.5)] text-gray-400 text-sm">
                            <i class="fas fa-redo-alt"></i>
                        </span>
                        <input type="password" name="confirm" id="confirm_pass" placeholder="Repeat new password" class="input-field rounded-l-none flex-1">
                    </div>
                </div>
                <button type="submit" name="update_pass" class="btn-primary w-full">
                    <i class="fas fa-key mr-2"></i>Update Password
                </button>
            </form>
        </div>

        <!-- Right Column – Stats -->
        <div class="lg:col-span-2 card">
            <h2 class="text-3xl font-bold mb-6 text-gradient flex items-center">
                <i class="fas fa-scroll mr-3 text-2xl"></i>Player Stats
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                <div class="stat-item">
                    <div class="icon-bg"><i class="fas fa-gamepad"></i></div>
                    <div><p class="label">Games Played</p> <p class="value"><?= $gamesPlayed ?></p></div>
                    <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:100%;"></div></div>
                </div>
                <div class="stat-item">
                    <div class="icon-bg"><i class="fas fa-trophy" style="color: var(--text-green);"></i></div>
                     <div><p class="label">Wins</p> <p class="value" style="color:var(--text-green);"><?= $wins ?></p></div>
                    <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:<?= $winrate ?>%; background: var(--text-green);"></div></div>
                </div>
                <div class="stat-item">
                    <div class="icon-bg"><i class="fas fa-skull-crossbones" style="color: var(--text-red);"></i></div>
                    <div><p class="label">Losses</p> <p class="value" style="color:var(--text-red);"><?= $losses ?></p></div>
                    <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:<?= $gamesPlayed > 0 ? round(($losses / $gamesPlayed) * 100) : 0 ?>%; background: var(--text-red);"></div></div>
                </div>
                <div class="stat-item">
                    <div class="icon-bg"><i class="fas fa-percentage" style="color: var(--text-purple);"></i></div>
                    <div><p class="label">Win Rate</p> <p class="value" style="color:var(--text-purple);"><?= $winrate ?>%</p></div>
                    <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:<?= $winrate ?>%; background: var(--text-purple);"></div></div>
                </div>
                <div class="stat-item">
                    <div class="icon-bg"><i class="fas fa-hourglass-half" style="color: var(--text-blue);"></i></div>
                    <div><p class="label">Total Play Time</p> <p class="value" style="color:var(--text-blue);"><?= $playedLabel ?></p></div>
                </div>
                <div class="stat-item">
                    <div class="icon-bg"><i class="fas fa-coins" style="color: var(--text-yellow);"></i></div>
                    <div><p class="label">Gold Earned</p> <p class="value" style="color: var(--text-yellow);"><?= number_format($money) ?></p></div>
                </div>
            </div>

            <div class="bg-gray-800/50 p-6 rounded-lg border border-amber-500/20">
                <h3 class="text-xl font-bold text-amber-400 mb-3 flex items-center"><i class="fas fa-crown mr-2"></i>Current Rank</h3>
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-center sm:text-left">
                        <p class="text-4xl font-bold text-gradient"><?= $rankLabel ?></p>
                        <p class="text-gray-400 text-sm">out of <?= $totalPlayers ?> players</p>
                    </div>
                    <div class="text-center sm:text-right">
                        <p class="text-2xl font-semibold" style="color:var(--text-amber)"><?= h($rankTitle) ?></p>
                        <p class="text-gray-400 text-sm"><?= $percentileLabel ?></p>
                    </div>
                </div>
                 <?php if ($currentUserScore !== null): ?>
                <p class="text-xs text-gray-500 mt-2 text-center sm:text-right">Score: <?= number_format($currentUserScore, 2) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- User's Maps Section -->
    <section class="card py-8">
        <h2 class="text-3xl font-bold mb-8 text-gradient text-center flex items-center justify-center">
            <i class="fas fa-map-marked-alt mr-4 text-2xl"></i>My Created Dungeons
        </h2>
        <?php if (empty($userMaps)): ?>
            <p class="text-center text-gray-400 text-lg">You haven't created any dungeons yet.
                <a href="create_map.php" class="text-amber-400 hover:text-amber-300 font-semibold underline">Craft one now!</a>
            </p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($userMaps as $map):
                    $play_url = "play.php?mode=play&level=" . ((int)$map['id'] <= 4 ? (int)$map['id'] : "custom_" . (int)$map['id']);
                ?>
                    <div class="map-card">
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-xl font-semibold text-amber-300 leading-tight"><?= h($map['name']) ?></h3>
                                <span class="text-xs text-gray-500 whitespace-nowrap ml-2 pt-1"><?= date('d M, Y', strtotime($map['creation_date'])) ?></span>
                            </div>
                            <p class="text-sm text-gray-400 mb-1">Size: <span class="font-semibold text-gray-300"><?= h($map['size']) ?>x<?= h($map['size']) ?></span></p>
                            <div class="text-sm text-gray-400 mb-3 flex items-center">
                                Difficulty:
                                <div class="difficulty-dots ml-2">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <span class="<?= $i <= $map['difficulty'] ? 'filled' : '' ?>"></span>
                                    <?php endfor; ?>
                                </div>
                                <span class="font-semibold text-gray-300 ml-1">(<?= h($map['difficulty']) ?>/10)</span>
                            </div>
                        </div>
                        <div class="mt-auto pt-3 border-t border-gray-700/50 flex justify-end items-center space-x-1 map-actions">
                            <a href="<?= $play_url ?>" class="tooltip">
                                <i class="fas fa-play fa-fw fa-lg"></i>
                                <span class="tooltiptext">Play</span>
                            </a>
                            <a href="create_map.php?map_id=<?= h($map['id']) ?>" class="tooltip">
                                <i class="fas fa-edit fa-fw fa-lg"></i>
                                <span class="tooltiptext">Edit</span>
                            </a>

                            <form action="profile.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this map? This action cannot be undone.');" class="inline-block">
                                <input type="hidden" name="delete_map_id" value="<?= h($map['id']) ?>">
                                <button type="submit" name="delete_map" class="tooltip">
                                    <i class="fas fa-trash-alt fa-fw fa-lg"></i>
                                    <span class="tooltiptext">Delete</span>
                                </button>
                            </form>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
         <div class="text-center mt-8">
            <a href="create_map.php" class="btn-primary"><i class="fas fa-plus-circle mr-2"></i>Create New Dungeon</a>
        </div>
    </section>

</div>
<footer class="text-center py-8 text-sm text-gray-500 border-t border-gray-800/50">
    Mystical Dungeons © <?= date('Y') ?>. All rights reserved.
</footer>

<script>
    // Auto-hide flash messages
    document.addEventListener('DOMContentLoaded', () => {
        const flashMessages = document.querySelectorAll('.flash-message-container .flash-message');
        flashMessages.forEach((flash, index) => {
            setTimeout(() => {
                flash.classList.add('fade-out');
                setTimeout(() => {
                    flash.remove();
                    // If it's the last flash message, remove the container if empty
                    if (index === flashMessages.length - 1) {
                        const container = document.querySelector('.flash-message-container');
                        if (container && container.children.length === 0) {
                           // container.remove(); // Optionally remove container if all flashes are gone
                        }
                    }
                }, 500); // Match animation duration
            }, 4500 + index * 300); // Stagger removal slightly
        });
    });
</script>

</body>
</html>