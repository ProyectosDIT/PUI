<?php
// /var/www/html/dit/tools/pui/login.php
require_once __DIR__ . '/config/db.php';

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        if (isset($token['error'])) { 
            header('Location: index.php?err=invalid_code'); 
            exit; 
        }

        $client->setAccessToken($token['access_token']);
        $google_oauth = new Google\Service\Oauth2($client);
        $user_info = $google_oauth->userinfo->get();
        
        $email = $user_info->email;
        $name = $user_info->name;
        $google_id = $user_info->id;

        $stmt = $pdo_hub->prepare("SELECT u.*, i.estatus as estatus_inst FROM usuarios u LEFT JOIN instituciones i ON u.institucion_id = i.id WHERE u.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['tmp_google_id'] = $google_id;
            $_SESSION['tmp_email'] = $email;
            $_SESSION['tmp_name'] = $name;
            header('Location: registro.php');
            exit;
        }

        if ($user['activo'] == 0) {
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Validación Pendiente - Gateway PUI</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    body { 
                        background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%); 
                        min-height: 100vh; 
                        display: flex; 
                        flex-direction: column; 
                        font-family: 'Segoe UI', system-ui, sans-serif; 
                    }
                    .glass-card {
                        background: rgba(255, 255, 255, 0.95);
                        backdrop-filter: blur(10px);
                        border: 1px solid rgba(255, 255, 255, 0.5);
                        border-radius: 1.5rem;
                        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
                    }
                    .icon-circle {
                        width: 90px;
                        height: 90px;
                        background: #fff3cd;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin: 0 auto 1.5rem auto;
                        box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
                        position: relative;
                    }
                    .icon-circle::before {
                        content: '';
                        position: absolute;
                        top: -10px; left: -10px; right: -10px; bottom: -10px;
                        border-radius: 50%;
                        border: 2px solid #ffc107;
                        animation: pulse 2s linear infinite;
                    }
                    @keyframes pulse {
                        0% { transform: scale(0.9); opacity: 1; }
                        100% { transform: scale(1.3); opacity: 0; }
                    }
                    .icon-circle i { font-size: 2.5rem; color: #d39e00; }
                    .footer-logos img { height: 40px; object-fit: contain; filter: grayscale(100%); opacity: 0.6; transition: 0.3s; }
                    .footer-logos img:hover { filter: grayscale(0%); opacity: 1; }
                    .sp-digital-logo { font-size: 1.1rem; text-decoration: none; display: inline-block; }
                    .sp-digital-logo .sp { font-weight: 900; color: #0d6efd; }
                    .sp-digital-logo .digital { font-weight: 300; color: #4b5563; }
                </style>
            </head>
            <body class="align-items-center justify-content-center">
                <div class="container d-flex flex-column justify-content-center flex-grow-1" style="max-width: 650px;">
                    
                    <div class="card glass-card p-5 text-center">
                        <div class="icon-circle">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                        <h2 class="fw-bold text-dark mb-3">Auditoría en Proceso</h2>
                        
                        <p class="text-secondary fs-5 mb-4">Hola <strong><?= htmlspecialchars($name) ?></strong>. Hemos localizado tu cuenta institucional, pero se encuentra bajo un bloqueo de seguridad temporal.</p>
                        
                        <div class="alert bg-light border border-warning text-start shadow-sm mb-4 p-4 rounded-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fa-solid fa-lock text-warning fs-4 me-3"></i>
                                <h6 class="fw-bold text-dark mb-0">Protocolo de Ciberseguridad Activo</h6>
                            </div>
                            <p class="small text-muted mb-0 ms-5">
                                Un <strong>Administrador (De UPAEP o de tu Universidad)</strong> está validando tu cargo e identidad institucional antes de otorgarte acceso a la red de interconexión.
                            </p>
                        </div>

                        <a href='index.php' class="btn btn-dark btn-lg fw-bold rounded-pill px-5 py-3 mt-2 shadow-sm">
                            <i class="fa-solid fa-arrow-left me-2"></i> Volver a la pantalla principal
                        </a>
                    </div>

                </div>

                <div class="w-100 text-center py-4 mt-auto">
                    <p class="small text-muted mb-3 fw-bold text-uppercase tracking-wider" style="letter-spacing: 1px;">Infraestructura soportada por:</p>
                    <div class="d-flex justify-content-center align-items-center gap-4 flex-wrap footer-logos mb-3">
                        <a href="https://upaep.mx" target="_blank"><img src="https://upaep.mx/images/upaep/Logo_UPAEP.svg" alt="UPAEP"></a>
                        <span class="text-muted opacity-50 fs-4">|</span>
                        <img src="https://shadow.spdigital.mx/images/logo/dit_color.png?v=1773553723" alt="DIT">
                    </div>
                    <p class="small text-muted mb-0">
                        Desarrollada por <a href="https://spdigital.mx/" target="_blank" class="sp-digital-logo"><span class="sp">SP.</span><span class="digital">Digital</span></a>
                    </p>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        $updateStmt = $pdo_hub->prepare("UPDATE usuarios SET google_id = ?, nombre = ?, ultimo_acceso = NOW() WHERE id = ?");
        $updateStmt->execute([$google_id, $name, $user['id']]);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['institucion_id'] = $user['institucion_id'];
        $_SESSION['email'] = $email;
        $_SESSION['puede_aprobar'] = $user['puede_aprobar'];
        
        generateCsrfToken();

        header('Location: app.php');
        exit;

    } catch (Exception $e) {
        die("Error de autenticación con Google: " . $e->getMessage());
    }
}

header('Location: index.php');
exit;
?>