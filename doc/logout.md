# 🚪 logout.md — Logout Page Documentation

## 🔍 Purpose

This file is responsible for securely **ending a user session** and redirecting them back to the homepage (`index.php`). It is a simple and essential part of the authentication system.

---

## 🗂️ File

### `logout.php`

Location: `/htdocs/logout.php`

---

## 🔐 Functionality

### ✅ What it does:

1. **Destroys the session** using the function `logout_user()`.
2. **Optionally sets a flash message** to inform the user they have been logged out.
3. **Redirects** the user to `index.php` (homepage).

---

## 📄 Code Summary

```php
<?php
require_once __DIR__ . '/includes/auth.php';

logout_user();                      // Clears the session
flash('success', 'You have been logged out.'); // (Optional) success message
redirect('index.php');             // Redirects to home
```

---

## ⚙️ Used Functions

All functions are defined in `includes/auth.php` and `includes/helpers.php`.

### From `auth.php`

* `logout_user()`

  * Calls `unset($_SESSION['user'])` and `session_destroy()`.

### From `helpers.php`

* `flash($type, $message)`

  * Stores a temporary message to be displayed on the next page load.
* `redirect($url)`

  * Safely redirects to a given URL using `header()` and `exit;`

---

## ✅ Usage

Just link a logout button or menu item to:

```html
<a href="logout.php">Log Out</a>
```

No additional HTML or JavaScript is needed.

---

## 🔗 Related Pages

* `login-signup.php` → for creating and destroying sessions.
* `index.php` → the redirect target after logout.
* `includes/auth.php` → contains the session handling logic.
* `includes/helpers.php` → contains redirect and flash utilities.

---

## 🧠 Tip

Protect any user-only pages by checking:

```php
if (!is_logged_in()) {
    redirect('login-signup.php');
}
```

This ensures logged-out users can’t access protected areas after logging out.
