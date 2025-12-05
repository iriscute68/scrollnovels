<?php
// inc/slugify.php
/**
 * Convert a string to a URL-friendly slug
 */
function slugify(string $text): string {
  $text = strtolower($text);
  $text = preg_replace('/[^a-z0-9]+/', '-', $text);
  $text = trim($text, '-');
  return $text;
}
