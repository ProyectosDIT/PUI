<?php
// /var/www/html/dit/tools/pui/registro.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mailer.php';

if (!isset($_SESSION['tmp_google_id'])) {
    header('Location: index.php');
    exit;
}

$ip_accion = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
if (strpos($ip_accion, ',') !== false) {
    $ip_accion = trim(explode(',', $ip_accion)[0]); // Si hay varias, tomar la primera
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo_hub->beginTransaction();
        $inst_id = $_POST['institucion_id'];
        $nombre_inst_registro = "";
        
        if ($inst_id === 'nueva') {
            $nombre_inst_registro = trim($_POST['nueva_institucion']);
            $dominio = trim($_POST['dominio_institucion']);
            $rfc = strtoupper(trim($_POST['rfc_institucion']));
            
            $stmtCheckRfc = $pdo_hub->prepare("SELECT id FROM instituciones WHERE rfc_homoclave = ?");
            $stmtCheckRfc->execute([$rfc]);
            if ($stmtCheckRfc->fetch()) {
                throw new Exception("El RFC ingresado ($rfc) ya está registrado en otra institución.");
            }

            $stmtInst = $pdo_hub->prepare("INSERT INTO instituciones (nombre, dominio_correo, rfc_homoclave, estatus) VALUES (?, ?, ?, 'pendiente')");
            $stmtInst->execute([$nombre_inst_registro, $dominio, $rfc]);
            $inst_id = $pdo_hub->lastInsertId();

            $stmtDemo = $pdo_hub->prepare("INSERT INTO origenes_datos (institucion_id, nombre_campus, tipo_conexion, host, puerto, usuario, password_encriptado, nombre_bd, nombre_vista) VALUES (?, 'Campus de Prueba Local', 'demo_local', NULL, NULL, NULL, NULL, 'pui_upaep_hub', 'vw_ejemplo_universidad')");
            $stmtDemo->execute([$inst_id]);
        } else {
            // Obtener el nombre de la institución existente para el correo
            $stmtN = $pdo_hub->prepare("SELECT nombre FROM instituciones WHERE id = ?");
            $stmtN->execute([$inst_id]);
            $nombre_inst_registro = $stmtN->fetchColumn();
        }

        $stmtCount = $pdo_hub->prepare("SELECT COUNT(*) FROM usuarios WHERE institucion_id = ?");
        $stmtCount->execute([$inst_id]);
        $puede_aprobar = ($stmtCount->fetchColumn() == 0) ? 1 : 0;

        $stmtUser = $pdo_hub->prepare("INSERT INTO usuarios (institucion_id, google_id, email, nombre, cargo, telefono, justificacion, activo, puede_aprobar) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)");
        $stmtUser->execute([
            $inst_id, $_SESSION['tmp_google_id'], $_SESSION['tmp_email'], $_SESSION['tmp_name'],
            trim($_POST['cargo']), trim($_POST['telefono']), trim($_POST['justificacion']), $puede_aprobar
        ]);

        $pdo_hub->commit();
        
        // --- NOTIFICACIONES POR CORREO ---
        $userEmail = $_SESSION['tmp_email'];
        $userName = $_SESSION['tmp_name'];
        $fecha_accion = date('d/m/Y H:i:s');
        
        // Bloque de auditoría HTML reutilizable
        $auditBlock = "
        <div style='background-color:#f8fafc; padding:15px; border-left:4px solid #0d6efd; margin-top:25px; font-size:13px;'>
            <strong style='color:#0f172a;'>Ficha de Registro:</strong><br><br>
            &bull; <b>Institución:</b> $nombre_inst_registro<br>
            &bull; <b>Usuario Solicitante:</b> $userEmail<br>
            &bull; <b>Dirección IP:</b> $ip_accion<br>
            &bull; <b>Fecha:</b> $fecha_accion
        </div>";

        // 1. Alerta al Superadministrador
        $stmtSa = $pdo_hub->query("SELECT email, nombre FROM usuarios WHERE rol = 'superadmin' LIMIT 1");
        $superadmin = $stmtSa->fetch();
        if ($superadmin) {
            $msgSA = "<h3 style='color:#0d6efd; margin-top:0;'>Nueva Solicitud de Acceso</h3>
                      <p>El usuario <b>{$userName}</b> ha solicitado unirse a la plataforma Gateway PUI.</p>
                      <p><b>Cargo:</b> {$_POST['cargo']}<br><b>Justificación:</b> {$_POST['justificacion']}</p>
                      <p>Por favor, ingresa al panel de administración para auditar y validar su acceso.</p>
                      $auditBlock";
            enviarCorreoPUI($superadmin['email'], $superadmin['nombre'], "PUI - Nueva Solicitud: $nombre_inst_registro", $msgSA);
        }

        // 2. Correo de Confirmación al Usuario
        $msgUser = "<h3 style='color:#198754; margin-top:0;'>Solicitud en Proceso</h3>
                    <p>Hola <b>{$userName}</b>,</p>
                    <p>Hemos recibido correctamente tu solicitud para acceder al <b>Gateway Institucional PUI</b> en representación de <b>$nombre_inst_registro</b>.</p>
                    <p>Por políticas de ciberseguridad, tu cuenta se encuentra actualmente en un proceso de auditoría. Recibirás una notificación en este correo en cuanto tu acceso sea autorizado.</p>
                    $auditBlock";
        enviarCorreoPUI($userEmail, $userName, "Confirmación de Registro - Gateway PUI", $msgUser);

        unset($_SESSION['tmp_google_id'], $_SESSION['tmp_email'], $_SESSION['tmp_name']);
        
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Registro Exitoso - Gateway PUI</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body { background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%); min-height: 100vh; display: flex; flex-direction: column; font-family: 'Segoe UI', system-ui, sans-serif; }
                .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
                .icon-circle { width: 90px; height: 90px; background: #d1e7dd; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3); position: relative; }
                .icon-circle i { font-size: 3rem; color: #198754; }
                .footer-logos img { height: 40px; object-fit: contain; filter: grayscale(100%); opacity: 0.6; transition: 0.3s; }
                .footer-logos img:hover { filter: grayscale(0%); opacity: 1; }
                .sp-digital-logo { font-size: 1.1rem; text-decoration: none; display: inline-block; }
                .sp-digital-logo .sp { font-weight: 900; color: #0d6efd; }
                .sp-digital-logo .digital { font-weight: 300; color: #4b5563; }
            </style>
			 <?php require_once __DIR__ . '/views/analytics.php'; ?>
        </head>
        <body class="align-items-center justify-content-center">
            <div class="container d-flex flex-column justify-content-center flex-grow-1" style="max-width: 650px;">
                <div class="card glass-card p-5 text-center">
                    <div class="icon-circle"><i class="fa-solid fa-check-double"></i></div>
                    <h2 class="fw-bold text-dark mb-3">¡Solicitud Procesada!</h2>
                    
                    <p class="text-secondary fs-5 mb-4">Tus datos y los de tu institución han sido enviados correctamente al HUB Central de UPAEP para su revisión.</p>
                    
                    <div class="alert bg-light border border-success text-start shadow-sm mb-4 p-4 rounded-4">
                        <p class="mb-3 d-flex align-items-center"><i class="fa-solid fa-server fs-4 me-3 text-primary"></i> <span>Hemos precargado un <strong>Campus de Prueba</strong> en tu cuenta para que puedas usar el Simulador en cuanto ingreses.</span></p>
                        <hr class="text-secondary opacity-25">
                        <p class="mb-0 d-flex align-items-center"><i class="fa-solid fa-list-check fs-4 me-3 text-success"></i> <span><strong>Siguiente Paso:</strong> El equipo auditará tu expediente para autorizar tu acceso seguro. <b>Te hemos enviado un correo de confirmación.</b></span></p>
                    </div>

                    <a href='index.php' class="btn btn-dark btn-lg fw-bold rounded-pill px-5 py-3 mt-2 shadow-sm"><i class="fa-solid fa-house me-2"></i> Volver al Inicio</a>
                </div>
            </div>

            <div class="w-100 text-center py-4 mt-auto">
                <p class="small text-muted mb-3 fw-bold text-uppercase tracking-wider" style="letter-spacing: 1px;">Infraestructura soportada por:</p>
                <div class="d-flex justify-content-center align-items-center gap-4 flex-wrap footer-logos mb-3">
                    <a href="https://upaep.mx" target="_blank"><img src="https://upaep.mx/images/upaep/Logo_UPAEP.svg" alt="UPAEP"></a>
                    <span class="text-muted opacity-50 fs-4">|</span>
                    <img src="https://shadow.spdigital.mx/images/logo/dit_color.png?v=1773553723" alt="DIT">
                </div>
                <p class="small text-muted mb-0">Desarrollada por <a href="https://spdigital.mx/" target="_blank" class="sp-digital-logo"><span class="sp">SP.</span><span class="digital">Digital</span></a></p>
            </div>
        </body>
        </html>
        <?php
        exit;

    } catch (Exception $e) {
        $pdo_hub->rollBack();
        $error = "Error al procesar el registro: " . $e->getMessage();
    }
}

$instituciones = $pdo_hub->query("SELECT id, nombre, rfc_homoclave FROM instituciones ORDER BY nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar Registro - Gateway PUI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%); min-height: 100vh; display: flex; flex-direction: column; font-family: 'Segoe UI', system-ui, sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
        .footer-logos img { height: 40px; object-fit: contain; filter: grayscale(100%); opacity: 0.6; transition: 0.3s; }
        .footer-logos img:hover { filter: grayscale(0%); opacity: 1; }
        .sp-digital-logo { font-size: 1.1rem; text-decoration: none; display: inline-block; }
        .sp-digital-logo .sp { font-weight: 900; color: #0d6efd; }
        .sp-digital-logo .digital { font-weight: 300; color: #4b5563; }
    </style>
	<?php require_once __DIR__ . '/views/analytics.php'; ?>
</head>
<body class="align-items-center justify-content-center pt-5">

    <div class="container d-flex flex-column justify-content-center flex-grow-1 mb-5" style="max-width: 750px;">
        <div class="card glass-card border-0 p-5">
            <div class="text-center mb-5">
                <i class="fa-solid fa-building-user fa-4x text-primary mb-3"></i>
                <h3 class="fw-bold text-dark">Alta Institucional - Gateway PUI</h3>
                <p class="text-secondary">Hola <strong><?= htmlspecialchars($_SESSION['tmp_name']) ?></strong> (<?= htmlspecialchars($_SESSION['tmp_email']) ?>).<br>Para auditar tu acceso, requerimos validar la institución educativa a la que representas.</p>
            </div>

            <?php if(isset($error)) echo "<div class='alert alert-danger shadow-sm fw-bold rounded-3'><i class='fa-solid fa-triangle-exclamation me-2'></i> $error</div>"; ?>

            <form method="POST">
                <div class="mb-4">
                    <label class="fw-bold text-dark mb-2">Institución Educativa Representada</label>
                    <select name="institucion_id" id="institucion_id" class="form-select form-select-lg border-primary border-opacity-50 fw-bold shadow-sm rounded-3" required onchange="toggleNuevaInst()">
                        <option value="">-- Selecciona tu Universidad --</option>
                        <?php foreach($instituciones as $inst): ?>
                            <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['nombre']) ?> (RFC: <?= htmlspecialchars($inst['rfc_homoclave']) ?>)</option>
                        <?php endforeach; ?>
                        <option value="nueva" class="fw-bold text-primary">+ Registrar Nueva Universidad al HUB</option>
                    </select>
                </div>

                <div id="div_nueva_inst" style="display: none;" class="p-4 bg-white border border-primary border-opacity-25 rounded-4 mb-4 shadow-sm">
                    <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-circle-info me-2"></i> Expediente de Nueva Institución</h6>
                    <div class="mb-3">
                        <label class="fw-bold small text-muted mb-1">Nombre Oficial de la Universidad</label>
                        <input type="text" name="nueva_institucion" id="nueva_institucion" class="form-control rounded-3 bg-light" placeholder="Ej. Universidad Autónoma...">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="fw-bold small text-muted mb-1">RFC (Con Homoclave)</label>
                            <input type="text" name="rfc_institucion" id="rfc_institucion" class="form-control rounded-3 bg-light text-uppercase font-monospace" placeholder="Ej. UPAE123456789">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small text-muted mb-1">Sitio Web (Dominio Oficial)</label>
                            <input type="text" name="dominio_institucion" id="dominio_institucion" class="form-control rounded-3 bg-light" placeholder="Ej. upaep.mx">
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4 border-top pt-4">
                    <div class="col-md-6">
                        <label class="fw-bold small text-muted mb-1">Tu Cargo / Puesto</label>
                        <input type="text" name="cargo" class="form-control rounded-3 bg-light" placeholder="Ej. Director de TI, CIO..." required>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold small text-muted mb-1">Teléfono de Contacto (Laboral)</label>
                        <input type="text" name="telefono" class="form-control rounded-3 bg-light font-monospace" placeholder="Con extensión si aplica" required>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="fw-bold small text-muted mb-1">Justificación Legal para el Acceso a la Plataforma</label>
                    <textarea name="justificacion" class="form-control rounded-3 bg-light" rows="3" placeholder="Fui designado por rectoría para coordinar la interconexión con la PUI del gobierno federal..." required></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-bold py-3 shadow rounded-pill fs-5"><i class="fa-solid fa-paper-plane me-2"></i> Enviar Expediente a Revisión</button>
            </form>
        </div>
    </div>

    <div class="w-100 text-center py-4 mt-auto">
        <p class="small text-muted mb-3 fw-bold text-uppercase tracking-wider" style="letter-spacing: 1px;">Infraestructura soportada por:</p>
        <div class="d-flex justify-content-center align-items-center gap-4 flex-wrap footer-logos mb-3">
            <a href="https://upaep.mx" target="_blank"><img src="https://upaep.mx/images/upaep/Logo_UPAEP.svg" alt="UPAEP"></a>
            <span class="text-muted opacity-50 fs-4">|</span>
            <img src="https://shadow.spdigital.mx/images/logo/dit_color.png?v=1773553723" alt="DIT">
        </div>
        <p class="small text-muted mb-0">Desarrollada por <a href="https://spdigital.mx/" target="_blank" class="sp-digital-logo"><span class="sp">SP.</span><span class="digital">Digital</span></a></p>
    </div>

    <script>
        function toggleNuevaInst() {
            var select = document.getElementById('institucion_id');
            var divNueva = document.getElementById('div_nueva_inst');
            var reqInputs = ['nueva_institucion', 'rfc_institucion', 'dominio_institucion'];
            if (select.value === 'nueva') {
                divNueva.style.display = 'block'; reqInputs.forEach(id => document.getElementById(id).required = true);
            } else {
                divNueva.style.display = 'none'; reqInputs.forEach(id => document.getElementById(id).required = false);
            }
        }
    </script>
</body>
</html>