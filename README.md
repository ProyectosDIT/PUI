# 🏛️ HUB Central PUI - Gateway de Interconexión

Sistema de interconexión, enrutamiento y cifrado para la **Plataforma Única de Información (PUI)**. Este HUB actúa como un Gateway intermedio que permite a las instituciones conectar sus bases de datos (MySQL, PostgreSQL, MongoDB, SQL Server, Oracle), repositorios SFTP o cargas manuales CSV, cifrar la información en tiempo real (AES/JWS) y exponer los endpoints requeridos por el Gobierno Federal.

## 📋 Requisitos del Servidor

- **Sistema Operativo:** Linux (Ubuntu/Debian, CentOS, AlmaLinux, etc.)
- **Servidor Web:** Apache o Nginx
- **Base de Datos:** MySQL 8.0+ o MariaDB 10.5+
- **PHP:** Versión 7.4 o superior (Recomendado PHP 8.1+)
- **Extensiones PHP:** `pdo`, `pdo_mysql`, `openssl`, `curl`, `mbstring`. *(Opcionales según las conexiones requeridas: `pdo_pgsql`, `pdo_sqlsrv`, `pdo_oci`, `mongodb`, `ftp`)*
- **Composer:** Instalado globalmente.
- **Certificado SSL:** Obligatorio (HTTPS) para comunicación con SEGOB y OAuth.

---

## ⚙️ Guía de Instalación Paso a Paso

### 1. Preparación del Entorno
Clona el repositorio en tu servidor web (por ejemplo, en `/var/www/html/pui`):
```bash
git clone https://github.com/tu-usuario/hub-pui-central.git /var/www/html/pui
cd /var/www/html/pui
```

Instala las dependencias mediante Composer (Google API Client, PHP Dotenv):
```bash
composer install
```

Crea el directorio de subidas y asígnale permisos al usuario de tu servidor web (usualmente `www-data` o `apache`):
```bash
mkdir uploads
chown -R www-data:www-data uploads
chmod -R 755 uploads
```

### 2. Base de Datos
1. Ingresa a tu gestor de MySQL/MariaDB y crea la base de datos:
```sql
CREATE DATABASE pui_upaep_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
2. Importa el esquema inicial proporcionado en el repositorio:
```bash
mysql -u tu_usuario -p pui_upaep_hub < pui_upaep_hub.sql
```

### 3. Configuración del Sistema (Variables de Entorno)
El sistema utiliza un archivo `.env` en la raíz (junto a la carpeta `vendor` o en la raíz de tu proyecto, ajusta la ruta en `config/db.php` si es necesario). 

Crea un archivo `.env` con la siguiente estructura:
```ini
# Google OAuth 2.0
GOOGLE_CLIENT_ID="tu-client-id.apps.googleusercontent.com"
GOOGLE_CLIENT_SECRET="tu-client-secret"

# Llaves de Encriptación Locales (AES-256-CBC) para credenciales de BD
HUB_AES_KEY="coloca-aqui-una-llave-de-32-caracteres"
HUB_AES_IV="llave-de-16-chars"

# SendGrid API Key para correos
SENDGRID_API_KEY="SG.tu-api-key-de-sendgrid..."
```

> **Nota de Seguridad:** Puedes generar llaves seguras en la terminal linux:
> `HUB_AES_KEY`: `openssl rand -base64 24` (o ajusta a 32 bytes)
> `HUB_AES_IV`: `openssl rand -base64 12` (o ajusta a 16 bytes)

### 4. Ajustes en Archivos Clave
Antes de usar el sistema, debes modificar parámetros fijos de configuración en tu entorno:

1. **En `config/db.php`:**
   - Coloca tu usuario y contraseña de MySQL en la conexión PDO:
     `'mysql:host=127.0.0.1;port=3306;dbname=pui_upaep_hub...', 'TU_USUARIO', 'TU_PASSWORD'`
   - Cambia la URL de redirección de Google por la tuya:
     `$client->setRedirectUri('https://tu-dominio.com/pui/login.php');`

2. **En `config/mailer.php`:**
   - Cambia el correo remitente (`"email" => "no-reply@tudominio.com"`) por el correo validado en tu cuenta de SendGrid.

### 5. Script de Creación del Super Administrador
Dado que el inicio de sesión es exclusivamente por Google Workspace, primero debes iniciar sesión normalmente a través del portal web y llenar el formulario de "Registro". Una vez que el sistema registre tu correo, ingresa a tu base de datos y ejecuta este comando para convertirte en **Super Administrador**:

```sql
UPDATE usuarios 
SET rol = 'superadmin', activo = 1, puede_aprobar = 1 
WHERE email = 'tu_correo@tu_dominio.com';
```
*A partir de este momento, cuando inicies sesión, verás el "Centro de Control Global" en lugar de la vista de Universidad.*

---

## 🔑 Configuración de Servicios de Terceros

### A) Google OAuth 2.0 (Login Seguro)
1. Ingresa a [Google Cloud Console](https://console.cloud.google.com/).
2. Crea un nuevo proyecto.
3. Ve a **APIs & Services > Credentials**.
4. Haz clic en "Create Credentials" > "OAuth client ID".
5. Selecciona **Web application**.
6. En **Authorized redirect URIs**, agrega la URL exacta hacia tu script de login: `https://tu-dominio.com/pui/login.php`
7. Copia el `Client ID` y `Client Secret` en tu archivo `.env`.

### B) SendGrid (Correos Transaccionales)
1. Crea una cuenta en [Twilio SendGrid](https://sendgrid.com/).
2. Completa el proceso de "Sender Authentication" (Autenticación de dominio) para asegurar que los correos no lleguen a SPAM.
3. Ve a **Settings > API Keys** y crea una nueva API Key con permisos completos de *Mail Send*.
4. Cópiala en la variable `SENDGRID_API_KEY` de tu archivo `.env`.

---

## ⏱️ Configuración del Motor CRON (Búsqueda Autónoma)
El sistema incluye un script llamado `motor_cron.php` que se encarga de buscar coincidencias en las bases de datos de las instituciones conectadas de forma autónoma (Fases 1, 2 y 3). 

Debes configurarlo en el crontab del servidor para que se ejecute cada X minutos (por ejemplo, cada 10 minutos):

```bash
crontab -e
```
Agrega la siguiente línea (ajusta las rutas según corresponda):
```bash
*/10 * * * * php /var/www/html/pui/motor_cron.php >> /var/log/pui_cron.log 2>&1
```

---

## 🛡️ Consideraciones de Ciberseguridad
- **Aislamiento:** Asegúrate de que los puertos de las bases de datos de las Universidades (ej. 33
