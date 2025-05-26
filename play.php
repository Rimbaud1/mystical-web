<?php
// 1. db.php démarre la session
require_once __DIR__.'/includes/db.php';
// 2. auth.php et helpers.php peuvent maintenant utiliser la session
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';

if (!is_logged_in()) {
    redirect('login-signup.php');
    exit;
}

$current_user = current_user();
if (!$current_user || !isset($current_user['id']) || !isset($current_user['name'])) {
    flash('error', 'User session data is corrupted. Please log in again.');
    logout_user();
    redirect('login-signup.php');
    exit;
}
$user_id = $current_user['id'];
$user_name = $current_user['name'];

$stmt_stats = $pdo->prepare('SELECT * FROM stats WHERE user_id = ?');
$stmt_stats->execute([$user_id]);
$user_stats = $stmt_stats->fetch();

if (!$user_stats) {
    $pdo->prepare('INSERT INTO stats (user_id, played_time, current_level, user_game_count, win_count, money) VALUES (?, 0, 0, 0, 0, 0)')
        ->execute([$user_id]);
    $stmt_stats->execute([$user_id]);
    $user_stats = $stmt_stats->fetch();
}
$current_user_level_completed = (int)($user_stats['current_level'] ?? 0);
$played_levels_session_key = 'played_story_levels_' . $user_id;
$_SESSION[$played_levels_session_key] = $_SESSION[$played_levels_session_key] ?? [];


$mode = $_GET['mode'] ?? 'select_initial_mode'; // select_initial_mode, select_base_level, select_custom_level, play_story_intro, play
$map_to_load = null;
$map_type_playing = null; // 'base' or 'custom'
$story_level_to_intro = null;

$flash_messages = get_flashes();

// --- Story Content ---
$story_content = [
    1 => [
        'audio' => 'assets/audios/story/1.wav',
        'speaker' => 'Adventurer',
        'duration' => 69,
        'en' => [
            "I... I don't know how long I’ve been down here. Time... it doesn’t exist anymore. Just the cold, the dark... and the whispers. God, the whispers... they’re not human. I swear something’s watching me—always just beyond the torchlight. I came in looking for treasure, for glory. Fool!",
            "These walls... they breathe. They shift when I’m not looking. I tried marking my path—but the marks vanish, like the dungeon wants me lost.",
            "I’m not alone. I’ve heard screams… and worse—laughter. Not mine. Not human.",
            "If anyone finds this… tell them… tell them to seal this cursed place forever.",
            "Please… don’t let it take another soul."
        ],
        'fr' => [
            "Je... Je ne sais plus depuis combien de temps je suis ici. Le temps... il n'existe plus. Juste le froid, l'obscurité... et les murmures. Mon Dieu, les murmures... ils ne sont pas humains. Je jure que quelque chose m'observe, toujours juste au-delà de la lumière de la torche. Je suis venu chercher des trésors, la gloire. Imbécile !",
            "Ces murs... ils respirent. Ils bougent quand je ne regarde pas. J'ai essayé de marquer mon chemin, mais les marques disparaissent, comme si le donjon voulait que je me perde.",
            "Je ne suis pas seul. J'ai entendu des cris... et pire, des rires. Pas les miens. Pas humains.",
            "Si quelqu'un trouve ceci... dites-leur... dites-leur de sceller cet endroit maudit à jamais.",
            "S'il vous plaît... ne le laissez pas prendre une autre âme."
        ]
    ],
    2 => [
        'audio' => 'assets/audios/story/2.wav',
        'speaker' => 'Adventurer',
        'duration' => 38,
        'en' => [
            "Please—please! I can’t take this anymore! They’re close… I hear them… they move in the shadows! I tried—I tried to reach the exit, but the doors… they closed behind me…",
            "I’m trapped. I don’t have the strength left to fight. My hands are shaking—I dropped my weapon, I—I can’t even hold it anymore!",
            "If anyone finds this… finish it. Finish the dungeon. Destroy whatever’s down here… and bring help. Bring torches, bring weapons—bring an army if you have to!",
            "Just—get me out. Get me out!",
            "I’m begging you…"
        ],
        'fr' => [
            "S'il vous plaît, s'il vous plaît ! Je n'en peux plus ! Ils sont proches... Je les entends... ils bougent dans les ombres ! J'ai essayé, j'ai essayé d'atteindre la sortie, mais les portes... elles se sont refermées derrière moi...",
            "Je suis piégé. Je n'ai plus la force de me battre. Mes mains tremblent, j'ai laissé tomber mon arme, je... je ne peux même plus la tenir !",
            "Si quelqu'un trouve ceci... terminez-le. Terminez le donjon. Détruisez ce qui se trouve ici... et amenez de l'aide. Apportez des torches, des armes, amenez une armée s'il le faut !",
            "Juste... sortez-moi de là. Sortez-moi de là !",
            "Je vous en supplie..."
        ]
    ],
    3 => [
        'audio' => 'assets/audios/story/3.wav',
        'speaker' => 'Dungeon Master',
        'duration' => 53,
        'en' => [
            "Hhhhehe… You’re in now, aren’t you? (tongue click) Tsk.",
            "One step in — and snap! (sharp finger-snap sound) No way out.",
            "Ooooh, I love this part… when they start to realize. (short raspy laugh) Hhhhahh…",
            "The torch dims. The walls breathe. (quick inhale) —You feel it, don’t you? The dungeon watching.",
            "Tap-tap-tap! (fingers mimicking approaching footsteps) That’s not you.",
            "It’s coming. It’s always coming. (small burst of manic laughter)",
            "You’ll scream, beg, crawl — and I’ll watch. With popcorn! Hahaha!",
            "And when you break… (suddenly soft, near-whisper)",
            "I’ll whisper one name...",
            "(slows dramatically — deep, echoing reverence) Mystical… Dungeons."
        ],
        'fr' => [
            "Héhéhé... Tu es entré maintenant, n'est-ce pas ? (clic de langue) Tsk.",
            "Un pas à l'intérieur - et crac ! (bruit sec de claquement de doigts) Plus de sortie.",
            "Ooooh, j'adore ce moment... quand ils commencent à réaliser. (petit rire rauque) Hhhhahh...",
            "La torche faiblit. Les murs respirent. (inspiration rapide) - Tu le sens, n'est-ce pas ? Le donjon qui observe.",
            "Tap-tap-tap ! (doigts imitant des bruits de pas qui s'approchent) Ce n'est pas toi.",
            "Ça arrive. Ça arrive toujours. (petite explosion de rire maniaque)",
            "Tu crieras, supplieras, ramperas - et je regarderai. Avec du pop-corn ! Hahaha !",
            "Et quand tu craqueras... (soudainement doux, presque un murmure)",
            "Je murmurerai un nom...",
            "(ralentit considérablement - profond, réverbérant de respect) Mystical... Dungeons."
        ]
    ],
    4 => [
        'audio' => 'assets/audios/story/4.wav',
        'speaker' => 'Dungeon Master',
        'duration' => 65,
        'en' => [
            "Three… You’ve endured three of my trials… How… unexpected.",
            "They fell to despair. They broke under fear. But you… You walked. You learned.",
            "And now… you stand at the threshold. This is no trial. This is the descent.",
            "The walls here remember. They know your scent. They watched you grow… Stronger.",
            "But strength… tastes just as sweet when it breaks. (Quiet chuckle)",
            "You descend. Not to conquer. To be claimed.",
            "(Final line — slow, deep, epic) Mystical… Dungeons."
        ],
        'fr' => [
            "Trois... Tu as enduré trois de mes épreuves... Comme c'est... inattendu.",
            "Ils sont tombés dans le désespoir. Ils ont craqué sous la peur. Mais toi... Tu as marché. Tu as appris.",
            "Et maintenant... tu te tiens au seuil. Ce n'est pas une épreuve. C'est la descente.",
            "Les murs ici se souviennent. Ils connaissent ton odeur. Ils t'ont vu grandir... Plus fort.",
            "Mais la force... a un goût tout aussi doux quand elle se brise. (Petit rire)",
            "Tu descends. Pas pour conquérir. Pour être revendiqué.",
            "(Dernière ligne - lente, profonde, épique) Mystical... Dungeons."
        ]
    ],
];


