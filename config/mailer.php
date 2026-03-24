<?php
// /var/www/html/dit/tools/pui/config/mailer.php

/**
 * Función centralizada para enviar correos transaccionales del PUI de forma NATIVA
 */
function enviarCorreoPUI($toEmail, $toName, $subject, $mensajeHtml) {
    global $pdo_hub; // Traemos la conexión a DB para registrar la auditoría
    
    // Tomar el API Key del entorno
    $apiKey = getenv('SENDGRID_API_KEY') ?: (isset($_ENV['SENDGRID_API_KEY']) ? $_ENV['SENDGRID_API_KEY'] : '');
    
    if (empty($apiKey)) {
        error_log("PUI Mailer Error: SendGrid API Key no configurada.");
        return false;
    }

    // 1. Generar código de Tracking único para saber si lo abren
    $tracking_code = bin2hex(random_bytes(16));
    
    // 2. Detectar protocolo para la URL del píxel y del botón
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
    $base_url = ($is_https ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . "/dit/tools/pui";
    $pixel_url = "$base_url/track.php?c=$tracking_code";

    $htmlTemplate = "
    <div style='font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05); background-color: #ffffff;'>
        
        <!-- ENCABEZADO CON LOGOS -->
        <div style='background-color: #ffffff; padding: 30px 25px 20px; text-align: center; border-bottom: 3px solid #f1f5f9;'>
            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom: 15px;'>
                <tr>
                    <td align='center' style='vertical-align: middle;'>
                        <img src='https://upaep.mx/images/upaep/Logo_UPAEP.svg' width='140' alt='UPAEP' style='display: inline-block; vertical-align: middle; border: none; outline: none;'>
                        <span style='display: inline-block; width: 2px; height: 40px; background-color: #cbd5e1; vertical-align: middle; margin: 0 20px;'></span>
                        <img src='https://shadow.spdigital.mx/images/logo/dit_color.png?v=1773553723' width='110' alt='DIT' style='display: inline-block; vertical-align: middle; border: none; outline: none;'>
                    </td>
                </tr>
            </table>
            <h2 style='color: #0f172a; margin: 15px 0 0 0; font-weight: 800; font-size: 22px; letter-spacing: -0.5px;'>Gateway Institucional PUI</h2>
            <p style='color: #64748b; margin: 5px 0 0 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;'>Plataforma Segura de Interconexión</p>
        </div>

        <!-- CUERPO DEL CORREO -->
        <div style='padding: 40px 35px; background-color: #ffffff; color: #334155; line-height: 1.6; font-size: 15px;'>
            $mensajeHtml

            <!-- BOTÓN CALL TO ACTION UNIVERSAL (Enlace a la plataforma) -->
            <div style='text-align: center; margin-top: 35px; border-top: 1px solid #e2e8f0; padding-top: 25px;'>
                <a href='$base_url' style='background-color: #0f172a; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>Ingresar a la Plataforma</a>
            </div>
        </div>

        <!-- PIE DE PÁGINA CORPORATIVO -->
        <div style='background-color: #f8fafc; padding: 25px; text-align: center; border-top: 1px solid #e2e8f0;'>
            <p style='color: #64748b; font-size: 12px; margin: 0 0 15px 0;'>
                <img src='https://cdn-icons-png.flaticon.com/512/3064/3064196.png' width='16' style='vertical-align: middle; margin-right: 5px; opacity: 0.5;'>
                Este es un aviso automático de seguridad. Por favor, no respondas a este correo.
            </p>
            <p style='color: #94a3b8; font-size: 11px; margin: 0; line-height: 1.5;'>
                Infraestructura soportada por <strong>UPAEP</strong><br>
                Desarrollada por <a href='https://spdigital.mx/' target='_blank' style='color: #0d6efd; text-decoration: none; font-weight: 800;'>SP.Digital</a>
            </p>
        </div>

        <!-- PÍXEL DE RASTREO -->
        <img src='$pixel_url' width='1' height='1' style='display:none;' alt='' />
    </div>";

    // Estructura del cuerpo del correo (JSON requerido por el API v3 de SendGrid)
    $payload = [
        "personalizations" => [
            [
                "to" => [
                    [
                        "email" => $toEmail,
                        "name" => $toName
                    ]
                ],
                "subject" => $subject
            ]
        ],
        "from" => [
            "email" => "no-reply@spdigital.mx", // REEMPLAZA CON TU DOMINIO VERIFICADO EN SENDGRID
            "name" => "Gateway PUI - UPAEP"
        ],
        "content" => [
            [
                "type" => "text/html",
                "value" => $htmlTemplate
            ]
        ]
    ];

    // Configuración de cURL para conectar al API de SendGrid directamente
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.sendgrid.com/v3/mail/send");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json"
    ]);

    // Ejecutar petición
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Validar si SendGrid aceptó el correo (Código 202 es éxito)
    if ($httpCode >= 200 && $httpCode < 300) {
        
        // --- GUARDAR LOG DE AUDITORÍA DE CORREO ---
        if(isset($pdo_hub)) {
            try {
                $stmt = $pdo_hub->prepare("INSERT INTO logs_correos (to_email, subject, tracking_code) VALUES (?, ?, ?)");
                $stmt->execute([$toEmail, $subject, $tracking_code]);
            } catch (Exception $e) {
                error_log("Error al registrar auditoria de correo: " . $e->getMessage());
            }
        }
        
        return true;
    } else {
        error_log("Fallo al enviar correo mediante SendGrid API. HTTP Code: $httpCode. Response: $response");
        return false;
    }
}
?>