<?php
/* logout.php – destroys the session, shows a flash, then returns to home */

require_once __DIR__ . '/includes/auth.php';   // loads helpers.php as well

logout_user();                                 // clear session & cookie
flash('success', 'You have been logged out.'); // optional message
redirect('index.php');                         // back to home
