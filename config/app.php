<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Change this one value if your XAMPP folder has a different name.
 * Example: /stalk-stable
 */
defined('BASE_URL') || define('BASE_URL', '/stalk_stable');

defined('APP_NAME') || define('APP_NAME', 'Stalk & Stable');
defined('APP_TAGLINE') || define('APP_TAGLINE', 'Alcohol Distribution Management System');

date_default_timezone_set('Africa/Nairobi');

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