if ($mode === 'play') {
    $level_param = $_GET['level'] ?? null;
    if ($level_param === null) {
        flash('error', 'No level specified to play.');
        redirect('play.php?mode=select_initial_mode');
        exit;
    }

    if (strpos($level_param, 'custom_') === false) {
        $base_map_id_check = (int)$level_param;
        if ($base_map_id_check >= 1 && $base_map_id_check <= 4) {
            // On vérifie si le niveau a déjà été marqué comme "intro jouée" CETTE SESSION ou DANS LA BDD (via current_user_level_completed)
            $has_played_intro_this_session = in_array($base_map_id_check, $_SESSION[$played_levels_session_key]);
            $is_new_uncompleted_level = $base_map_id_check > $current_user_level_completed;

            if ($is_new_uncompleted_level && !$has_played_intro_this_session && isset($story_content[$base_map_id_check])) {
                $_SESSION[$played_levels_session_key][] = $base_map_id_check; 
                $_SESSION[$played_levels_session_key] = array_unique($_SESSION[$played_levels_session_key]);
                sort($_SESSION[$played_levels_session_key]);
                redirect('play.php?mode=play_story_intro&level=' . $base_map_id_check);
                exit;
            }
        }
    }
}


if ($mode === 'play_story_intro') {
    $story_level_to_intro = isset($_GET['level']) ? (int)$_GET['level'] : null;
    if ($story_level_to_intro === null || !isset($story_content[$story_level_to_intro])) {
        flash('error', 'Invalid story level for intro.');
        redirect('play.php?mode=select_base_level');
        exit;
    }
} elseif ($mode === 'play') {
    $level_param = $_GET['level'] ?? null;

    if (strpos($level_param, 'custom_') === 0) {
        $custom_map_id = (int)substr($level_param, 7);
        if ($custom_map_id > 4) {
            $stmt = $pdo->prepare('SELECT m.*, u.name as author_name FROM Map m JOIN User u ON m.user_id = u.user_id WHERE m.id = ? LIMIT 1');
            $stmt->execute([$custom_map_id]);
            $map_to_load = $stmt->fetch();
            if (!$map_to_load) {
                flash('error', 'Custom map not found or invalid.');
                redirect('play.php?mode=select_custom_level');
                exit;
            }
            $map_type_playing = 'custom';
        } else {
            flash('error', 'Invalid custom map ID specified.');
            redirect('play.php?mode=select_custom_level');
            exit;
        }
    } else { 
        $base_map_id = (int)$level_param;
        if ($base_map_id >= 1 && $base_map_id <= 4) {
            if ($base_map_id > $current_user_level_completed + 1) {
                 flash('error', 'You must complete previous levels first!');
                 redirect('play.php?mode=select_base_level');
                 exit;
            }
            $stmt = $pdo->prepare('SELECT m.*, u.name as author_name FROM Map m LEFT JOIN User u ON m.user_id = u.user_id WHERE m.id = ? LIMIT 1');
            $stmt->execute([$base_map_id]);
            $map_to_load = $stmt->fetch();
            if (!$map_to_load) {
                flash('error', 'Base level not found.');
                redirect('play.php?mode=select_base_level');
                exit;
            }
            $map_type_playing = 'base';
        } else {
            flash('error', 'Invalid base level ID specified.');
            redirect('play.php?mode=select_base_level');
            exit;
        }
    }
}


