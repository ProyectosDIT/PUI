<?php
// /var/www/html/dit/tools/pui/app.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mailer.php';

if (!isset($_SESSION['user_id'])) { 
    header('Location: index.php'); 
    exit; 
}

$is_superadmin = ($_SESSION['rol'] === 'superadmin');
$puede_aprobar = 0;
$flash_msg = ''; 
$flash_type = '';
$simulacion_resultado = null;

// =================================================================================
// GLOBALES DE AUDITORÍA
// =================================================================================
$ip_accion = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
if (strpos($ip_accion, ',') !== false) {
    $ip_accion = trim(explode(',', $ip_accion)[0]);
}

function registrarAuditoria($pdo, $user_id, $accion, $detalles, $ip) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs_auditoria (user_id, accion, detalles, ip) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $accion, $detalles, $ip]);
    } catch(Exception $e) { error_log("Error guardando log: " . $e->getMessage()); }
}

$mi_institucion = "Administración Central UPAEP";

// =================================================================================
// CÁLCULO DINÁMICO DE PERMISOS POR CONTEXTO (INSTITUCIÓN)
// =================================================================================
if (!$is_superadmin) {
    $stmtN = $pdo_hub->prepare("SELECT nombre FROM instituciones WHERE id = ?");
    $stmtN->execute([$_SESSION['institucion_id']]);
    $mi_institucion = $stmtN->fetchColumn();
    
    $stmtStatus = $pdo_hub->prepare("
        SELECT puede_aprobar FROM (
            SELECT puede_aprobar FROM usuarios WHERE id = ? AND institucion_id = ?
            UNION
            SELECT puede_aprobar FROM usuario_instituciones WHERE usuario_id = ? AND institucion_id = ?
        ) as t LIMIT 1
    ");
    $stmtStatus->execute([$_SESSION['user_id'], $_SESSION['institucion_id'], $_SESSION['user_id'], $_SESSION['institucion_id']]);
    $puede_aprobar = $stmtStatus->fetchColumn() ?: 0;
    $_SESSION['puede_aprobar'] = $puede_aprobar;
}

$fecha_accion = date('d/m/Y H:i:s');
$email_ejecutor = $_SESSION['email'];

$auditBlock = "
<div style='background-color:#f8fafc; padding:15px; border-left:4px solid #0d6efd; margin-top:25px; font-size:13px;'>
    <strong style='color:#0f172a;'>Ficha de Auditoría de la Acción:</strong><br><br>
    &bull; <b>Institución Afectada:</b> $mi_institucion<br>
    &bull; <b>Ejecutado por:</b> $email_ejecutor<br>
    &bull; <b>Dirección IP Registrada:</b> $ip_accion<br>
    &bull; <b>Fecha y Hora:</b> $fecha_accion
</div>";

$sa = $pdo_hub->query("SELECT email, nombre FROM usuarios WHERE rol = 'superadmin' LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si tienes una función verifyCsrfToken() global, se ejecuta aquí:
    if (function_exists('verifyCsrfToken') && isset($_POST['csrf_token'])) {
        verifyCsrfToken($_POST['csrf_token']);
    }
    
    // =================================================================================
    // NUEVO: ENDPOINTS AJAX (PREVIEW, GENERAR RSA, SINCRONIZAR SEGOB)
    // =================================================================================
    
    // 1. PREVISUALIZAR NODOS
    if (isset($_POST['action']) && $_POST['action'] === 'preview_nodo') {
        header('Content-Type: application/json');
        try {
            if ($is_superadmin) throw new Exception("Función no disponible para el Superadmin Global.");
            
            $stmtNode = $pdo_hub->prepare("SELECT * FROM origenes_datos WHERE id = ? AND institucion_id = ?");
            $stmtNode->execute([$_POST['origen_id'], $_SESSION['institucion_id']]);
            $node = $stmtNode->fetch();
            
            if (!$node) throw new Exception("Nodo no encontrado o no tienes permisos.");
            
            $data = []; $columns = []; $msg = "";
            
            if ($node['tipo_conexion'] === 'manual') {
                if (!empty($node['archivo_local']) && file_exists($node['archivo_local'])) {
                    $ext = strtolower(pathinfo($node['archivo_local'], PATHINFO_EXTENSION));
                    if ($ext === 'csv') {
                        if (($handle = fopen($node['archivo_local'], "r")) !== FALSE) {
                            $columns = fgetcsv($handle, 10000, ",");
                            if($columns) { $columns[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $columns[0]); } // Limpiar BOM
                            $count = 0;
                            while (($row = fgetcsv($handle, 10000, ",")) !== FALSE && $count < 50) {
                                $data[] = array_map(function($v) { return mb_convert_encoding($v, 'UTF-8', 'auto'); }, $row);
                                $count++;
                            }
                            fclose($handle);
                        }
                    } elseif ($ext === 'json') {
                        $json = json_decode(file_get_contents($node['archivo_local']), true);
                        if (is_array($json) && count($json) > 0) {
                            $columns = array_keys($json[0]);
                            foreach(array_slice($json, 0, 50) as $jRow) { $data[] = array_values($jRow); }
                        }
                    }
                } else {
                    throw new Exception("El archivo físico no se encuentra en la bóveda. Intenta subirlo de nuevo.");
                }
            } elseif ($node['tipo_conexion'] === 'demo_local') {
                $stmtDemo = $pdo_hub->query("SELECT * FROM vw_ejemplo_universidad LIMIT 50");
                $rows = $stmtDemo->fetchAll(PDO::FETCH_ASSOC);
                if(count($rows) > 0) {
                    $columns = array_keys($rows[0]);
                    foreach($rows as $r) { $data[] = array_values($r); }
                }
            } else {
                $msg = "Por lineamientos de seguridad (Zero-Trust), la extracción de datos en crudo hacia el navegador desde bases de datos externas (SQL/NoSQL) o redes SFTP no está permitida en esta vista previa. Utiliza el Simulador de Pruebas para validar cómo el HUB consumirá los datos.";
            }
            
            echo json_encode(['success' => true, 'columns' => $columns, 'data' => $data, 'message' => $msg]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit; 
    }

    // 2. GENERADOR DE LLAVES RSA
    if (isset($_POST['action']) && $_POST['action'] === 'generar_rsa_keys') {
        header('Content-Type: application/json');
        try {
            if ($is_superadmin) throw new Exception("Función no disponible para el Superadmin.");
            
            $config = array(
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            );
            $res = openssl_pkey_new($config);
            
            if (!$res) throw new Exception("Error al generar las llaves en el servidor. Verifica que OpenSSL esté configurado.");
            
            openssl_pkey_export($res, $privKey);
            $pubKeyDetails = openssl_pkey_get_details($res);
            $pubKey = $pubKeyDetails["key"];
            
            echo json_encode(['success' => true, 'private_key' => $privKey, 'public_key' => $pubKey]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // 3. SINCRONIZAR REPORTES PERDIDOS CON SEGOB (Sección 7.4 del Manual)
    if (isset($_POST['action']) && $_POST['action'] === 'sincronizar_segob') {
        header('Content-Type: application/json');
        try {
            if ($is_superadmin) throw new Exception("Función no disponible para el Superadmin.");
            
            $stmtInst = $pdo_hub->prepare("SELECT rfc_homoclave, clave_api_gobierno FROM instituciones WHERE id = ?");
            $stmtInst->execute([$_SESSION['institucion_id']]);
            $instInfo = $stmtInst->fetch();
            
            if (!$instInfo || empty($instInfo['clave_api_gobierno'])) {
                throw new Exception("Debes configurar tu Contraseña API Gobierno en la pestaña de Seguridad antes de poder sincronizar con SEGOB.");
            }
            
            // a) Autenticarse en el Gateway de SEGOB
            $url_login_gobierno = "https://www.api.plataformadebusqueda.gob.mx/api/2_3_0/login";
            $chLogin = curl_init($url_login_gobierno);
            curl_setopt($chLogin, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chLogin, CURLOPT_POST, true);
            curl_setopt($chLogin, CURLOPT_POSTFIELDS, json_encode(['institucion_id' => $instInfo['rfc_homoclave'], 'clave' => $instInfo['clave_api_gobierno']]));
            curl_setopt($chLogin, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $login_res = curl_exec($chLogin);
            $login_code = curl_getinfo($chLogin, CURLINFO_HTTP_CODE);
            curl_close($chLogin);

            $login_data = json_decode($login_res, true);
            if ($login_code !== 200 || empty($login_data['token'])) {
                throw new Exception("Error de Autenticación con SEGOB. Verifica tu Contraseña API.");
            }
            $token_jwt = $login_data['token'];

            // b) Traer la lista de Reportes Activos
            $url_reportes = "https://www.api.plataformadebusqueda.gob.mx/api/2_3_0/reportes";
            $chRep = curl_init($url_reportes);
            curl_setopt($chRep, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chRep, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_jwt]);
            $rep_res = curl_exec($chRep);
            $rep_code = curl_getinfo($chRep, CURLINFO_HTTP_CODE);
            curl_close($chRep);

            if ($rep_code !== 200) {
                throw new Exception("El servidor de SEGOB devolvió un error al listar los reportes (HTTP $rep_code).");
            }

            $reportes_segob = json_decode($rep_res, true);
            if (!is_array($reportes_segob)) {
                throw new Exception("La respuesta de SEGOB no tiene un formato válido.");
            }

            // c) Conciliar con la Base de Datos Local
            $insertados = 0;
            $stmtCheck = $pdo_hub->prepare("SELECT id_reporte FROM reportes_pui WHERE id_reporte = ? AND institucion_id = ?");
            $stmtInsert = $pdo_hub->prepare("INSERT INTO reportes_pui (id_reporte, institucion_id, curp, datos_completos, fase_actual, estatus) VALUES (?, ?, ?, ?, 1, 'activo')");

            foreach ($reportes_segob as $rep) {
                if (empty($rep['id']) || empty($rep['curp'])) continue;
                
                $stmtCheck->execute([$rep['id'], $_SESSION['institucion_id']]);
                if (!$stmtCheck->fetch()) {
                    $stmtInsert->execute([$rep['id'], $_SESSION['institucion_id'], strtoupper($rep['curp']), json_encode($rep, JSON_UNESCAPED_UNICODE)]);
                    $insertados++;
                }
            }

            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Sincronizar SEGOB", "Se sincronizaron $insertados folios nuevos provenientes del servidor federal.", $ip_accion);
            echo json_encode(['success' => true, 'message' => "¡Sincronización Completa! Se recuperaron $insertados reportes de búsqueda nuevos."]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // =================================================================================
    // LOGICA MULTI-INSTITUCIONAL MEJORADA
    // =================================================================================
    if (!$is_superadmin && isset($_POST['action']) && $_POST['action'] === 'cambiar_institucion') {
        $nueva_inst = $_POST['target_inst'];
        
        $check = $pdo_hub->prepare("
            SELECT activo, puede_aprobar FROM usuarios WHERE id = ? AND institucion_id = ? AND activo = 1
            UNION
            SELECT activo, puede_aprobar FROM usuario_instituciones WHERE usuario_id = ? AND institucion_id = ? AND activo = 1
        ");
        $check->execute([$_SESSION['user_id'], $nueva_inst, $_SESSION['user_id'], $nueva_inst]);
        $row = $check->fetch();
        
        if ($row) {
            $_SESSION['institucion_id'] = $nueva_inst;
            $_SESSION['puede_aprobar'] = $row['puede_aprobar'];
            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Cambio Contexto", "Usuario saltó a Institución ID: $nueva_inst", $ip_accion);
            header("Location: app.php"); exit;
        } else {
            $flash_msg = "Aún no tienes el acceso aprobado para esa institución."; $flash_type = "danger";
        }
    }

    if (!$is_superadmin && isset($_POST['action']) && $_POST['action'] === 'solicitar_institucion') {
        try {
            $inst_id = $_POST['institucion_id'];
            $es_nueva = false;
            
            if ($inst_id === 'nueva') {
                $es_nueva = true;
                $pdo_hub->prepare("INSERT INTO instituciones (nombre, dominio_correo, rfc_homoclave, estatus) VALUES (?, ?, ?, 'pendiente')")->execute([$_POST['nueva_institucion'], $_POST['dominio_institucion'], strtoupper($_POST['rfc_institucion'])]);
                $inst_id = $pdo_hub->lastInsertId();
                $pdo_hub->prepare("INSERT INTO origenes_datos (institucion_id, nombre_campus, tipo_conexion, nombre_bd, nombre_vista) VALUES (?, 'Campus Base', 'demo_local', 'hub', 'vw')")->execute([$inst_id]);
            }
            
            $pdo_hub->prepare("INSERT INTO usuario_instituciones (usuario_id, institucion_id, cargo, justificacion, activo) VALUES (?, ?, ?, ?, 0)")->execute([$_SESSION['user_id'], $inst_id, $_POST['cargo'], $_POST['justificacion']]);
            
            if (!$es_nueva) {
                $stmtInstName = $pdo_hub->prepare("SELECT nombre FROM instituciones WHERE id = ?");
                $stmtInstName->execute([$inst_id]);
                $targetInstName = $stmtInstName->fetchColumn();

                $stmtUnivAdmins = $pdo_hub->prepare("
                    SELECT u.email, u.nombre 
                    FROM usuarios u 
                    LEFT JOIN usuario_instituciones ui ON u.id = ui.usuario_id AND ui.institucion_id = ?
                    WHERE (u.institucion_id = ? AND u.puede_aprobar = 1 AND u.activo = 1)
                       OR (ui.institucion_id = ? AND ui.puede_aprobar = 1 AND ui.activo = 1)
                ");
                $stmtUnivAdmins->execute([$inst_id, $inst_id, $inst_id]);
                
                foreach($stmtUnivAdmins->fetchAll() as $adminUniv) {
                    $msgUnivAdmin = "<h3 style='color:#0d6efd; margin-top:0;'>Solicitud de Vinculación de Multi-Campus</h3>
                                     <p>Hola <b>{$adminUniv['nombre']}</b>,</p>
                                     <p>El usuario <b>{$_SESSION['nombre']}</b> ({$_SESSION['email']}) ha solicitado vincular su cuenta actual a tu institución <b>$targetInstName</b>.</p>
                                     <p>Para autorizarlo, debes escalar este requerimiento internamente al SuperAdministrador de UPAEP.</p>
                                     $auditBlock";
                    enviarCorreoPUI($adminUniv['email'], $adminUniv['nombre'], "PUI - Solicitud de Vinculación Multi-Institución", $msgUnivAdmin);
                }
            }

            $flash_msg = "Solicitud enviada a revisión. Recibirás un correo cuando el administrador te apruebe."; $flash_type = "success";
            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Solicitar Acceso Multicuenta", "Solicitó unirse a ID Inst: $inst_id", $ip_accion);
        } catch(Exception $e) { $flash_msg = "Ocurrió un error o ya habías solicitado asociarte a esta institución previamente."; $flash_type = "warning"; }
    }

    if ($is_superadmin && isset($_POST['action']) && $_POST['action'] === 'aprobar_asociacion') {
        try {
            $pdo_hub->prepare("UPDATE usuario_instituciones SET activo = 1 WHERE id = ?")->execute([$_POST['assoc_id']]);
            $flash_msg = "Asociación multi-institución aprobada."; $flash_type = "success";
            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Aprobar Vínculo", "Aprobó solicitud de vinculación ID: {$_POST['assoc_id']}", $ip_accion);
        } catch(Exception $e) { $flash_msg = "Error: " . $e->getMessage(); $flash_type = "danger"; }
    }

    // =================================================================================
    // LÓGICA SUPERADMIN (UPAEP) - SE APLICA POR INSTITUCIÓN
    // =================================================================================
    if ($is_superadmin && isset($_POST['action']) && $_POST['action'] === 'aprobar_usuario') {
        try {
            $pdo_hub->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?")->execute([$_POST['user_id']]);
            $pdo_hub->prepare("UPDATE usuario_instituciones SET activo = 1 WHERE usuario_id = ? AND institucion_id = (SELECT institucion_id FROM usuarios WHERE id = ?)")->execute([$_POST['user_id'], $_POST['user_id']]);
            
            $flash_msg = "Usuario validado y aprobado exitosamente."; $flash_type = "success";
            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Aprobar Usuario", "Usuario ID: {$_POST['user_id']}", $ip_accion);
            
            $stmtU = $pdo_hub->prepare("SELECT u.email, u.nombre, i.nombre as inst FROM usuarios u LEFT JOIN instituciones i ON u.institucion_id = i.id WHERE u.id = ?"); 
            $stmtU->execute([$_POST['user_id']]); 
            $usr = $stmtU->fetch();
            
            if($usr) {
                $customAudit = str_replace($mi_institucion, $usr['inst'], $auditBlock);
                enviarCorreoPUI($usr['email'], $usr['nombre'], "PUI UPAEP - Cuenta Validada", "<h3 style='color:#198754; margin-top:0;'>Acceso Concedido</h3><p>Hola <b>{$usr['nombre']}</b>,</p><p>Tu cuenta institucional ha sido verificada y aprobada por el equipo de UPAEP. Ya tienes acceso a la plataforma Gateway PUI.</p>$customAudit");
            }
        } catch (Exception $e) { $flash_msg = "Error: " . $e->getMessage(); $flash_type = "danger"; }
    }

    if ($is_superadmin && isset($_POST['action']) && $_POST['action'] === 'aprobar_institucion') {
		if ($is_superadmin && isset($_POST['action']) && $_POST['action'] === 'suspender_institucion') {
			try {
				$pdo_hub->prepare("UPDATE instituciones SET estatus = 'suspendida' WHERE id = ?")->execute([$_POST['inst_id']]);
				$flash_msg = "Institución suspendida. El tráfico con la SEGOB se ha cortado."; $flash_type = "warning";
				registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Suspender Institución", "Activó el Kill Switch para la Institución ID: {$_POST['inst_id']}", $ip_accion);
			} catch (Exception $e) { $flash_msg = "Error: " . $e->getMessage(); $flash_type = "danger"; }
		}
        try {
            $pdo_hub->prepare("UPDATE instituciones SET estatus = 'aprobada' WHERE id = ?")->execute([$_POST['inst_id']]);
            $flash_msg = "Institución autorizada."; $flash_type = "success";
            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Autorizar Institución", "Institución ID: {$_POST['inst_id']}", $ip_accion);
            
            $stmtI = $pdo_hub->prepare("SELECT nombre FROM instituciones WHERE id = ?"); 
            $stmtI->execute([$_POST['inst_id']]); 
            $nomInst = $stmtI->fetchColumn();
            
            $customAudit = str_replace($mi_institucion, $nomInst, $auditBlock);
            
            $admins = $pdo_hub->prepare("SELECT email, nombre FROM usuarios WHERE institucion_id = ? AND puede_aprobar = 1"); $admins->execute([$_POST['inst_id']]);
            foreach($admins->fetchAll() as $adm) {
                enviarCorreoPUI($adm['email'], $adm['nombre'], "PUI UPAEP - Universidad Autorizada", "<h3 style='color:#0d6efd; margin-top:0;'>Institución Validada Oficialmente</h3><p>Hola <b>{$adm['nombre']}</b>,</p><p>El nodo general de <b>$nomInst</b> ha sido autorizado en el HUB Central.</p><p>Las URLs de interconexión expuestas por el Gateway ya están operativas para ser registradas en la PUI del gobierno federal.</p>$customAudit");
            }
        } catch (Exception $e) { $flash_msg = "Error: " . $e->getMessage(); $flash_type = "danger"; }
    }

    if ($is_superadmin && isset($_POST['action']) && $_POST['action'] === 'toggle_delegar_superadmin') {
        try {
            $target_uid = $_POST['target_user_id'];
            $target_inst = $_POST['inst_id'];
            
            $uData = $pdo_hub->prepare("
                SELECT u.email, u.nombre, i.nombre as inst,
                (SELECT puede_aprobar FROM (
                    SELECT puede_aprobar FROM usuarios WHERE id = ? AND institucion_id = ?
                    UNION 
                    SELECT puede_aprobar FROM usuario_instituciones WHERE usuario_id = ? AND institucion_id = ?
                ) t LIMIT 1) as puede_aprobar
                FROM usuarios u 
                LEFT JOIN instituciones i ON i.id = ?
                WHERE u.id = ?
            "); 
            $uData->execute([$target_uid, $target_inst, $target_uid, $target_inst, $target_inst, $target_uid]); 
            $usr = $uData->fetch();
            
            $new_val = $usr['puede_aprobar'] ? 0 : 1;
            
            $pdo_hub->prepare("UPDATE usuarios SET puede_aprobar = ? WHERE id = ? AND institucion_id = ?")->execute([$new_val, $target_uid, $target_inst]);
            $pdo_hub->prepare("UPDATE usuario_instituciones SET puede_aprobar = ? WHERE usuario_id = ? AND institucion_id = ?")->execute([$new_val, $target_uid, $target_inst]);
            
            $flash_msg = "Privilegios actualizados."; $flash_type = "success";
            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Modificar Privilegios Admin", "Afectó Usuario ID: $target_uid en la Inst: $target_inst", $ip_accion);

            $rolText = $new_val ? "<strong style='color:green;'>OTORGADO</strong> el rol de Administrador Institucional" : "<strong style='color:red;'>REVOCADO</strong> el rol de Administrador Institucional";
            $customAudit = str_replace($mi_institucion, $usr['inst'], $auditBlock);
            
            enviarCorreoPUI($usr['email'], $usr['nombre'], "PUI UPAEP - Actualización de Privilegios", "<h3 style='color:#ffc107; margin-top:0;'>Modificación de Permisos</h3><p>Hola <b>{$usr['nombre']}</b>,</p><p>El Superadministrador de UPAEP te ha $rolText en tu institución. Este cambio aplica de forma inmediata.</p>$customAudit");
        } catch (Exception $e) { $flash_msg = "Error: " . $e->getMessage(); $flash_type = "danger"; }
    }

    // =================================================================================
    // LÓGICA DE GESTIÓN DE EQUIPO (ADMINISTRADORES INSTITUCIONALES) 
    // =================================================================================
    if (!$is_superadmin && $puede_aprobar && isset($_POST['action']) && $_POST['action'] === 'aprobar_usuario_inst') {
        try {
            $target_uid = $_POST['target_user_id'];
            $current_inst = $_SESSION['institucion_id'];
            
            $pdo_hub->prepare("UPDATE usuarios SET activo = 1 WHERE id = ? AND institucion_id = ?")->execute([$target_uid, $current_inst]);
            $pdo_hub->prepare("UPDATE usuario_instituciones SET activo = 1 WHERE usuario_id = ? AND institucion_id = ?")->execute([$target_uid, $current_inst]);
            
            $flash_msg = "Compañero aprobado."; $flash_type = "success";
            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Aprobar Colaborador", "Aprobó ID: $target_uid en Inst: $current_inst", $ip_accion);
            
            $stmtU = $pdo_hub->prepare("SELECT email, nombre FROM usuarios WHERE id = ?"); $stmtU->execute([$target_uid]); $usr = $stmtU->fetch();
            
            if($usr) {
                enviarCorreoPUI($usr['email'], $usr['nombre'], "PUI UPAEP - Acceso Concedido", "<h3 style='color:#198754; margin-top:0;'>Acceso Concedido</h3><p>Hola <b>{$usr['nombre']}</b>,</p><p>El Administrador de tu institución ha aprobado tu acceso a la plataforma. Ya puedes iniciar sesión de manera normal.</p>$auditBlock");
                if ($sa) enviarCorreoPUI($sa['email'], $sa['nombre'], "Aviso: Nuevo Usuario Validado en Institución", "<p>Un administrador de la institución ha dado de alta a un nuevo miembro de su equipo.</p><p><b>Nuevo usuario:</b> {$usr['email']}</p>$auditBlock");
            }
        } catch (Exception $e) { $flash_msg = "Error: " . $e->getMessage(); $flash_type = "danger"; }
    }

    if (!$is_superadmin && $puede_aprobar && isset($_POST['action']) && $_POST['action'] === 'toggle_delegar_inst') {
        try {
            $target_uid = $_POST['target_user_id'];
            $current_inst = $_SESSION['institucion_id'];
            
            if ($target_uid != $_SESSION['user_id']) { 
                
                $stmtCheck = $pdo_hub->prepare("
                    SELECT 'u' as source, puede_aprobar FROM usuarios WHERE id = ? AND institucion_id = ?
                    UNION
                    SELECT 'ui' as source, puede_aprobar FROM usuario_instituciones WHERE usuario_id = ? AND institucion_id = ?
                ");
                $stmtCheck->execute([$target_uid, $current_inst, $target_uid, $current_inst]);
                $rows = $stmtCheck->fetchAll();
                
                $new_val = 0;
                if (!empty($rows)) {
                    $new_val = $rows[0]['puede_aprobar'] ? 0 : 1;
                    $pdo_hub->prepare("UPDATE usuarios SET puede_aprobar = ? WHERE id = ? AND institucion_id = ?")->execute([$new_val, $target_uid, $current_inst]);
                    $pdo_hub->prepare("UPDATE usuario_instituciones SET puede_aprobar = ? WHERE usuario_id = ? AND institucion_id = ?")->execute([$new_val, $target_uid, $current_inst]);
                }
                
                $flash_msg = "Permisos actualizados."; $flash_type = "success";
                registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Modificar Privilegios Equipo", "Afectó ID: $target_uid en Inst: $current_inst", $ip_accion);
                
                $uData = $pdo_hub->prepare("SELECT email, nombre FROM usuarios WHERE id = ?"); $uData->execute([$target_uid]); $usr = $uData->fetch();
                $rolText = $new_val ? "<strong style='color:green;'>OTORGADO</strong> el rol de Administrador de la Universidad" : "<strong style='color:red;'>REVOCADO</strong> el rol de Administrador de la Universidad";
                
                enviarCorreoPUI($usr['email'], $usr['nombre'], "PUI - Delegación de Permisos", "<h3 style='color:#ffc107; margin-top:0;'>Delegación de Permisos</h3><p>Hola <b>{$usr['nombre']}</b>,</p><p>Se te ha $rolText.</p>$auditBlock");
            }
        } catch (Exception $e) { $flash_msg = "Error: " . $e->getMessage(); $flash_type = "danger"; }
    }
    
    // =================================================================================
    // LÓGICA DE MANTENIMIENTO TÉCNICO Y ARCHIVOS (AGREGADO clave_webhook)
    // =================================================================================
    if (!$is_superadmin && isset($_POST['action']) && $_POST['action'] === 'guardar_llaves') {
        try {
            $pdo_hub->prepare("UPDATE instituciones SET llave_publica_gob = ?, llave_privada_inst = ?, clave_biometricos = ?, clave_api_gobierno = ?, clave_webhook = ? WHERE id = ?")
                    ->execute([trim($_POST['llave_pub']), trim($_POST['llave_priv']), trim($_POST['clave_bio']), trim($_POST['clave_api_gob']), trim($_POST['clave_webhook']), $_SESSION['institucion_id']]);
            $flash_msg = "Llaves y credenciales actualizadas."; $flash_type = "success";
            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Modificar Criptografía", "Modificó las llaves y/o credenciales API de la red", $ip_accion);
            
            $msgLlaves = "<h3 style='color:#dc3545; margin-top:0;'>ALERTA DE SEGURIDAD: Criptografía Modificada</h3>
                          <p>Se acaba de modificar el esquema de <b>Llaves Criptográficas y/o Credenciales API</b> de la institución.</p>
                          <p>Si esta acción no fue autorizada, repórtenlo inmediatamente.</p>$auditBlock";
            
            if ($sa) enviarCorreoPUI($sa['email'], $sa['nombre'], "ALERTA PUI: Modificación de Llaves", $msgLlaves);
            
            $admins = $pdo_hub->prepare("
                SELECT u.email, u.nombre FROM usuarios u 
                LEFT JOIN usuario_instituciones ui ON u.id = ui.usuario_id AND ui.institucion_id = ?
                WHERE (u.institucion_id = ? AND u.puede_aprobar = 1) OR (ui.institucion_id = ? AND ui.puede_aprobar = 1)
            "); 
            $admins->execute([$_SESSION['institucion_id'], $_SESSION['institucion_id'], $_SESSION['institucion_id']]);
            foreach($admins->fetchAll() as $adm) enviarCorreoPUI($adm['email'], $adm['nombre'], "ALERTA: Modificación Criptográfica PUI", $msgLlaves);

        } catch (Exception $e) { $flash_msg = "Error: " . $e->getMessage(); $flash_type = "danger"; }
    }

    if (!$is_superadmin && isset($_POST['action']) && $_POST['action'] === 'guardar_campus') {
        try {
            $tipo_db = $_POST['tipo_db'];
            $req_ssh = isset($_POST['req_ssh']) ? 1 : 0;
            $pass_enc = !empty($_POST['pass_db']) ? encryptStr($_POST['pass_db']) : null;
            $ssh_pass_enc = !empty($_POST['ssh_pass']) ? encryptStr($_POST['ssh_pass']) : null;
            $origen_id = !empty($_POST['origen_id']) ? $_POST['origen_id'] : null;
            $archivo_ruta = null;

            $puerto = !empty($_POST['puerto']) ? (int)$_POST['puerto'] : null;
            $ssh_puerto = !empty($_POST['ssh_puerto']) ? (int)$_POST['ssh_puerto'] : null;
            $host = !empty($_POST['host']) ? $_POST['host'] : null;
            $user_db = !empty($_POST['user_db']) ? $_POST['user_db'] : null;
            $nombre_bd = !empty($_POST['nombre_bd']) ? $_POST['nombre_bd'] : null;
            $vista = !empty($_POST['vista']) ? $_POST['vista'] : null;
            $ssh_host = !empty($_POST['ssh_host']) ? $_POST['ssh_host'] : null;
            $ssh_user = !empty($_POST['ssh_user']) ? $_POST['ssh_user'] : null;

            if ($tipo_db === 'manual') {
                if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/uploads/inst_' . $_SESSION['institucion_id'] . '/';
                    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                    $ext = strtolower(pathinfo($_FILES['archivo_csv']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['csv', 'json'])) throw new Exception("Solo archivos CSV/JSON.");
                    $archivo_ruta = $upload_dir . time() . '_datos.' . $ext;
                    move_uploaded_file($_FILES['archivo_csv']['tmp_name'], $archivo_ruta);
                } elseif (empty($origen_id)) {
                    throw new Exception("Debes subir un archivo para el tipo de conexión manual.");
                }
            }

            if (!empty($origen_id)) {
                $stmtOld = $pdo_hub->prepare("SELECT archivo_local FROM origenes_datos WHERE id = ? AND institucion_id = ?");
                $stmtOld->execute([$origen_id, $_SESSION['institucion_id']]);
                $oldNode = $stmtOld->fetch();
                if ($oldNode && !empty($oldNode['archivo_local']) && file_exists($oldNode['archivo_local'])) {
                    if ($archivo_ruta || $tipo_db !== 'manual') unlink($oldNode['archivo_local']);
                }

                $sql = "UPDATE origenes_datos SET nombre_campus=?, tipo_conexion=?, host=?, puerto=?, usuario=?, nombre_bd=?, nombre_vista=?, requiere_ssh=?, ssh_host=?, ssh_puerto=?, ssh_usuario=? ";
                $params = [$_POST['nombre_campus'], $tipo_db, $host, $puerto, $user_db, $nombre_bd, $vista, $req_ssh, $ssh_host, $ssh_puerto, $ssh_user];
                
                if ($pass_enc) { $sql .= ", password_encriptado=? "; $params[] = $pass_enc; }
                if ($ssh_pass_enc) { $sql .= ", ssh_password_encriptado=? "; $params[] = $ssh_pass_enc; }
                if ($archivo_ruta) { $sql .= ", archivo_local=? "; $params[] = $archivo_ruta; } 
                elseif ($tipo_db !== 'manual') { $sql .= ", archivo_local=NULL "; }
                
                $sql .= " WHERE id=? AND institucion_id=?"; 
                $params[] = $origen_id; $params[] = $_SESSION['institucion_id'];
                
                $pdo_hub->prepare($sql)->execute($params);
                $flash_msg = "Configuración del nodo actualizada.";
                registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Actualizar Nodo", "Editó nodo: {$_POST['nombre_campus']} (ID: $origen_id)", $ip_accion);
                
            } else {
                $pdo_hub->prepare("INSERT INTO origenes_datos (institucion_id, nombre_campus, tipo_conexion, host, puerto, usuario, password_encriptado, nombre_bd, nombre_vista, requiere_ssh, ssh_host, ssh_puerto, ssh_usuario, ssh_password_encriptado, archivo_local) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$_SESSION['institucion_id'], $_POST['nombre_campus'], $tipo_db, $host, $puerto, $user_db, $pass_enc, $nombre_bd, $vista, $req_ssh, $ssh_host, $ssh_puerto, $ssh_user, $ssh_pass_enc, $archivo_ruta]);
                $flash_msg = "Nodo agregado exitosamente.";
                registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Crear Nodo", "Instaló un nuevo nodo: {$_POST['nombre_campus']}", $ip_accion);
            }
            $flash_type = "success";
        } catch (Exception $e) { $flash_msg = "Error: " . $e->getMessage(); $flash_type = "danger"; }
    }

    if (!$is_superadmin && isset($_POST['action']) && $_POST['action'] === 'eliminar_campus') {
        try {
            $stmtDel = $pdo_hub->prepare("SELECT archivo_local, nombre_campus FROM origenes_datos WHERE id = ? AND institucion_id = ?");
            $stmtDel->execute([$_POST['origen_id'], $_SESSION['institucion_id']]);
            $nodo = $stmtDel->fetch();
            if ($nodo && !empty($nodo['archivo_local']) && file_exists($nodo['archivo_local'])) unlink($nodo['archivo_local']);
            $pdo_hub->prepare("DELETE FROM origenes_datos WHERE id = ? AND institucion_id = ?")->execute([$_POST['origen_id'], $_SESSION['institucion_id']]);
            $flash_msg = "Nodo eliminado."; $flash_type = "success";
            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Eliminar Nodo", "Borró permanentemente el nodo: {$nodo['nombre_campus']} (ID: {$_POST['origen_id']})", $ip_accion);
        } catch (Exception $e) { $flash_msg = "Error: " . $e->getMessage(); $flash_type = "danger"; }
    }

    // =================================================================================
    // LÓGICA DE SIMULACIÓN REAL HTTP CURL
    // =================================================================================
    if (!$is_superadmin && isset($_POST['action']) && $_POST['action'] === 'simular') {
        $curp_simular = strtoupper(trim($_POST['curp_simular']));
        try {
            $stmtInst = $pdo_hub->prepare("SELECT * FROM instituciones WHERE id = ?");
            $stmtInst->execute([$_SESSION['institucion_id']]);
            $inst_data = $stmtInst->fetch();
            
            $rfc_inst = urlencode($inst_data['rfc_homoclave']);
            $has_keys = !empty($inst_data['llave_publica_gob']) && !empty($inst_data['llave_privada_inst']);
            $nivel_simulacion = $has_keys ? "Nivel 2: Avanzado (Muestra datos extraídos y simula Firma JWS)" : "Nivel 1: Básico (Sin llaves. Solo texto plano)";

            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
            $protocol = $is_https ? "https://" : "http://";
            $base_url_api = $protocol . $_SERVER['HTTP_HOST'] . "/dit/tools/pui/api_pui.php";

            // PASO 1: Login Gubernamental (Usa la clave Webhook si existe, sino simulación)
            $login_url = $base_url_api . "?tenant=" . $rfc_inst . "&ep=login";
            $clave_webhook_sim = !empty($inst_data['clave_webhook']) ? $inst_data['clave_webhook'] : 'SIMULACION_GOB_2026';
            
            $ch = curl_init($login_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['usuario' => 'PUI', 'clave' => $clave_webhook_sim]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $login_res = curl_exec($ch);
            $login_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $login_data = json_decode($login_res, true);
            $token = $login_data['token'] ?? 'TOKEN_NO_RECIBIDO';

            // PASO 2: Disparo de Búsqueda
            $reporte_url = $base_url_api . "?tenant=" . $rfc_inst . "&ep=activar-reporte";
            $payload_gobierno = ['id' => 'SIM-' . strtoupper(uniqid()), 'curp' => $curp_simular, 'fase_busqueda' => '3'];

            $ch2 = curl_init($reporte_url);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true); 
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($payload_gobierno));
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $token]);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
            $reporte_res = curl_exec($ch2);
            $reporte_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            $parsed_reporte = json_decode($reporte_res, true) ?: $reporte_res;

            $simulacion_resultado = [
                "_Meta" => ["Modo_Prueba" => "Consumo HTTP Real (cURL)", "Nivel_Realismo" => $nivel_simulacion],
                "Paso_1_Autenticacion" => ["URL" => "POST /login", "HTTP_Status" => $login_code, "Respuesta_API" => $login_data],
                "Paso_2_Activar_Busqueda" => [
                    "URL" => "POST /activar-reporte",
                    "Headers" => "Authorization: Bearer " . substr($token, 0, 15) . "...",
                    "Payload_Enviado" => $payload_gobierno,
                    "HTTP_Status" => $reporte_code,
                    "Respuesta_API_Final" => $parsed_reporte
                ]
            ];

            if ($has_keys && isset($parsed_reporte['datos_extraidos']) && !empty($parsed_reporte['datos_extraidos'])) {
                $payload_secreto = base64_encode(json_encode($parsed_reporte['datos_extraidos']));
                $simulacion_resultado["Paso_3_Cifrado_JWS_(Transmision_Segura)"] = [
                    "Info" => "El HUB encripta tu información antes de entregarla al Gobierno.",
                    "Firma_Header_JWS" => "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6IjIwMjYifQ...",
                    "Payload_Cifrado_AES" => substr($payload_secreto, 0, 80) . "... (Contenido Oculto)"
                ];
            }
            
            registrarAuditoria($pdo_hub, $_SESSION['user_id'], "Prueba de Simulador HTTP", "Disparó petición de prueba de fase 3 a la CURP: $curp_simular", $ip_accion);
        } catch (Exception $e) { $simulacion_resultado = ["error_general_http" => $e->getMessage()]; }
    }
}

// =================================================================================
// CARGA DE VARIABLES PARA LA INTERFAZ
// =================================================================================
$inst = null; $campus_list = []; $mis_instituciones_extra = [];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . "/dit/tools/pui/api_pui.php";

if (!$is_superadmin) {
    $stmt = $pdo_hub->prepare("SELECT * FROM instituciones WHERE id = ?"); $stmt->execute([$_SESSION['institucion_id']]); $inst = $stmt->fetch();
    $stmtDb = $pdo_hub->prepare("SELECT * FROM origenes_datos WHERE institucion_id = ? ORDER BY nombre_campus ASC"); $stmtDb->execute([$_SESSION['institucion_id']]); $campus_list = $stmtDb->fetchAll();
    
    $stmtMulti = $pdo_hub->prepare("
        SELECT ui.institucion_id, ui.activo, ui.cargo, i.nombre, i.rfc_homoclave 
        FROM usuario_instituciones ui 
        JOIN instituciones i ON ui.institucion_id = i.id 
        WHERE ui.usuario_id = ?
        UNION
        SELECT u.institucion_id, u.activo, u.cargo, i.nombre, i.rfc_homoclave
        FROM usuarios u
        JOIN instituciones i ON u.institucion_id = i.id
        WHERE u.id = ?
    ");
    $stmtMulti->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $mis_instituciones_extra = $stmtMulti->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HUB PUI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php require_once __DIR__ . '/views/analytics.php'; ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', system-ui, sans-serif; }
        .top-navbar { background: linear-gradient(90deg, #0d1117, #1e293b); }
        .custom-nav-tabs .list-group-item { border: none; border-bottom: 1px solid #f0f0f0; color: #4b5563; }
        .custom-nav-tabs .list-group-item.active { background-color: #0d6efd; color: white; border-color: #0d6efd; }
        .card { border-radius: 0.75rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg top-navbar text-white shadow-sm px-4 py-3">
    <span class="navbar-brand fw-bold text-white"><i class="fa-solid fa-shield-halved text-success me-2"></i> HUB PUI Central</span>
    
    <?php if(!$is_superadmin && count($mis_instituciones_extra) > 1): ?>
        <form method="POST" class="ms-4 me-auto mb-0">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="cambiar_institucion">
            <select name="target_inst" class="form-select form-select-sm bg-dark text-white border-secondary fw-bold" onchange="this.form.submit()">
                <?php foreach($mis_instituciones_extra as $mex): if($mex['activo']==1): ?>
                    <option value="<?= $mex['institucion_id'] ?>" <?= ($mex['institucion_id'] == $inst['id']) ? 'selected' : '' ?>>
                        <?= ($mex['institucion_id'] == $inst['id']) ? '🔹 ' : '' ?><?= htmlspecialchars($mex['nombre']) ?> <?= ($mex['institucion_id'] == $inst['id']) ? '(Actual)' : '' ?>
                    </option>
                <?php endif; endforeach; ?>
            </select>
        </form>
    <?php endif; ?>

    <div class="ms-auto d-flex align-items-center">
        <?php if($puede_aprobar): ?>
            <span class="badge bg-warning text-dark me-3 shadow-sm"><i class="fa-solid fa-star"></i> Admin Delegado</span>
        <?php endif; ?>
        <span class="me-4 small"><i class="fa-regular fa-user me-1"></i> <?= htmlspecialchars($_SESSION['email']) ?> (<?= strtoupper($_SESSION['rol']) ?>)</span>
        <a href="logout.php" class="btn btn-danger btn-sm fw-bold"><i class="fa-solid fa-power-off me-1"></i> Salir</a>
    </div>
</nav>

<div class="container-fluid mt-4 px-4 pb-5">
    <?php if($flash_msg): ?>
        <div class="alert alert-<?= $flash_type ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-<?= $flash_type == 'success' ? 'check' : 'xmark' ?> me-2"></i> <?= $flash_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php 
        if ($is_superadmin) { require_once __DIR__ . '/views/admin_view.php'; } 
        else { require_once __DIR__ . '/views/univ_view.php'; }
    ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('.datatable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            pageLength: 10, ordering: true
        });

        <?php if($simulacion_resultado): ?>
            var simuladorTab = new bootstrap.Tab(document.querySelector('a[href="#list-simulador"]'));
            simuladorTab.show();
        <?php endif; ?>
    });
</script>
</body>
</html>