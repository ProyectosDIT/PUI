<?php
// /var/www/html/dit/tools/pui/config/db.php

if (session_status() === PHP_SESSION_NONE) {
	session_name('SESION_PUI'); 
    session_start();
}

// 1. Carga de librerías y Variables de Entorno Globales (Como en tu script de accesos)
require_once '/var/www/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable('/var/www');
$dotenv->load();

// 2. Conexión a la Base de Datos del HUB PUI
$opciones = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
    $pdo_hub = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=pui_upaep_hub;charset=utf8mb4', 
        '', // <-- CAMBIA POR TU USUARIO MYSQL
        '', // <-- CAMBIA POR TU PASSWORD MYSQL
        $opciones
    );
} catch (PDOException $e) {
    die("Error de conexión a DB HUB PUI.");
}

// 3. Configuración de Google OAuth (Replicando tu lógica)
$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
// URL dinámica calculada para el callback
$client->setRedirectUri('https://shadow.spdigital.mx/dit/tools/pui/login.php'); 
$client->addScope("email");
$client->addScope("profile");

// 4. Criptografía y Seguridad
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die(json_encode(['error' => 'Error de validación de seguridad (CSRF).']));
    }
}

function encryptStr($plain_text) {
    if(empty($plain_text)) return null;
    return openssl_encrypt($plain_text, 'AES-256-CBC', $_ENV['HUB_AES_KEY'], 0, $_ENV['HUB_AES_IV']);
}

function decryptStr($encrypted_text) {
    if(empty($encrypted_text)) return null;
    return openssl_decrypt($encrypted_text, 'AES-256-CBC', $_ENV['HUB_AES_KEY'], 0, $_ENV['HUB_AES_IV']);
}
?>