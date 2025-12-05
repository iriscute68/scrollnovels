<?php
// Google OAuth Configuration
// Only load Google client if Composer autoload exists.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
	require_once $autoload;
	if (class_exists('Google_Client')) {
		$googleClient = new Google_Client();
		$googleClient->setClientId('14679695374-2ouitfeqp4mso0h2vnu17avhhnqqe5ei.apps.googleusercontent.com');
		$googleClient->setClientSecret('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');
		$googleClient->setRedirectUri('http://localhost/pages/google-callback.php');
		$googleClient->addScope('email');
		$googleClient->addScope('profile');
		return $googleClient;
	}
}

// Fallback: return null if Google client unavailable.
return null;
