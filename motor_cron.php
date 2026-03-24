<?php
// /var/www/html/dit/tools/pui/motor_cron.php
require_once __DIR__ . '/config/db.php';

// =========================================================================
// 1. FUNCIONES CRIPTOGRÁFICAS Y MAPEO
// =========================================================================

function cifrarBiometrico($base64_data, $clave_aes) {
    if(empty($clave_aes) || empty($base64_data)) return null;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
    $cifrado = openssl_encrypt($base64_data, 'aes-256-gcm', $clave_aes, OPENSSL_RAW_DATA, $iv, $tag);
    if($cifrado === false) return null;
    return base64_encode($iv . $cifrado . $tag);
}

function mapearFilaParaGobierno($fila, $clave_bio = null, $fase = "3", $curp_buscada = '') {
    $f = array_change_key_case($fila, CASE_LOWER);
    $curp_final = strtoupper($f['curp'] ?? $curp_buscada);
    
    // Anexo 5: Diccionario de Estados
    $mapa_estados = [
        'AS'=>'AGUASCALIENTES','BC'=>'BAJA CALIFORNIA','BS'=>'BAJA CALIFORNIA SUR',
        'CC'=>'CAMPECHE','CS'=>'CHIAPAS','CH'=>'CHIHUAHUA','DF'=>'CDMX',
        'CL'=>'COAHUILA','CM'=>'COLIMA','DG'=>'DURANGO','GT'=>'GUANAJUATO',
        'GR'=>'GUERRERO','HG'=>'HIDALGO','JC'=>'JALISCO','MC'=>'MÉXICO',
        'MN'=>'MICHOACÁN','MS'=>'MORELOS','NT'=>'NAYARIT','NL'=>'NUEVO LEÓN',
        'OC'=>'OAXACA','PL'=>'PUEBLA','QO'=>'QUERÉTARO','QR'=>'QUINTANA ROO',
        'SP'=>'SAN LUIS POTOSÍ','SL'=>'SINALOA','SR'=>'SONORA','TC'=>'TABASCO',
        'TS'=>'TAMAULIPAS','TL'=>'TLAXCALA','VZ'=>'VERACRUZ','YN'=>'YUCATÁN',
        'ZS'=>'ZACATECAS','NE'=>'FORÁNEO'
    ];

    $lugar_nac = 'DESCONOCIDO';
    if (!empty($f['lugar_nacimiento'])) {
        $lugar_nac = strtoupper($f['lugar_nacimiento']);
    } elseif (strlen($curp_final) >= 13) {
        $codigo_estado = substr($curp_final, 11, 2);
        $lugar_nac = $mapa_estados[$codigo_estado] ?? 'DESCONOCIDO';
    }

    $res = [
        'curp' => $curp_final,
        'lugar_nacimiento' => $lugar_nac,
        'fecha_nacimiento' => $f['fecha_nacimiento'] ?? null,
        'sexo_asignado' => $f['sexo_asignado'] ?? null,
        'telefono' => $f['telefono'] ?? null,
        'correo' => $f['correo'] ?? ($f['correo_electronico'] ?? null)
    ];

    if (!empty($f['nombre'])) {
        $res['nombre_completo'] = [
            'nombre' => $f['nombre'],
            'primer_apellido' => $f['primer_apellido'] ?? null,
            'segundo_apellido' => $f['segundo_apellido'] ?? null
        ];
    }

    if (!empty($f['direccion']) || !empty($f['codigo_postal']) || !empty($f['calle'])) {
        $res['domicilio'] = [
            'direccion' => $f['direccion'] ?? null,
            'calle' => $f['calle'] ?? null,
            'numero' => $f['numero'] ?? null,
            'colonia' => $f['colonia'] ?? null,
            'codigo_postal' => $f['codigo_postal'] ?? null,
            'municipio_o_alcaldia' => $f['municipio_o_alcaldia'] ?? null,
            'entidad_federativa' => $f['entidad_federativa'] ?? null
        ];
    }

    // Biometría: Fotos
    if (!empty($f['foto_base64']) && !empty($clave_bio)) {
        $foto_cifrada = cifrarBiometrico($f['foto_base64'], $clave_bio);
        if($foto_cifrada) {
            $res['fotos'] = [$foto_cifrada];
            $res['formato_fotos'] = $f['formato_fotos'] ?? "jpg"; 
        }
    }

    // Biometría: Huellas (Anexo 4)
    $etiquetas_huellas = ['rone','rtwo','rthree','rfour','rfive','lone','ltwo','lthree','lfour','lfive','rpalm','lpalm'];
    $huellas_procesadas = [];
    foreach ($etiquetas_huellas as $etiqueta) {
        if (!empty($f[$etiqueta]) && !empty($clave_bio)) {
            $huella_cifrada = cifrarBiometrico($f[$etiqueta], $clave_bio);
            if ($huella_cifrada) $huellas_procesadas[$etiqueta] = $huella_cifrada;
        }
    }
    if (!empty($huellas_procesadas)) {
        $res['huellas'] = $huellas_procesadas;
        $res['formato_huellas'] = $f['formato_huellas'] ?? "wsq";
    }

    $res['fase_busqueda'] = (string)$fase;

    // FASE 1: Se omiten datos de evento. FASE 2 y 3: Se incluyen.
    if ($fase !== "1") {
        $res['tipo_evento'] = $f['tipo_evento'] ?? null;
        $res['fecha_evento'] = $f['fecha_evento'] ?? null;
        $res['descripcion_lugar_evento'] = $f['descripcion_lugar_evento'] ?? null;
        if (!empty($f['direccion_evento'])) {
            $res['direccion_evento'] = is_array($f['direccion_evento']) ? $f['direccion_evento'] : ['direccion' => $f['direccion_evento']];
        }
    }

    return array_filter($res, function($v) { return $v !== null && $v !== ''; });
}

