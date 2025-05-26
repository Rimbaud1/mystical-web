<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* -------------------------------------------------------------
 *  REGISTER
 * ----------------------------------------------------------- */
function register_user(string $username, string $email, string $password): bool
{
    global $pdo;

    // Unique user name OR e‑mail
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM User WHERE name = :n OR email = :e');
    $stmt->execute([':n' => $username, ':e' => $email]);
    if ($stmt->fetchColumn() > 0) {
        flash('error', 'Username or e‑mail already in use.');
        return false;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO User (name, email, password, role, creation_date)
                       VALUES (:n, :e, :p, "user", NOW())')
            ->execute([':n' => $username, ':e' => $email, ':p' => $hash]);

        $userId = (int) $pdo->lastInsertId();

        // Initial stats row
        $pdo->prepare('INSERT INTO Stats (user_id, played_time, current_level,
                                          user_game_count, win_count, money)
                       VALUES (:u, 0, 0, 0, 0, 0)')
            ->execute([':u' => $userId]);

        $pdo->commit();
        flash('success', 'Sign‑up successful! Please log in.');
        return true;

    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Error while creating the account.');
        return false;
    }
}

/* -------------------------------------------------------------
 *  LOGIN  (e‑mail only)
 * ----------------------------------------------------------- */
function login_user(string $email, string $password): bool
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT * FROM User WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id'   => (int) $user['user_id'],
            'name' => $user['name'],
            'role' => $user['role'],
        ];
        return true;
    }
    flash('error', 'Incorrect e‑mail or password.');
    return false;
}

/* ----------------------------------------------------------- */
function logout_user(): void      { unset($_SESSION['user']); session_destroy(); }
function is_logged_in(): bool     { return isset($_SESSION['user']); }
function current_user(): ?array   { return $_SESSION['user'] ?? null; }
