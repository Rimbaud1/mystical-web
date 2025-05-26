<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';

if (!is_logged_in()) redirect('login-signup.php');
$me = current_user();
if ($me['role'] !== 'admin') { flash('error','Access denied.'); redirect('index.php'); }

$section = $_GET['section'] ?? 'dashboard';           // dashboard | users | maps
$flash   = get_flashes();

/* ---------- CRUD logic ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST'){
  switch($_POST['action'] ?? ''){
    case'delete_user':
      $uid=(int)$_POST['uid']; if($uid && $uid!=$me['id']){
        $pdo->prepare('DELETE FROM stats WHERE user_id=?')->execute([$uid]);
        $pdo->prepare('DELETE FROM map   WHERE user_id=?')->execute([$uid]);
        $pdo->prepare('DELETE FROM User  WHERE user_id=?')->execute([$uid]);
        flash('success','User deleted.'); }
      break;

    case'delete_map':
      $mid=(int)$_POST['mid'];
      if($mid && !in_array($mid,[1,2,3,4])){
        $pdo->prepare('DELETE FROM Map WHERE id=?')->execute([$mid]);
        flash('success','Map deleted.'); }
      break;

    case'change_role':
      $uid=(int)$_POST['uid']; $new=$_POST['role'];
      if($uid && in_array($new,['user','admin']) && $uid!=$me['id']){
        $pdo->prepare('UPDATE User SET role=? WHERE user_id=?')->execute([$new,$uid]);
        flash('success','Role updated.'); }
      break;
  }
  header("Location: admin.php?section=$section"); exit;
}

/* ---------- Stats ---------- */
if($section==='dashboard'){
  $stats=[
    'users'  => $pdo->query('SELECT COUNT(*) FROM User')->fetchColumn(),
    'maps'   => $pdo->query('SELECT COUNT(*) FROM Map')->fetchColumn(),
    'games'  => $pdo->query('SELECT SUM(user_game_count) FROM stats')->fetchColumn(),
    'hours'  => $pdo->query('SELECT SUM(played_time) FROM stats')->fetchColumn()/3600
  ];
}

