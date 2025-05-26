<?php
/* ---------------------------------------------------------------
 * index.php — Mystical Dungeons landing / home
 * ------------------------------------------------------------- */
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/helpers.php';

$logged = is_logged_in();

/* ---- Top‑3 players by a composite score ---- */
$top = [];
// Formule de classement : win_rate * (1 + log10(nombre_de_parties))
// Cela favorise les joueurs avec un bon win_rate sur un nombre conséquent de parties.
$stmt = $pdo->query(
  "SELECT u.name,
          s.win_count,
          s.user_game_count,
          (CASE WHEN s.user_game_count = 0 THEN 0
                ELSE ROUND((s.win_count / s.user_game_count) * 100) END) AS wr,
          (CASE WHEN s.user_game_count = 0 THEN 0 
                ELSE (s.win_count / s.user_game_count) * (1 + LOG10(s.user_game_count)) 
           END) AS ranking_score
   FROM Stats s
   JOIN User u ON u.user_id = s.user_id
   WHERE s.user_game_count > 0 -- Assure que user_game_count est au moins 1 pour LOG10 et la division
   ORDER BY ranking_score DESC, s.user_game_count DESC -- Classement principal par score, puis par nombre de parties
   LIMIT 3"
);
$top = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mystical Dungeons</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=MedievalSharp&display=swap');
        
        body {
            font-family: 'MedievalSharp', cursive;
            background: #0f0e17;
            color: #e8e8e8;
            background-image: radial-gradient(circle at 10% 20%, rgba(255,137,6,.08) 0, transparent 20%), 
                              radial-gradient(circle at 90% 80%, rgba(229,49,112,.08) 0, transparent 20%);
            overflow: hidden; 
        }
        
        .hero-gradient {
            background: linear-gradient(135deg, rgba(15,14,23,.9) 0%, rgba(26,26,46,.95) 100%);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff8906 0%, #e53170 100%);
            transition: .3s;
            box-shadow: 0 4px 18px rgba(229, 49, 112, .35);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(229, 49, 112, .45);
        }
        
        .text-gradient {
            background: linear-gradient(135deg, #ff8906 0%, #e53170 100%);
            -webkit-background-clip: text;
            color: transparent;
        }

        .player-card{
            background:rgba(26,26,46,.85);
            border:1px solid rgba(255,137,6,.25);
            transition:.3s
        }
        .player-card:hover{
            transform:translateY(-3px);
            box-shadow:0 5px 15px rgba(255,137,6,.2)
        }
        
        /* Animation Styles - MODIFIED FOR SPEED */
        .logo-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #0f0e17;
            z-index: 9999;
            animation: fadeOut 0.7s ease-in-out 4.3s forwards; /* Duration 0.7s, Delay 4.3s -> ends at 5s */
        }
        
        .logo-animation {
            position: relative;
            text-align: center;
        }
        
        .logo-text {
            font-size: 5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #ff8906 0%, #e53170 100%);
            -webkit-background-clip: text;
            color: transparent;
            opacity: 0;
            transform: scale(0.5);
            animation: textAppear 1s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.2s forwards; /* Duration 1s, Delay 0.2s */
        }
        
        .sword {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(45deg) scale(0);
            font-size: 8rem;
            color: #ff8906;
            opacity: 0;
            animation: swordAppear 0.8s ease-in-out 0.8s forwards; /* Duration 0.8s, Delay 0.8s */
        }
        
        .sparks {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            animation: sparksAppear 1s ease-in-out 1.5s forwards; /* Duration 1s, Delay 1.5s */
        }
        
        .spark {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #e53170;
            border-radius: 50%;
            box-shadow: 0 0 10px 2px #e53170;
        }
        
        .subtitle {
            font-size: 1.5rem;
            color: #a7a9be;
            margin-top: 1rem;
            opacity: 0;
            transform: translateY(20px);
            animation: subtitleAppear 0.7s ease-out 2s forwards; /* Duration 0.7s, Delay 2s */
        }
        
        .loading-bar {
            width: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff8906, #e53170);
            margin: 2rem auto 0;
            border-radius: 2px;
            animation: loadingBar 1.8s linear 2.5s forwards; /* Duration 1.8s, Delay 2.5s -> ends at 4.3s */
        }
        
        @keyframes textAppear {
            0% { opacity: 0; transform: scale(0.5); }
            80% { opacity: 1; transform: scale(1.1); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        @keyframes swordAppear {
            0% { opacity: 0; transform: translate(-50%, -50%) rotate(45deg) scale(0); }
            50% { opacity: 1; transform: translate(-50%, -50%) rotate(0deg) scale(1.2); }
            100% { opacity: 1; transform: translate(-50%, -50%) rotate(0deg) scale(1); }
        }
        
        @keyframes sparksAppear {
            0% { opacity: 0; }
            20% { opacity: 1; }
            100% { opacity: 0; }
        }
        
        @keyframes subtitleAppear {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes loadingBar {
            0% { width: 0; }
            100% { width: 100%; }
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
    </style>
</head>
<body>
    <!-- Logo Animation -->
    <div class="logo-container" id="logoAnimation">
        <div class="logo-animation">
            <div class="logo-text">Mystical Dungeons</div>
            <div class="sword">
                <i class="fas fa-sword"></i>
            </div>
            <div class="sparks" id="sparksContainer"></div>
            <div class="subtitle">Enter the labyrinth of legends</div>
            <div class="loading-bar-container" style="width: 200px; margin: 2rem auto 0;">
                 <div class="loading-bar"></div>
            </div>
        </div>
    </div>

    <!-- Main Content (hidden initially) -->
    <div style="display: none;" id="mainContent">
        <?php require __DIR__.'/includes/navbar.php'; ?>

        <!-- Hero -->
        <section class="hero-gradient min-h-screen flex items-center pt-20 pb-20 px-4">
         <div class="container mx-auto">
           <div class="max-w-3xl mx-auto text-center">
             <h1 class="text-5xl md:text-6xl font-bold mb-6 text-gradient">Mystical Dungeons</h1>
             <p class="text-xl text-gray-300 mb-8">Explore random dungeons, craft your own labyrinths and challenge the community!</p>

             <div class="flex flex-col sm:flex-row justify-center gap-4">
               <a href="<?= $logged ? 'play.php' : 'login-signup.php' ?>" class="btn-primary px-8 py-3 rounded-lg font-bold text-white">
                 <i class="fas fa-play mr-2"></i> Play Now
               </a>
               <a href="<?= $logged ? 'create_map.php' : 'login-signup.php' ?>" class="bg-gray-800 hover:bg-gray-700 px-8 py-3 rounded-lg font-bold text-white border border-amber-500/30">
                 <i class="fas fa-map mr-2"></i> Create Map
               </a>
               <?php if($logged): ?>
                 <a href="profile.php" class="bg-gray-800 hover:bg-gray-700 px-8 py-3 rounded-lg font-bold text-white border border-amber-500/30">
                   <i class="fas fa-user mr-2"></i> Profile
                 </a>
               <?php endif; ?>
             </div>
           </div>
         </div>
        </section>

        <!-- Top players -->
        <section class="py-16 px-4 bg-gray-900/50">
         <div class="container mx-auto">
           <h2 class="text-3xl font-bold mb-12 text-center text-gradient"><i class="fas fa-crown mr-2"></i> Top Players</h2>
           <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
             <?php foreach($top as $p): ?>
               <div class="player-card rounded-lg p-6">
                 <div class="mb-4"> 
                   <div><h3 class="text-xl font-bold text-amber-400"><?=h($p['name'])?></h3></div>
                 </div>
                 <div class="flex justify-between">
                   <div><p class="text-gray-400">Wins</p><p class="text-xl font-bold"><?=h($p['win_count'])?></p></div>
                   <div><p class="text-gray-400">Games</p><p class="text-xl font-bold"><?=h($p['user_game_count'])?></p></div>
                   <div><p class="text-gray-400">Winrate</p><p class="text-xl font-bold text-green-400"><?=h($p['wr'])?>%</p></div>
                 </div>
               </div>
             <?php endforeach; ?>
             <?php if (empty($top)): ?>
                <p class="text-center text-gray-400 md:col-span-3">No players with completed games yet. Be the first!</p>
             <?php endif; ?>
           </div>
         </div>
        </section>

        <!-- Game description -->
        <section class="py-16 px-4">
         <div class="container mx-auto max-w-4xl">
           <div class="text-center mb-12">
             <h2 class="text-3xl font-bold mb-4 text-gradient">The Game</h2>
             <p class="text-xl text-gray-300">Mystical Dungeons is an online maze game set in a medieval fantasy universe.</p>
           </div>
           <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
             <div>
               <h3 class="text-2xl font-bold text-amber-400 mb-4">Features</h3>
               <ul class="space-y-3 text-gray-300">
                 <li class="flex"><i class="fas fa-random text-amber-500 mr-3 mt-1"></i>Random dungeon generator and 4 base difficulty levels</li>
                 <li class="flex"><i class="fas fa-edit text-amber-500 mr-3 mt-1"></i>Custom map creation and sharing with all players</li>
                 <li class="flex"><i class="fas fa-lightbulb text-amber-500 mr-3 mt-1"></i>Integrated solver to find solutions</li>
               </ul>
             </div>
             <div class="flex justify-center items-center">
               <img src="./assets/images/preview.png" alt="Preview" class="rounded-lg border border-amber-500/30 shadow-lg">
             </div>
           </div>
         </div>
        </section>

        <!-- CTA -->
        <section class="py-16 px-4 bg-gray-900/50">
         <div class="container mx-auto text-center">
           <h2 class="text-3xl font-bold mb-6 text-gradient">Ready to explore?</h2>
           <p class="text-xl text-gray-300 mb-8 max-w-2xl mx-auto">Join our community of adventurers and begin your quest today!</p>
           <a href="<?= $logged ? 'play.php' : 'login-signup.php' ?>" class="btn-primary inline-block px-8 py-3 rounded-lg text-white font-bold text-lg">
             <i class="fas fa-play mr-2"></i> Play for Free
           </a>
         </div>
        </section>

        <!-- Footer -->
        <footer class="py-8 px-4 border-t border-gray-800">
         <div class="container mx-auto text-center">
           <p class="text-gray-500">© 2025 Mystical Dungeons. All rights reserved.</p>
         </div>
        </footer>
    </div>

    <script>
        function createSparks() {
            const container = document.getElementById('sparksContainer');
            if (!container) return;
            const sparksCount = 40; // Slightly fewer for faster overall feel
            
            for (let i = 0; i < sparksCount; i++) {
                const spark = document.createElement('div');
                spark.className = 'spark';
                
                const x = Math.random() * 100;
                const y = Math.random() * 100;
                // Shorter delay to fit within the new 1s sparksAppear animation
                const delay = Math.random() * 0.8; 
                
                spark.style.left = `${x}%`;
                spark.style.top = `${y}%`;
                
                const size = 1.5 + Math.random() * 2.5; // Slightly smaller sparks
                spark.style.width = `${size}px`;
                spark.style.height = `${size}px`;
                
                const hue = 30 + Math.random() * 30;
                spark.style.background = `hsl(${hue}, 90%, 60%)`;
                spark.style.boxShadow = `0 0 6px 1px hsl(${hue}, 90%, 60%)`; // Smaller shadow
                
                // Faster individual sparkle animation
                spark.style.animation = `individualSparkle ${0.3 + Math.random() * 0.4}s ease-in-out ${delay}s infinite alternate`;
                
                container.appendChild(spark);
            }
            
            if (!document.getElementById('sparkleAnimationStyle')) {
                const style = document.createElement('style');
                style.id = 'sparkleAnimationStyle';
                style.textContent = `
                    @keyframes individualSparkle {
                        0% { opacity: 0; transform: scale(0.3); }
                        50% { opacity: 1; transform: scale(1); }
                        100% { opacity: 0; transform: scale(0.3); }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            createSparks();
            
            // MODIFIED: New total duration is ~5 seconds
            setTimeout(() => {
                const logoAnimation = document.getElementById('logoAnimation');
                const mainContent = document.getElementById('mainContent');
                
                if (logoAnimation) {
                    logoAnimation.style.display = 'none';
                }
                if (mainContent) {
                    mainContent.style.display = 'block';
                }
                document.body.style.overflow = 'auto';
            }, 5000); // 5 seconds
        });
    </script>
</body>
</html>