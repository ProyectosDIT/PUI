<?php 
// /var/www/html/dit/tools/pui/views/admin_view.php
?>
<h3 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-screwdriver-wrench me-2 text-primary"></i> Centro de Control Global UPAEP</h3>

<ul class="nav nav-pills mb-4 shadow-sm bg-white rounded p-2" id="adminTabs" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active fw-bold" data-bs-toggle="pill" data-bs-target="#tab-users"><i class="fa-solid fa-users me-1"></i> Usuarios (Primarios)</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tab-assoc"><i class="fa-solid fa-network-wired me-1"></i> Accesos Multicuenta</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tab-inst"><i class="fa-solid fa-building-columns me-1"></i> Instituciones</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tab-traffic"><i class="fa-solid fa-chart-line me-1"></i> Tráfico API</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link fw-bold text-success" data-bs-toggle="pill" data-bs-target="#tab-emails"><i class="fa-solid fa-envelope-open-text me-1"></i> Auditoría Correos</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link fw-bold text-danger" data-bs-toggle="pill" data-bs-target="#tab-logs"><i class="fa-solid fa-user-secret me-1"></i> Logs Auditoría</button></li>
</ul>

<div class="tab-content">
    
    <div class="tab-pane fade show active" id="tab-users">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle w-100 datatable">
                    <thead class="table-dark"><tr><th>Usuario / Cargo</th><th>Institución Base (Principal)</th><th>Último Acceso</th><th>Acción</th></tr></thead>
                    <tbody>
                        <?php 
                        $usuarios = $pdo_hub->query("SELECT u.*, i.nombre as inst_nombre FROM usuarios u LEFT JOIN instituciones i ON u.institucion_id = i.id ORDER BY u.activo ASC")->fetchAll();
                        foreach($usuarios as $u): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                                <?php if($u['puede_aprobar']==1 && $u['rol'] !== 'superadmin') echo "<span class='badge bg-warning text-dark ms-1'><i class='fa-solid fa-star'></i> Admin</span>"; ?>
                                <?php if($u['rol'] === 'superadmin') echo "<span class='badge bg-danger ms-1'><i class='fa-solid fa-shield-halved'></i> SUPERADMIN</span>"; ?>
                                <br><small class="text-muted"><?= htmlspecialchars($u['email']) ?> - <?= htmlspecialchars($u['cargo']) ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($u['inst_nombre'] ?? 'N/A') ?></span></td>
                            <td class="small text-muted"><?= $u['ultimo_acceso'] ?></td>
                            <td>
                                <?php if($u['activo'] == 1 && $u['rol'] !== 'superadmin'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="toggle_delegar_superadmin">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="inst_id" value="<?= $u['institucion_id'] ?>">
                                        <button class="btn btn-sm <?= $u['puede_aprobar']?'btn-outline-danger':'btn-outline-dark' ?> shadow-sm">
                                            <?= $u['puede_aprobar']?'Quitar Permiso de Admin':'Hacer Administrador' ?>
                                        </button>
                                    </form>
                                <?php elseif($u['activo'] == 0): ?>
                                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="action" value="aprobar_usuario"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="btn btn-success btn-sm fw-bold shadow-sm">Aprobar Entrada</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-assoc">
        <div class="alert alert-info border-info shadow-sm"><i class="fa-solid fa-circle-info me-2"></i> Aquí validas o modificas los permisos de usuarios que pertenecen a más de una universidad.</div>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body table-responsive">
                <table class="table datatable align-middle w-100 table-hover">
                    <thead class="table-primary"><tr><th>Usuario Vinculado</th><th>Institución Secundaria</th><th>Estatus / Permiso</th><th>Acción</th></tr></thead>
                    <tbody>
                        <?php 
                        $asocs = $pdo_hub->query("SELECT ui.*, u.nombre as user_name, u.email, i.nombre as inst_name FROM usuario_instituciones ui JOIN usuarios u ON ui.usuario_id = u.id JOIN instituciones i ON ui.institucion_id = i.id")->fetchAll();
                        foreach($asocs as $a): ?>
                        <tr>
                            <td><b><?= htmlspecialchars($a['user_name']) ?></b><br><small><?= htmlspecialchars($a['email']) ?> - <?= htmlspecialchars($a['cargo']) ?></small></td>
                            <td><span class="badge bg-dark"><?= htmlspecialchars($a['inst_name']) ?></span></td>
                            <td>
                                <?php if($a['activo'] == 0): ?>
                                    <span class="text-warning fw-bold small"><i class="fa-solid fa-clock"></i> Pendiente de Aprobar Acceso</span>
                                <?php else: ?>
                                    <span class="text-success fw-bold small"><i class="fa-solid fa-check-circle"></i> Acceso Aprobado</span><br>
                                    <?php if($a['puede_aprobar'] == 1): ?>
                                        <span class="badge bg-warning text-dark mt-1"><i class="fa-solid fa-star"></i> Admin en este Campus</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($a['activo'] == 0): ?>
                                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="action" value="aprobar_asociacion"><input type="hidden" name="assoc_id" value="<?= $a['id'] ?>"><button class="btn btn-success btn-sm shadow-sm">Aprobar Vínculo</button></form>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="toggle_delegar_superadmin">
                                        <input type="hidden" name="target_user_id" value="<?= $a['usuario_id'] ?>">
                                        <input type="hidden" name="inst_id" value="<?= $a['institucion_id'] ?>">
                                        <button class="btn btn-sm <?= $a['puede_aprobar']?'btn-outline-danger':'btn-outline-dark' ?> shadow-sm">
                                            <?= $a['puede_aprobar']?'Quitar Admin':'Hacer Admin' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-inst">
        <div class="alert alert-warning border-warning shadow-sm"><i class="fa-solid fa-shield-halved me-2"></i> <strong>Sección 10 del DOF:</strong> Es tu responsabilidad como HUB validar que cada institución haya superado auditorías SAST, DAST y SCA antes de interconectarla a la red de la PUI.</div>
        <div class="card shadow-sm border-0">
            <div class="card-body table-responsive">
                <table class="table table-hover datatable align-middle w-100">
                    <thead class="table-dark"><tr><th>Institución</th><th>RFC</th><th>Nodos</th><th>Estatus</th><th>Validación DOF y Acción</th></tr></thead>
                    <tbody>
                        <?php
                        $directorio = $pdo_hub->query("SELECT i.id, i.nombre, i.rfc_homoclave, i.estatus, COUNT(o.id) as nodos FROM instituciones i LEFT JOIN origenes_datos o ON i.id = o.institucion_id GROUP BY i.id")->fetchAll();
                        foreach($directorio as $dir): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($dir['nombre']) ?></td>
                            <td class="font-monospace text-muted"><?= htmlspecialchars($dir['rfc_homoclave']) ?></td>
                            <td><span class="badge bg-primary rounded-pill"><?= $dir['nodos'] ?></span></td>
                            <td>
                                <?php if ($dir['estatus'] == 'aprobada'): ?>
                                    <span class="text-success fw-bold"><i class="fa-solid fa-check"></i> Activa</span>
                                <?php elseif ($dir['estatus'] == 'suspendida'): ?>
                                    <span class="text-danger fw-bold"><i class="fa-solid fa-ban"></i> Suspendida</span>
                                <?php else: ?>
                                    <span class="text-warning fw-bold"><i class="fa-solid fa-clock"></i> Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($dir['estatus'] != 'aprobada'): ?>
                                    <form method="POST" onsubmit="return validateAudit(this)">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="aprobar_institucion">
                                        <input type="hidden" name="inst_id" value="<?= $dir['id'] ?>">
                                        <div class="form-check small mb-2">
                                            <input class="form-check-input check-audit" type="checkbox" id="check_<?= $dir['id'] ?>" required>
                                            <label class="form-check-label text-muted" for="check_<?= $dir['id'] ?>" style="font-size:0.8rem;">
                                                Confirmo que he recibido y validado los reportes de ciberseguridad (SAST/DAST) de este recinto.
                                            </label>
                                        </div>
                                        <button class="btn btn-success w-100 btn-sm fw-bold shadow-sm"><i class="fa-solid fa-plug-circle-check"></i> Autorizar Red</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" onsubmit="return confirm('¿ESTÁS SEGURO? Suspender la institución cortará inmediatamente todo el tráfico con el Gobierno Federal y devolverá errores 404 a las peticiones de la PUI. Úsalo solo en caso de sospecha de brecha de seguridad.')">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="suspender_institucion">
                                        <input type="hidden" name="inst_id" value="<?= $dir['id'] ?>">
                                        <button class="btn btn-outline-danger w-100 btn-sm fw-bold shadow-sm" title="Cortar tráfico de datos hacia la SEGOB"><i class="fa-solid fa-skull-crossbones"></i> Suspender Tráfico</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-traffic">
        <div class="card shadow-sm border-0">
            <div class="card-body table-responsive">
                <table class="table datatable align-middle w-100">
                    <thead class="table-dark"><tr><th>Institución</th><th>RFC</th><th>Total Peticiones (Gob)</th><th>Último Consumo</th></tr></thead>
                    <tbody>
                        <?php
                        $consumos = $pdo_hub->query("SELECT i.nombre, i.rfc_homoclave, COUNT(l.id) as total_req, MAX(l.fecha_peticion) as ultimo_req FROM instituciones i LEFT JOIN logs_pui l ON i.id = l.institucion_id GROUP BY i.id")->fetchAll();
                        foreach($consumos as $c): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($c['nombre']) ?></td>
                            <td class="font-monospace text-muted small"><?= htmlspecialchars($c['rfc_homoclave']) ?></td>
                            <td><span class="badge bg-primary"><?= $c['total_req'] ?> reqs</span></td>
                            <td class="small text-muted"><?= $c['ultimo_req'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-emails">
        <div class="alert alert-info border-info shadow-sm"><i class="fa-solid fa-eye me-2"></i> Píxel de Rastreo Activo. Verifica en tiempo real qué correos han sido leídos.</div>
        <div class="card shadow-sm border-0">
            <div class="card-body table-responsive">
                <table class="table datatable align-middle w-100">
                    <thead class="table-success"><tr><th>Destinatario</th><th>Asunto del Correo</th><th>Fecha Envío</th><th>Estatus (Tracking)</th><th>Fecha Apertura</th></tr></thead>
                    <tbody>
                        <?php
                        $correos = $pdo_hub->query("SELECT * FROM logs_correos ORDER BY id DESC")->fetchAll();
                        foreach($correos as $co): ?>
                        <tr>
                            <td><?= htmlspecialchars($co['to_email']) ?></td>
                            <td class="small fw-bold text-muted"><?= htmlspecialchars($co['subject']) ?></td>
                            <td class="small text-muted"><?= $co['fecha_envio'] ?></td>
                            <td>
                                <?php if($co['abierto']): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-envelope-open"></i> Leído</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fa-solid fa-envelope"></i> No Leído</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= $co['fecha_apertura'] ?? '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-logs">
        <div class="card shadow-sm border-0">
            <div class="card-body table-responsive">
                <table class="table datatable align-middle w-100">
                    <thead class="table-danger"><tr><th>Fecha y Hora</th><th>Administrador Ejecutor</th><th>Tipo de Acción</th><th>IP Registrada</th><th>Detalles Técnicos</th></tr></thead>
                    <tbody>
                        <?php
                        $logs = $pdo_hub->query("SELECT l.*, u.email as admin_email FROM logs_auditoria l JOIN usuarios u ON l.user_id = u.id ORDER BY l.id DESC")->fetchAll();
                        foreach($logs as $l): ?>
                        <tr>
                            <td class="small text-muted"><?= $l['fecha'] ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($l['admin_email']) ?></td>
                            <td><span class="badge bg-danger bg-opacity-10 text-danger border border-danger"><?= htmlspecialchars($l['accion']) ?></span></td>
                            <td class="font-monospace small text-muted"><?= htmlspecialchars($l['ip']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($l['detalles']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
    function validateAudit(form) {
        let checkbox = form.querySelector('.check-audit');
        if (!checkbox.checked) {
            alert('Para cumplir con el Manual Técnico DOF de la SEGOB, debes confirmar haber recibido los reportes de vulnerabilidades.');
            return false;
        }
        return confirm('¿Confirmas la interconexión de esta universidad al HUB Central de la PUI? Se le notificará al responsable por correo electrónico.');
    }
</script>