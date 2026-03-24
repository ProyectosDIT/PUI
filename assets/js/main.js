/* /var/www/html/dit/tools/pui/assets/js/main.js */
document.addEventListener("DOMContentLoaded", function() {
    const tipoDb = document.getElementById('tipo_db');
    const reqSsh = document.getElementById('req_ssh');
    const sshBox = document.getElementById('ssh_box');
    const lblBd = document.getElementById('lbl_bd');
    const lblVista = document.getElementById('lbl_vista');

    function actualizarInterfaz() {
        if (!tipoDb) return;
        
        if (tipoDb.value === 'sftp') {
            lblBd.innerText = 'Ruta Raíz SFTP (Ej. /var/csv/)';
            lblVista.innerText = 'Prefijo Archivo (Ej. data_)';
        } else if (tipoDb.value === 'mongodb') {
            lblBd.innerText = 'Base de Datos Mongo';
            lblVista.innerText = 'Colección';
        } else {
            lblBd.innerText = 'Base de Datos (SID)';
            lblVista.innerText = 'Nombre de la Vista (Ej. vw_pui)';
        }

        if (reqSsh) {
            sshBox.style.display = reqSsh.checked ? 'flex' : 'none';
        }
    }

    if (tipoDb) {
        tipoDb.addEventListener('change', actualizarInterfaz);
        reqSsh.addEventListener('change', actualizarInterfaz);
        actualizarInterfaz();
    }
});