<?php
require_once __DIR__ . '/core/email.php';
require_once __DIR__ . '/authentication/database.php';

$pdo = getPdo();
// Replace 1 with the id of a real user in your users table.
var_dump(sendVerificationEmail($pdo, 1, 'you@example.com', 'testuser'));