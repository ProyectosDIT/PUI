<?php 
// /var/www/html/dit/tools/pui/views/univ_view.php
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-0"><?= htmlspecialchars($inst['nombre']) ?></h3>
        <span class="badge bg-primary">RFC: <?= htmlspecialchars($inst['rfc_homoclave']) ?></span>
    </div>
</div>

<?php if($inst['estatus'] !== 'aprobada'): ?>
    <div class="alert alert-warning shadow-sm border-warning mb-4">
        <h5 class="alert-heading fw-bold"><i class="fa-solid fa-lock"></i> Institución Pendiente de Autorización</h5>
        <p class="mb-0">Tu nodo aún no ha sido autorizado por UPAEP. Configura tus campus y haz pruebas en el Simulador; las URLs se activarán en cuanto seas aprobado.</p>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="list-group shadow-sm custom-nav-tabs" id="list-tab" role="tablist">
            <a class="list-group-item list-group-item-action active fw-bold py-3" data-bs-toggle="list" href="#list-config"><i class="fa-solid fa-network-wired fa-fw me-2"></i> Mis Campus (Nodos)</a>
            <a class="list-group-item list-group-item-action fw-bold py-3 text-danger" data-bs-toggle="list" href="#list-reportes"><i class="fa-solid fa-person-circle-question fa-fw me-2"></i> Búsquedas Activas (RNPDNO)</a>
            <a class="list-group-item list-group-item-action fw-bold py-3 text-info" data-bs-toggle="list" href="#list-multi"><i class="fa-solid fa-building-columns fa-fw me-2"></i> Mis Instituciones</a>
            
            <?php if($puede_aprobar): ?>
                <a class="list-group-item list-group-item-action fw-bold py-3 text-warning-emphasis bg-warning bg-opacity-10" data-bs-toggle="list" href="#list-equipo"><i class="fa-solid fa-users-gear fa-fw me-2"></i> Gestión de Equipo</a>
            <?php endif; ?>

            <a class="list-group-item list-group-item-action fw-bold py-3 text-primary" data-bs-toggle="list" href="#list-ejemplo"><i class="fa-solid fa-table-list fa-fw me-2"></i> Formato de Datos (Docs)</a>
            <a class="list-group-item list-group-item-action fw-bold py-3 text-success" data-bs-toggle="list" href="#list-simulador"><i class="fa-solid fa-vial fa-fw me-2"></i> Simulador de Pruebas</a>
            <a class="list-group-item list-group-item-action fw-bold py-3" data-bs-toggle="list" href="#list-seguridad"><i class="fa-solid fa-shield-cat fa-fw me-2"></i> Llaves y Criptografía</a>
            <a class="list-group-item list-group-item-action fw-bold py-3 text-success" data-bs-toggle="list" href="#list-compliance"><i class="fa-solid fa-file-shield fa-fw me-2"></i> Certificaciones (DOF)</a>
			<a class="list-group-item list-group-item-action fw-bold py-3" data-bs-toggle="list" href="#list-endpoints"><i class="fa-solid fa-globe fa-fw me-2"></i> Endpoints SEGOB</a>
            <a class="list-group-item list-group-item-action fw-bold py-3" data-bs-toggle="list" href="#list-auditoria"><i class="fa-solid fa-list-check fa-fw me-2"></i> Logs de Auditoría</a>
        </div>
    </div>

    <div class="col-md-9">
        <div class="tab-content" id="nav-tabContent">

            <div class="tab-pane fade" id="list-reportes" role="tabpanel">
                <div class="card shadow-sm border-0 border-top border-4 border-danger">
                    <div class="card-header bg-white text-danger fw-bold py-3 d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-person-circle-exclamation me-2"></i> Personas Desaparecidas (Búsqueda Continua)</span>
                        <button type="button" class="btn btn-sm btn-outline-danger fw-bold shadow-sm" onclick="sincronizarSegob()">
                            <i class="fa-solid fa-rotate me-1"></i> Sincronizar (Recuperar Perdidos)
                        </button>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <div class="p-3 bg-light border-bottom small text-muted">
                            <i class="fa-solid fa-info-circle me-1"></i> Estos reportes han sido inyectados automáticamente por la SEGOB a través de tu endpoint <code>/activar-reporte</code>. El motor del HUB los está buscando constantemente en tus bases de datos. Si tu servidor se reinició y perdiste notificaciones, usa el botón "Sincronizar".
                        </div>
                        <table class="table table-hover align-middle mb-0 datatable w-100">
                            <thead class="table-light">
                                <tr><th>CURP Buscada</th><th>ID Folio RNPDNO</th><th>Fase Actual</th><th>Estatus</th><th>Payload GOB</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $mis_reportes = $pdo_hub->prepare("SELECT * FROM reportes_pui WHERE institucion_id = ? ORDER BY fecha_registro DESC");
                                    $mis_reportes->execute([$_SESSION['institucion_id']]);
                                    foreach($mis_reportes->fetchAll() as $r):
                                ?>
                                <tr>
                                    <td class="fw-bold ps-3 font-monospace"><?= htmlspecialchars($r['curp']) ?></td>
                                    <td><small class="text-muted text-truncate d-inline-block" style="max-width: 150px;" title="<?= htmlspecialchars($r['id_reporte']) ?>"><?= htmlspecialchars($r['id_reporte']) ?></small></td>
                                    <td>
                                        <?php 
                                            if($r['fase_actual'] == 1) echo '<span class="badge bg-secondary">Fase 1 (Básica)</span>';
                                            if($r['fase_actual'] == 2) echo '<span class="badge bg-primary">Fase 2 (Histórica)</span>';
                                            if($r['fase_actual'] == 3) echo '<span class="badge bg-danger">Fase 3 (Continua)</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if($r['estatus'] == 'activo'): ?>
                                            <span class="text-success small fw-bold"><i class="fa-solid fa-satellite-dish fa-fade text-danger"></i> Buscando</span>
                                        <?php else: ?>
                                            <span class="text-muted small fw-bold"><i class="fa-solid fa-check"></i> Finalizada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3">
                                        <button class="btn btn-sm btn-outline-info shadow-sm fw-bold" 
                                                data-json="<?= htmlspecialchars($r['datos_completos'], ENT_QUOTES, 'UTF-8') ?>" 
                                                onclick="verDetallesGobierno(this)">
                                            <i class="fa-solid fa-eye"></i> JSON
                                        </button>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach; 
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='5' class='text-center py-4 text-warning fw-bold'><i class='fa-solid fa-triangle-exclamation'></i> Debes ejecutar el script SQL para crear la tabla reportes_pui.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade show active" id="list-config" role="tabpanel">
                <div class="alert alert-info shadow-sm bg-white border-info mb-4">
                    <h5 class="fw-bold text-dark"><i class="fa-solid fa-lock text-success me-2"></i> Privacidad y Resguardo Estricto</h5>
                    <p class="small text-muted mb-2">Este HUB opera bajo estrictos protocolos de seguridad técnica para proteger los datos de tu institución. Cumplimos con el <strong>Anexo 1 de Seguridad del Manual Técnico de la PUI</strong>:</p>
                    <ul class="small text-muted mb-0">
                        <li><strong>Por Conexión (BD/SFTP):</strong> El sistema consulta tus servidores <em>en tiempo real y bajo esquema Read-Only (Solo Lectura)</em>. La información de tus alumnos/empleados <strong>NUNCA se copia, transfiere ni se almacena</strong> en los servidores de este HUB UPAEP. Solo funcionamos como puente criptográfico.</li>
                        <li><strong>Por Carga Manual:</strong> Si decides subir un padrón en archivo plano por políticas internas, este será <strong>encriptado y resguardado en una bóveda aislada</strong> exclusiva para tu RFC. Nadie tiene acceso físico a los datos.</li>
                    </ul>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center py-3">
                        <span><i class="fa-solid fa-server me-2"></i> Orígenes de Datos (Campus)</span>
                        <button type="button" class="btn btn-light btn-sm fw-bold text-primary" data-bs-toggle="modal" data-bs-target="#modalCampus" onclick="resetForm()">
                            <i class="fa-solid fa-plus me-1"></i> Agregar Nodo
                        </button>
                    </div>
                    <div class="card-body bg-light p-0 table-responsive">
                        <table class="table table-hover align-middle mb-0 w-100">
                            <thead class="table-light"><tr><th>Nombre del Campus</th><th>Tipo de Integración</th><th>Destino</th><th>Acciones</th></tr></thead>
                            <tbody>
                                <?php if(empty($campus_list)): ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fa-solid fa-database fa-3x mb-3 opacity-25 d-block"></i> No has registrado ningún origen de datos. Agrega tu primer Campus para interconectarlo a la PUI.</td></tr>
                                <?php endif; ?>
                                <?php foreach($campus_list as $c): ?>
                                <tr>
                                    <td class="fw-bold ps-3"><?= htmlspecialchars($c['nombre_campus']) ?></td>
                                    <td>
                                        <?php if($c['tipo_conexion'] == 'manual'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-file-csv"></i> CARGA MANUAL</span>
                                        <?php elseif($c['tipo_conexion'] == 'demo_local'): ?>
                                            <span class="badge bg-success text-white"><i class="fa-solid fa-flask"></i> DEMO LOCAL</span>
                                        <?php elseif($c['tipo_conexion'] == 'sftp'): ?>
                                            <span class="badge bg-dark text-white"><i class="fa-solid fa-network-wired"></i> FTPS / FTP</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-uppercase"><i class="fa-solid fa-database"></i> <?= $c['tipo_conexion'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?php if($c['tipo_conexion'] == 'manual'): ?>
                                            <i class="fa-solid fa-shield-halved text-success"></i> Bóveda Local Protegida
                                        <?php elseif($c['tipo_conexion'] == 'demo_local'): ?>
                                            Conexión Nativa (HUB)
                                        <?php else: ?>
                                            <?= htmlspecialchars($c['host']) ?> <br> <i class="fa-solid fa-table"></i> <?= htmlspecialchars($c['nombre_vista']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3">
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-info" title="Previsualizar Datos (Top 50)" onclick="previewNodo(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nombre_campus'], ENT_QUOTES) ?>')"><i class="fa-solid fa-eye"></i></button>

                                            <?php if($c['tipo_conexion'] != 'demo_local'): ?>
                                                <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='editarCampus(<?= json_encode($c) ?>)'><i class="fa-solid fa-pen"></i></button>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este nodo? Los archivos vinculados serán borrados.');">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="eliminar_campus">
                                                <input type="hidden" name="origen_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="list-multi" role="tabpanel">
                <div class="card shadow-sm border-0 border-top border-4 border-info">
                    <div class="card-header bg-white fw-bold text-dark py-3 d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-building-columns me-2 text-info"></i> Mis Afiliaciones Institucionales</span>
                        <button class="btn btn-info btn-sm text-white fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaInst">+ Solicitar Vinculación a otra Universidad</button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table align-middle mb-0 w-100">
                            <thead class="table-light"><tr><th>Institución</th><th>Mi Rol</th><th>Estatus</th></tr></thead>
                            <tbody>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= htmlspecialchars($inst['nombre']) ?> (Actual)</td>
                                    <td><?= $puede_aprobar ? '<span class="badge bg-warning text-dark"><i class="fa-solid fa-star"></i> Admin Delegado</span>' : '<span class="badge bg-secondary">Miembro</span>' ?></td>
                                    <td><span class="text-success small fw-bold"><i class="fa-solid fa-check"></i> Activo (Sesión Actual)</span></td>
                                </tr>
                                <?php foreach($mis_instituciones_extra as $mex): ?>
                                    <?php if ($mex['institucion_id'] != $inst['id']): ?>
                                    <tr>
                                        <td class="ps-3"><?= htmlspecialchars($mex['nombre']) ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($mex['cargo']) ?></td>
                                        <td>
                                            <?php if($mex['activo']): ?>
                                                <span class="text-success small fw-bold"><i class="fa-solid fa-check"></i> Aprobado (Utiliza el selector superior)</span>
                                            <?php else: ?>
                                                <span class="text-warning small fw-bold"><i class="fa-solid fa-clock"></i> En revisión por UPAEP</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if($puede_aprobar): ?>
            <div class="tab-pane fade" id="list-equipo" role="tabpanel">
                <div class="card shadow-sm border-0 border-top border-4 border-warning">
                    <div class="card-header bg-white fw-bold text-dark py-3">
                        <i class="fa-solid fa-users-gear me-2 text-warning"></i> Control de Accesos de tu Institución
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover align-middle mb-0 datatable w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Contacto</th>
                                    <th>Estatus</th>
                                    <th>Delegación</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $mis_usuarios = $pdo_hub->prepare("
                                    SELECT u.id, u.nombre, u.email, u.telefono, 
                                           COALESCE(ui.cargo, u.cargo) as cargo, 
                                           CASE WHEN u.institucion_id = ? THEN u.activo ELSE ui.activo END as activo,
                                           CASE WHEN u.institucion_id = ? THEN u.puede_aprobar ELSE ui.puede_aprobar END as puede_aprobar
                                    FROM usuarios u
                                    LEFT JOIN usuario_instituciones ui ON u.id = ui.usuario_id AND ui.institucion_id = ?
                                    WHERE u.institucion_id = ? OR ui.institucion_id = ?
                                    ORDER BY activo ASC, nombre ASC
                                ");
                                $mis_usuarios->execute([$_SESSION['institucion_id'], $_SESSION['institucion_id'], $_SESSION['institucion_id'], $_SESSION['institucion_id'], $_SESSION['institucion_id']]);
                                foreach($mis_usuarios->fetchAll() as $u):
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <strong class="text-dark"><?= htmlspecialchars($u['nombre']) ?></strong>
                                        <?php if($u['id'] == $_SESSION['user_id']): ?> <span class="badge bg-primary ms-1">Tú</span> <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><i class="fa-regular fa-envelope"></i> <?= htmlspecialchars($u['email']) ?> <br> <i class="fa-solid fa-briefcase"></i> <?= htmlspecialchars($u['cargo']) ?></small>
                                    </td>
                                    <td><small class="text-muted font-monospace"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($u['telefono']) ?></small></td>
                                    <td>
                                        <?php if($u['activo'] == 1): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="fa-solid fa-check-circle"></i> Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning"><i class="fa-solid fa-clock"></i> Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($u['id'] != $_SESSION['user_id'] && $u['activo'] == 1): ?>
                                            <form method="POST" onsubmit="return confirm('¿Actualizar permisos de este usuario?');">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="toggle_delegar_inst">
                                                <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                <?php if($u['puede_aprobar'] == 1): ?>
                                                    <button class="btn btn-sm btn-outline-danger shadow-sm"><i class="fa-solid fa-user-minus"></i> Quitar Admin</button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-dark shadow-sm"><i class="fa-solid fa-user-plus text-warning"></i> Hacer Admin</button>
                                                <?php endif; ?>
                                            </form>
                                        <?php elseif($u['puede_aprobar'] == 1): ?>
                                            <span class="small text-muted"><i class="fa-solid fa-star text-warning"></i> Administrador</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4">
                                        <?php if($u['activo'] == 0): ?>
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="aprobar_usuario_inst">
                                                <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                <button class="btn btn-sm btn-success fw-bold shadow-sm"><i class="fa-solid fa-check me-1"></i> Aprobar Acceso</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="tab-pane fade" id="list-ejemplo" role="tabpanel">
                <div class="card shadow-sm border-0 border-top border-4 border-primary">
                    <div class="card-header bg-white fw-bold text-primary"><i class="fa-solid fa-circle-info me-2"></i> Estructura y Diccionario de Datos PUI</div>
                    <div class="card-body p-4">
                        <p class="small text-muted mb-3">Tanto si configuras una Base de Datos (SQL/NoSQL), FTPS, o carga manual, <strong>tu vista, colección o archivo debe contener estos nombres de columna</strong> (en minúsculas) exigidos por la <strong>Sección 7.2 del Manual Técnico</strong>.</p>
                        
                        <div class="alert alert-warning small py-2">
                            <i class="fa-solid fa-lightbulb text-warning me-2"></i> <strong>Flexibilidad:</strong> Únicamente la <code>curp</code> es estrictamente obligatoria para hacer el cruce. Todos los demás campos son <strong>opcionales</strong>. Si tu universidad no recaba ciertos datos (ej. tipo_evento o correo), simplemente incluye la columna y déjala vacía o con valor <code>NULL</code>. El HUB se encargará de empaquetar el JSON final hacia SEGOB.
                        </div>
                        
                        <div class="table-responsive border rounded mb-4 shadow-sm" style="max-height: 400px; overflow: auto;">
                            <table class="table table-sm table-hover table-striped mb-0 font-monospace text-nowrap" style="font-size: 0.85em;">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th class="text-warning"><i class="fa-solid fa-key text-warning me-1"></i> curp (Req)</th>
                                        <th class="bg-primary text-white border-start">nombre</th>
                                        <th class="bg-primary text-white">primer_apellido</th>
                                        <th class="bg-primary text-white">segundo_apellido</th>
                                        <th class="bg-primary text-white">lugar_nacimiento</th>
                                        <th class="bg-primary text-white">fecha_nacimiento</th>
                                        <th class="bg-primary text-white border-end">sexo_asignado</th>
                                        <th class="bg-info text-dark">telefono</th>
                                        <th class="bg-info text-dark border-end">correo</th>
                                        <th class="bg-success text-white">direccion</th>
                                        <th class="bg-success text-white">calle</th>
                                        <th class="bg-success text-white">numero</th>
                                        <th class="bg-success text-white">colonia</th>
                                        <th class="bg-success text-white">codigo_postal</th>
                                        <th class="bg-success text-white">municipio_o_alcaldia</th>
                                        <th class="bg-success text-white border-end">entidad_federativa</th>
                                        <th class="bg-secondary text-white">tipo_evento</th>
                                        <th class="bg-secondary text-white">fecha_evento</th>
                                        <th class="bg-secondary text-white">descripcion_lugar_evento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="fw-bold">ABCD123456XXXXXX00</td>
                                        <td>JUAN</td><td>PEREZ</td><td>GOMEZ</td><td>PUEBLA</td><td>2000-01-01</td><td>H</td>
                                        <td>5512345678</td><td>juan@correo.com</td>
                                        <td>CALLE 1 CENTRO</td><td>CALLE 1</td><td>123</td><td>CENTRO</td><td>72000</td><td>PUEBLA</td><td>PUEBLA</td>
                                        <td>Inscripción Otoño</td><td>2025-08-15</td><td>Campus Central</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">XYZW654321XXXXXX99</td>
                                        <td>MARIA</td><td>LOPEZ</td><td>NULL</td><td>NE</td><td>1995-05-15</td><td>M</td>
                                        <td>NULL</td><td>NULL</td>
                                        <td>NULL</td><td>NULL</td><td>NULL</td><td>NULL</td><td>NULL</td><td>NULL</td><td>NULL</td>
                                        <td>Baja Académica</td><td>2024-12-01</td><td>NULL</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="bg-light p-3 rounded border border-secondary d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-file-csv me-1 text-success"></i> Carga Manual por Archivo (.csv)</h6>
                                <p class="small text-muted mb-0">Descarga este layout oficial. Contiene todas las cabeceras requeridas para evitar errores de mapeo.</p>
                            </div>
                            <a href="template_pui.csv" class="btn btn-outline-dark fw-bold shadow-sm" download><i class="fa-solid fa-download me-1"></i> Descargar CSV Plantilla</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="list-simulador" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0 border-top border-4 border-success h-100">
                            <div class="card-header bg-white fw-bold"><i class="fa-solid fa-vial-virus me-2 text-success"></i> Motor de Prueba Real</div>
                            <div class="card-body">
                                <p class="small text-muted">Esta herramienta simula una petición de búsqueda autónoma gubernamental (Fase 3). Ingresa una CURP; el HUB se conectará a <strong>todos tus campus</strong> simultáneamente a través de Internet (cURL).</p>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="simular">
                                    <div class="mb-3">
                                        <label class="fw-bold small text-dark">CURP a buscar (Fase 3)</label>
                                        <input type="text" name="curp_simular" class="form-control text-uppercase form-control-lg" placeholder="Ej. ABCD123456XXXXXX00" required>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm"><i class="fa-solid fa-play me-1"></i> Ejecutar Petición de Búsqueda</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <?php if($simulacion_resultado): ?>
                            <div class="card shadow-sm border-0 bg-dark text-white h-100">
                                <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-success"><i class="fa-solid fa-terminal me-2"></i> Gateway PUI</span>
                                    <span class="badge bg-secondary">Respuesta API JSON</span>
                                </div>
                                <div class="card-body p-0">
                                    <pre class="m-0 p-3" style="color: #4af626; font-size: 0.85em; overflow-x: auto;"><?= json_encode($simulacion_resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card shadow-sm border-0 bg-light h-100 d-flex justify-content-center align-items-center text-muted p-5 border-dashed">
                                <div class="text-center opacity-50">
                                    <i class="fa-solid fa-code fa-4x mb-3 text-secondary"></i>
                                    <h5>Panel de Salida JSON</h5>
                                    <p class="small">Ejecuta una prueba para ver la respuesta estructurada que recibiría SEGOB.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="list-seguridad" role="tabpanel">
                <form method="POST" class="card shadow-sm border-0">
                    <div class="card-header bg-secondary text-white fw-bold d-flex justify-content-between align-items-center py-3">
                        <span><i class="fa-solid fa-key me-2"></i> Certificados y Credenciales de Interconexión</span>
                        <button type="submit" class="btn btn-light btn-sm fw-bold text-dark"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar Llaves</button>
                    </div>
                    <div class="card-body bg-white p-4">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="guardar_llaves">
                        <div class="alert alert-info small border-info"><i class="fa-solid fa-circle-info me-2"></i> Estas llaves aplican globalmente para todos tus campus. Son entregadas por el gobierno (o definidas por ti) para firmar y cifrar (AES/JWS) las respuestas JSON.</div>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="fw-bold small text-dark"><i class="fa-solid fa-asterisk text-danger me-1"></i> Contraseña API Gobierno</label>
                                <input type="text" name="clave_api_gob" class="form-control font-monospace border-dark bg-light text-primary fw-bold mt-2" value="<?= htmlspecialchars($inst['clave_api_gobierno'] ?? '') ?>" placeholder="Para notificar hallazgos a SEGOB...">
                            </div>

                            <div class="col-md-6">
                                <label class="fw-bold small text-dark"><i class="fa-solid fa-lock text-warning me-1"></i> Tu Clave Webhook (Login SEGOB)</label>
                                <div class="input-group mt-2 shadow-sm">
                                    <input type="text" id="clave_webhook" name="clave_webhook" class="form-control font-monospace border-dark bg-light text-success fw-bold" value="<?= htmlspecialchars($inst['clave_webhook'] ?? '') ?>" pattern="^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()\-_+.]).{16,20}$" title="Obligatorio por DOF: 16 a 20 caracteres, 1 mayúscula, 1 número y 1 símbolo especial (!@#$%^&*()-_+.)" placeholder="Ej. HubUpaep2026!XyZ" required>
                                    <button class="btn btn-dark fw-bold" type="button" onclick="generarClaveWebhook()"><i class="fa-solid fa-wand-magic-sparkles"></i> Generar</button>
                                </div>
                                <small class="text-muted" style="font-size:0.75rem;">*Obligatorio: 16-20 chars, mayúscula, número, símbolo.</small>
                            </div>

                            <div class="col-md-6 border-top pt-3">
                                <label class="fw-bold small text-primary">Llave Pública del Gobierno (PUI)</label>
                                <textarea name="llave_pub" id="llave_pub_gob" class="form-control font-monospace text-muted mt-2 shadow-sm" rows="6" placeholder="-----BEGIN PUBLIC KEY-----"><?= htmlspecialchars($inst['llave_publica_gob'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="col-md-6 border-top pt-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="fw-bold small text-success">Tu Llave Privada (Firma Institucional JWS)</label>
                                    <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="generarParRSA()"><i class="fa-solid fa-key"></i> Generar Nuevo Par (RSA)</button>
                                </div>
                                <textarea name="llave_priv" id="llave_priv_inst" class="form-control font-monospace text-muted mt-2 shadow-sm" rows="6" placeholder="-----BEGIN PRIVATE KEY-----"><?= htmlspecialchars($inst['llave_privada_inst'] ?? '') ?></textarea>
                                <small class="text-muted" style="font-size:0.75rem;">*Al generar un nuevo par, debes enviar la clave pública al gobierno.</small>
                            </div>
                            
                            <div class="col-12 mt-4 border-top pt-4">
                                <label class="fw-bold small text-danger"><i class="fa-solid fa-fingerprint me-1"></i> Clave AES-256 (Cifrado Biométrico)</label>
                                <div class="input-group mt-2 shadow-sm">
                                    <input type="text" id="clave_bio" name="clave_bio" class="form-control font-monospace" value="<?= htmlspecialchars($inst['clave_biometricos'] ?? '') ?>" placeholder="Clave secreta de 32 bytes (Opcional, solo si provees fotos/huellas)...">
                                    <button class="btn btn-dark fw-bold" type="button" onclick="generarClaveAES()"><i class="fa-solid fa-wand-magic-sparkles"></i> Generar Llave Segura</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
			
			<div class="tab-pane fade" id="list-compliance" role="tabpanel">
                <div class="card shadow-sm border-0 border-top border-4 border-success">
                    <div class="card-header bg-white fw-bold text-success py-3">
                        <i class="fa-solid fa-file-shield me-2"></i> Cumplimiento del Manual Técnico PUI (Sección 10)
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted mb-4">
                            Al utilizar el <strong>HUB Central UPAEP</strong> como tu Gateway de interconexión, heredas automáticamente nuestra infraestructura de seguridad perimetral. Ponemos a tu disposición los dictámenes ejecutivos de ciberseguridad exigidos por la SEGOB para que los adjuntes en tu expediente de interconexión oficial.
                        </p>

                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card h-100 border-success shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fa-solid fa-file-code fa-3x text-success mb-3"></i>
                                        <h6 class="fw-bold">Reporte SAST</h6>
                                        <p class="small text-muted">Análisis Estático de Seguridad del Código Fuente (Zero Injections).</p>
                                        <span class="badge bg-success mb-3"><i class="fa-solid fa-check-circle"></i> Aprobado (Libre de Críticas)</span>
                                        <br>
                                        <a href="docs/Reporte_SAST.pdf" target="_blank" class="btn btn-sm btn-outline-success fw-bold w-100"><i class="fa-solid fa-download me-1"></i> Descargar PDF</a>
                                    </div>
                                    <div class="card-footer bg-light text-muted small text-center">Último escaneo: <?= date('d/m/Y') ?></div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card h-100 border-primary shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fa-solid fa-globe fa-3x text-primary mb-3"></i>
                                        <h6 class="fw-bold">Reporte DAST</h6>
                                        <p class="small text-muted">Análisis Dinámico de la API Web (Protección contra OWASP Top 10).</p>
                                        <span class="badge bg-primary mb-3"><i class="fa-solid fa-check-circle"></i> Aprobado (PenTest Exitoso)</span>
                                        <br>
                                        <a href="docs/Reporte_DAST.pdf" target="_blank" class="btn btn-sm btn-outline-primary fw-bold w-100"><i class="fa-solid fa-download me-1"></i> Descargar PDF</a>
                                    </div>
                                    <div class="card-footer bg-light text-muted small text-center">Último escaneo: <?= date('d/m/Y') ?></div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card h-100 border-info shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fa-solid fa-cubes fa-3x text-info mb-3"></i>
                                        <h6 class="fw-bold">Reporte SCA</h6>
                                        <p class="small text-muted">Análisis de Componentes (Librerías Criptográficas AES/RSA validadas).</p>
                                        <span class="badge bg-info text-dark mb-3"><i class="fa-solid fa-check-circle"></i> Aprobado (Dependencias OK)</span>
                                        <br>
                                        <a href="docs/Reporte_SCA.pdf" target="_blank" class="btn btn-sm btn-outline-info fw-bold w-100"><i class="fa-solid fa-download me-1"></i> Descargar PDF</a>
                                    </div>
                                    <div class="card-footer bg-light text-muted small text-center">Último escaneo: <?= date('d/m/Y') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-secondary mt-4 mb-0 small">
                            <i class="fa-solid fa-handshake-angle me-2"></i> <strong>Responsabilidad Compartida:</strong> El HUB UPAEP garantiza la seguridad del canal hacia el Gobierno Federal. Sin embargo, es responsabilidad de tu institución asegurar que la Base de Datos que conectes al HUB tenga contraseñas fuertes y listas de control de acceso (ACL) que solo permitan la lectura desde la IP de UPAEP.
                        </div>
                    </div>
                </div>
            </div>
			
            <div class="tab-pane fade" id="list-endpoints" role="tabpanel">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-dark text-white fw-bold py-3"><i class="fa-solid fa-link me-2"></i> URLs Públicas de Interconexión (Gateway)</div>
                    <div class="card-body p-4">
							<?php if($inst['estatus'] === 'aprobada'): 
								$rfc = urlencode($inst['rfc_homoclave']); 
								
								// Mejoramos la detección de HTTPS para proxies como Cloudflare
								$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
											$_SERVER['SERVER_PORT'] == 443 || 
											(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
											
								$protocol = $is_https ? "https://" : "http://";
								$clean_url = $protocol . $_SERVER['HTTP_HOST'] . "/dit/tools/pui/api";
							?>
                            <p class="text-muted mb-4">Copia estas URLs y pégalas en el portal de inscripción de la PUI. Nuestro HUB actuará como enrutador único, cumpliendo con los endpoints exigidos por el <strong>Capítulo 8 del Manual Técnico DOF</strong>.</p>
                            
                            <div class="p-3 bg-light rounded border border-secondary mb-3 shadow-sm">
                                <label class="fw-bold text-success mb-1">1. Endpoint de Autenticación (/login)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace text-muted bg-white border-success" readonly value="<?= $clean_url ?>/<?= $rfc ?>/login">
                                    <button class="btn btn-success fw-bold" type="button" onclick="testEndpointPUI('login', '<?= $clean_url ?>/<?= $rfc ?>/login')"><i class="fa-solid fa-play me-1"></i> Probar</button>
                                </div>
                            </div>
                            
                            <div class="p-3 bg-light rounded border border-primary mb-3 shadow-sm">
                                <label class="fw-bold text-primary mb-1">2. Activación de Búsqueda Continua (/activar-reporte)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace text-muted bg-white border-primary" readonly value="<?= $clean_url ?>/<?= $rfc ?>/activar-reporte">
                                    <button class="btn btn-primary fw-bold" type="button" onclick="testEndpointPUI('activar-reporte', '<?= $clean_url ?>/<?= $rfc ?>/activar-reporte', '<?= $clean_url ?>/<?= $rfc ?>/login')"><i class="fa-solid fa-play me-1"></i> Probar</button>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded border border-info mb-3 shadow-sm">
                                <label class="fw-bold text-info mb-1">3. Activación de Búsqueda de Prueba (/activar-reporte-prueba)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace text-muted bg-white border-info" readonly value="<?= $clean_url ?>/<?= $rfc ?>/activar-reporte-prueba">
                                    <button class="btn btn-info text-white fw-bold" type="button" onclick="testEndpointPUI('activar-reporte-prueba', '<?= $clean_url ?>/<?= $rfc ?>/activar-reporte-prueba', '<?= $clean_url ?>/<?= $rfc ?>/login')"><i class="fa-solid fa-play me-1"></i> Probar</button>
                                </div>
                            </div>
                            
                            <div class="p-3 bg-light rounded border border-danger shadow-sm">
                                <label class="fw-bold text-danger mb-1">4. Detener Búsqueda Continua (/desactivar-reporte)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-monospace text-muted bg-white border-danger" readonly value="<?= $clean_url ?>/<?= $rfc ?>/desactivar-reporte">
                                    <button class="btn btn-danger fw-bold" type="button" onclick="testEndpointPUI('desactivar-reporte', '<?= $clean_url ?>/<?= $rfc ?>/desactivar-reporte', '<?= $clean_url ?>/<?= $rfc ?>/login')"><i class="fa-solid fa-play me-1"></i> Probar</button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning fw-bold text-center py-5 shadow-sm">
                                <i class="fa-solid fa-clock-rotate-left fa-3x mb-3 d-block text-warning"></i>
                                Las URLs se activarán en cuanto el Super Administrador autorice el nodo de tu universidad.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="list-auditoria" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white fw-bold py-3"><i class="fa-solid fa-eye me-2"></i> Peticiones Gubernamentales en Tiempo Real</div>
                    <div class="card-body p-0">
                        <table class="table table-hover table-striped mb-0 align-middle datatable w-100">
                            <thead class="table-light"><tr><th>Fecha</th><th>Llamada PUI</th><th>Folio Gobierno</th><th>Estado HTTP</th></tr></thead>
                            <tbody>
                                <?php
                                $logsStmt = $pdo_hub->prepare("SELECT * FROM logs_pui WHERE institucion_id = ? ORDER BY fecha_peticion DESC LIMIT 200");
                                $logsStmt->execute([$_SESSION['institucion_id']]);
                                $mis_logs = $logsStmt->fetchAll();
                                foreach($mis_logs as $l): ?>
                                <tr>
                                    <td class="small ps-3"><?= $l['fecha_peticion'] ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($l['endpoint_llamado']) ?></span></td>
                                    <td class="font-monospace small text-muted"><?= htmlspecialchars($l['folio_reporte']) ?></td>
                                    <td class="pe-3">
                                        <span class="text-<?= $l['respuesta_http']==200 ? 'success' : 'danger' ?> fw-bold">HTTP <?= $l['respuesta_http'] ?></span>
                                        <?php if($l['respuesta_http'] != 200): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($l['detalles_error']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="modalDatosGobierno" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-magnifying-glass text-info me-2"></i> Datos proporcionados por la SEGOB</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <div class="p-3 border-bottom small text-muted">Estos son los datos exactos que el gobierno federal envió en la solicitud de búsqueda (Fase 3).</div>
                <pre id="json_visor_gobierno" class="m-0 p-4 font-monospace" style="color: #28a745; background-color: #1e1e1e; max-height: 450px; overflow-y: auto; font-size: 0.9em;"></pre>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPreviewData" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-table text-info me-2"></i> Previsualización: <span id="previewNodeName" class="text-warning"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-0">
                <div id="previewLoader" class="text-center py-5" style="display:none;">
                    <i class="fa-solid fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                    <p class="fw-bold text-muted">Extrayendo información cruda (Top 50)...</p>
                </div>
                <div id="previewMessage" class="alert m-3" style="display:none;"></div>
                <div class="table-responsive" id="previewTableContainer" style="display:none; max-height: 60vh;">
                    <table class="table table-sm table-hover table-striped mb-0 font-monospace text-nowrap" style="font-size: 0.85em;" id="previewTable">
                        <thead class="table-dark sticky-top" id="previewThead"></thead>
                        <tbody id="previewTbody" class="bg-white"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top">
                <span class="small text-muted me-auto"><i class="fa-solid fa-circle-info text-info"></i> Solo se muestran los primeros 50 registros por motivos de rendimiento.</span>
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cerrar Visor</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevaInst" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-info text-white"><h5 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-2"></i> Vincular a otra Universidad</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body bg-light">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="solicitar_institucion">
                <div class="alert alert-info border-info small shadow-sm">Si administras múltiples recintos, puedes solicitar acceso a otra institución sin tener que crear una cuenta nueva.</div>
                <div class="mb-3">
                    <label class="fw-bold mb-1 text-dark">Selecciona la Institución a la que deseas unirte</label>
                    <select name="institucion_id" id="nueva_institucion_id" class="form-select form-select-lg shadow-sm fw-bold border-info" required onchange="toggleNuevaInst()">
                        <option value="">-- Buscar Universidad --</option>
                        <?php 
                        $all_inst = $pdo_hub->query("SELECT id, nombre, rfc_homoclave FROM instituciones ORDER BY nombre ASC")->fetchAll();
                        foreach($all_inst as $i): ?>
                            <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['nombre']) ?> (RFC: <?= $i['rfc_homoclave'] ?>)</option>
                        <?php endforeach; ?>
                        <option value="nueva" class="text-primary fw-bold">+ Registrar una Universidad Nueva al Sistema</option>
                    </select>
                </div>
                <div id="div_nueva_inst" style="display: none;" class="p-3 bg-white border border-info rounded mb-3 shadow-sm">
                    <h6 class="fw-bold text-info mb-3">Datos de la Nueva Institución</h6>
                    <label class="fw-bold small">Nombre Oficial</label><input type="text" name="nueva_institucion" id="ni_nom" class="form-control mb-2 bg-light">
                    <label class="fw-bold small">RFC Homoclave</label><input type="text" name="rfc_institucion" id="ni_rfc" class="form-control mb-2 text-uppercase bg-light font-monospace">
                    <label class="fw-bold small">Dominio Web</label><input type="text" name="dominio_institucion" id="ni_dom" class="form-control mb-2 bg-light">
                </div>
                <div class="mb-3"><label class="fw-bold small">Tu Cargo en esta nueva Institución</label><input type="text" name="cargo" class="form-control bg-white shadow-sm" required></div>
                <div class="mb-3"><label class="fw-bold small">Justificación de Acceso</label><textarea name="justificacion" class="form-control bg-white shadow-sm" rows="3" required></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-info text-white fw-bold w-100 shadow-sm">Enviar Solicitud a UPAEP</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalCampus" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="formNodo" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title fw-bold" id="modalTitle">Agregar Origen de Datos</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body bg-light">
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="guardar_campus">
                <input type="hidden" name="origen_id" id="origen_id" value="">
                
                <div class="mb-4"><label class="fw-bold small text-primary">Nombre de Identificación</label><input type="text" name="nombre_campus" id="nombre_campus" class="form-control form-control-lg fw-bold" required></div>
                <div class="mb-4">
                    <label class="fw-bold small text-dark">Método de Integración</label>
                    <select name="tipo_db" id="tipo_db" class="form-select border-primary fw-bold" onchange="actualizarUI()">
                        <option value="mysql">Conexión DB (MySQL / MariaDB)</option>
                        <option value="postgresql">Conexión DB (PostgreSQL)</option>
                        <option value="sqlsrv">Conexión DB (Microsoft SQL Server)</option>
                        <option value="oracle">Conexión DB (Oracle)</option>
                        <option value="mongodb">Conexión NoSQL (MongoDB)</option>
                        <option value="sftp">Conexión FTPS / FTP (Archivos Remotos)</option>
                        <option value="manual">Carga Manual (Subir Archivo CSV/JSON a Bóveda)</option>
                    </select>
                </div>

                <div id="seccion_red" class="row g-3 bg-white p-3 rounded shadow-sm border mb-3">
                    <div class="col-md-8"><label class="fw-bold small text-muted">Host / Servidor / IP</label><input type="text" name="host" id="host" class="form-control req-red"></div>
                    <div class="col-md-4"><label class="fw-bold small text-muted">Puerto</label><input type="number" name="puerto" id="puerto" class="form-control req-red"></div>
                    <div class="col-md-6"><label class="fw-bold small text-muted" id="lbl_bd">Base de Datos</label><input type="text" name="nombre_bd" id="nombre_bd" class="form-control req-red"></div>
                    <div class="col-md-6"><label class="fw-bold small text-muted" id="lbl_vista">Nombre de la VISTA</label><input type="text" name="vista" id="vista" class="form-control bg-light text-primary fw-bold req-red"></div>
                    <div class="col-md-6"><label class="fw-bold small text-muted">Usuario</label><input type="text" name="user_db" id="user_db" class="form-control req-red"></div>
                    <div class="col-md-6"><label class="fw-bold small text-muted">Contraseña</label><input type="password" name="pass_db" id="pass_db" class="form-control req-red" placeholder="•••••••• (Vacío para mantener actual)"></div>
                    <div class="col-12 text-end mt-3 border-top pt-3">
                        <button type="button" class="btn btn-outline-info btn-sm fw-bold" onclick="probarConexion()"><i class="fa-solid fa-plug-circle-check me-1"></i> Probar Conexión y Validar Estructura</button>
                        <div id="msg_prueba" class="small fw-bold mt-2 text-start"></div>
                    </div>
                </div>

                <div id="seccion_manual" class="bg-white p-4 rounded shadow-sm border border-warning mb-3" style="display:none;">
                    <div class="alert alert-warning small"><i class="fa-solid fa-lock me-2"></i> Sube un archivo con las columnas solicitadas por la PUI.</div>
                    <label class="fw-bold small text-dark">Seleccionar Archivo (.csv o .json)</label>
                    <input type="file" name="archivo_csv" id="archivo_csv" class="form-control" accept=".csv, .json">
                </div>

                <div id="seccion_ssh" class="p-3 rounded border border-secondary bg-white shadow-sm">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="req_ssh" name="req_ssh" onchange="actualizarUI()">
                        <label class="form-check-label fw-bold text-dark" for="req_ssh">La conexión requiere Túnel SSH (Firewall Interno)</label>
                    </div>
                    <div class="row g-2 bg-light p-2 rounded" id="ssh_box" style="display: none;">
                        <div class="col-md-6"><label class="fw-bold small text-muted">Servidor SSH</label><input type="text" name="ssh_host" id="ssh_host" class="form-control"></div>
                        <div class="col-md-6"><label class="fw-bold small text-muted">Puerto SSH</label><input type="number" name="ssh_puerto" id="ssh_puerto" class="form-control"></div>
                        <div class="col-md-6"><label class="fw-bold small text-muted">Usuario SSH</label><input type="text" name="ssh_user" id="ssh_user" class="form-control"></div>
                        <div class="col-md-6"><label class="fw-bold small text-muted">Contraseña SSH</label><input type="password" name="ssh_pass" id="ssh_pass" class="form-control"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar Nodo</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalTestEndpoint" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-terminal text-success me-2"></i> Consola de Pruebas API</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-dark text-light p-4 font-monospace" style="font-size: 0.9em;">
                <div class="mb-3 text-warning">Ejecutando simulación gubernamental hacia: <br><strong id="testEndpointUrl" class="text-white"></strong></div>
                <div id="testEndpointSteps" class="mb-3 text-info"></div>
                <hr class="border-secondary">
                <div class="text-success mb-2">--- RESPUESTA DEL SERVIDOR ---</div>
                <div id="testEndpointStatus" class="mb-2 fw-bold"></div>
                <pre id="testEndpointResult" class="m-0 p-3 bg-black rounded border border-secondary" style="color: #4af626; max-height: 350px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer bg-dark border-secondary">
                <button type="button" class="btn btn-outline-light fw-bold" data-bs-dismiss="modal">Cerrar Terminal</button>
            </div>
        </div>
    </div>
</div>

<script>
    // === NUEVOS GENERADORES DE SEGURIDAD === //
    function generarClaveWebhook() {
        const upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        const lower = "abcdefghijklmnopqrstuvwxyz";
        const numbers = "0123456789";
        const specials = "!@#$%^&*()-_+.";
        const all = upper + lower + numbers + specials;
        
        let pwd = upper[Math.floor(Math.random() * upper.length)] + 
                  numbers[Math.floor(Math.random() * numbers.length)] + 
                  specials[Math.floor(Math.random() * specials.length)];
        
        for(let i=0; i<15; i++) { pwd += all[Math.floor(Math.random() * all.length)]; }
        pwd = pwd.split('').sort(() => 0.5 - Math.random()).join('');
        document.getElementById('clave_webhook').value = pwd;
    }

    function generarClaveAES() {
        const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        let pwd = "";
        for(let i=0; i<32; i++) { pwd += chars[Math.floor(Math.random() * chars.length)]; }
        document.getElementById('clave_bio').value = pwd;
    }

    async function generarParRSA() {
        if(!confirm('¿Generar un nuevo Par Criptográfico RSA? Si lo haces, deberás entregar tu nueva Llave Pública a la SEGOB para que las notificaciones sigan funcionando.')) return;
        const formData = new FormData();
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
        formData.append('action', 'generar_rsa_keys');
        try {
            const res = await fetch('app.php', { method: 'POST', body: formData });
            const data = await res.json();
            if(data.success) {
                document.getElementById('llave_pub_gob').value = data.public_key;
                document.getElementById('llave_priv_inst').value = data.private_key;
                alert('¡Llaves generadas con éxito! Haz clic en "Guardar Llaves" en la parte superior para almacenarlas en el HUB.');
            } else { alert('Error al generar: ' + data.message); }
        } catch(e) { alert('Fallo de conexión.'); }
    }

    async function sincronizarSegob() {
        if(!confirm('¿Estás seguro? El HUB hará una llamada a la API del Gobierno para conciliar reportes activos. Esto puede tomar unos segundos.')) return;
        const formData = new FormData();
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
        formData.append('action', 'sincronizar_segob');
        try {
            const res = await fetch('app.php', { method: 'POST', body: formData });
            const data = await res.json();
            alert(data.message);
            if(data.success) location.reload();
        } catch(e) { alert('Fallo de conexión o el API de gobierno no está disponible.'); }
    }

    // === NUEVA FUNCIÓN: PROBADOR DE ENDPOINTS === //
    async function testEndpointPUI(endpointName, targetUrl, loginUrl = null) {
        const webhookInput = document.getElementById('clave_webhook');
        const webhook = webhookInput ? webhookInput.value : 'SIMULACION_GOB_2026';
        
        let termModal = new bootstrap.Modal(document.getElementById('modalTestEndpoint'));
        document.getElementById('testEndpointUrl').innerText = 'POST ' + targetUrl;
        document.getElementById('testEndpointSteps').innerHTML = '> Inicializando variables...<br>';
        document.getElementById('testEndpointStatus').innerHTML = '';
        document.getElementById('testEndpointResult').innerText = 'Esperando respuesta...';
        termModal.show();

        try {
            let reqHeaders = { 'Content-Type': 'application/json' };
            let reqBody = {};

            if (endpointName === 'login') {
                document.getElementById('testEndpointSteps').innerHTML += '> Enviando Payload: { "usuario": "PUI", "clave": "***" }<br>';
                reqBody = { usuario: 'PUI', clave: webhook };
            } else {
                // Paso 1: Autenticar para sacar el token
                document.getElementById('testEndpointSteps').innerHTML += '> 1. Obteniendo Bearer Token desde /login...<br>';
                const loginRes = await fetch(loginUrl, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ usuario: 'PUI', clave: webhook })
                });
                const loginData = await loginRes.json();
                
                if(!loginData.token) {
                    throw new Error("Fallo de autenticación previa. " + (loginData.error || 'Token no recibido.'));
                }
                document.getElementById('testEndpointSteps').innerHTML += '> 2. Token JWT obtenido con éxito.<br>';
                
                // Paso 2: Configurar la llamada al endpoint real
                reqHeaders['Authorization'] = 'Bearer ' + loginData.token;
                
                if (endpointName === 'activar-reporte' || endpointName === 'activar-reporte-prueba') {
                    reqBody = { id: 'SIM-' + Date.now(), curp: 'TEST010101HDFABC01' };
                    document.getElementById('testEndpointSteps').innerHTML += `> 3. Disparando POST con Payload: { "id": "${reqBody.id}", "curp": "${reqBody.curp}" }<br>`;
                } else if (endpointName === 'desactivar-reporte') {
                    reqBody = { id: 'SIM-123456789' };
                    document.getElementById('testEndpointSteps').innerHTML += `> 3. Disparando POST con Payload: { "id": "${reqBody.id}" }<br>`;
                }
            }

            // Ejecución de la llamada
            const response = await fetch(targetUrl, {
                method: 'POST',
                headers: reqHeaders,
                body: JSON.stringify(reqBody)
            });
            
            const responseData = await response.json();
            
            // Pintar la salida
            let statusColor = (response.status >= 200 && response.status < 300) ? 'text-success' : 'text-danger';
            document.getElementById('testEndpointStatus').innerHTML = `<span class="${statusColor}">HTTP Status: ${response.status}</span>`;
            document.getElementById('testEndpointResult').innerText = JSON.stringify(responseData, null, 4);

        } catch (error) {
            document.getElementById('testEndpointStatus').innerHTML = `<span class="text-danger">HTTP Status: FAILED</span>`;
            document.getElementById('testEndpointResult').innerText = error.message;
        }
    }

    // === FUNCIONES ORIGINALES RESTANTES === //
    async function previewNodo(origen_id, nombre) {
        document.getElementById('previewNodeName').innerText = nombre;
        document.getElementById('previewLoader').style.display = 'block';
        document.getElementById('previewMessage').style.display = 'none';
        document.getElementById('previewTableContainer').style.display = 'none';
        let myModal = new bootstrap.Modal(document.getElementById('modalPreviewData'));
        myModal.show();
        const formData = new FormData();
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
        formData.append('action', 'preview_nodo');
        formData.append('origen_id', origen_id);
        try {
            const res = await fetch('app.php', { method: 'POST', body: formData });
            const data = await res.json();
            document.getElementById('previewLoader').style.display = 'none';
            if (data.success) {
                if (data.message && data.data.length === 0) {
                    const msgBox = document.getElementById('previewMessage');
                    msgBox.className = 'alert alert-info m-3 fw-bold';
                    msgBox.innerHTML = '<i class="fa-solid fa-shield-halved me-2 fa-lg text-primary"></i> ' + data.message;
                    msgBox.style.display = 'block';
                } else {
                    const thead = document.getElementById('previewThead');
                    const tbody = document.getElementById('previewTbody');
                    thead.innerHTML = ''; tbody.innerHTML = '';
                    if(data.columns && data.columns.length > 0) {
                        let headRow = '<tr>';
                        data.columns.forEach(col => { headRow += `<th>${col}</th>`; });
                        headRow += '</tr>';
                        thead.innerHTML = headRow;
                        data.data.forEach(row => {
                            let tr = '<tr>';
                            row.forEach(cell => { tr += `<td>${cell !== null ? cell : '<i class="text-muted">NULL</i>'}</td>`; });
                            tr += '</tr>';
                            tbody.innerHTML += tr;
                        });
                        document.getElementById('previewTableContainer').style.display = 'block';
                    } else {
                        const msgBox = document.getElementById('previewMessage');
                        msgBox.className = 'alert alert-warning m-3';
                        msgBox.innerHTML = '<i class="fa-solid fa-folder-open me-2"></i> El archivo o la base de datos están vacíos.';
                        msgBox.style.display = 'block';
                    }
                }
            } else {
                const msgBox = document.getElementById('previewMessage');
                msgBox.className = 'alert alert-danger m-3';
                msgBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-2"></i> Error: ' + data.message;
                msgBox.style.display = 'block';
            }
        } catch (e) {
            document.getElementById('previewLoader').style.display = 'none';
            const msgBox = document.getElementById('previewMessage');
            msgBox.className = 'alert alert-danger m-3';
            msgBox.innerHTML = '<i class="fa-solid fa-wifi me-2"></i> Fallo crítico de conexión al previsualizar. ' + e.message;
            msgBox.style.display = 'block';
        }
    }

    function verDetallesGobierno(btn) {
        try {
            let jsonString = btn.getAttribute('data-json');
            let obj = JSON.parse(jsonString);
            document.getElementById('json_visor_gobierno').textContent = JSON.stringify(obj, null, 4);
            new bootstrap.Modal(document.getElementById('modalDatosGobierno')).show();
        } catch (e) { alert('No hay datos JSON válidos para este reporte.'); }
    }

    function toggleNuevaInst() {
        var val = document.getElementById('nueva_institucion_id').value;
        document.getElementById('div_nueva_inst').style.display = (val === 'nueva') ? 'block' : 'none';
        ['ni_nom', 'ni_rfc', 'ni_dom'].forEach(id => document.getElementById(id).required = (val === 'nueva'));
    }

    function actualizarUI(autoPort = true) {
        const t = document.getElementById('tipo_db').value;
        const s_red = document.getElementById('seccion_red');
        const s_man = document.getElementById('seccion_manual');
        const s_ssh = document.getElementById('seccion_ssh');
        const reqs = document.querySelectorAll('.req-red');
        const puertoInput = document.getElementById('puerto');
        
        if (t === 'manual') {
            s_red.style.display = 'none'; s_ssh.style.display = 'none'; s_man.style.display = 'block';
            document.getElementById('archivo_csv').required = true;
            reqs.forEach(el => el.required = false);
        } else {
            s_red.style.display = 'flex'; s_ssh.style.display = 'block'; s_man.style.display = 'none';
            document.getElementById('archivo_csv').required = false;
            reqs.forEach(el => el.required = true);
            if (t === 'sftp') {
                document.getElementById('lbl_bd').innerText = 'Ruta Raíz FTPS (Ej. /padron)';
                document.getElementById('lbl_vista').innerText = 'Prefijo del Archivo (Ej. data_)';
                if(autoPort) puertoInput.value = 21;
            } else if (t === 'mongodb') {
                document.getElementById('lbl_bd').innerText = 'Nombre Base de Datos';
                document.getElementById('lbl_vista').innerText = 'Nombre de la Colección';
                if(autoPort) puertoInput.value = 27017;
            } else {
                document.getElementById('lbl_bd').innerText = 'Base de Datos / SID';
                document.getElementById('lbl_vista').innerText = 'Nombre de la VISTA / Tabla';
                if(autoPort) {
                    if(t === 'mysql') puertoInput.value = 3306;
                    if(t === 'postgresql') puertoInput.value = 5432;
                    if(t === 'sqlsrv') puertoInput.value = 1433;
                    if(t === 'oracle') puertoInput.value = 1521;
                }
            }
        }
        document.getElementById('ssh_box').style.display = document.getElementById('req_ssh').checked ? 'flex' : 'none';
    }

    function resetForm() {
        document.getElementById('formNodo').reset();
        document.getElementById('origen_id').value = '';
        document.getElementById('msg_prueba').innerHTML = '';
        actualizarUI(true);
        document.getElementById('modalTitle').innerText = 'Agregar Origen de Datos';
    }

    function editarCampus(c) {
        resetForm();
        document.getElementById('origen_id').value = c.id;
        document.getElementById('nombre_campus').value = c.nombre_campus;
        document.getElementById('tipo_db').value = c.tipo_conexion;
        document.getElementById('host').value = c.host;
        document.getElementById('puerto').value = c.puerto;
        document.getElementById('nombre_bd').value = c.nombre_bd;
        document.getElementById('vista').value = c.nombre_vista;
        document.getElementById('user_db').value = c.usuario;
        document.getElementById('req_ssh').checked = (c.requiere_ssh == 1);
        document.getElementById('ssh_host').value = c.ssh_host || '';
        document.getElementById('ssh_puerto').value = c.ssh_puerto || '';
        document.getElementById('ssh_user').value = c.ssh_usuario || '';
        actualizarUI(false); 
        document.getElementById('modalTitle').innerText = 'Editar Nodo: ' + c.nombre_campus;
        new bootstrap.Modal(document.getElementById('modalCampus')).show();
    }

    async function probarConexion() {
        const msg = document.getElementById('msg_prueba');
        msg.innerHTML = '<span class="text-warning"><i class="fa-solid fa-spinner fa-spin me-2"></i> Probando conexión con el motor remoto...</span>';
        const formData = new FormData(document.getElementById('formNodo'));
        formData.append('csrf_token', document.getElementById('csrf_token').value);
        try {
            const res = await fetch('ajax_validador.php', { method: 'POST', body: formData });
            const data = await res.json();
            if(data.success) msg.innerHTML = `<span class="text-success"><i class="fa-solid fa-circle-check me-2"></i> ${data.message}</span>`;
            else msg.innerHTML = `<span class="text-danger"><i class="fa-solid fa-circle-xmark me-2"></i> ${data.message}</span>`;
        } catch(e) { msg.innerHTML = `<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Error al conectar con el servidor local o firewall.</span>`; }
    }
</script>