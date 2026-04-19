<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function send_rating_notify(array $r): void {
    $title    = $r['recipe_title'];
    $url      = 'https://dutchgoose.nl' . $r['recipe_url'];
    $stars    = $r['stars'];
    $name     = $r['name'];
    $comment  = $r['comment'] ?: '(geen comment)';
    $ip_short = substr($r['ip_hash'], 0, 8);
    $time_nl  = human_date_nl($r['created_at']);

    $subject = "[Dutch Goose] Nieuwe rating: {$title} - {$stars}\u{2605}";

    $body = "Nieuwe rating ontvangen op Dutch Goose.\n\n"
          . "Recept: {$title}\n"
          . "URL: {$url}\n"
          . "Sterren: {$stars}/5\n"
          . "Naam: {$name}\n"
          . "Comment: {$comment}\n"
          . "IP-hash: {$ip_short}\n"
          . "Tijd: {$time_nl}\n\n"
          . "Modereren: https://dutchgoose.nl/api/moderatie.php\n";

    $headers = implode("\r\n", [
        'From: ' . FROM_EMAIL,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: Dutch-Goose-Ratings/1.0',
    ]);

    $ok = mail(NOTIFY_EMAIL, $subject, $body, $headers);

    $log_line = date('Y-m-d H:i:s') . ' | to=' . NOTIFY_EMAIL
              . ' | recipe=' . $r['recipe_url']
              . ' | stars=' . $stars
              . ' | ok=' . ($ok ? '1' : '0') . "\n";

    file_put_contents(__DIR__ . '/data/email.log', $log_line, FILE_APPEND | LOCK_EX);
}