if (!in_array($mode, ['play', 'play_story_intro'])) {
    $base_levels_data = [];
    $user_maps_data = [];
    $search_query = '';

    if ($mode === 'select_base_level') {
        $stmt = $pdo->query(
            'SELECT id, name, difficulty, map_game_count, 
                    best_player_time, best_player_time_name, 
                    best_player_moves, best_player_moves_name
             FROM Map WHERE id IN (1, 2, 3, 4) ORDER BY id ASC'
        );
        $base_levels_data = $stmt->fetchAll();
    }

    if ($mode === 'select_custom_level') {
        $search_query = trim($_GET['search_map_name'] ?? '');
        $sql = 'SELECT m.id, m.name, m.size, m.difficulty, m.map_game_count, u.name as author_name,
                       m.best_player_time, m.best_player_time_name, 
                       m.best_player_moves, m.best_player_moves_name,
                       m.creation_date
                FROM Map m
                JOIN User u ON m.user_id = u.user_id
                WHERE m.id > 4';
        $params = [];
        if (!empty($search_query)) {
            $sql .= ' AND m.name LIKE ?';
            $params[] = '%' . $search_query . '%';
        }
        $sql .= ' ORDER BY m.creation_date DESC LIMIT 18';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $user_maps_data = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Play | Mystical Dungeons</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=MedievalSharp&display=swap');
body{font-family:'MedievalSharp',cursive;background:#0f0e17;color:#e8e8e8;background-image:radial-gradient(circle at 10% 20%,rgba(255,137,6,.08)0,transparent 20%),radial-gradient(circle at 90% 80%,rgba(229,49,112,.08)0,transparent 20%)}
.panel{background:#1b1b2de8;border:1px solid rgba(255,137,6,.3);box-shadow:0 8px 20px rgba(0,0,0,.35); transition: border-color 0.3s ease, transform 0.2s ease;}
.panel:hover:not(.panel-locked):not(.no-hover-effect){ border-color: rgba(255,137,6,.6); transform: translateY(-3px); }
.panel-locked { opacity: 0.6; cursor: not-allowed; background: #1a1a2c; }
.panel-locked:hover { transform: none; border-color: rgba(255,137,6,.3); }
.text-grad, .text-gradient {background:linear-gradient(135deg,#ff8906 0%,#e53170 100%);-webkit-background-clip:text;color:transparent;display:inline-block;}
.btn{background:linear-gradient(135deg,#ff8906 0%,#e53170 100%);color:#fff;padding:.5rem 1.2rem;border-radius:.5rem;transition: transform 0.2s ease, box-shadow 0.2s ease; font-weight: bold; text-align: center; display: inline-block;}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(229,49,112,.45)}
.btn:disabled, .btn-disabled { opacity: 0.7; cursor: not-allowed; transform: none; box-shadow: none; background: #555; }
.input-field { background-color: rgba(255,255,255,0.05); border: 1px solid rgba(255,137,6,0.3); border-radius: 0.375rem; padding: 0.5rem 0.75rem; color: #e8e8e8; transition: border-color 0.2s; }
.input-field:focus { outline: none; border-color: #ff8906; box-shadow: 0 0 0 2px rgba(255,137,6,0.2); }
.grid-wrap{max-height:80vh;max-width:calc(100vw - 30px);overflow:auto;border:3px solid rgba(255,137,6,.25); border-radius: 8px; margin:auto; background: #10101b;}
.grid{display:grid;gap:1px; background-color: rgba(40,40,60,0.3);}
.cell{background-size:contain;background-repeat:no-repeat;background-position:center;user-select:none;transition:box-shadow 0.2s ease-in-out, background-color 0.2s ease-in-out, transform 0.1s ease-out;}
.player{background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"><circle cx="15" cy="8" r="6" fill="%23ffd700"/><polygon points="10,14 20,14 23,27 7,27" fill="%23ffd700"/></svg>');z-index:10;}
@keyframes shake{0%,100%{transform:translateX(0) rotate(0)}20%{transform:translateX(-5px) rotate(-3deg)}40%{transform:translateX(5px) rotate(3deg)}60%{transform:translateX(-4px) rotate(-2deg)}80%{transform:translateX(4px) rotate(2deg)}}
.player-collide{animation:shake .3s ease-in-out}
@keyframes victoryPulse{0%,100%{transform:scale(1);filter:brightness(100%) drop-shadow(0 0 4px %23ffd700)}50%{transform:scale(1.3);filter:brightness(170%) drop-shadow(0 0 10px %23ffc107) drop-shadow(0 0 15px %23ff8906)}}
.player-victory{animation:victoryPulse .7s infinite ease-in-out}
@keyframes giveUpFade{0%{transform:scale(1) rotate(0deg);opacity:1;filter:brightness(100%)}100%{transform:scale(0.2) rotate(360deg);opacity:0;filter:brightness(30%)}}
.player-giveup{animation:giveUpFade .7s forwards ease-in}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes scaleUpBounce{0%{transform:scale(.5) translateY(30px);opacity:0}60%{transform:scale(1.05) translateY(-5px);opacity:1}80%{transform:scale(.98) translateY(2px)}100%{transform:scale(1) translateY(0);opacity:1}}
.modal-backdrop{animation:fadeIn .3s ease-out forwards}
.modal-content{animation:scaleUpBounce .45s ease-out forwards}
.wall{background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%232C2C3A"/><rect x="1" y="1" width="13" height="8" fill="%2345455C"/><rect x="15" y="1" width="14" height="8" fill="%233D3D50"/><rect x="1" y="10" width="8" height="9" fill="%23505065"/><rect x="10" y="10" width="10" height="9" fill="%2345455C"/><rect x="21" y="10" width="8" height="9" fill="%233D3D50"/><rect x="1" y="20" width="14" height="9" fill="%233D3D50"/><rect x="16" y="20" width="13" height="9" fill="%2345455C"/></svg>');}
.path{background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30"><rect width="30" height="30" fill="%23141422"/><path d="M0 0h30v30H0z" fill="none" stroke="%23292929" stroke-width="1"/></svg>');}
.start{background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%23141422"/><path d="M15,24 L8,16 H12 V6 H18 V16 H22 L15,24 Z" fill="%2338c172" stroke="%232A8A4A" stroke-width="1.5" stroke-linejoin="round"/></svg>');}
.exit{background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%23141422"/><path d="M15,26 L6,16 H11 V5 H19 V16 H24 L15,26 Z" fill="%23e3342f" stroke="%23B02020" stroke-width="2" stroke-linejoin="miter"/><path d="M15,23 L9,16.5 H12.5 V7 H17.5 V16.5 H21 L15,23 Z" fill="none" stroke="%23FF6B6B" stroke-width="1"/></svg>');}
.button-tile{background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%23141422"/><circle cx="15" cy="15" r="11" fill="%23303035"/><circle cx="15" cy="15" r="10" fill="%23484850"/><circle cx="15" cy="15" r="7" fill="%23a0522d" stroke="%2370300D" stroke-width="1.5"/><circle cx="15" cy="15" r="3.5" fill="%23d2b48c" stroke="%23B0845C" stroke-width="1"/></svg>');}
.button-tile:hover { transform: scale(1.1); box-shadow: 0 0 10px #ffd700; }
.doorC{background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%23141422"/><path d="M5,28 V4 Q5,2 8,2 H22 Q25,2 25,4 V28 Z" fill="%234A3B31" stroke="%232C2C3A" stroke-width="0.5"/><rect x="7" y="3.5" width="16" height="24" fill="%236b4f3f" rx="1"/><line x1="11" y1="3.5" x2="11" y2="27.5" stroke="%235A3F31" stroke-width="1.5"/><line x1="15" y1="3.5" x2="15" y2="27.5" stroke="%2352382A" stroke-width="1.5"/><line x1="19" y1="3.5" x2="19" y2="27.5" stroke="%235A3F31" stroke-width="1.5"/><rect x="6.5" y="6" width="17" height="3.5" fill="%233A3A3A" stroke="%232A2A2A" stroke-width="0.5"/><rect x="6.5" y="20" width="17" height="3.5" fill="%233A3A3A" stroke="%232A2A2A" stroke-width="0.5"/><circle cx="8.5" cy="7.75" r="0.7" fill="%232A2A2A"/><circle cx="21.5" cy="7.75" r="0.7" fill="%232A2A2A"/><circle cx="8.5" cy="21.75" r="0.7" fill="%232A2A2A"/><circle cx="21.5" cy="21.75" r="0.7" fill="%232A2A2A"/><rect x="13" y="13.5" width="4" height="3" rx="0.5" fill="%232A2A2A"/><rect x="13.5" y="14" width="3" height="1" fill="%23d2b48c" opacity="0.6"/></svg>');}
.doorO{background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%23141422"/><path d="M5,28 V4 Q5,2 8,2 H22 Q25,2 25,4 V28 H20 V4.5 H10 V28 Z" fill="%234A3B31" stroke="%232C2C3A" stroke-width="0.5"/><g transform="translate(21 3.5) rotate(15 0 12)"><rect width="6" height="24" fill="%236b4f3f" rx="1"/><line x1="2" y1="0" x2="2" y2="24" stroke="%235A3F31" stroke-width="1"/><line x1="4" y1="0" x2="4" y2="24" stroke="%2352382A" stroke-width="1"/><rect y="2.5" width="6" height="3.5" fill="%233A3A3A" stroke="%232A2A2A" stroke-width="0.5"/><rect y="16.5" width="6" height="3.5" fill="%233A3A3A" stroke="%232A2A2A" stroke-width="0.5"/></g></svg>');}
.path-highlight {box-shadow: inset 0 0 10px 3px rgba(56, 193, 114, 0.7) !important;transition: box-shadow 0.1s ease-out, background-color 0.1s ease-out;}

/* Mode Selection Button Styles (réintégré) */
.mode-select-button {
    background: linear-gradient(145deg, rgba(40,38,58,0.9) 0%, rgba(27,27,45,0.9) 100%);
    border: 2px solid rgba(255,137,6,.4);
    color: #e0e0e0;
    padding: 2rem 1.5rem;
    border-radius: 0.75rem;
    text-align: center;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 220px;
}
.mode-select-button:hover:not(.btn-disabled) {
    transform: translateY(-5px) scale(1.03);
    border-color: rgba(255,137,6,.7);
    box-shadow: 0 10px 25px rgba(229,49,112,.25);
}
.mode-select-button i {
    font-size: 3rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg,#ff8906 0%,#e53170 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.mode-select-button h2 {
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
    color: #f0f0f0;
}
.mode-select-button p {
    font-size: 0.9rem;
    color: #a0a0b0;
    line-height: 1.4;
}
.btn-disabled.mode-select-button {
    opacity: 0.5;
    cursor: not-allowed;
    background: #2a2a3c;
    border-color: #444;
}
.btn-disabled.mode-select-button:hover { transform: none; box-shadow: none; }


.roadmap-container { display: flex; flex-wrap: nowrap; justify-content: flex-start; align-items: flex-start; gap: 2.5rem; position: relative; padding: 2rem 1rem; overflow-x: auto; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; margin-bottom: 2rem; }
.roadmap-container::-webkit-scrollbar { height: 8px; }
.roadmap-container::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 4px; }
.roadmap-container::-webkit-scrollbar-thumb { background: rgba(255,137,6,.5); border-radius: 4px; }
.roadmap-container::-webkit-scrollbar-thumb:hover { background: rgba(255,137,6,.7); }
.roadmap-item { background: #1e1e32e8; border: 1px solid rgba(255,137,6,.2); border-radius: 10px; padding: 1.5rem; width: 300px; text-align: center; position: relative; transition: transform 0.2s ease, box-shadow 0.2s ease; flex-shrink: 0; scroll-snap-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
.roadmap-item:not(.locked):hover { transform: translateY(-5px) scale(1.02); box-shadow: 0 8px 20px rgba(255,137,6,.2); }
.roadmap-item.locked { opacity:0.5; cursor:not-allowed; background: #1a1a2c; border-color: #333; }
.roadmap-item.locked:hover { transform: none; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
.roadmap-item .level-icon { font-size: 1.5rem; position: absolute; top: 12px; right: 12px; padding: 5px; background: rgba(0,0,0,0.2); border-radius: 50%; line-height: 1; }
.roadmap-item .level-icon .fa-lock { color: #e53170; }
.roadmap-item .level-icon .fa-unlock-alt { color: #ffae34; } 
.roadmap-item .level-icon .fa-check-circle { color: #4ade80; } 
.roadmap-item h3 { font-size: 1.6rem; margin-bottom: 0.5rem; color: #ffc107; }
.roadmap-item .chapter-number { font-size: 0.9rem; color: #888; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
.roadmap-item p { font-size: 0.85rem; color: #b0b0c0; margin-bottom: 0.3rem; }
.roadmap-item .stats-grid { display: grid; grid-template-columns: 1fr; gap: 0.3rem; margin-top:1rem; font-size: 0.8rem; text-align: left; padding: 0 0.5rem;}
.roadmap-item .stats-grid div { display: flex; justify-content: space-between; }
.roadmap-item .stats-grid strong { color: #ccc; } 
.roadmap-item .stats-grid span { color: #ffc107; font-weight: bold; } 
.roadmap-item .play-button { margin-top: 1.5rem; display: block; width: 100%; padding: 0.75rem 1rem; font-size: 1rem;}
.roadmap-item:not(:last-child)::after { content: ''; position: absolute; top: 50%; right: -2.25rem; width: 1.5rem; height: 3px; background: rgba(255,137,6,.3); transform: translateY(-50%); z-index: -1; }
.roadmap-item.locked:not(:last-child)::after { background: #444; }
.roadmap-item.completed:not(:last-child)::after { background: rgba(74, 222, 128, 0.5); }

.community-maps-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
.community-map-card { background: #1a182c; border: 1px solid rgba(170, 100, 255, 0.3); border-radius: 10px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s ease; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
.community-map-card:hover { transform: translateY(-5px) scale(1.02); box-shadow: 0 10px 25px rgba(170, 100, 255, 0.2); border-color: rgba(170, 100, 255, 0.6); }
.community-map-card-header { margin-bottom: 1rem; }
.community-map-card-header h3 { font-size: 1.6rem; color: #e0cfff; margin-bottom: 0.25rem; line-height: 1.2; }
.community-map-card-header .map-author { font-size: 0.9rem; color: #ffae34; }
.community-map-card-meta { display: flex; justify-content: space-between; font-size: 0.8rem; color: #a09cb8; margin-bottom: 1rem; border-top: 1px solid rgba(170, 100, 255, 0.15); padding-top: 0.75rem; }
.community-map-card-meta span { display: inline-flex; align-items: center; gap: 0.3rem; }
.community-map-card-meta i { color: #ff8906; }
.community-map-card-stats { font-size: 0.8rem; color: #b0aec6; margin-bottom: 1.2rem; }
.community-map-card-stats p { margin-bottom: 0.2rem; }
.community-map-card-stats strong { color: #d0cce0; }
.community-map-card-stats span { color: #e0cfff; font-weight: bold; }
.community-map-card .play-custom-btn { background: linear-gradient(135deg, #ab47bc 0%, #7b1fa2 100%); align-self: stretch; padding: 0.75rem; }
.community-map-card .play-custom-btn:hover { box-shadow: 0 6px 20px rgba(171, 71, 188, 0.4); }

.page-header-container { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; /* Reduced bottom margin */ }
.page-header-container h1 { margin-bottom: 0 !important; /* Remove default h1 margin */ }
.page-subtitle { text-align: center; color: #a0aec0; margin-bottom: 2.5rem; font-size: 0.95rem; margin-top: -0.25rem; /* Pull up slightly */ }

.back-button { display: inline-flex; align-items: center; padding: 0.6rem 1.2rem; font-size: 0.9rem; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 0.375rem; transition: background 0.2s ease; }
.back-button:hover { background: rgba(255,255,255,0.12); }

.story-intro-backdrop { position: fixed; inset: 0; background-color: rgba(0,0,0,0.9); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 1rem; animation: fadeIn 0.5s ease-out; }
.story-intro-content { background: #100e17 url('assets/images/scroll_background.png') center center / cover; background-blend-mode: overlay; color: #e0dac7; padding: 2rem 2.5rem; border-radius: 10px; border: 3px solid #5a3e2b; box-shadow: 0 0 30px rgba(0,0,0,0.7), inset 0 0 15px rgba(0,0,0,0.5); max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; text-align: center; animation: scaleUpBounce 0.6s ease-out; position: relative; }
.story-intro-content h1 { font-family: 'MedievalSharp', cursive; font-size: 2.8rem; color: #ffc107; text-shadow: 2px 2px 5px rgba(0,0,0,0.7); margin-bottom: 1rem; }
.story-intro-content .story-speaker { font-size: 1.1rem; color: #ff8906; margin-bottom: 1.5rem; font-style: italic; }
.story-text-container { display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem; text-align: left; line-height: 1.7; font-size: 1.1rem; }
@media (min-width: 768px) { .story-text-container { grid-template-columns: 1fr 1fr; } }
.story-text-container h3 { font-size: 1.3rem; color: #ffae34; margin-bottom: 0.75rem; border-bottom: 1px solid rgba(255,174,52,0.3); padding-bottom: 0.5rem; }
.story-text-container p { margin-bottom: 0.8rem; color: #d2c8b3; }
.story-skip-button { position: absolute; top: 1rem; right: 1rem; background: rgba(0,0,0,0.4); border: 1px solid #5a3e2b; padding: 0.4rem 0.8rem; font-size: 0.9rem; }
.story-play-button { padding: 0.8rem 2rem; font-size: 1.2rem; }
.story-intro-content::-webkit-scrollbar { width: 8px; }
.story-intro-content::-webkit-scrollbar-track { background: rgba(90,62,43,0.2); border-radius: 4px; }
.story-intro-content::-webkit-scrollbar-thumb { background: #5a3e2b; border-radius: 4px; }

.record-message { padding: 0.5rem 1rem; margin-top: 0.5rem; margin-bottom: 0.5rem; border-radius: 0.375rem; font-weight: bold; text-align: center; }
.record-time { background-color: rgba(56, 189, 248, 0.15); color: #7dd3fc; border: 1px solid rgba(56, 189, 248, 0.3); }
.record-moves { background-color: rgba(74, 222, 128, 0.15); color: #86efac; border: 1px solid rgba(74, 222, 128, 0.3); }
.record-both { background: linear-gradient(135deg, rgba(250, 204, 21, 0.2) 0%, rgba(245, 158, 11, 0.2) 100%); color: #fde047; border: 1px solid rgba(250, 204, 21, 0.4); }
</style>
</head>
<body class="min-h-screen flex flex-col">
<?php require __DIR__.'/includes/navbar.php'; ?>

<?php if($flash_messages): ?><div id="flash-container-php" class="fixed top-20 right-5 z-[100] w-full max-w-xs sm:max-w-sm">
    <?php foreach($flash_messages as $type => $messages) foreach($messages as $msg): ?>
    <div class="px-4 py-3 mb-2 rounded text-sm shadow-lg 
        <?= $type ==='error'?'bg-red-500/90 border border-red-700 text-white':'bg-emerald-500/90 border border-emerald-700 text-white' ?>
        <?= $type ==='info'?'!bg-sky-500/90 !border !border-sky-700 !text-white':'' ?>
        <?= $type ==='warning'?'!bg-amber-500/90 !border !border-amber-700 !text-white':'' ?>
        " role="alert">
        <strong class="font-bold"><?= ucfirst(h($type)) ?>!</strong>
        <span class="block sm:inline"><?=h($msg)?></span>
    </div>
<?php endforeach; ?></div>
<script>
    setTimeout(() => {
        const flashContainer = document.getElementById('flash-container-php');
        if(flashContainer) {
            flashContainer.style.transition = 'opacity 0.5s ease-out';
            flashContainer.style.opacity = '0';
            setTimeout(() => flashContainer.remove(), 500);
        }
    }, 4500);
</script>
<?php endif; ?>
<div id="flash-container-manual" class="fixed top-20 right-5 z-[100] w-full max-w-xs sm:max-w-sm"></div>


<div class="container mx-auto py-10 px-4 flex-grow">

<?php if ($mode === 'select_initial_mode'): ?>
    <h1 class="text-4xl font-bold text-grad mb-12 text-center"><i class="fas fa-dungeon mr-3"></i>Choose Your Path</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
        <a href="#" class="mode-select-button btn-disabled" onclick="event.preventDefault(); showManualFlash('info', 'Random Labyrinth is coming soon!');">
            <i class="fas fa-dice-d20"></i>
            <h2>Random Labyrinth</h2>
            <p>Venture into an unpredictably generated dungeon. (Coming Soon)</p>
        </a>
        <a href="play.php?mode=select_base_level" class="mode-select-button">
            <i class="fas fa-book-dead"></i>
            <h2>Story Chapters</h2>
            <p>Follow the ancient prophecies through pre-defined challenges.</p>
        </a>
        <a href="play.php?mode=select_custom_level" class="mode-select-button">
            <i class="fas fa-scroll"></i>
            <h2>Community Realms</h2>
            <p>Explore labyrinths crafted by fellow adventurers.</p>
        </a>
    </div>

<?php elseif ($mode === 'select_base_level'): ?>
    <div class="page-header-container">
        <h1 class="text-4xl font-bold text-grad"><i class="fas fa-map-signs mr-3"></i>Story Chapters</h1>
        <a href="play.php?mode=select_initial_mode" class="back-button"><i class="fas fa-arrow-left mr-2"></i>Back to Modes</a>
    </div>
    <p class="page-subtitle">Embark on the main quest. Complete chapters to unlock the next.</p>
    
    <?php if (empty($base_levels_data)): ?>
        <p class="text-gray-400 text-center panel p-6">No base levels available at the moment.</p>
    <?php else: ?>
        <div class="roadmap-container">
            <?php foreach($base_levels_data as $level):
                $level_id = (int)$level['id'];
                $is_completed = $level_id <= $current_user_level_completed;
                $is_unlocked = $level_id <= $current_user_level_completed + 1;
                $is_locked = !$is_unlocked;
                $item_class = $is_locked ? 'locked' : ($is_completed ? 'completed' : 'unlocked');
            ?>
            <div class="roadmap-item <?= $item_class ?>">
                <div class="level-icon">
                    <?php if ($is_locked): ?> <i class="fas fa-lock" title="Locked"></i>
                    <?php elseif ($is_completed): ?> <i class="fas fa-check-circle" title="Completed"></i>
                    <?php else: ?> <i class="fas fa-unlock-alt" title="Unlocked"></i>
                    <?php endif; ?>
                </div>
                <h3><?= h($level['name']) ?></h3>
                <p class="chapter-number">Chapter <?= $level_id ?></p>
                <?php if($level['difficulty']): ?><p><strong>Difficulty:</strong> <?= (int)$level['difficulty'] ?>/10</p><?php endif; ?>
                <p><strong>Played:</strong> <?= (int)$level['map_game_count'] ?> times</p>
                <div class="stats-grid">
                    <div><strong>Best Time:</strong> <span><?= ($level['best_player_time'] > 0 && $level['best_player_time_name'] !== 'none' && $level['best_player_time_name'] !== null) ? h($level['best_player_time']).'s ('.h($level['best_player_time_name']).')' : 'N/A' ?></span></div>
                    <div><strong>Best Moves:</strong> <span><?= ($level['best_player_moves'] < 999 && $level['best_player_moves'] > 0 && $level['best_player_moves_name'] !== 'none' && $level['best_player_moves_name'] !== null) ? h($level['best_player_moves']).' ('.h($level['best_player_moves_name']).')' : 'N/A' ?></span></div>
                </div>
                <?php if ($is_unlocked): ?>
                    <a href="play.php?mode=play&level=<?= $level_id ?>" class="btn play-button"><i class="fas fa-play mr-2"></i><?= $is_completed ? 'Replay Chapter' : 'Enter Chapter' ?></a>
                <?php else: ?>
                    <button class="btn play-button btn-disabled" disabled><i class="fas fa-lock mr-2"></i>Locked</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($mode === 'select_custom_level'): ?>
    <div class="page-header-container">
        <h1 class="text-4xl font-bold text-grad"><i class="fas fa-scroll mr-3"></i>Community Realms</h1>
        <a href="play.php?mode=select_initial_mode" class="back-button"><i class="fas fa-arrow-left mr-2"></i>Back to Modes</a>
    </div>
    <p class="page-subtitle">Discover unique challenges created by other players.</p>
    <form method="GET" action="play.php" class="mb-8 max-w-lg mx-auto">
        <input type="hidden" name="mode" value="select_custom_level">
        <div class="flex"><input type="text" name="search_map_name" placeholder="Search by map name..." value="<?= h($search_query) ?>" class="input-field flex-grow mr-2"><button type="submit" class="btn p-2 px-4"><i class="fas fa-search"></i></button></div>
    </form>
    <?php if (empty($user_maps_data)): ?>
        <p class="text-gray-400 text-center panel p-6 no-hover-effect"><?= $search_query ? 'No maps found for "'.h($search_query).'".' : 'No community maps found yet. <a href="create_map.php" class="text-amber-400 hover:underline">Why not create one?</a>' ?></p>
    <?php else: ?>
        <div class="community-maps-grid">
            <?php foreach($user_maps_data as $map): ?>
            <div class="community-map-card">
                <div>
                    <div class="community-map-card-header">
                        <h3><?= h($map['name']) ?></h3>
                        <p class="map-author">By: <?= h($map['author_name'] ?: 'Unknown Creator') ?></p>
                    </div>
                    <div class="community-map-card-meta">
                        <span><i class="fas fa-arrows-alt"></i> <?= (int)$map['size'] ?>x<?= (int)$map['size'] ?></span>
                        <span><i class="fas fa-tachometer-alt"></i> <?= ($map['difficulty'] ? (int)$map['difficulty'].'/10' : 'N/A') ?></span>
                        <span><i class="fas fa-gamepad"></i> <?= (int)$map['map_game_count'] ?> plays</span>
                    </div>
                    <div class="community-map-card-stats">
                        <p><strong>Best Time:</strong> <span><?= ($map['best_player_time'] > 0 && $map['best_player_time_name'] !== 'none' && $map['best_player_time_name'] !== null) ? h($map['best_player_time']).'s ('.h($map['best_player_time_name']).')' : 'N/A' ?></span></p>
                        <p><strong>Best Moves:</strong> <span><?= ($map['best_player_moves'] < 999 && $map['best_player_moves'] > 0 && $map['best_player_moves_name'] !== 'none' && $map['best_player_moves_name'] !== null) ? h($map['best_player_moves']).' ('.h($map['best_player_moves_name']).')' : 'N/A' ?></span></p>
                    </div>
                </div>
                <a href="play.php?mode=play&level=custom_<?= (int)$map['id'] ?>" class="btn play-custom-btn"><i class="fas fa-play mr-2"></i>Enter Realm</a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($mode === 'random_options'): ?>
    <div class="page-header-container"><h1 class="text-4xl font-bold text-grad"><i class="fas fa-cogs mr-3"></i>Random Labyrinth</h1><a href="play.php?mode=select_initial_mode" class="back-button"><i class="fas fa-arrow-left mr-2"></i>Back to Modes</a></div>
    <p class="page-subtitle">Configure your randomly generated adventure.</p>
    <div class="panel max-w-md mx-auto p-8 no-hover-effect">
        <p class="text-xl text-center text-gray-300"><i class="fas fa-tools text-2xl mr-2 text-amber-400"></i>Random labyrinth generation is under active development!</p>
        <p class="text-center mt-6"><a href="play.php?mode=select_initial_mode" class="btn"><i class="fas fa-arrow-left mr-2"></i>Return to Mode Selection</a></p>
    </div>

<?php elseif ($mode === 'play_story_intro' && $story_level_to_intro && isset($story_content[$story_level_to_intro])): 
    $story = $story_content[$story_level_to_intro];
?>
    <div class="story-intro-backdrop" id="storyBackdrop">
        <div class="story-intro-content">
            <button id="skipStoryBtn" class="btn story-skip-button"><i class="fas fa-forward mr-1"></i> Skip</button>
            <h1>Chapter <?= $story_level_to_intro ?>: The Tale Unfolds...</h1>
            <p class="story-speaker">Narrated by: <?= h($story['speaker']) ?></p>
            
            <div class="story-text-container">
                <div>
                    <h3><i class="fas fa-scroll mr-2"></i> English Narration</h3>
                    <?php foreach($story['en'] as $paragraph): ?><p><?= nl2br(h($paragraph)) ?></p><?php endforeach; ?>
                </div>
                <div>
                    <h3><i class="fas fa-language mr-2"></i> Traduction Française</h3>
                     <?php foreach($story['fr'] as $paragraph): ?><p><?= nl2br(h($paragraph)) ?></p><?php endforeach; ?>
                </div>
            </div>
            <button id="playStoryAudioBtn" class="btn story-play-button"><i class="fas fa-volume-up mr-2"></i> Listen to the Story</button>
            <audio id="storyAudioPlayer" src="<?= h($story['audio']) ?>"></audio>
        </div>
    </div>
    <script>
        const storyAudio = document.getElementById('storyAudioPlayer');
        const playBtn = document.getElementById('playStoryAudioBtn');
        const skipBtn = document.getElementById('skipStoryBtn');
        const levelToPlay = <?= $story_level_to_intro ?>;

        function proceedToGame() {
            if (storyAudio) { storyAudio.pause(); storyAudio.currentTime = 0; }
            window.location.href = `play.php?mode=play&level=${levelToPlay}`;
        }
        if(playBtn) playBtn.addEventListener('click', () => {
            if (storyAudio.paused) {
                storyAudio.play().catch(e => console.error("Audio play failed:", e));
                playBtn.innerHTML = '<i class="fas fa-pause mr-2"></i> Pause Narration';
            } else {
                storyAudio.pause();
                playBtn.innerHTML = '<i class="fas fa-volume-up mr-2"></i> Resume Narration';
            }
        });
        if(storyAudio) storyAudio.onended = () => {
            if(playBtn) playBtn.innerHTML = '<i class="fas fa-check mr-2"></i> Narration Finished. Play Again or Skip.';
        };
        if(skipBtn) skipBtn.addEventListener('click', proceedToGame);
    </script>

<?php elseif($mode === 'play' && $map_to_load):
  $current_map_id = (int)$map_to_load['id'];
  $current_map_size = (int)$map_to_load['size'];
  // MAP_CHARS will be initialized in JS from MAP_CHARS_ORIGINAL
?>
  <div class="text-center">
      <h1 class="text-4xl font-bold text-grad mb-1"><i class="fas fa-map-signs mr-3"></i><?=h($map_to_load['name'])?></h1>
      <p class="text-gray-400 text-sm mb-1">
          <?php if ($map_to_load['author_name']): ?>Crafted by: <span class="text-amber-300"><?= h($map_to_load['author_name']) ?></span> |
          <?php elseif ($map_type_playing === 'base'): ?>An Ancient Chapter |<?php endif; ?>
          Size: <?= $current_map_size ?>x<?= $current_map_size ?>
          <?php if($map_to_load['difficulty']): ?> | Difficulty: <?= (int)$map_to_load['difficulty'] ?>/10 <?php endif; ?>
      </p>
      <p class="text-gray-400 text-xs mb-3">Ventured <?= (int)$map_to_load['map_game_count'] ?> times</p>
      <?php if( ($map_to_load['best_player_time'] > 0 && $map_to_load['best_player_time_name'] && $map_to_load['best_player_time_name'] !== 'none' && $map_to_load['best_player_time_name'] !== null) || ($map_to_load['best_player_moves'] < 999 && $map_to_load['best_player_moves'] > 0 && $map_to_load['best_player_moves_name'] && $map_to_load['best_player_moves_name'] !== 'none' && $map_to_load['best_player_moves_name'] !== null) ): ?>
      <div class="text-xs text-sky-300 mb-4">
          Best Time: <span class="font-bold"><?= ($map_to_load['best_player_time'] > 0 && $map_to_load['best_player_time_name'] && $map_to_load['best_player_time_name'] !== 'none' && $map_to_load['best_player_time_name'] !== null) ? h($map_to_load['best_player_time']).'s (by '.h($map_to_load['best_player_time_name']).')' : 'N/A' ?></span>
          <span class="mx-2">|</span>
          Best Moves: <span class="font-bold"><?= ($map_to_load['best_player_moves'] < 999 && $map_to_load['best_player_moves'] > 0 && $map_to_load['best_player_moves_name'] && $map_to_load['best_player_moves_name'] !== 'none' && $map_to_load['best_player_moves_name'] !== null) ? h($map_to_load['best_player_moves']).' (by '.h($map_to_load['best_player_moves_name']).')' : 'N/A' ?></span>
      </div><?php endif; ?>
  </div>
  <div class="text-center mb-6"><button id="startBtn" class="btn text-lg px-8 py-3"><i class="fas fa-play-circle mr-2"></i>Start Playing</button></div>
  <div id="hud" class="flex flex-wrap gap-x-4 gap-y-3 mb-4 items-center justify-center hidden">
    <div class="panel p-2 px-3 text-sm no-hover-effect">Moves: <span id="movesDisplay" class="font-bold text-amber-300">0</span></div>
    <div class="panel p-2 px-3 text-sm no-hover-effect">Time: <span id="timeDisplay" class="font-bold text-amber-300">0s</span></div>
    <button id="zoomInBtn" class="btn btn-sm p-2 px-2" title="Zoom In"><i class="fas fa-search-plus"></i></button>
    <button id="zoomOutBtn" class="btn btn-sm p-2 px-2" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
    <?php if ($map_type_playing === 'custom'): ?>
    <button id="hintBtn" class="btn btn-sm p-2 px-2" style="background:linear-gradient(135deg,#17a2b8 0%,#106c7a 100%);" title="Hint"><i class="fas fa-lightbulb mr-1"></i>Hint</button>
    <button id="giveUpBtn" class="btn btn-sm p-2 px-2" style="background:linear-gradient(135deg,#c82333 0%,#a01010 100%);" title="Give Up"><i class="fas fa-flag-checkered mr-1"></i>Give Up</button>
    <?php endif; ?>
  </div>
  <div id="gridWrapper" class="grid-wrap hidden"><div id="gameGrid" class="grid"></div></div>

  <script>
  const MAP_ID = <?= $current_map_id ?>;
  const MAP_TYPE = '<?= $map_type_playing ?>'; 
  const MAP_SIZE = <?= $current_map_size ?>;
  const MAP_CHARS_ORIGINAL = Object.freeze([...<?= json_encode(str_split(str_replace(';','',$map_to_load['map_value']))) ?>]);
  let MAP_CHARS_CURRENT = [...MAP_CHARS_ORIGINAL]; // Mutable copy for doors
  const USER_NAME = <?= json_encode($user_name) ?>;
  const IS_CUSTOM_MAP = <?= $map_type_playing === 'custom' ? 'true' : 'false' ?>;

  const AUDIO_FILES = {
      collision: <?= json_encode(glob('assets/audios/collision/*.mp3') ?: []) ?>,
      victory: <?= json_encode(glob('assets/audios/victoire/*.mp3') ?: []) ?>,
      giveUp: <?= json_encode(glob('assets/audios/giveup/*.mp3') ?: []) ?>,
      ambiance: <?= json_encode(glob('assets/audios/ambiance/*.mp3') ?: []) ?>,
      // Add a door toggle sound if you have one:
      // door_toggle: ['assets/audios/effects/door_creak.mp3'] 
  };
  let logicalGrid = [], currentTilePx = 30, playerPos = { x:0, y:0 }, startPos = { x:0, y:0 }, exitPos = { x:0, y:0 };
  let moveCounter = 0, timeElapsedSec = 0, gameTimerId, isGameActive = false;
  let playerDivElement, ambianceSound;
  let hintRevealed = false;

  const gameGridElement = document.getElementById('gameGrid');
  const startButtonElement = document.getElementById('startBtn');
  const hudElement = document.getElementById('hud');
  const gridWrapperElement = document.getElementById('gridWrapper');
  const movesDisplayElement = document.getElementById('movesDisplay');
  const timeDisplayElement = document.getElementById('timeDisplay');

  const TILE_WALL = 0, TILE_PATH = 1, TILE_START = 2, TILE_EXIT = 3, TILE_BUTTON = 4, TILE_DOOR_C = 5, TILE_DOOR_O = 6;
  const TILE_CLASSES = ['wall','path','start','exit','button-tile','doorC','doorO'];
  const IS_TILE_PASSABLE = tileValue => ![TILE_WALL, TILE_DOOR_C].includes(tileValue);

  const getRandomAudioPath = (type) => AUDIO_FILES[type] ? AUDIO_FILES[type][Math.floor(Math.random() * AUDIO_FILES[type].length)] : null;
  const playSoundEffect = (typeKey, volume = 0.7, loop = false) => {
      const path = getRandomAudioPath(typeKey);
      if (!path) return null;
      const audio = new Audio(path);
      audio.volume = volume;
      audio.loop = loop;
      audio.play().catch(e => console.warn("Audio play prevented for " + typeKey + ":", e));
      return audio;
  };

  function updateCellAppearance(x, y, newTileValue) {
    const cellIndex = y * MAP_SIZE + x;
    const cellDiv = gameGridElement.children[cellIndex];
    if (cellDiv) {
        TILE_CLASSES.forEach(cls => cellDiv.classList.remove(cls));
        cellDiv.classList.add(TILE_CLASSES[newTileValue]);
    }
  }

  function toggleAllDoors() {
    for(let r = 0; r < MAP_SIZE; r++) {
      for(let c = 0; c < MAP_SIZE; c++) {
        const currentIndex = r * MAP_SIZE + c;
        const originalTileInMapChars = +MAP_CHARS_ORIGINAL[currentIndex]; // Check original map def

        if (originalTileInMapChars === TILE_DOOR_C || originalTileInMapChars === TILE_DOOR_O) { // Only toggle if it was originally a door
            if (logicalGrid[r][c] === TILE_DOOR_C) {
                logicalGrid[r][c] = TILE_DOOR_O;
                MAP_CHARS_CURRENT[currentIndex] = String(TILE_DOOR_O); // Update current working map
                updateCellAppearance(c, r, TILE_DOOR_O);
            } else if (logicalGrid[r][c] === TILE_DOOR_O) {
                logicalGrid[r][c] = TILE_DOOR_C;
                MAP_CHARS_CURRENT[currentIndex] = String(TILE_DOOR_C);
                updateCellAppearance(c, r, TILE_DOOR_C);
            }
        }
      }
    }
    // playSoundEffect('door_toggle', 0.4); 
  }

  function buildGameGrid() {
    MAP_CHARS_CURRENT = [...MAP_CHARS_ORIGINAL]; // Reset current map to original state (doors closed/open as defined)
    gameGridElement.innerHTML = ''; 
    gameGridElement.style.gridTemplateColumns = `repeat(${MAP_SIZE}, ${currentTilePx}px)`;
    gameGridElement.style.gridTemplateRows = `repeat(${MAP_SIZE}, ${currentTilePx}px)`;
    logicalGrid = []; 
    for(let r = 0; r < MAP_SIZE; r++) {
      logicalGrid[r] = [];
      for(let c = 0; c < MAP_SIZE; c++) {
        const tileValue = +MAP_CHARS_CURRENT[r * MAP_SIZE + c];
        logicalGrid[r][c] = tileValue;
        const cellDiv = document.createElement('div');
        cellDiv.className = 'cell ' + TILE_CLASSES[tileValue];
        cellDiv.style.width = cellDiv.style.height = currentTilePx + 'px';
        gameGridElement.appendChild(cellDiv);
        if(tileValue === TILE_START) { startPos = { x: c, y: r }; }
        if(tileValue === TILE_EXIT) { exitPos = { x: c, y: r }; }
      }
    }
  }

  function initializeAndStartGame() {
    startButtonElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
    startButtonElement.disabled = true;
    setTimeout(() => {
        hudElement.classList.remove('hidden');
        gridWrapperElement.classList.remove('hidden');
        buildGameGrid(); 
        playerDivElement = document.createElement('div');
        playerDivElement.className = 'cell player'; 
        playerDivElement.style.width = playerDivElement.style.height = currentTilePx + 'px';
        playerPos = { ...startPos };
        renderPlayerPosition();    
        isGameActive = true;
        moveCounter = 0; timeElapsedSec = 0; hintRevealed = false;
        if (IS_CUSTOM_MAP) {
            const hintBtn = document.getElementById('hintBtn');
            if (hintBtn) { hintBtn.disabled = false; hintBtn.style.opacity = 1; }
        }
        movesDisplayElement.textContent = moveCounter;
        timeDisplayElement.textContent = timeElapsedSec + 's';
        if (gameTimerId) clearInterval(gameTimerId);
        gameTimerId = setInterval(() => { if(isGameActive) timeDisplayElement.textContent = ++timeElapsedSec + 's'; }, 1000);
        if (ambianceSound) ambianceSound.pause(); 
        ambianceSound = playSoundEffect('ambiance', 0.20, true); 
        startButtonElement.style.display = 'none'; 
    }, 150);
  }
  function renderPlayerPosition() {
    const targetCell = gameGridElement.children[playerPos.y * MAP_SIZE + playerPos.x];
    if (targetCell) {
        if(playerDivElement.parentNode) playerDivElement.parentNode.removeChild(playerDivElement);
        targetCell.appendChild(playerDivElement);
    }
  }

  function handlePlayerMove(dx, dy) {
    if (!isGameActive || !playerDivElement) return;
    const nextX = playerPos.x + dx;
    const nextY = playerPos.y + dy;
    playerDivElement.classList.remove('player-collide', 'player-victory', 'player-giveup');

    if (nextX >= 0 && nextY >= 0 && nextX < MAP_SIZE && nextY < MAP_SIZE) {
        const nextTileValueInLogic = logicalGrid[nextY][nextX]; // Check the current state of the logical grid
        if (IS_TILE_PASSABLE(nextTileValueInLogic)) {
            playerPos.x = nextX; playerPos.y = nextY;
            moveCounter++;
            movesDisplayElement.textContent = moveCounter;
            renderPlayerPosition();

            const originalTileAtPlayerPos = +MAP_CHARS_ORIGINAL[nextY * MAP_SIZE + nextX]; // What the tile is defined as in the map string
            if (originalTileAtPlayerPos === TILE_BUTTON) {
                toggleAllDoors();
            }

            if (playerPos.x === exitPos.x && playerPos.y === exitPos.y) {
                playerDivElement.classList.add('player-victory');
                triggerGameEnd(true, MAP_TYPE === 'base' ? 'Chapter Complete!' : 'Realm Conquered!');
            }
        } else {
            playSoundEffect('collision', 0.4); 
            playerDivElement.classList.add('player-collide');
            setTimeout(() => playerDivElement.classList.remove('player-collide'), 300); 
        }
    } else { 
        playSoundEffect('collision', 0.4); 
        playerDivElement.classList.add('player-collide');
        setTimeout(() => playerDivElement.classList.remove('player-collide'), 300); 
    }
  }

  function adjustZoomLevel(delta) { if (!playerDivElement) return; const newTilePx = Math.min(70, Math.max(18, currentTilePx + delta)); if (newTilePx === currentTilePx) return; currentTilePx = newTilePx; playerDivElement.style.width = playerDivElement.style.height = currentTilePx + 'px'; buildGameGrid(); renderPlayerPosition(); }
  document.getElementById('zoomInBtn').onclick = () => adjustZoomLevel(5);
  document.getElementById('zoomOutBtn').onclick = () => adjustZoomLevel(-5);

  if (IS_CUSTOM_MAP) { const giveUpBtn = document.getElementById('giveUpBtn'); const hintBtn = document.getElementById('hintBtn'); if (giveUpBtn) giveUpBtn.onclick = () => { if (!isGameActive || !playerDivElement) return; revealSolutionPath(); playerDivElement.classList.add('player-giveup'); triggerGameEnd(false, 'Realm Abandoned'); }; if (hintBtn) hintBtn.onclick = () => { if (!isGameActive || hintRevealed) return; revealSolutionPath(); hintRevealed = true; hintBtn.disabled = true; hintBtn.style.opacity = 0.7; }; }
  
  function findPathBFS() { const queue = [[startPos.x, startPos.y, []]]; const visited = new Set([`${startPos.x},${startPos.y}`]); const DIRS = [[0, -1], [0, 1], [-1, 0], [1, 0]]; while (queue.length) { const [cx, cy, path] = queue.shift(); if (cx === exitPos.x && cy === exitPos.y) return path.concat([[cx,cy]]); for (const [dx, dy] of DIRS) { const nx = cx + dx; const ny = cy + dy; const nextKey = `${nx},${ny}`; if (nx >= 0 && ny >= 0 && nx < MAP_SIZE && ny < MAP_SIZE && !visited.has(nextKey) && IS_TILE_PASSABLE(logicalGrid[ny][nx])) { visited.add(nextKey); const newPath = path.concat([[cx,cy]]); queue.push([nx, ny, newPath]); }}} return null; }
  function revealSolutionPath() { const solutionPath = findPathBFS(); if (solutionPath) { solutionPath.forEach(([px, py], index) => { if (!((px === startPos.x && py === startPos.y) || (px === exitPos.x && py === exitPos.y) || (playerDivElement && px === playerPos.x && py === playerPos.y))) { const cellDiv = gameGridElement.children[py * MAP_SIZE + px]; if (cellDiv) { setTimeout(() => cellDiv.classList.add('path-highlight'), index * 25);}}});}}

  async function triggerGameEnd(isVictory, titleText) {
    isGameActive = false; clearInterval(gameTimerId); if(ambianceSound) { ambianceSound.pause(); ambianceSound.currentTime = 0; }
    if (isVictory) playSoundEffect('victory', 0.6); else playSoundEffect('giveUp', 0.5);
    const formData = new FormData(); formData.append('map_id', MAP_ID); formData.append('map_type', MAP_TYPE); formData.append('time_taken', timeElapsedSec); formData.append('moves_made', moveCounter); formData.append('is_victory', isVictory ? '1' : '0'); formData.append('used_hint', hintRevealed ? '1' : '0');
    let recordMessages = [];
    try { const response = await fetch('ajax_save_score.php', { method: 'POST', body: formData }); const resultText = await response.text(); try { const result = JSON.parse(resultText); if (result.success) { console.log('Score saved.'); if (result.new_best_time) recordMessages.push({type: 'time', text: "🏆 New Best Time Record!"}); if (result.new_best_moves) recordMessages.push({type: 'moves', text: "✨ New Fewest Moves Record!"}); } else { console.error('Failed to save score:', result.error || 'Unknown. Raw:', resultText); } } catch (e) { console.error('Error parsing JSON (ajax_save_score):', e, "Raw:", resultText); } } catch (error) { console.error('Network error saving score:', error); }
    const modalOverlayDiv = document.createElement('div'); modalOverlayDiv.className = 'fixed inset-0 bg-black/80 flex items-center justify-center z-50 modal-backdrop p-4';
    let modalHTML = `<div class="panel p-6 md:p-8 text-center modal-content w-full max-w-md"><h2 class="text-3xl ${isVictory ? 'text-green-400' : 'text-red-500'} mb-3 font-bold"><i class="fas ${isVictory ? 'fa-trophy' : (titleText.includes('Abandoned') ? 'fa-flag' : 'fa-skull-crossbones')} mr-2"></i>${titleText}</h2>`;
    if (isVictory && recordMessages.length > 0) { recordMessages.forEach(msg => { let RmsgClass = 'record-both'; if (recordMessages.length === 1) RmsgClass = msg.type === 'time' ? 'record-time' : 'record-moves'; modalHTML += `<p class="record-message ${RmsgClass} mb-1">${msg.text}</p>`; });}
    modalHTML += `<p class="mb-1 text-gray-300 text-lg">Moves: <b class="text-amber-300">${moveCounter}</b></p><p class="mb-6 text-gray-300 text-lg">Time: <b class="text-amber-300">${timeElapsedSec}s</b></p>`;
    if (isVictory && MAP_TYPE === 'base' && MAP_ID < 4) modalHTML += `<a href="play.php?mode=play&level=${MAP_ID + 1}" class="btn text-base mb-3 w-full sm:w-auto sm:mr-2"><i class="fas fa-arrow-right mr-2"></i>Next Chapter</a>`;
    modalHTML += `<a href="play.php?mode=${MAP_TYPE === 'base' ? 'select_base_level' : 'select_custom_level'}" class="btn text-base w-full sm:w-auto" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);"><i class="fas ${MAP_TYPE === 'base' ? 'fa-map-signs' : 'fa-scroll'} mr-2"></i>Back to Selection</a><a href="play.php?mode=select_initial_mode" class="block mt-4 text-sm text-gray-400 hover:text-amber-300">Return to Main Menu</a></div>`;
    modalOverlayDiv.innerHTML = modalHTML; document.body.appendChild(modalOverlayDiv); modalOverlayDiv.onclick = (e) => { if (e.target === modalOverlayDiv) modalOverlayDiv.remove(); }
  }

  document.onkeydown = event => { if (!isGameActive || !playerDivElement) return; let moved = false; switch(event.key.toLowerCase()) { case 'arrowup': case 'w': handlePlayerMove(0, -1); moved = true; break; case 'arrowdown': case 's': handlePlayerMove(0, 1); moved = true; break; case 'arrowleft': case 'a': handlePlayerMove(-1, 0); moved = true; break; case 'arrowright': case 'd': handlePlayerMove(1, 0); moved = true; break; } if (moved) event.preventDefault(); };
  startButtonElement.onclick = initializeAndStartGame;
  
  function showManualFlash(type, message) { const container = document.getElementById('flash-container-manual'); if (!container) return; let bgColor = 'bg-sky-500/90 border-sky-700'; if (type === 'success') bgColor = 'bg-emerald-500/90 border-emerald-700'; if (type === 'error') bgColor = 'bg-red-500/90 border-red-700'; if (type === 'warning') bgColor = 'bg-amber-500/90 border-amber-700'; const flashDiv = document.createElement('div'); flashDiv.className = `px-4 py-3 mb-2 rounded text-sm shadow-lg text-white border ${bgColor} opacity-0 transition-opacity duration-300 ease-out`; flashDiv.setAttribute('role', 'alert'); flashDiv.innerHTML = `<strong class="font-bold">${type.charAt(0).toUpperCase() + type.slice(1)}!</strong> <span class="block sm:inline">${message}</span>`; container.appendChild(flashDiv); setTimeout(() => flashDiv.style.opacity = '1', 50); setTimeout(() => { flashDiv.style.opacity = '0'; setTimeout(() => flashDiv.remove(), 300); }, 4700); }
  </script>
<?php else: ?>
    <div class="panel p-8 text-center no-hover-effect">
        <h2 class="text-2xl text-red-400 mb-4">Oops! Something Went Astray</h2>
        <p class="text-gray-300 mb-6">Could not load the requested game mode or specified map.</p>
        <p class="text-xs text-gray-500">Current mode: <?= h($mode) ?></p>
        <?php if (isset($_GET['level'])): ?> <p class="text-xs text-gray-500">Level param: <?= h($_GET['level']) ?></p> <?php endif; ?>
        <a href="play.php?mode=select_initial_mode" class="btn mt-6"><i class="fas fa-compass mr-2"></i>Return to Portal</a>
    </div>
<?php endif; ?>
</div>
<footer class="py-6 px-4 border-t border-gray-800/50 text-center mt-auto">
    <p class="text-gray-500 text-sm">© <?= date('Y') ?> Mystical Dungeons. All rights reserved.</p>
</footer>
</body></html>