function enviarPayloadGobierno($url, $payload_array, $token, $inst_id, $pdo) {
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_array, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $pdo->prepare("INSERT INTO coincidencias_enviadas (institucion_id, id_reporte, endpoint_destino, payload_enviado, respuesta_http) VALUES (?, ?, ?, ?, ?)")
            ->execute([$inst_id, $payload_array['id'], $url, json_encode($payload_array, JSON_UNESCAPED_UNICODE), $code]);

    } catch (Exception $e) {
        echo " -> Error en cURL: " . $e->getMessage() . "\n";
    }
}

// =========================================================================
// 2. INICIO DEL MOTOR CRON PRINCIPAL
// =========================================================================
echo "Iniciando CRON PUI - " . date('Y-m-d H:i:s') . "\n";

$sql = "
    SELECT DISTINCT i.id as inst_id, i.rfc_homoclave, i.clave_api_gobierno, i.clave_biometricos 
    FROM instituciones i
    JOIN reportes_pui r ON i.id = r.institucion_id
    WHERE r.estatus = 'activo' AND r.id_reporte NOT LIKE 'SIM-%'
";
$instituciones_con_reportes = $pdo_hub->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (count($instituciones_con_reportes) === 0) {
    die("No hay reportes de búsqueda activos y reales en cola.\n");
}

$url_login_gobierno = "https://www.api.plataformadebusqueda.gob.mx/api/2_3_0/login";
$url_notificar_gob = "https://www.api.plataformadebusqueda.gob.mx/api/2_3_0/notificar-coincidencia";
$url_finalizada_gob = "https://www.api.plataformadebusqueda.gob.mx/api/2_3_0/busqueda-finalizada";

