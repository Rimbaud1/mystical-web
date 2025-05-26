# 📜 login-signup.md — Authentication Page Documentation

## 🔍 Purpose

This page allows users to either **log in** or **sign up** to the Mystical Dungeons website. It is a **dual-form page**, styled with TailwindCSS, fully in English, and handles all backend logic via PHP with session management.

---

## 🗂️ Files Used

### 1. `login-signup.php`

* Frontend form with two modes: **login** and **sign-up**.
* Processes `POST` requests.
* Displays flash messages (errors or success).
* On success: redirects to `index.php`.

### 2. `/includes/auth.php`

* Contains logic for:

  * `register_user()` – Registers a new user.
  * `login_user()` – Logs in a user using **e-mail + password** only.
  * `logout_user()` – Ends the session.
  * `is_logged_in()` – Checks if user is logged in.
  * `current_user()` – Gets current session user info.

### 3. `/includes/helpers.php`

* Contains:

  * `flash($type, $msg)` – Stores flash messages.
  * `get_flashes()` – Retrieves and clears flash messages.
  * `h($str)` – Sanitizes text output.

### 4. `bdd_mystical_dungeon.sql`

* Required MySQL structure.
* Tables used:

  * `User (user_id, name, email, password, role, creation_date)`
  * `Stats (user_id, played_time, current_level, user_game_count, win_count, money)`

---

## 💡 Features

### ✅ Login

* Fields:

  * Email (required, type="email")
  * Password (required, min 6 chars)
* Logic:

  * Validates credentials using `login_user()`
  * On success: sets session, redirects to `index.php`

### ✅ Sign Up

* Fields:

  * Username (alphanumeric, 3–20 chars)
  * Email (valid format)
  * Password + Confirm Password (match required, min 6 chars)
* Logic:

  * Checks if username or email already exist.
  * Inserts into `User` and `Stats` tables.
  * Automatically logs the user in after registration.
  * Redirects to `index.php`

---

## 💬 Flash Messages

Displayed using Tailwind classes. Types:

* `error`: red
* `success`: green

Example:

```php
flash('error', 'Incorrect email or password.');
```

---

## 🎨 Styling

* Fully themed with **TailwindCSS**
* Font: "MedievalSharp" from Google Fonts
* Custom torch light effects using CSS animations
* Fully responsive
* Tabs for toggling forms

---

## 🔄 Javascript

* Manages toggle between forms:

  * Top tab buttons
  * Bottom inline links ("No account yet? Sign Up")
* Supports anchor `#signup` in URL for auto-activating sign-up form.

```js
if (location.hash === '#signup') showSignup();
```

---

## 🔐 Session Management

* Upon login, stores user info in `$_SESSION['user']`
* Used across the site to track current user.

---

## 🧪 Validation (HTML + PHP)

* All inputs validated client-side (HTML5 attributes)
* Also revalidated server-side before inserting into DB or logging in.

---

## 🧩 Dependencies

* `PHP >= 7.4`
* `MySQL`
* `Tailwind CSS`
* `Font Awesome`

---

## ✅ To Do After Auth

Once connected:

* Users are redirected to `index.php`
* All other pages can check `is_logged_in()` or `current_user()` to personalize content or protect routes.

---

## 🧠 Tips

* Use `$_SESSION` to check logged-in state.
* Protect private pages with:

```php
if (!is_logged_in()) {
    header('Location: login-signup.php');
    exit;
}
```

---

## 🔗 Related Pages

* `logout.php` → calls `logout_user()` and redirects to home.
* `profile.php` → uses `current_user()` to show stats.
* `navbar.php` → adjusts links/buttons based on `is_logged_in()` and user role.
