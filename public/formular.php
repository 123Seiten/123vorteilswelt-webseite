<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Vienna');
error_reporting(E_ALL);
ini_set('display_errors', '0');

require __DIR__ . '/lib/phpmailer/Exception.php';
require __DIR__ . '/lib/phpmailer/PHPMailer.php';
require __DIR__ . '/lib/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=utf-8');

function antwortOk(?string $hinweis = null): never {
    echo json_encode(['ok' => true, 'hinweis' => $hinweis]); exit;
}
function antwortFehler(string $code): never {
    echo json_encode(['ok' => false, 'fehler' => $code]); exit;
}
function feld(string $name): string { return trim((string) ($_POST[$name] ?? '')); }
function kopfzeileSaeubern(string $wert): string {
    return trim(str_replace(["\r", "\n", '%0a', '%0d', '%0A', '%0D'], '', $wert));
}
function datenVerzeichnis(): string {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
    $ausserhalb = rtrim(dirname($docRoot), '/\\') . '/formular-daten';
    if ((is_dir($ausserhalb) || @mkdir($ausserhalb, 0700, true)) && is_writable($ausserhalb)) {
        return $ausserhalb;
    }
    return __DIR__;
}
function rateLimitErreicht(string $pfad, string $schluessel, int $maxProStunde): bool {
    $jetzt = time();
    $handle = @fopen($pfad, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if ($handle) fclose($handle);
        return false;
    }
    $inhalt = stream_get_contents($handle) ?: '';
    $eintraege = [];
    foreach (preg_split('/\r?\n/', $inhalt) as $zeile) {
        $zeile = trim($zeile);
        if ($zeile === '' || !str_contains($zeile, ':')) continue;
        [$k, $t] = explode(':', $zeile, 2);
        $t = (int) $t;
        if ($jetzt - $t < 3600) $eintraege[] = [$k, $t];
    }
    $vorhandene = 0;
    foreach ($eintraege as $e) if ($e[0] === $schluessel) $vorhandene++;
    $eintraege[] = [$schluessel, $jetzt];
    ftruncate($handle, 0); rewind($handle);
    foreach ($eintraege as $e) fwrite($handle, $e[0] . ':' . $e[1] . "\n");
    fflush($handle); flock($handle, LOCK_UN); fclose($handle);
    return ($vorhandene + 1) > $maxProStunde;
}
function mailVersenden(array $config, string $an, string $betreff, string $text,
                       string $replyToAdresse = '', string $replyToName = ''): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['secure'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom($config['from'], $config['fromName']);
        $mail->addAddress($an);
        if ($replyToAdresse !== '') $mail->addReplyTo($replyToAdresse, $replyToName);
        $mail->Subject = $betreff;
        $mail->isHTML(false);
        $mail->Body = $text;
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

// 1 · Nur POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); antwortFehler('methode'); }
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { antwortFehler('upload'); }

// 2 · Konfiguration
$configPfad = __DIR__ . '/mail-config.php';
if (!is_file($configPfad)) antwortFehler('konfiguration');
$config = require $configPfad;

// 3 · Honeypot
if (!empty($_POST['_gotcha'])) antwortOk(null);

// Formulartyp
$formular = $_POST['formular'] ?? '';
if (!in_array($formular, ['anfrage', 'franchise'], true)) antwortFehler('formular');

// 4 · Zeitprüfung (< 3s = Bot)
$ts = (float) ($_POST['_ts'] ?? 0);
if ($ts <= 0 || (microtime(true) * 1000 - $ts) < 3000) antwortFehler('validierung');

// 5 · Sperrlisten
$datenDir = datenVerzeichnis();
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$emailRoh = feld('email');
$emailHash = hash('sha256', strtolower($emailRoh));
$ipLimit = rateLimitErreicht($datenDir . '/_rate-ip.txt', $ip, 3);
$mailLimit = rateLimitErreicht($datenDir . '/_rate-mail.txt', $emailHash, 2);
$bestaetigungGrund = $ipLimit ? 'IP-Grenze überschritten, max. 3 pro Stunde'
    : ($mailLimit ? 'E-Mail-Grenze überschritten, max. 2 pro Stunde' : null);
$bestaetigungVersuchen = $bestaetigungGrund === null;
$hinweisCode = $ipLimit ? 'rate-ip' : ($mailLimit ? 'rate-mail' : null);

// 6 · Pflichtfelder
$name = feld('name');
$email = $emailRoh;
$telefon = feld('telefon');
$termin = feld('termin');
if ($formular === 'anfrage') {
    $plz = feld('plz');
    if ($name === '' || $email === '' || $plz === '' || empty($_POST['datenschutz'])) antwortFehler('validierung');
} else {
    $bestehenderBetrieb = feld('bestehender_betrieb');
    $regionenRoh = array_map('strval', $_POST['regionen'] ?? []);
    if ($name === '' || $email === '' || $bestehenderBetrieb === '' || $regionenRoh === [] || empty($_POST['datenschutz'])) antwortFehler('validierung');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) antwortFehler('validierung');

// 7 · Säubern
$name = kopfzeileSaeubern($name);
$email = kopfzeileSaeubern($email);
$telefon = kopfzeileSaeubern($telefon);
$termin = kopfzeileSaeubern($termin);
$datum = date('d.m.Y'); $uhrzeit = date('H:i');

// 8 · Interne Mail
if ($formular === 'anfrage') {
    $firma = kopfzeileSaeubern(feld('firma'));
    $bereiche = array_map('kopfzeileSaeubern', array_map('strval', $_POST['bereiche'] ?? []));
    $bereicheAnzeige = $bereiche !== [] ? implode(', ', $bereiche) : 'Allgemein';

    $kontakt = ['Name:      ' . $name];
    if ($firma !== '')   $kontakt[] = 'Firma:     ' . $firma;
    $kontakt[] = 'E-Mail:    ' . $email;
    if ($telefon !== '') $kontakt[] = 'Telefon:   ' . $telefon;
    $kontakt[] = 'PLZ:       ' . $plz;

    $abschnitte = [
        'Neue Anfrage über 123vorteilswelt.at',
        'Eingegangen am ' . $datum . ' um ' . $uhrzeit,
        '', 'AUSGEWÄHLTE BEREICHE',
        $bereiche !== [] ? implode("\n", array_map(fn($b) => '- ' . $b, $bereiche)) : '- Allgemein',
        '', 'KONTAKT', implode("\n", $kontakt),
    ];
    if ($termin !== '') { $abschnitte[] = ''; $abschnitte[] = 'WUNSCHTERMIN'; $abschnitte[] = $termin; }
    if ($bestaetigungGrund !== null) { $abschnitte[] = ''; $abschnitte[] = 'HINWEIS: Bestätigungsmail nicht versendet (' . $bestaetigungGrund . ').'; }
    $abschnitte[] = ''; $abschnitte[] = '--';
    $abschnitte[] = 'Antworten Sie direkt auf diese E-Mail — sie geht an ' . $email . '.';

    $internErfolg = mailVersenden($config, $config['empfaenger']['anfrage'],
        '[Anfrage] ' . $bereicheAnzeige . ' – ' . $plz, implode("\n", $abschnitte), $email, $name);
} else {
    $firma = kopfzeileSaeubern(feld('firma'));
    $plz = kopfzeileSaeubern(feld('plz'));
    $bestehenderBetrieb = kopfzeileSaeubern($bestehenderBetrieb);
    $regionen = array_map('kopfzeileSaeubern', $regionenRoh);
    $regionenAnzeige = implode(', ', $regionen);

    $angaben = ['Region(en):          ' . $regionenAnzeige,
                'Ich bin:             ' . $bestehenderBetrieb];
    if ($plz !== '')   $angaben[] = 'PLZ:                 ' . $plz;
    if ($firma !== '') $angaben[] = 'Firma:               ' . $firma;

    $bewerber = ['Name:      ' . $name, 'E-Mail:    ' . $email];
    if ($telefon !== '') $bewerber[] = 'Telefon:   ' . $telefon;

    $abschnitte = [
        'Neue Franchise-Bewerbung über 123vorteilswelt.at',
        'Eingegangen am ' . $datum . ' um ' . $uhrzeit,
        '', 'BEWERBER', implode("\n", $bewerber),
        '', 'ANGABEN', implode("\n", $angaben),
    ];
    if ($termin !== '') { $abschnitte[] = ''; $abschnitte[] = 'WUNSCHTERMIN'; $abschnitte[] = $termin; }
    if ($bestaetigungGrund !== null) { $abschnitte[] = ''; $abschnitte[] = 'HINWEIS: Bestätigungsmail nicht versendet (' . $bestaetigungGrund . ').'; }
    $abschnitte[] = ''; $abschnitte[] = '--';
    $abschnitte[] = 'Antworten Sie direkt auf diese E-Mail — sie geht an ' . $email . '.';

    $internErfolg = mailVersenden($config, $config['empfaenger']['franchise'],
        '[Franchise] ' . $regionenAnzeige . ' – ' . $name, implode("\n", $abschnitte), $email, $name);
}
if (!$internErfolg) antwortFehler('versand');

// 9 · Bestätigungsmail (scheitert sie: kein Fehler für den Absender)
if ($bestaetigungVersuchen) {
    $fuss = ["", "", "123Vorteilswelt Franchise GmbH", "Wienersdorfer Straße 20-24/M37/12/1",
             "2514 Traiskirchen", "Telefon: +43 660 7693620",
             "office@123vorteilswelt.at · 123vorteilswelt.at", "", "Diese E-Mail wurde automatisch erstellt."];
    if ($formular === 'anfrage') {
        $z = ['Guten Tag ' . $name . ',', '',
              'vielen herzlichen Dank! Wir bestätigen den Eingang Ihrer Anfrage.',
              'Ihr regionaler Ansprechpartner meldet sich an Werktagen innerhalb von',
              '24 Stunden bei Ihnen.', '', 'Ihre Angaben im Überblick:', '',
              'Bereiche:  ' . $bereicheAnzeige];
        if ($firma !== '')   $z[] = 'Firma:     ' . $firma;
        $z[] = 'Name:      ' . $name;
        $z[] = 'E-Mail:    ' . $email;
        if ($telefon !== '') $z[] = 'Telefon:   ' . $telefon;
        $z[] = 'PLZ:       ' . $plz;
        if ($termin !== '')  $z[] = 'Wunschtermin: ' . $termin;
        $z = array_merge($z, ['', 'Stimmt etwas nicht? Antworten Sie einfach auf diese E-Mail.',
              '', 'Freundliche Grüße', 'Ihr Team von 123Vorteilswelt'], $fuss);
        $betreff = 'Ihre Anfrage bei 123Vorteilswelt';
    } else {
        $z = ['Guten Tag ' . $name . ',', '',
              'vielen herzlichen Dank für Ihr Interesse an einer Partnerschaft mit',
              '123Vorteilswelt! Wir bestätigen den Eingang Ihrer Bewerbung.', '',
              'Wir melden uns persönlich bei Ihnen. Da wir Gebiete exklusiv vergeben,',
              'nehmen wir uns für jede Bewerbung die nötige Zeit.', '',
              'Ihre Angaben im Überblick:', '',
              'Region(en): ' . $regionenAnzeige, 'Ich bin:    ' . $bestehenderBetrieb,
              'Name:       ' . $name, 'E-Mail:     ' . $email];
        if ($telefon !== '') $z[] = 'Telefon:    ' . $telefon;
        if ($termin !== '')  $z[] = 'Wunschtermin: ' . $termin;
        $z = array_merge($z, ['', 'Möchten Sie etwas ergänzen? Antworten Sie einfach auf diese E-Mail.',
              '', 'Freundliche Grüße', 'Ihr Team von 123Vorteilswelt'], $fuss);
        $betreff = 'Ihre Bewerbung bei 123Vorteilswelt';
    }
    if (!mailVersenden($config, $email, $betreff, implode("\n", $z), $config['antwortAn'], '123Vorteilswelt')) {
        $hinweisCode = 'bestaetigung-fehler';
    }
}

antwortOk($hinweisCode);