foreach ($instituciones_con_reportes as $inst) {
    echo "Procesando Institución: {$inst['rfc_homoclave']}\n";

    if (empty($inst['clave_api_gobierno'])) {
        echo " -> OMITIDA: No tiene 'clave_api_gobierno' configurada.\n";
        continue;
    }

    // 1. OBTENER TOKEN GOBIERNO
    $chLogin = curl_init($url_login_gobierno);
    curl_setopt($chLogin, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chLogin, CURLOPT_POST, true);
    curl_setopt($chLogin, CURLOPT_POSTFIELDS, json_encode(['institucion_id' => $inst['rfc_homoclave'], 'clave' => $inst['clave_api_gobierno']]));
    curl_setopt($chLogin, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $login_res = curl_exec($chLogin);
    $login_code = curl_getinfo($chLogin, CURLINFO_HTTP_CODE);
    curl_close($chLogin);

    $login_data = json_decode($login_res, true);
    if ($login_code !== 200 || empty($login_data['token'])) {
        echo " -> ERROR DE LOGIN GOBIERNO. Código HTTP: $login_code\n";
        continue;
    }
    $token_jwt = $login_data['token'];

    // 2. BUSCAR TODOS LOS REPORTES ACTIVOS
    $stmtRep = $pdo_hub->prepare("SELECT * FROM reportes_pui WHERE institucion_id = ? AND estatus = 'activo' AND id_reporte NOT LIKE 'SIM-%'");
    $stmtRep->execute([$inst['inst_id']]);
    $reportes = $stmtRep->fetchAll(PDO::FETCH_ASSOC);

    $stmtNodos = $pdo_hub->prepare("SELECT * FROM origenes_datos WHERE institucion_id = ?");
    $stmtNodos->execute([$inst['inst_id']]);
    $nodos = $stmtNodos->fetchAll();

    foreach ($reportes as $reporte) {
        $curp_buscar = $reporte['curp'];
        $datos_completos_gob = json_decode($reporte['datos_completos'], true) ?: [];
        $fecha_actual = new DateTime();
        
        $requiere_fase_1_y_2 = empty($reporte['fecha_ultima_busqueda']);
        
        // Ventana de tiempo (12 años) para Fase 2
        $fecha_desaparicion_str = $datos_completos_gob['fecha_desaparicion'] ?? null;
        $fecha_desaparicion = $fecha_desaparicion_str ? new DateTime($fecha_desaparicion_str) : null;
        
        $omitir_fase_2 = false;
        if (!$fecha_desaparicion) {
            $omitir_fase_2 = true;
        } else {
            $doce_anios_atras = (clone $fecha_actual)->modify('-12 years');
            $fecha_inicio_fase2 = $fecha_desaparicion > $doce_anios_atras ? $fecha_desaparicion : $doce_anios_atras;
            $fecha_fin_fase2 = $fecha_actual;
        }

        $coincidencias_crudas = [];

        // 3. EXTRACCIÓN DE DATOS DE TODOS LOS NODOS (COMPLETO)
        foreach ($nodos as $campus) {
            try {
                if ($campus['tipo_conexion'] === 'demo_local' && $curp_buscar) {
                    $stmtQ = $pdo_hub->prepare("SELECT * FROM vw_ejemplo_universidad WHERE curp = ?");
                    $stmtQ->execute([$curp_buscar]);
                    foreach ($stmtQ->fetchAll(PDO::FETCH_ASSOC) as $row) $coincidencias_crudas[] = $row;
                }
                elseif (in_array($campus['tipo_conexion'], ['mysql', 'postgresql', 'sqlsrv', 'oracle']) && $curp_buscar) {
                    $pass_dec = decryptStr($campus['password_encriptado']);
                    $dsn = "";
                    if ($campus['tipo_conexion'] === 'mysql') $dsn = "mysql:host={$campus['host']};port={$campus['puerto']};dbname={$campus['nombre_bd']};charset=utf8mb4";
                    elseif ($campus['tipo_conexion'] === 'postgresql') $dsn = "pgsql:host={$campus['host']};port={$campus['puerto']};dbname={$campus['nombre_bd']}";
                    elseif ($campus['tipo_conexion'] === 'sqlsrv') $dsn = "sqlsrv:Server={$campus['host']},{$campus['puerto']};Database={$campus['nombre_bd']}";
                    elseif ($campus['tipo_conexion'] === 'oracle') $dsn = "oci:dbname=//{$campus['host']}:{$campus['puerto']}/{$campus['nombre_bd']};charset=AL32UTF8";

                    $pdo = new PDO($dsn, $campus['usuario'], $pass_dec, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
                    $stmtQ = $pdo->prepare("SELECT * FROM {$campus['nombre_vista']} WHERE curp = ?");
                    $stmtQ->execute([$curp_buscar]);
                    foreach ($stmtQ->fetchAll(PDO::FETCH_ASSOC) as $row) $coincidencias_crudas[] = $row;
                } 
                elseif ($campus['tipo_conexion'] === 'mongodb' && $curp_buscar && class_exists('MongoDB\Driver\Manager')) {
                    $pass_dec = decryptStr($campus['password_encriptado']);
                    $uri = "mongodb://";
                    if (!empty($campus['usuario'])) $uri .= urlencode($campus['usuario']).":".urlencode($pass_dec)."@";
                    $uri .= "{$campus['host']}:{$campus['puerto']}/{$campus['nombre_bd']}";
                    
                    $manager = new MongoDB\Driver\Manager($uri);
                    $query = new MongoDB\Driver\Query(['curp' => $curp_buscar]);
                    $cursor = $manager->executeQuery("{$campus['nombre_bd']}.{$campus['nombre_vista']}", $query);
                    foreach ($cursor as $doc) $coincidencias_crudas[] = json_decode(json_encode($doc), true);
                }
                elseif ($campus['tipo_conexion'] === 'manual' && $curp_buscar) {
                    $archivo = $campus['archivo_local'];
                    if (file_exists($archivo) && ($handle = fopen($archivo, "r")) !== FALSE) {
                        $headers_csv = fgetcsv($handle, 1000, ",");
                        $curp_index = array_search('curp', array_map('strtolower', $headers_csv));
                        if ($curp_index !== false) {
                            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                                if (isset($data[$curp_index]) && strtoupper(trim($data[$curp_index])) === strtoupper($curp_buscar)) {
                                    $coincidencias_crudas[] = array_combine($headers_csv, $data);
                                }
                            }
                        }
                        fclose($handle);
                    }
                }
                elseif ($campus['tipo_conexion'] === 'sftp' && $curp_buscar) {
                    $conn = @ftp_ssl_connect($campus['host'], $campus['puerto'] ?: 21, 5) ?: @ftp_connect($campus['host'], $campus['puerto'] ?: 21, 5);
                    if ($conn && @ftp_login($conn, $campus['usuario'], decryptStr($campus['password_encriptado']))) {
                        ftp_pasv($conn, true);
                        if (@ftp_chdir($conn, $campus['nombre_bd'])) {
                            $archivos = ftp_nlist($conn, ".");
                            if (is_array($archivos)) {
                                $mas_reciente = ''; $fecha_max = -1;
                                foreach ($archivos as $av) {
                                    if ($av !== '.' && $av !== '..' && strpos($av, $campus['nombre_vista']) === 0) {
                                        $mdtm = ftp_mdtm($conn, $av);
                                        if ($mdtm > $fecha_max) { $fecha_max = $mdtm; $mas_reciente = $av; }
                                    }
                                }
                                if ($mas_reciente) {
                                    $temp_stream = fopen('php://temp', 'r+');
                                    if (ftp_fget($conn, $temp_stream, $mas_reciente, FTP_ASCII)) {
                                        rewind($temp_stream);
                                        $headers_csv = fgetcsv($temp_stream, 1000, ",");
                                        if ($headers_csv) {
                                            $curp_idx = array_search('curp', array_map('strtolower', $headers_csv));
                                            if ($curp_idx !== false) {
                                                while (($data = fgetcsv($temp_stream, 1000, ",")) !== FALSE) {
                                                    if (isset($data[$curp_idx]) && strtoupper(trim($data[$curp_idx])) === strtoupper($curp_buscar)) {
                                                        $coincidencias_crudas[] = array_combine($headers_csv, $data);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    fclose($temp_stream);
                                }
                            }
                        }
                        ftp_close($conn);
                    }
                }
            } catch (Exception $connError) { continue; }
        }

        // 4. PROCESAMIENTO Y ENVÍO POR FASES
        if (count($coincidencias_crudas) > 0) {
            
            // PRIMERA VEZ: Fase 1 y 2
            if ($requiere_fase_1_y_2) {
                
                // === EJECUTAR FASE 1 ===
                $registro_mas_reciente = $coincidencias_crudas[0];
                $payload_fase1 = mapearFilaParaGobierno($registro_mas_reciente, $inst['clave_biometricos'], "1", $curp_buscar);
                $payload_fase1['id'] = $reporte['id_reporte'];
                $payload_fase1['institucion_id'] = $inst['rfc_homoclave'];
                enviarPayloadGobierno($url_notificar_gob, $payload_fase1, $token_jwt, $inst['inst_id'], $pdo_hub);

                // === EJECUTAR FASE 2 ===
                if (!$omitir_fase_2) {
                    foreach ($coincidencias_crudas as $row) {
                        if (!empty($row['fecha_evento'])) {
                            $f_ev = new DateTime($row['fecha_evento']);
                            if ($f_ev >= $fecha_inicio_fase2 && $f_ev <= $fecha_fin_fase2) {
                                $payload_fase2 = mapearFilaParaGobierno($row, $inst['clave_biometricos'], "2", $curp_buscar);
                                $payload_fase2['id'] = $reporte['id_reporte'];
                                $payload_fase2['institucion_id'] = $inst['rfc_homoclave'];
                                enviarPayloadGobierno($url_notificar_gob, $payload_fase2, $token_jwt, $inst['inst_id'], $pdo_hub);
                            }
                        }
                    }
                }

                // === NOTIFICAR FIN FASE HISTÓRICA ===
                $payload_fin = ['id' => $reporte['id_reporte'], 'institucion_id' => $inst['rfc_homoclave']];
                enviarPayloadGobierno($url_finalizada_gob, $payload_fin, $token_jwt, $inst['inst_id'], $pdo_hub);
                $pdo_hub->prepare("UPDATE reportes_pui SET fase_actual = 3 WHERE id_reporte = ?")->execute([$reporte['id_reporte']]);
                
            } else {
                // === EJECUTAR FASE 3 ===
                foreach ($coincidencias_crudas as $row) {
                    $payload_fase3 = mapearFilaParaGobierno($row, $inst['clave_biometricos'], "3", $curp_buscar);
                    $payload_fase3['id'] = $reporte['id_reporte'];
                    $payload_fase3['institucion_id'] = $inst['rfc_homoclave'];
                    enviarPayloadGobierno($url_notificar_gob, $payload_fase3, $token_jwt, $inst['inst_id'], $pdo_hub);
                }
            }
        } elseif ($requiere_fase_1_y_2) {
            // No hubo hallazgos, pero el gobierno exige notificar el fin de la búsqueda inicial
            $payload_fin = ['id' => $reporte['id_reporte'], 'institucion_id' => $inst['rfc_homoclave']];
            enviarPayloadGobierno($url_finalizada_gob, $payload_fin, $token_jwt, $inst['inst_id'], $pdo_hub);
            $pdo_hub->prepare("UPDATE reportes_pui SET fase_actual = 3 WHERE id_reporte = ?")->execute([$reporte['id_reporte']]);
        }

        // Actualizar fecha
        $pdo_hub->prepare("UPDATE reportes_pui SET fecha_ultima_busqueda = NOW() WHERE id_reporte = ?")->execute([$reporte['id_reporte']]);
    }
}

echo "CRON finalizado correctamente.\n";
?>