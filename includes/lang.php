<?php
$lang = $_COOKIE['lang'] ?? 'en';
$dict = include "lang/$lang.php";
function __($key) { global $dict; return $dict[$key] ?? $key; }
?>