/* ---------- Users list ---------- */
if($section==='users'){
  $q  = trim($_GET['q'] ?? '');
  $p  = max(1,(int)($_GET['p'] ?? 1));
  $per=15; $off=($p-1)*$per;
  $where = $q?'WHERE name LIKE :q OR email LIKE :q':'';
  $total=$pdo->prepare("SELECT COUNT(*) FROM User $where");
  if($q) $total->execute([':q'=>"%$q%"]); else $total->execute();
  $total=(int)$total->fetchColumn();
  $stmt=$pdo->prepare("SELECT u.*,s.user_game_count,s.win_count
                       FROM User u
                       LEFT JOIN stats s ON s.user_id=u.user_id
                       $where ORDER BY u.user_id DESC LIMIT $per OFFSET $off");
  if($q) $stmt->execute([':q'=>"%$q%"]); else $stmt->execute();
  $users=$stmt->fetchAll();
}

/* ---------- Maps list ---------- */
if($section==='maps'){
  $q  = trim($_GET['q'] ?? '');
  $p  = max(1,(int)($_GET['p'] ?? 1));
  $per=15; $off=($p-1)*$per;
  $where=$q?'WHERE m.name LIKE :q OR u.name LIKE :q':'';
  $total=$pdo->prepare("SELECT COUNT(*) FROM Map m LEFT JOIN User u ON u.user_id=m.user_id $where");
  if($q) $total->execute([':q'=>"%$q%"]); else $total->execute();
  $total=(int)$total->fetchColumn();
  $stmt=$pdo->prepare("SELECT m.*,u.name author FROM Map m
                       LEFT JOIN User u ON u.user_id=m.user_id
                       $where ORDER BY m.id DESC LIMIT $per OFFSET $off");
  if($q) $stmt->execute([':q'=>"%$q%"]); else $stmt->execute();
  $maps=$stmt->fetchAll();
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin | Mystical Dungeons</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=MedievalSharp&display=swap');
body{font-family:'MedievalSharp',cursive;background:#0f0e17;color:#e8e8e8}
.sidebar{width:220px;background:#11131d;position:fixed;top:0;bottom:0;border-right:1px solid rgba(255,137,6,.2);padding-top:4.5rem}
.sidebar a{display:flex;align-items:center;padding:.75rem 1rem;color:#ccc;border-left:3px solid transparent}
.sidebar a:hover{background:#1b1b2d;color:#ffae34}
.sidebar .active{color:#ffae34;border-color:#ff8906;background:#1b1b2d}
.content{margin-left:220px;padding:2rem}
h1{background:linear-gradient(135deg,#ff8906,#e53170);-webkit-background-clip:text;color:transparent}
.tableWrap{overflow-x:auto;background:#1b1b2de8;border:1px solid rgba(255,137,6,.25);border-radius:.5rem}
table{min-width:700px}
th,td{padding:.6rem 1rem;border-bottom:1px solid rgba(255,137,6,.15)}
th{background:rgba(255,137,6,.08);color:#ffae34;cursor:pointer;white-space:nowrap}
tr:hover td{background:rgba(255,255,255,.03)}
.stat{background:#1b1b2de8;border:1px solid rgba(255,137,6,.3);padding:1.5rem;border-radius:.5rem;text-align:center}
.btn{padding:.3rem .6rem;border-radius:.25rem;font-size:.8rem}
.btn-del{background:#7f1d1d;border:1px solid #991b1b}
.btn-del:hover{background:#991b1b}
select{background:#0f0e17;border:1px solid rgba(255,137,6,.4);padding:.25rem .4rem;border-radius:.25rem;color:#e8e8e8}
input[type=text]{background:#0f0e17;border:1px solid rgba(255,137,6,.4);padding:.4rem .6rem;border-radius:.3rem;width:100%}
.pagination a{padding:.4rem .7rem;margin:.1rem;border-radius:.25rem;background:rgba(255,137,6,.2)}
.pagination .current{background:#ff8906;color:#0f0e17;font-weight:bold}
</style>
</head>
<body>

<?php require __DIR__.'/includes/navbar.php'; ?>

<!-- Sidebar -->
<aside class="sidebar">
  <a href="admin.php?section=dashboard" class="<?= $section==='dashboard'?'active':'' ?>"><i class="fas fa-tachometer-alt mr-2 w-5 text-center"></i>Dashboard</a>
  <a href="admin.php?section=users"     class="<?= $section==='users'?'active':'' ?>"><i class="fas fa-users mr-2 w-5 text-center"></i>Users</a>
  <a href="admin.php?section=maps"      class="<?= $section==='maps'?'active':'' ?>"><i class="fas fa-map mr-2 w-5 text-center"></i>Maps</a>
</aside>

<div class="content">
  <?php foreach($flash as $t=>$msgs) foreach($msgs as $m): ?>
    <div class="px-4 py-2 mb-4 rounded <?= $t==='error'?'bg-red-600/80':'bg-emerald-600/80' ?>"><?=h($m)?></div>
  <?php endforeach;?>

  <?php if($section==='dashboard'): ?>
    <h1 class="text-4xl font-bold mb-8">Site Overview</h1>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="stat"><i class="fas fa-users text-amber-400 text-2xl mb-2"></i><p class="text-3xl font-bold"><?= $stats['users']??0 ?></p><p class="text-gray-400">Users</p></div>
      <div class="stat"><i class="fas fa-map text-purple-400 text-2xl mb-2"></i><p class="text-3xl font-bold"><?= $stats['maps']??0 ?></p><p class="text-gray-400">Maps</p></div>
      <div class="stat"><i class="fas fa-gamepad text-green-400 text-2xl mb-2"></i><p class="text-3xl font-bold"><?= $stats['games']??0 ?></p><p class="text-gray-400">Games Played</p></div>
      <div class="stat"><i class="fas fa-clock text-sky-400 text-2xl mb-2"></i><p class="text-3xl font-bold"><?= number_format($stats['hours']??0,1) ?>h</p><p class="text-gray-400">Play Time</p></div>
    </div>

  <?php elseif($section==='users'): ?>
    <h1 class="text-3xl mb-6">Users</h1>
    <form class="mb-4 flex" method="get">
      <input type="hidden" name="section" value="users">
      <input type="text" name="q" placeholder="Search name / email" value="<?=h($q)?>" class="mr-2">
      <button class="btn-primary text-sm">Search</button>
    </form>
    <?php if($users): ?>
    <div class="tableWrap mb-4">
      <table>
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Games</th><th>Win%</th><th></th></tr></thead>
        <tbody>
        <?php foreach($users as $u):
          $wr=$u['user_game_count']?round($u['win_count']*100/$u['user_game_count']).'%' : '—';?>
          <tr>
            <td><?= $u['user_id'] ?></td>
            <td><?=h($u['name'])?></td>
            <td><?=h($u['email'])?></td>
            <td>
              <?php if($u['user_id']==$me['id']): ?>
                <span class="italic"><?= $u['role'] ?></span>
              <?php else: ?>
                <form method="post" class="inline">
                  <input type="hidden" name="action" value="change_role">
                  <input type="hidden" name="uid" value="<?=$u['user_id']?>">
                  <select name="role" onchange="this.form.submit()">
                    <option <?= $u['role']=='user'?'selected':'' ?>>user</option>
                    <option <?= $u['role']=='admin'?'selected':'' ?>>admin</option>
                  </select>
                </form>
              <?php endif;?>
            </td>
            <td><?=$u['user_game_count']??0?></td>
            <td><?=$wr?></td>
            <td>
              <?php if($u['user_id']!=$me['id']): ?>
              <form method="post" onsubmit="return confirm('Delete user <?=h($u['name'])?> ?');" class="inline">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="uid" value="<?=$u['user_id']?>">
                <button class="btn btn-del" title="Delete"><i class="fas fa-trash"></i></button>
              </form>
              <?php endif;?>
            </td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php endif;?>
    <!-- pagination -->
    <?php if($total>$per): $pages=ceil($total/$per);?>
      <div class="pagination"><?php for($i=1;$i<=$pages;$i++): ?>
        <a href="?section=users&q=<?=urlencode($q)?>&p=<?=$i?>" class="<?= $i==$p?'current':'' ?>"><?=$i?></a>
      <?php endfor;?></div>
    <?php endif;?>

  <?php elseif($section==='maps'): ?>
    <h1 class="text-3xl mb-6">Maps</h1>
    <form class="mb-4 flex" method="get">
      <input type="hidden" name="section" value="maps">
      <input type="text" name="q" placeholder="Search map / author" value="<?=h($q)?>" class="mr-2">
      <button class="btn-primary text-sm">Search</button>
    </form>
    <?php if($maps): ?>
    <div class="tableWrap mb-4">
      <table>
        <thead><tr><th>ID</th><th>Name</th><th>Author</th><th>Size</th><th>Difficulty</th><th>Plays</th><th></th></tr></thead>
        <tbody>
        <?php foreach($maps as $m): ?>
          <tr>
            <td><?=$m['id']?></td><td><?=h($m['name'])?></td>
            <td><?=h($m['author']??'Base')?></td>
            <td><?=$m['size']?>×<?=$m['size']?></td>
            <td><?=$m['difficulty']?></td>
            <td><?=$m['map_game_count']?></td>
            <td>
              <?php if(!in_array($m['id'],[1,2,3,4])): ?>
              <form method="post" class="inline" onsubmit="return confirm('Delete map <?=h($m['name'])?> ?');">
               <input type="hidden" name="action" value="delete_map">
               <input type="hidden" name="mid" value="<?=$m['id']?>">
               <button class="btn btn-del" title="Delete"><i class="fas fa-trash"></i></button>
              </form>
              <?php else: ?><span class="text-xs text-gray-400">Base</span><?php endif;?>
            </td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php endif;?>
    <?php if($total>$per): $pages=ceil($total/$per);?>
      <div class="pagination"><?php for($i=1;$i<=$pages;$i++): ?>
        <a href="?section=maps&q=<?=urlencode($q)?>&p=<?=$i?>" class="<?= $i==$p?'current':'' ?>"><?=$i?></a>
      <?php endfor;?></div>
    <?php endif;?>
  <?php endif;?>
</div>
</body></html>
