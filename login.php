<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.html");
    exit();
}

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {
    die("Hiányzó adatok.");
}

/*
 |--------------------------------------------------
 | SUPABASE ADATOK
 |--------------------------------------------------
 | Ezeket a Supabase Dashboard > Project Settings > API résznél találod.
 | A service_role kulcsot SOHA ne tedd frontendbe.
 */
$supabaseUrl = "https://lsbdbfzeugqnurkmdhxh.supabase.co";
$serviceRoleKey = "IDE_MASOLD_BE_A_SERVICE_ROLE_SECRET_KEYT";
$anonKey = "sb_publishable_GrhobqQt4uN4Cl5G9t1YmQ_KCKw-wFP";

/*
 |--------------------------------------------------
 | 1. Felhasználónév alapján email lekérése
 |--------------------------------------------------
 | Server oldalon service role kulccsal kérdezzük le,
 | így nem kell publikus email-kereső endpointot csinálni.
 */
$profileUrl = $supabaseUrl . "/rest/v1/profiles?select=email&username=eq." . urlencode($username) . "&limit=1";

$ch = curl_init($profileUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: " . $serviceRoleKey,
    "Authorization: Bearer " . $serviceRoleKey,
    "Content-Type: application/json"
]);

$profileResponse = curl_exec($ch);
$profileHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($profileHttpCode !== 200) {
    die("Hiba a profil lekérdezésekor.");
}

$profiles = json_decode($profileResponse, true);

if (!$profiles || count($profiles) === 0) {
    die("Hibás felhasználónév vagy jelszó.");
}

$email = $profiles[0]["email"] ?? "";

if ($email === "") {
    die("Hibás felhasználónév vagy jelszó.");
}

/*
 |--------------------------------------------------
 | 2. Belépés Supabase Auth-tal email + jelszó alapján
 |--------------------------------------------------
 */
$authUrl = $supabaseUrl . "/auth/v1/token?grant_type=password";

$payload = json_encode([
    "email" => $email,
    "password" => $password
]);

$ch = curl_init($authUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: " . $anonKey,
    "Content-Type: application/json"
]);

$authResponse = curl_exec($ch);
$authHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$authData = json_decode($authResponse, true);

if ($authHttpCode !== 200 || isset($authData["error"])) {
    die("Hibás felhasználónév vagy jelszó.");
}

/*
 |--------------------------------------------------
 | 3. Session mentése
 |--------------------------------------------------
 */
$_SESSION["supabase_access_token"] = $authData["access_token"] ?? null;
$_SESSION["supabase_refresh_token"] = $authData["refresh_token"] ?? null;
$_SESSION["supabase_user"] = $authData["user"] ?? null;
$_SESSION["username"] = $username;

/*
 |--------------------------------------------------
 | 4. Átirányítás
 |--------------------------------------------------
 */
header("Location: fooldal.html");
exit();
?>