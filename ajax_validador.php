<?php
// /var/www/html/dit/tools/pui/ajax_validador.php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
    exit;
}

try {
    $tipo = $_POST['tipo_db'];
    
    // 1. VALIDAR BASES DE DATOS RELACIONALES (MySQL, PostgreSQL, SQL Server, Oracle)
    if (in_array($tipo, ['mysql', 'postgresql', 'sqlsrv', 'oracle'])) {
        $host = $_POST['host']; $puerto = $_POST['puerto']; 
        $db = $_POST['nombre_bd']; $user = $_POST['user_db']; 
        $pass = $_POST['pass_db']; $vista = $_POST['vista'];
        
        if (empty($host) || empty($db) || empty($user) || empty($vista)) {
            throw new Exception("Faltan parámetros requeridos de red.");
        }

        $dsn = "";
        $drivers = PDO::getAvailableDrivers();

        if ($tipo === 'mysql') {
            $dsn = "mysql:host={$host};port={$puerto};dbname={$db};charset=utf8mb4";
        } elseif ($tipo === 'postgresql') {
            $dsn = "pgsql:host={$host};port={$puerto};dbname={$db}";
        } elseif ($tipo === 'sqlsrv') {
            if (!in_array('sqlsrv', $drivers)) throw new Exception("El driver pdo_sqlsrv de PHP no está instalado en el servidor UPAEP.");
            $dsn = "sqlsrv:Server={$host},{$puerto};Database={$db}";
        } elseif ($tipo === 'oracle') {
            if (!in_array('oci', $drivers)) throw new Exception("El driver pdo_oci de PHP no está instalado en el servidor UPAEP.");
            $dsn = "oci:dbname=//{$host}:{$puerto}/{$db};charset=AL32UTF8";
        }

        $pdo_test = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
            PDO::ATTR_TIMEOUT => 3 
        ]);

        $stmt = $pdo_test->query("SELECT * FROM {$vista} LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row !== false) {
            $columnas = array_map('strtolower', array_keys($row));
            if (!in_array('curp', $columnas)) {
                throw new Exception("Conexión exitosa, pero la vista/tabla NO tiene la columna obligatoria 'curp'.");
            }
        }
        echo json_encode(['success' => true, 'message' => "¡Conexión exitosa a " . strtoupper($tipo) . "! La estructura es válida."]);
    } 
    
    // 2. VALIDAR MONGODB (No Relacional)
    elseif ($tipo === 'mongodb') {
        if (!class_exists('MongoDB\Driver\Manager')) {
            throw new Exception("La extensión oficial de MongoDB para PHP no está instalada en el servidor UPAEP.");
        }
        
        $host = $_POST['host']; $puerto = $_POST['puerto'] ?: 27017; 
        $db = $_POST['nombre_bd']; $user = $_POST['user_db']; 
        $pass = $_POST['pass_db']; $vista = $_POST['vista']; // En Mongo, vista = colección

        $uri = "mongodb://";
        if (!empty($user) && !empty($pass)) {
            $uri .= urlencode($user).":".urlencode($pass)."@";
        }
        $uri .= "{$host}:{$puerto}/{$db}";

        $manager = new MongoDB\Driver\Manager($uri);
        $command = new MongoDB\Driver\Command(['ping' => 1]);
        $manager->executeCommand($db, $command); // Lanza excepción si falla

        // Probar si existe la llave curp en el primer documento
        $query = new MongoDB\Driver\Query([], ['limit' => 1]);
        $cursor = $manager->executeQuery("$db.$vista", $query);
        $doc = current($cursor->toArray());
        
        if ($doc) {
            $doc_array = json_decode(json_encode($doc), true);
            $llaves = array_map('strtolower', array_keys($doc_array));
            if (!in_array('curp', $llaves)) {
                throw new Exception("Conexión exitosa, pero la colección no tiene el nodo obligatorio 'curp'.");
            }
        }
        echo json_encode(['success' => true, 'message' => "¡Conexión MongoDB Exitosa! Servidor y colección accesibles."]);
    }

    // 3. VALIDAR SERVIDOR FTPS/FTP
    elseif ($tipo === 'sftp') { 
        $host = $_POST['host']; $puerto = empty($_POST['puerto']) ? 21 : $_POST['puerto']; 
        $user = $_POST['user_db']; $pass = $_POST['pass_db']; 
        $ruta = $_POST['nombre_bd']; $prefijo = $_POST['vista'];
        
        if(empty($host) || empty($user) || empty($pass) || empty($ruta) || empty($prefijo)) {
            throw new Exception("Faltan datos obligatorios para probar la conexión FTPS.");
        }
        
        $conn = @ftp_ssl_connect($host, $puerto, 5) ?: @ftp_connect($host, $puerto, 5);
        if(!$conn) throw new Exception("No hay respuesta del servidor en $host:$puerto.");
        
        if(!@ftp_login($conn, $user, $pass)) {
            ftp_close($conn); throw new Exception("Credenciales FTPS incorrectas.");
        }
        
        ftp_pasv($conn, true); 
        if(!@ftp_chdir($conn, $ruta)) {
            ftp_close($conn); throw new Exception("Conexión exitosa, pero la ruta '$ruta' no existe o no tiene permisos.");
        }
        
        ftp_close($conn);
        echo json_encode(['success' => true, 'message' => "¡Conexión FTPS Exitosa! Servidor y directorio validados."]);
    } 
    
    // 4. CARGA MANUAL
    else {
        echo json_encode(['success' => true, 'message' => 'Validación omitida para este método. Asegúrate de subir el archivo.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>