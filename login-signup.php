<?php
require_once __DIR__ . '/includes/auth.php';

/* ------------ HANDLE POST ------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* -------- SIGN‑UP -------- */
    if ($action === 'signup') {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm']  ?? '';

        if ($password !== $confirm) {
            flash('error', 'Passwords do not match.');
            header('Location: login-signup.php#signup');
            exit;
        }

        $ok = register_user(
            trim($_POST['username'] ?? ''),
            trim($_POST['email']    ?? ''),
            $password
        );

        /* success → auto‑login then home */
        if ($ok && login_user(trim($_POST['email']), $password)) {
            header('Location: index.php');
            exit;
        }

        /* failure → back to sign‑up */
        header('Location: login-signup.php#signup');
        exit;
    }

    /* -------- LOG‑IN (e‑mail only) -------- */
    if ($action === 'login') {
        if (login_user(trim($_POST['email'] ?? ''), $_POST['password'] ?? '')) {
            header('Location: index.php');
            exit;
        }
        /* wrong credentials */
        header('Location: login-signup.php');
        exit;
    }
}

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mystical Dungeons – Auth</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Original themed CSS -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=MedievalSharp&display=swap');
        body{font-family:'MedievalSharp',cursive;background:#0f0e17;color:#e8e8e8;background-image:radial-gradient(circle at 10% 20%,rgba(255,137,6,.08)0%,transparent 20%),radial-gradient(circle at 90% 80%,rgba(229,49,112,.08)0%,transparent 20%)}
        .auth-container{background:rgba(26,26,46,.85);backdrop-filter:blur(12px);border:1px solid rgba(255,137,6,.25);box-shadow:0 0 40px rgba(255,137,6,.15)}
        .form-input{background:rgba(58,58,58,.6);border:1px solid rgba(255,137,6,.4);transition:all .3s ease-out}
        .form-input:focus{border-color:#ff8906;box-shadow:0 0 12px rgba(255,137,6,.6);background:rgba(58,58,58,.8)}
        .auth-btn{background:linear-gradient(135deg,#ff8906 0%,#e53170 100%);transition:all .3s cubic-bezier(.4,0,.2,1);box-shadow:0 4px 18px rgba(229,49,112,.35)}
        .auth-btn:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(229,49,112,.45)}
        .toggle-form{position:relative;overflow:hidden;transition:all .3s ease}
        .toggle-form::after{content:'';position:absolute;bottom:0;left:0;width:0;height:3px;background:#ff8906;transition:width .3s ease-out}
        .toggle-form:hover::after{width:100%}
        .torch-effect{position:absolute;width:180px;height:180px;background:radial-gradient(circle,rgba(255,137,6,.45)0%,transparent 70%);border-radius:50%;filter:blur(24px);z-index:-1;animation:flicker 5s infinite alternate}
        @keyframes flicker{0%,100%{opacity:.7;transform:scale(1)}25%{opacity:.5;transform:scale(.95)}50%{opacity:.8;transform:scale(1.05)}75%{opacity:.6;transform:scale(.98)}}
        .password-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        @media (max-width:640px){.password-row{grid-template-columns:1fr}}
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <!-- Decorative torches -->
    <div class="torch-effect" style="top:10%;left:10%"></div>
    <div class="torch-effect" style="bottom:15%;right:15%;animation-delay:1s"></div>

    <!-- Main container -->
    <div class="auth-container rounded-xl overflow-hidden w-full max-w-md relative z-10">

        <!-- Flash messages -->
        <?php if ($flashes): ?>
            <div class="px-8 pt-6">
                <?php foreach ($flashes as $type => $msgs): ?>
                    <?php foreach ($msgs as $msg): ?>
                        <p class="mb-2 text-sm
                                   <?= $type === 'error'
                                        ? 'text-red-400'
                                        : 'text-emerald-400' ?>">
                            <?= h($msg) ?>
                        </p>
                    <?php endforeach ?>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <!-- Tabs -->
        <div class="flex border-b border-gray-700">
            <button id="login-tab"
                    class="toggle-form flex-1 py-4 text-center font-bold text-amber-400 bg-gray-900">
                <i class="fas fa-door-open mr-2"></i>Log&nbsp;In
            </button>
            <button id="signup-tab"
                    class="toggle-form flex-1 py-4 text-center font-bold text-gray-400 hover:text-amber-400">
                <i class="fas fa-scroll mr-2"></i>Sign&nbsp;Up
            </button>
        </div>

        <!-- Forms -->
        <div class="p-8">
            <!-- LOGIN -->
            <form id="login-form" class="space-y-6" method="POST" action="">
                <input type="hidden" name="action" value="login">

                <div class="text-center mb-6">
                    <i class="fas fa-dungeon text-5xl text-amber-500 mb-2"></i>
                    <h2 class="text-2xl font-bold bg-gradient-to-r from-amber-500 to-red-500 bg-clip-text text-transparent">
                        Mystical Dungeons
                    </h2>
                    <p class="text-gray-400 mt-2">Enter the mystical realm</p>
                </div>

                <div>
                    <label class="block text-gray-400 mb-2">E‑mail</label>
                    <input name="email" required type="email"
                           class="form-input w-full px-4 py-3 rounded-lg text-white placeholder-gray-500"
                           placeholder="your@mail.com">
                </div>

                <div>
                    <label class="block text-gray-400 mb-2">Password</label>
                    <input name="password" required minlength="6" type="password"
                           class="form-input w-full px-4 py-3 rounded-lg text-white placeholder-gray-500"
                           placeholder="Your secret password">
                </div>

                <button type="submit" class="auth-btn w-full py-3 rounded-lg text-white font-bold">
                    Enter the dungeon <i class="fas fa-arrow-right ml-2"></i>
                </button>

                <div class="text-center text-gray-500 text-sm">
                    No account yet?
                    <button type="button" id="link-to-signup"
                            class="text-amber-400 hover:underline">Sign&nbsp;Up</button>
                </div>
            </form>

            <!-- SIGN‑UP -->
            <form id="signup-form" class="space-y-6 hidden" method="POST" action="">
                <input type="hidden" name="action" value="signup">

                <div class="text-center mb-6">
                    <i class="fas fa-scroll text-5xl text-purple-500 mb-2"></i>
                    <h2 class="text-2xl font-bold bg-gradient-to-r from-purple-500 to-blue-500 bg-clip-text text-transparent">
                        Join the Adventure
                    </h2>
                    <p class="text-gray-400 mt-2">Create your legend</p>
                </div>

                <div>
                    <label class="block text-gray-400 mb-2">Username</label>
                    <input name="username" required
                           pattern="^[A-Za-z0-9_-]{3,20}$" minlength="3" maxlength="20"
                           class="form-input w-full px-4 py-3 rounded-lg text-white placeholder-gray-500"
                           placeholder="Choose your hero name">
                </div>

                <div>
                    <label class="block text-gray-400 mb-2">E‑mail</label>
                    <input name="email" required type="email"
                           class="form-input w-full px-4 py-3 rounded-lg text-white placeholder-gray-500"
                           placeholder="your@mail.com">
                </div>

                <div class="password-row">
                    <div>
                        <label class="block text-gray-400 mb-2">Password</label>
                        <input name="password" required minlength="6" type="password"
                               class="form-input w-full px-4 py-3 rounded-lg text-white placeholder-gray-500"
                               placeholder="Create a strong password">
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-2">Confirm Password</label>
                        <input name="confirm" required minlength="6" type="password"
                               class="form-input w-full px-4 py-3 rounded-lg text-white placeholder-gray-500"
                               placeholder="Repeat your password">
                    </div>
                </div>

                <button type="submit"
                        class="auth-btn w-full py-3 rounded-lg text-white font-bold"
                        style="background:linear-gradient(135deg,#9f7aea 0%,#2563eb 100%)">
                    Begin Adventure <i class="fas fa-dragon ml-2"></i>
                </button>

                <div class="text-center text-gray-500 text-sm">
                    Already have an account?
                    <button type="button" id="link-to-login"
                            class="text-amber-400 hover:underline">Log&nbsp;In</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Switch logic -->
    <script>
        const loginTab   = document.getElementById('login-tab');
        const signupTab  = document.getElementById('signup-tab');
        const loginForm  = document.getElementById('login-form');
        const signupForm = document.getElementById('signup-form');
        const toSignup   = document.getElementById('link-to-signup');
        const toLogin    = document.getElementById('link-to-login');

        function showLogin() {
            loginTab.classList.add('text-amber-400','bg-gray-900');
            signupTab.classList.remove('text-amber-400','bg-gray-900');
            signupTab.classList.add('text-gray-400');
            loginTab.classList.remove('text-gray-400');
            loginForm.classList.remove('hidden');
            signupForm.classList.add('hidden');
        }
        function showSignup() {
            signupTab.classList.add('text-amber-400','bg-gray-900');
            loginTab.classList.remove('text-amber-400','bg-gray-900');
            loginTab.classList.add('text-gray-400');
            signupTab.classList.remove('text-gray-400');
            signupForm.classList.remove('hidden');
            loginForm.classList.add('hidden');
        }

        loginTab.addEventListener('click', showLogin);
        signupTab.addEventListener('click', showSignup);
        toSignup .addEventListener('click', showSignup);
        toLogin  .addEventListener('click', showLogin);

        // If redirected with #signup, activate the correct tab
        if (location.hash === '#signup') showSignup();
    </script>
</body>
</html>
