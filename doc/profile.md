# 👤 profile.md — Profile Page Documentation

## 🔍 Purpose
This page allows logged-in users to manage their **profile settings** and view detailed **game statistics**. It includes:
- Username modification
- Password change
- Player statistics (games, wins, losses, winrate, time played, gold, max level, ranking)

---

## 🗂️ Files Involved

### `/profile.php`
- Displays user profile and statistics.
- Handles form submissions to update username or password.
- Requires user to be authenticated.

### `/includes/auth.php`
- `is_logged_in()` — Checks if session exists.
- `current_user()` — Retrieves session user info.
- `logout_user()` — Logs user out.

### `/includes/helpers.php`
- `flash($type, $msg)` — Stores feedback message (success or error).
- `get_flashes()` — Retrieves messages and clears them.
- `redirect($url)` — Safely redirects to another page.
- `h($str)` — Escapes output for HTML.

### `/includes/db.php`
- Establishes the `$pdo` connection to MySQL using PDO.

### `/includes/navbar.php`
- Injects a dynamic navigation bar that adapts based on session.
- Includes dropdown on "Account" with links to profile, logout (and admin if role = 'admin').

---

## 🧠 Features

### ✅ Username Change
- Validates format (3–20 chars, alphanum, `-`, `_`).
- Ensures uniqueness in database.
- Updates session after change.

### ✅ Password Change
- Requires current password.
- New password must be ≥ 6 characters and match confirmation.
- Passwords stored using `password_hash()`.

---

## 📊 Player Statistics
Fetched from the `Stats` table (linked via `user_id`).

Displayed stats:
- **Games Played**
- **Wins**
- **Losses** = games - wins
- **Winrate** = wins / games (% if games > 0)
- **Time Played**: in hours + minutes (based on seconds stored)
- **Gold**: value in `Stats.money`
- **Max Level Reached**
- **Ranking**: calculated based on winrate percentile

---

## 🔐 Security & Access
- If not logged in → redirects to `login-signup.php`.
- All updates are server-side validated.
- Flash messages are shown for feedback.

---

## 🧩 Database Tables Used

### `User`
- `user_id`, `name`, `email`, `password`, `role`

### `Stats`
- `user_id`, `user_game_count`, `win_count`, `played_time`, `current_level`, `money`

---

## 🧪 Validation Summary
- **Username**: validated via regex in PHP.
- **Password**: validated (length + confirmation).
- **All fields**: trimmed and sanitized.
- Flash errors on all invalid attempts.

---

## 💡 UI/UX Notes
- Built with **TailwindCSS**
- Medieval theme
- Dynamic stats shown with colored icons and progress bars
- Click-to-toggle Account menu in navbar
- Flash messages styled with Tailwind background + text colors

---

## 📌 Notes
- `Money` stat added in May 2025 to show player currency.
- Ranking based on global winrate percentile with dynamic query.
- Flash messages persist only until next page load (session-based).

---

## 🔗 Related Pages
- `login-signup.php` → user creation and login
- `logout.php` → session destroy and redirect
- `admin.php` → optional (only shown in navbar if `role === 'admin'`)
