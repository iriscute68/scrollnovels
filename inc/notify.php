<?php
// inc/notify.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/**
 * Create an in-site notification and optionally send email.
 * $user_id - int
 * $title - string
 * $body - string
 * $url - string (relative link)
 * $sendEmail - bool
 */
function notify_user(int $user_id, string $title, string $body = '', string $url = '', bool $sendEmail = false) {
  global $pdo;
  $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, body, url) VALUES (?, ?, ?, ?)");
  $stmt->execute([$user_id, $title, $body, $url]);

  if ($sendEmail) {
    // Minimal email (improve with a mailer lib)
    $user = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
    $user->execute([$user_id]);
    $u = $user->fetch();
    if ($u && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
      $to = $u['email'];
      $subject = "[Scroll Novels] $title";
      $plain = "Hello " . ($u['username'] ?? 'writer') . ",\n\n" . $body . "\n\nVisit: " . (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . "://" . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $url . "\n\n— Scroll Novels";

      // Prefer PHPMailer SMTP when available and configured
      if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
          $mail = new PHPMailer\PHPMailer\PHPMailer(true);
          $smtpHost = getenv('SMTP_HOST') ?: null;
          $smtpPort = getenv('SMTP_PORT') ?: 587;
          $smtpUser = getenv('SMTP_USER') ?: null;
          $smtpPass = getenv('SMTP_PASS') ?: null;
          $smtpFrom = getenv('SMTP_FROM') ?: 'no-reply@scrollnovels.com';

          if ($smtpHost && $smtpUser && $smtpPass) {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)$smtpPort;
          }

          $mail->setFrom($smtpFrom);
          $mail->addAddress($to, $u['username'] ?? '');
          $mail->Subject = $subject;
          $mail->Body = nl2br(htmlspecialchars($body)) . "<br><br>Visit: " . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
          $mail->isHTML(true);
          $mail->AltBody = $plain;
          $mail->send();
        } catch (Exception $e) {
          // fallback to mail()
          @mail($to, $subject, $plain);
        }
      } else {
        @mail($to, $subject, $plain);
      }
    }
  }
}
