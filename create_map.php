<?php
/* ------------------------------------------------------------------
 * create_map.php – Mystical Dungeons map builder (v6.2 2025-05-24) - With Edit Mode & Zoom
 * ----------------------------------------------------------------- */
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/helpers.php';

if (!is_logged_in()) redirect('login-signup.php');
$user   = current_user();
$userId = $user['id'];

$editing_map_id = null;
$current_map_data = null;
$page_title_action = "Create";
$submit_button_text = "Save Dungeon";

if (isset($_GET['map_id'])) {
    $editing_map_id = (int)$_GET['map_id'];
    $stmt = $pdo->prepare('SELECT * FROM Map WHERE id = ? AND user_id = ?');
    $stmt->execute([$editing_map_id, $userId]);
    $current_map_data = $stmt->fetch();

    if ($current_map_data) {
        $page_title_action = "Edit";
        $submit_button_text = "Update Dungeon";
    } else {
        flash('error', 'Map not found or you do not have permission to edit it.');
        redirect('create_map.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_map'])) {
    $name   = trim($_POST['map_name']??'');
    $size   = (int) ($_POST['size']??0);
    $matrix = trim($_POST['matrix']??'');
    $posted_map_id = isset($_POST['map_id']) && !empty($_POST['map_id']) ? (int)$_POST['map_id'] : null;

    $redirect_url_on_error = 'create_map.php?map_value=' . urlencode($matrix) . '&map_name=' . urlencode($name);
    if ($posted_map_id) {
        $redirect_url_on_error .= '&map_id=' . $posted_map_id;
    }

    if($name===''||$size<3||$size>150){flash('error','Invalid map data (name required, size 3-150).');redirect($redirect_url_on_error);exit;}
    $rows = explode(';',$matrix);
    if(count($rows)!==$size){flash('error','Row count mismatch with size.');redirect($redirect_url_on_error);exit;}
    foreach($rows as $r_idx => $r_val){
        if(strlen($r_val)!==$size){flash('error',"Row ".($r_idx+1)." length mismatch.");redirect($redirect_url_on_error);exit;}
        if(preg_match('/[^0-6]/',$r_val)){ flash('error',"Row ".($r_idx+1)." contains invalid characters (only 0-6 allowed)."); redirect($redirect_url_on_error); exit;}
    }

    $cornerVals = [$rows[0][0], $rows[0][$size-1], $rows[$size-1][0], $rows[$size-1][$size-1]];
    if(in_array('2',$cornerVals) || in_array('3',$cornerVals)){
        flash('error','Start/Exit cannot be in a corner.'); redirect($redirect_url_on_error); exit;
    }

    $is_problem_tile = function($v) { return in_array($v, ['1','2','3','4','5','6']); };
    for ($i = 0; $i < $size - 1; $i++) {
        for ($j = 0; $j < $size - 1; $j++) {
            $val1 = $rows[$i][$j]; $val2 = $rows[$i][$j+1];
            $val3 = $rows[$i+1][$j]; $val4 = $rows[$i+1][$j+1];
            if ($is_problem_tile($val1) && $is_problem_tile($val2) && $is_problem_tile($val3) && $is_problem_tile($val4)) {
                 flash('error', 'Map cannot contain 2x2 squares of paths, doors, buttons, start, or exit points.');
                 redirect($redirect_url_on_error); exit;
            }
        }
    }

    $ver = (int) shell_exec('"'.__DIR__.'/algos/verif.exe" '.$size.' "'.$matrix.'"');
    if($ver!==1){ flash('error','No valid path between Start and Exit. The map might be unsolvable.'); redirect($redirect_url_on_error); exit; }

    $diff = (int) shell_exec('"'.__DIR__.'/algos/difficulte.exe" "'.$matrix.'" '.$size);

    if ($posted_map_id) {
        $stmt_check = $pdo->prepare('SELECT user_id FROM Map WHERE id = ?');
        $stmt_check->execute([$posted_map_id]);
        $map_owner = $stmt_check->fetchColumn();
        if ($map_owner == $userId) {
            $stmt = $pdo->prepare('UPDATE Map SET name = ?, map_value = ?, size = ?, difficulty = ?, creation_date = NOW()
                                   WHERE id = ? AND user_id = ?');
            $stmt->execute([$name, $matrix, $size, $diff, $posted_map_id, $userId]);
            flash('success','Dungeon updated successfully!');
            redirect('create_map.php?map_id=' . $posted_map_id);
        } else {
            flash('error','Error updating map. Permission denied or map not found.');
            redirect($redirect_url_on_error);
        }
    } else {
        $pdo->prepare('INSERT INTO Map (user_id,name,creation_date,map_value,size,
                        map_game_count,difficulty,best_player_time,best_player_moves,
                        best_player_time_name,best_player_moves_name)
                       VALUES (?,?,NOW(),?, ?,0,?,0,999,"none","none")')
            ->execute([$userId,$name,$matrix,$size,$diff]);
        flash('success','Dungeon saved!');
        redirect('create_map.php');
    }
    exit;
}

$flashes = get_flashes();
$preset  = [8,10,15,20,30,50];

$initial_map_value_js = "null";
$initial_map_name_js = "null";

if ($current_map_data) {
    $initial_map_value_js = json_encode($current_map_data['map_value']);
    $initial_map_name_js = json_encode($current_map_data['name']);
} elseif (isset($_GET['map_value'])) {
    $initial_map_value_js = json_encode(trim($_GET['map_value']));
    if (isset($_GET['map_name'])) {
        $initial_map_name_js = json_encode(trim($_GET['map_name']));
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= h($page_title_action) ?> Dungeon | Mystical Dungeons</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=MedievalSharp&display=swap');
body{font-family:'MedievalSharp',cursive;background:#0f0e17;color:#e8e8e8;background-image:radial-gradient(circle at 10% 20%,rgba(255,137,6,.08)0,transparent 20%),radial-gradient(circle at 90% 80%,rgba(229,49,112,.08)0,transparent 20%)}
.panel{background:rgba(27,27,45,0.9);border:1px solid rgba(255,137,6,.3);box-shadow:0 10px 25px rgba(0,0,0,.35);backdrop-filter: blur(3px);}
.text-grad, .text-gradient {
  background:linear-gradient(135deg,#ff8906 0%,#e53170 100%);
  -webkit-background-clip:text;
  color:transparent;
  display: inline-block;
}
.btn{background:linear-gradient(135deg,#ff8906 0%,#e53170 100%);color:#fff;padding:.5rem 1rem;border-radius:.5rem;transition: transform 0.2s ease, box-shadow 0.2s ease; font-weight:bold;}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(229,49,112,.45)}
.btn:disabled { opacity: 0.6; cursor: not-allowed; transform:none; box-shadow:none;}
.btn-secondary { background: rgba(74,74,90,0.6); border: 1px solid rgba(255,137,6,0.3); color: #e0e0e0; font-weight:normal;}
.btn-secondary:hover { background: rgba(90,90,110,0.7); border-color: rgba(255,137,6,0.5); transform: translateY(-1px); box-shadow: 0 4px 15px rgba(255,137,6,.15)}
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }


.size-btn{border:1px solid rgba(255,137,6,.35);border-radius:.5rem;padding:.35rem .8rem; color: #d0d0d0; transition: background-color 0.2s, color 0.2s, transform 0.1s;}
.size-btn:hover { background-color: rgba(255,137,6,.15); transform: translateY(-1px); }
.size-btn.active{background:#ff8906;color:#0f0e17;font-weight:bold}

.grid-wrap{
  max-height:75vh;
  max-width:100%;
  overflow:auto;
  border:3px solid rgba(255,137,6,.35);
  border-radius: 6px;
  display:flex;
  justify-content: flex-start; /* Keep grid aligned to top-left for consistent scroll behavior */
  background: rgba(15,14,23,0.5);
  padding: 5px;
}
.grid{
  display:inline-grid; /* Important for overflow and proper sizing */
  gap:1px;
  background:transparent; /* Grid background itself */
  image-rendering: pixelated; /* Better for scaled pixel art */
}
.cell{
  /* width and height will be set by JS */
  cursor:pointer;
  background-size:100% 100%; /* Make SVG/images scale with cell size */
  background-repeat: no-repeat;
  background-position: center;
  transition: transform 0.1s ease-out;
  image-rendering: pixelated; /* Better for scaled pixel art */
}
.cell:hover { transform: scale(1.1); z-index: 10; box-shadow: 0 0 5px rgba(255,137,6,0.5); }

/* SVG backgrounds should scale well if vector-based */
.wall { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%232C2C3A"/><rect x="1" y="1" width="13" height="8" fill="%2345455C"/><rect x="15" y="1" width="14" height="8" fill="%233D3D50"/><rect x="1" y="10" width="8" height="9" fill="%23505065"/><rect x="10" y="10" width="10" height="9" fill="%2345455C"/><rect x="21" y="10" width="8" height="9" fill="%233D3D50"/><rect x="1" y="20" width="14" height="9" fill="%233D3D50"/><rect x="16" y="20" width="13" height="9" fill="%2345455C"/></svg>');}
.path{background-image:url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"30\" height=\"30\"><rect width=\"30\" height=\"30\" fill=\"%23141422\"/><path d=\"M0 0h30v30H0z\" fill=\"none\" stroke=\"%23292929\" stroke-width=\"1\"/></svg>')}
.start { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%23141422"/><path d="M15,24 L8,16 H12 V6 H18 V16 H22 L15,24 Z" fill="%2338c172" stroke="%232A8A4A" stroke-width="1.5" stroke-linejoin="round"/></svg>');}
.exit { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%23141422"/><path d="M15,26 L6,16 H11 V5 H19 V16 H24 L15,26 Z" fill="%23e3342f" stroke="%23B02020" stroke-width="2" stroke-linejoin="miter"/><path d="M15,23 L9,16.5 H12.5 V7 H17.5 V16.5 H21 L15,23 Z" fill="none" stroke="%23FF6B6B" stroke-width="1"/></svg>');}
.button-tile { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%23141422"/><circle cx="15" cy="15" r="11" fill="%23303035"/><circle cx="15" cy="15" r="10" fill="%23484850"/><circle cx="15" cy="15" r="7" fill="%23a0522d" stroke="%2370300D" stroke-width="1.5"/><circle cx="15" cy="15" r="3.5" fill="%23d2b48c" stroke="%23B0845C" stroke-width="1"/></svg>');}
.doorC { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%23141422"/><path d="M5,28 V4 Q5,2 8,2 H22 Q25,2 25,4 V28 Z" fill="%234A3B31" stroke="%232C2C3A" stroke-width="0.5"/><rect x="7" y="3.5" width="16" height="24" fill="%236b4f3f" rx="1"/><line x1="11" y1="3.5" x2="11" y2="27.5" stroke="%235A3F31" stroke-width="1.5"/><line x1="15" y1="3.5" x2="15" y2="27.5" stroke="%2352382A" stroke-width="1.5"/><line x1="19" y1="3.5" x2="19" y2="27.5" stroke="%235A3F31" stroke-width="1.5"/><rect x="6.5" y="6" width="17" height="3.5" fill="%233A3A3A" stroke="%232A2A2A" stroke-width="0.5"/><rect x="6.5" y="20" width="17" height="3.5" fill="%233A3A3A" stroke="%232A2A2A" stroke-width="0.5"/><circle cx="8.5" cy="7.75" r="0.7" fill="%232A2A2A"/><circle cx="21.5" cy="7.75" r="0.7" fill="%232A2A2A"/><circle cx="8.5" cy="21.75" r="0.7" fill="%232A2A2A"/><circle cx="21.5" cy="21.75" r="0.7" fill="%232A2A2A"/><rect x="13" y="13.5" width="4" height="3" rx="0.5" fill="%232A2A2A"/><rect x="13.5" y="14" width="3" height="1" fill="%23d2b48c" opacity="0.6"/></svg>');}
.doorO { background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30"><rect width="30" height="30" fill="%23141422"/><path d="M5,28 V4 Q5,2 8,2 H22 Q25,2 25,4 V28 H20 V4.5 H10 V28 Z" fill="%234A3B31" stroke="%232C2C3A" stroke-width="0.5"/><g transform="translate(21 3.5) rotate(15 0 12)"><rect width="6" height="24" fill="%236b4f3f" rx="1"/><line x1="2" y1="0" x2="2" y2="24" stroke="%235A3F31" stroke-width="1"/><line x1="4" y1="0" x2="4" y2="24" stroke="%2352382A" stroke-width="1"/><rect y="2.5" width="6" height="3.5" fill="%233A3A3A" stroke="%232A2A2A" stroke-width="0.5"/><rect y="16.5" width="6" height="3.5" fill="%233A3A3A" stroke="%232A2A2A" stroke-width="0.5"/><circle cx="1" cy="4.25" r="0.7" fill="%232A2A2A"/><circle cx="5" cy="4.25" r="0.7" fill="%232A2A2A"/><circle cx="1" cy="18.25" r="0.7" fill="%232A2A2A"/><circle cx="5" cy="18.25" r="0.7" fill="%232A2A2A"/></g></svg>');}

.tool{border:1px solid rgba(255,137,6,.35);border-radius:.5rem;padding:.3rem .8rem;display:flex;align-items:center;gap:.4rem; color: #d0d0d0; transition: background-color 0.2s, color 0.2s, transform 0.1s;}
.tool .cell { width:20px;height:20px;background-size:100% 100%; cursor:default; }
.tool:hover { background-color: rgba(255,137,6,.15); transform: translateY(-1px); }
.tool.active{background:#ff8906;color:#0f0e17;font-weight:bold}
.tool .keybind { font-size: 0.7rem; color: #aaa; margin-left: auto; padding: 0.1rem 0.3rem; background: rgba(0,0,0,0.2); border-radius: 3px;}
.tool.active .keybind { color: #222; }

.loader {
    position: absolute; /* Changed from fixed to absolute to be contained in its panel */
    inset: 0;
    background: rgba(15, 14, 23, 0.85);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 100;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
    border-radius: inherit; /* Inherit border-radius from parent panel */
}
.loader.show {
    opacity: 1;
    visibility: visible;
}
.loader-icon {
    width: 60px;
    height: 60px;
    border: 5px solid rgba(255, 137, 6, 0.2);
    border-top-color: #ff8906;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
.loader-text {
    margin-top: 15px;
    color: #ff8906;
    font-size: 1.1rem;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style></head><body>

<?php require __DIR__.'/includes/navbar.php'; ?>

<?php if($flashes): ?><div class="container mx-auto mt-4 px-4"><?php foreach($flashes as $t=>$ms)foreach($ms as $m): ?>
<div class="px-4 py-3 mb-2 rounded <?= $t==='error'?'bg-red-600/20 text-red-300':'bg-emerald-600/20 text-emerald-300' ?>"><?=h($m)?></div>
<?php endforeach;?></div><?php endif; ?>

<div class="container mx-auto py-10 px-4">
<h1 class="text-4xl font-bold text-grad mb-8 flex items-center"><i class="fas fa-drafting-compass mr-3"></i><?= h($page_title_action) ?> Dungeon</h1>

<!-- SETTINGS -->
<div class="panel rounded-xl p-6 mb-10">
  <div class="grid md:grid-cols-3 gap-x-6 gap-y-4">
    <div>
      <label for="mapName" class="font-bold block mb-2 text-amber-300">Dungeon Name</label>
      <input id="mapName" class="bg-gray-800 border border-amber-500/30 rounded-lg w-full px-4 py-2 focus:ring-2 focus:ring-amber-500 outline-none" placeholder="e.g., Crypt of Shadows" value="<?= $current_map_data ? h($current_map_data['name']) : '' ?>">
    </div>
    <div><label class="font-bold block mb-2 text-amber-300">Preset Size</label><div class="flex flex-wrap gap-2"><?php foreach($preset as $p): ?><button class="size-btn <?= ($current_map_data && $current_map_data['size'] == $p) ? 'active' : '' ?>" data-size="<?=$p?>"><?=$p?>×<?=$p?></button><?php endforeach;?></div></div>
    <div><label for="customSize" class="font-bold block mb-2 text-amber-300">Custom Size (3-150)</label><div class="flex gap-2"><input id="customSize" type="number" min="3" max="150" class="bg-gray-800 border border-amber-500/30 rounded-lg w-24 px-3 py-2 focus:ring-2 focus:ring-amber-500 outline-none" value="<?= ($current_map_data && !in_array($current_map_data['size'], $preset)) ? h($current_map_data['size']) : '' ?>"><button id="applyCustom" class="btn btn-secondary py-2" type="button">Apply</button></div></div>
  </div>
</div>

<!-- TOOLS & IMPORT/EXPORT & ZOOM -->
<div class="panel rounded-xl p-6 mb-10">
  <div class="flex flex-wrap justify-between items-center mb-4 gap-4">
    <h2 class="text-2xl font-bold text-amber-400 flex items-center"><i class="fas fa-paint-brush mr-2"></i>Tools</h2>
    <div class="flex gap-2 items-center">
        <span class="text-sm text-gray-400 mr-1">Zoom:</span>
        <button id="zoomOutBtn" class="btn btn-secondary btn-sm p-1 px-2" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
        <button id="zoomInBtn" class="btn btn-secondary btn-sm p-1 px-2" title="Zoom In"><i class="fas fa-search-plus"></i></button>
        <div class="h-6 border-l border-gray-600 mx-2"></div> <!-- Separator -->
        <button id="importMatrixPopupBtn" class="btn btn-secondary btn-sm py-1 px-3" type="button" title="Import map data from string"><i class="fas fa-file-import mr-1"></i> Import</button>
        <button id="copyMatrixBtn" class="btn btn-secondary btn-sm py-1 px-3" type="button" title="Copy current map data to clipboard"><i class="fas fa-copy mr-1"></i> Copy</button>
    </div>
  </div>
  <div class="flex flex-wrap gap-3"><?php
    $tools=['wall'=>'Wall','path'=>'Path','start'=>'Start','exit'=>'Exit','button-tile'=>'Button','doorC'=>'Door-C','doorO'=>'Door-O'];
    $keybinds=['wall'=>'W','path'=>'P','start'=>'S','exit'=>'E','button-tile'=>'B','doorC'=>'C','doorO'=>'O'];
    foreach($tools as $id=>$lab): ?>
    <button class="tool" data-tool="<?=$id?>" title="Select <?= $lab ?><?php if(isset($keybinds[$id])): ?> (Shortcut: <?= $keybinds[$id] ?>)<?php endif; ?>">
        <span class="cell <?=$id?>"></span><?=$lab?>
        <?php if(isset($keybinds[$id])): ?><span class="keybind"><?= $keybinds[$id] ?></span><?php endif; ?>
    </button>
    <?php endforeach;?>
  </div>
</div>

<!-- GRID -->
<div class="panel rounded-xl p-6 mb-10 relative"> <!-- Added relative for loader positioning -->
  <h2 class="text-2xl font-bold text-amber-400 mb-4"><i class="fas fa-th-large mr-2"></i>Layout Editor</h2>
  <div class="grid-wrap"><div id="grid" class="grid"></div></div>
  <div id="loader" class="loader">
      <div class="loader-icon"></div>
      <div class="loader-text">Building your masterpiece...</div>
  </div>
</div>

<!-- SAVE / UPDATE -->
<form id="saveForm" method="POST" action="create_map.php<?= $editing_map_id ? '?map_id='.h($editing_map_id) : '' ?>" class="text-center">
  <input type="hidden" name="save_map" value="1">
  <?php if ($editing_map_id): ?>
    <input type="hidden" name="map_id" value="<?= h($editing_map_id) ?>">
  <?php endif; ?>
  <input type="hidden" name="map_name" id="formName">
  <input type="hidden" name="size" id="formSize">
  <textarea name="matrix" id="formMatrix" class="hidden"></textarea>
  <button id="saveDungeonBtn" class="btn text-lg px-8 py-3"><i class="fas fa-save mr-2"></i><?= h($submit_button_text) ?></button>
</form>
</div>

<script>
/* ---------- state ---------- */
const TOOL_VALUES = {wall:0, path:1, start:2, exit:3, 'button-tile':4, doorC:5, doorO:6};
const TILE_CLASS_NAMES = ['wall','path','start','exit','button-tile','doorC','doorO'];
const TILES_FOR_2X2_RULE = new Set([
    TOOL_VALUES.path, TOOL_VALUES.start, TOOL_VALUES.exit,
    TOOL_VALUES['button-tile'], TOOL_VALUES.doorC, TOOL_VALUES.doorO
]);
let currentCellSizePx = 28; // Initial cell size, will be dynamic
const MIN_CELL_SIZE_PX = 10;
const MAX_CELL_SIZE_PX = 70;
const ZOOM_STEP_PX = 4;

let currentGridSize = 10;
let logicalGrid = [];
let currentTool = 'wall';
let isPainting = false;

const initialMapValueFromPHP = <?php echo $initial_map_value_js; ?>;
const initialMapNameFromPHP = <?php echo $initial_map_name_js; ?>;
const editingMapIdPHP = <?= json_encode($editing_map_id); ?>;

/* ---------- DOM elements ---------- */
const gridElement = document.getElementById('grid');
const loaderElement = document.getElementById('loader');
const mapNameInputElement = document.getElementById('mapName');
const formMapNameElement = document.getElementById('formName');
const formGridSizeElement = document.getElementById('formSize');
const formMatrixTextareaElement = document.getElementById('formMatrix');
const customSizeInputElement = document.getElementById('customSize');
const copyMatrixButtonElement = document.getElementById('copyMatrixBtn');
const importMatrixPopupBtnElement = document.getElementById('importMatrixPopupBtn');
const saveDungeonButtonElement = document.getElementById('saveDungeonBtn');
const zoomInButtonElement = document.getElementById('zoomInBtn');
const zoomOutButtonElement = document.getElementById('zoomOutBtn');

/* ---------- helpers ---------- */
function getMatrixString(){
  return logicalGrid.map(row => row.join('')).join(';');
}

function showLoader(show = true, text = "Processing...") {
    loaderElement.querySelector('.loader-text').textContent = text;
    if (show) {
        loaderElement.classList.add('show');
    } else {
        loaderElement.classList.remove('show');
    }
}

function buildGridUI(newSize, initialGridData = null, preserveScroll = false){
  let scrollX = 0, scrollY = 0;
  const gridWrap = gridElement.parentElement; // Assuming .grid-wrap is parent
  if (preserveScroll && gridWrap) {
    scrollX = gridWrap.scrollLeft;
    scrollY = gridWrap.scrollTop;
  }

  currentGridSize = newSize;
  if (initialGridData) {
      logicalGrid = initialGridData;
  } else {
      // If logicalGrid already exists and has the right size, keep it (e.g. for zoom)
      // Otherwise, reinitialize
      if (!logicalGrid.length || logicalGrid.length !== newSize || (logicalGrid[0] && logicalGrid[0].length !== newSize) ) {
        logicalGrid = Array.from({length:newSize}, () => Array(newSize).fill(TOOL_VALUES.wall));
      }
  }
  formGridSizeElement.value = newSize;

  gridElement.innerHTML = '';
  gridElement.style.gridTemplateColumns = `repeat(${newSize}, ${currentCellSizePx}px)`;
  gridElement.style.gridTemplateRows = `repeat(${newSize}, ${currentCellSizePx}px)`;

  showLoader(true, `Building ${newSize}x${newSize} grid...`);

  let cellsRendered = 0;
  const totalCells = newSize * newSize;
  const batchSize = Math.max(100, Math.floor(totalCells / 10));

  function addCellsInBatch() {
    const fragment = document.createDocumentFragment();
    for(let i = 0; i < batchSize && cellsRendered < totalCells; i++, cellsRendered++){
      const x = cellsRendered % newSize;
      const y = Math.floor(cellsRendered / newSize);
      const cellDiv = document.createElement('div');
      const tileValue = (logicalGrid[y] && logicalGrid[y][x] !== undefined) ? logicalGrid[y][x] : TOOL_VALUES.wall;
      cellDiv.className = 'cell ' + (TILE_CLASS_NAMES[tileValue] || TILE_CLASS_NAMES[TOOL_VALUES.wall]);
      cellDiv.style.width = `${currentCellSizePx}px`; // Set cell size via JS
      cellDiv.style.height = `${currentCellSizePx}px`;
      cellDiv.dataset.x = x;
      cellDiv.dataset.y = y;
      cellDiv.onmousedown = (e) => { e.preventDefault(); isPainting = true; paintCell(x,y,cellDiv); };
      cellDiv.onmouseover = () => { if(isPainting) paintCell(x,y,cellDiv); };
      fragment.appendChild(cellDiv);
    }
    gridElement.appendChild(fragment);

    if (cellsRendered < totalCells) {
      requestAnimationFrame(addCellsInBatch);
    } else {
      showLoader(false);
      if (preserveScroll && gridWrap) {
        gridWrap.scrollLeft = scrollX;
        gridWrap.scrollTop = scrollY;
      }
    }
  }
  requestAnimationFrame(addCellsInBatch);
}

const isCorner = (x,y) => (x === 0 || x === currentGridSize - 1) && (y === 0 || y === currentGridSize - 1);

function isCreatingForbidden2x2Square(x, y, placingValue) {
  if (!TILES_FOR_2X2_RULE.has(placingValue)) {
    return false;
  }
  for (const [dx, dy] of [[0, 0], [-1, 0], [0, -1], [-1, -1]]) {
    const topLeftX = x + dx;
    const topLeftY = y + dy;
    if (topLeftX < 0 || topLeftY < 0 || topLeftX >= currentGridSize - 1 || topLeftY >= currentGridSize - 1) {
      continue;
    }
    const getGridValue = (r, c) => (r === y && c === x) ? placingValue : (logicalGrid[r] ? logicalGrid[r][c] : undefined);

    const squareValues = [
      getGridValue(topLeftY, topLeftX),
      getGridValue(topLeftY, topLeftX + 1),
      getGridValue(topLeftY + 1, topLeftX),
      getGridValue(topLeftY + 1, topLeftX + 1)
    ];
    if (squareValues.every(val => TILES_FOR_2X2_RULE.has(val))) {
      return true;
    }
  }
  return false;
}

/* ---------- paint ---------- */
function paintCell(x,y,cellElement){
  let valueToPlace = TOOL_VALUES[currentTool];
  if (valueToPlace === undefined) valueToPlace = TOOL_VALUES.wall;

  const isOnBorder = (x === 0 || y === 0 || x === currentGridSize - 1 || y === currentGridSize - 1);
  if(isOnBorder && ![TOOL_VALUES.wall, TOOL_VALUES.start, TOOL_VALUES.exit].includes(valueToPlace)) return;
  if(isCorner(x,y) && (valueToPlace === TOOL_VALUES.start || valueToPlace === TOOL_VALUES.exit)) return;
  if (isCreatingForbidden2x2Square(x, y, valueToPlace)) return;

  if(valueToPlace === TOOL_VALUES.start || valueToPlace === TOOL_VALUES.exit){
    for(let r = 0; r < currentGridSize; r++) {
      for(let c = 0; c < currentGridSize; c++) {
        if(logicalGrid[r] && logicalGrid[r][c] === valueToPlace && (r !== y || c !== x)){
           logicalGrid[r][c] = TOOL_VALUES.wall;
           const oldCellElement = gridElement.querySelector(`[data-x='${c}'][data-y='${r}']`);
           if (oldCellElement) oldCellElement.className = 'cell ' + TILE_CLASS_NAMES[TOOL_VALUES.wall];
        }
      }
    }
  }
  if (!logicalGrid[y]) logicalGrid[y] = [];
  logicalGrid[y][x] = valueToPlace;
  cellElement.className = 'cell ' + TILE_CLASS_NAMES[valueToPlace];
}

/* ---------- UI Interaction ---------- */
document.body.onmouseup = () => isPainting = false;
document.body.onmouseleave = () => isPainting = false;

document.querySelectorAll('.tool').forEach(button => {
  button.onclick = () => setActiveTool(button.dataset.tool);
});

function setActiveTool(toolName) {
    document.querySelectorAll('.tool').forEach(btn => btn.classList.remove('active'));
    const toolButton = document.querySelector(`.tool[data-tool="${toolName}"]`);
    if (toolButton) toolButton.classList.add('active');
    currentTool = toolName;
}

const TOOL_SHORTCUTS = {
    'w': 'wall', 'p': 'path', 's': 'start', 'e': 'exit',
    'b': 'button-tile', 'c': 'doorC', 'o': 'doorO'
};

document.onkeydown = event => {
    if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') return;
    if (document.body.classList.contains('prompt-active')) return;

    if (event.code === 'Space') {
        event.preventDefault();
        isPainting = true;
        return;
    }
    if ((event.ctrlKey || event.metaKey) && event.key === '+') { // Ctrl/Cmd + Plus
        event.preventDefault();
        adjustEditorZoomLevel(ZOOM_STEP_PX);
    }
    if ((event.ctrlKey || event.metaKey) && event.key === '-') { // Ctrl/Cmd + Minus
        event.preventDefault();
        adjustEditorZoomLevel(-ZOOM_STEP_PX);
    }

    const toolKey = event.key.toLowerCase();
    if (TOOL_SHORTCUTS[toolKey] && !event.ctrlKey && !event.metaKey && !event.altKey) { // Ensure no modifiers for tool shortcuts
        event.preventDefault();
        setActiveTool(TOOL_SHORTCUTS[toolKey]);
    }
};
document.onkeyup  = event => { if(event.code === 'Space') isPainting = false };

document.querySelectorAll('.size-btn').forEach(button => {
  button.onclick = () => {
    document.querySelectorAll('.size-btn').forEach(btn => btn.classList.remove('active'));
    button.classList.add('active');
    customSizeInputElement.value = '';
    buildGridUI(+button.dataset.size); // Use current cell size
  }
});

document.getElementById('applyCustom').onclick = () => {
  const val = +customSizeInputElement.value;
  if(val >= 3 && val <= 150){
    document.querySelectorAll('.size-btn').forEach(btn => btn.classList.remove('active'));
    buildGridUI(val); // Use current cell size
  } else {
    alert('Custom size must be between 3 and 150.');
  }
};

if (copyMatrixButtonElement) {
    copyMatrixButtonElement.onclick = () => {
        const matrixString = getMatrixString();
        navigator.clipboard.writeText(matrixString)
            .then(() => {
                const originalText = copyMatrixButtonElement.innerHTML;
                copyMatrixButtonElement.innerHTML = '<i class="fas fa-check mr-1"></i>Copied!';
                copyMatrixButtonElement.disabled = true;
                setTimeout(() => {
                    copyMatrixButtonElement.innerHTML = originalText;
                    copyMatrixButtonElement.disabled = false;
                }, 2000);
            })
            .catch(err => {
                console.error('Failed to copy matrix: ', err);
                alert('Failed to copy matrix. See console for details.');
            });
    };
}

/* ---------- import from string ---------- */
function parseMatrixString(matrixStr) {
    const trimmedMatrixStr = matrixStr.trim();
    if (!trimmedMatrixStr) return { error: "Matrix string cannot be empty." };
    const rows = trimmedMatrixStr.split(';');
    const size = rows.length;
    if (size < 3 || size > 150) return { error: `Invalid map size (${size}). Must be between 3 and 150.` };
    const newLogicalGrid = [];
    for (let i = 0; i < rows.length; i++) {
        const rowStr = rows[i].trim();
        if (rowStr.length !== size) return { error: `Row ${i+1} has incorrect length. Expected ${size}, got ${rowStr.length}.`};
        if (/[^0-6]/.test(rowStr)) return { error: `Row ${i+1} contains invalid characters. Only 0-6 allowed.` };
        newLogicalGrid.push(rowStr.split('').map(Number));
    }
    return { size: size, grid: newLogicalGrid };
}

function loadMapFromMatrixString(matrixString, source = "popup") {
    const parseResult = parseMatrixString(matrixString);
    if (parseResult.error) {
        alert(`Failed to import matrix${source === "URL" ? " from URL" : ""}: ${parseResult.error}`);
        if (source === "URL" && !editingMapIdPHP) {
            document.querySelector('.size-btn[data-size="10"]').classList.add('active');
            buildGridUI(10);
        }
        return false;
    }
    const { size, grid } = parseResult;
    customSizeInputElement.value = size;
    document.querySelectorAll('.size-btn').forEach(btn => {
        if (+btn.dataset.size === size) btn.classList.add('active');
        else btn.classList.remove('active');
    });
    buildGridUI(size, grid);
    if (source === "popup") alert('Matrix imported successfully! Grid updated.');
    return true;
}

if (importMatrixPopupBtnElement) {
    importMatrixPopupBtnElement.onclick = () => {
        document.body.classList.add('prompt-active');
        const matrixString = prompt("Paste matrix string (e.g., 000;012;000):", getMatrixString());
        document.body.classList.remove('prompt-active');
        if (matrixString !== null && matrixString.trim() !== "") loadMapFromMatrixString(matrixString, "popup");
        else if (matrixString !== null) alert("Import cancelled or empty matrix provided.");
    };
}

/* ---------- Zoom Functionality ---------- */
function adjustEditorZoomLevel(delta) {
    const newCellSize = Math.min(MAX_CELL_SIZE_PX, Math.max(MIN_CELL_SIZE_PX, currentCellSizePx + delta));
    if (newCellSize === currentCellSizePx) return; // No change
    currentCellSizePx = newCellSize;
    // Rebuild grid with new cell size, preserving existing logical grid data
    // Pass `true` to preserve scroll position
    buildGridUI(currentGridSize, logicalGrid, true);
}
if(zoomInButtonElement) zoomInButtonElement.onclick = () => adjustEditorZoomLevel(ZOOM_STEP_PX);
if(zoomOutButtonElement) zoomOutButtonElement.onclick = () => adjustEditorZoomLevel(-ZOOM_STEP_PX);


/* ---------- save client-side validation ---------- */
document.getElementById('saveForm').onsubmit = event => {
  const mapName = mapNameInputElement.value.trim();
  if(!mapName){ alert('Please name your dungeon before saving.'); event.preventDefault();return; }
  formMapNameElement.value = mapName;
  formGridSizeElement.value = currentGridSize;
  let hasStart = false, hasExit = false;
  for(let r = 0; r < currentGridSize; r++){
    for(let c = 0; c < currentGridSize; c++){
      if(logicalGrid[r] && logicalGrid[r][c] === TOOL_VALUES.start) hasStart = true;
      if(logicalGrid[r] && logicalGrid[r][c] === TOOL_VALUES.exit) hasExit = true;
      if (logicalGrid[r] && logicalGrid[r][c] !== undefined && isCreatingForbidden2x2Square(c, r, logicalGrid[r][c])) {
          alert(`Map cannot contain 2x2 squares of paths/doors/buttons/start/exit near cell (${c+1},${r+1}).`);
          event.preventDefault(); return;
      }
    }
  }
  if(!hasStart || !hasExit){ alert('A dungeon must have both a Start and an Exit tile.'); event.preventDefault();return; }
  if (currentGridSize > 0 && logicalGrid[0] && logicalGrid[currentGridSize-1]) {
    const cornersValues = [ logicalGrid[0][0], logicalGrid[0][currentGridSize-1], logicalGrid[currentGridSize-1][0], logicalGrid[currentGridSize-1][currentGridSize-1]];
    if(cornersValues.includes(TOOL_VALUES.start) || cornersValues.includes(TOOL_VALUES.exit)){
      alert('Start/Exit tiles cannot be placed in the corners of the map.'); event.preventDefault();return;
    }
  }
  formMatrixTextareaElement.value = getMatrixString();
  const loaderMessage = editingMapIdPHP ? "Updating dungeon..." : "Saving dungeon...";
  showLoader(true, `${loaderMessage} This may take a moment...`);
  saveDungeonButtonElement.disabled = true;
  saveDungeonButtonElement.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i>${editingMapIdPHP ? 'Updating...' : 'Saving...'}`;
};

/* ---------- init ---------- */
function initializeEditor() {
    let loadedFromUrlOrEdit = false;
    if (initialMapNameFromPHP) mapNameInputElement.value = initialMapNameFromPHP;

    if (initialMapValueFromPHP) {
        if (loadMapFromMatrixString(initialMapValueFromPHP, "URL")) {
            loadedFromUrlOrEdit = true;
            const parseResult = parseMatrixString(initialMapValueFromPHP);
            if (!parseResult.error) {
                const loadedSize = parseResult.size;
                document.querySelectorAll('.size-btn').forEach(btn => {
                    if (+btn.dataset.size === loadedSize) btn.classList.add('active');
                    else btn.classList.remove('active');
                });
                if (!document.querySelector('.size-btn.active')) customSizeInputElement.value = loadedSize;
                else customSizeInputElement.value = '';
            }
        }
    }
    if (!loadedFromUrlOrEdit) {
        const defaultSize = 10;
        const defaultSizeButton = document.querySelector(`.size-btn[data-size="${defaultSize}"]`);
        if (defaultSizeButton) defaultSizeButton.classList.add('active');
        customSizeInputElement.value = '';
        buildGridUI(defaultSize);
    }
    setActiveTool('wall');
    showLoader(false);
}

initializeEditor();
</script>
</body></html>