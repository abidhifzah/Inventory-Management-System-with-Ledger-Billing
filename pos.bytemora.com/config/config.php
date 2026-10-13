<?php
/**
 * Application configuration.
 *
 * For LOCAL TESTING the defaults below work with a typical XAMPP/MAMP/LAMP
 * MySQL install (root user, no password). For PRODUCTION (cPanel shared
 * hosting) replace these with the database name/user/password created in
 * cPanel > MySQL Databases, and set APP_DEBUG to false.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'byteawys_pos_db');
define('DB_USER', 'byteawys_pos_user');
define('DB_PASS', '$46Id123$4I5_Ten$');

define('APP_NAME', 'Retail POS');
define('APP_CURRENCY_SYMBOL', 'Rs.');

// Show detailed PHP/DB errors (turn OFF in production).
define('APP_DEBUG', false);
