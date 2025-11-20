<?php
// admin/tareas.php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Columnas Kanban (sin ideas ni bloqueado)
$COLUMNAS = [
  'pendiente'    => 'Pendiente',
  'en_progreso'  => 'En progreso',
  'en_revision'  => 'En revisión',
  'completada'   => 'Completada'
];

$prioridades = ['baja','media','alta','crítica'];
$equipos     = ['diseño','desarrollo'];

// Cargar TODAS las tareas (sin filtro por mes)
$sql = "
 SELECT t.id, t.ticket, t.titulo, t.descripcion, t.estado_kanban, t.prioridad, t.equipo,
        t.fecha_inicio, t.fecha_entrega, t.estimado_horas, t.real_horas, t.creado_en, t.orden,
        GROUP_CONCAT(DISTINCT u.id ORDER BY u.id SEPARATOR ',')     AS asignados_ids,
        GROUP_CONCAT(DISTINCT u.nombre ORDER BY u.id SEPARATOR ',') AS asignados_nombres,
        (SELECT COUNT(*) FROM tareas_checklist_items i
           JOIN tareas_checklists c ON c.id=i.checklist_id
           WHERE c.tarea_id=t.id) AS checklist_total,
        (SELECT COUNT(*) FROM tareas_checklist_items i
           JOIN tareas_checklists c ON c.id=i.checklist_id
           WHERE c.tarea_id=t.id AND i.done=1) AS checklist_done,
        (SELECT COUNT(*) FROM tareas_adjuntos a WHERE a.tarea_id=t.id) AS adjuntos_total
   FROM tareas t
   LEFT JOIN tareas_asignados a ON a.tarea_id=t.id
   LEFT JOIN usuarios u ON u.id=a.usuario_id
  GROUP BY t.id
  ORDER BY FIELD(t.estado_kanban,'pendiente','en_progreso','en_revision','completada'),
           t.orden, t.id DESC
";
$tareas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fecha_corta($ts){ return $ts ? date('Y-m-d', strtotime($ts)) : ''; }
function badge_prioridad($p){
  $map = [
    'crítica' => 'bg-red-100 text-red-800 ring-red-200',
    'alta'    => 'bg-orange-100 text-orange-800 ring-orange-200',
    'media'   => 'bg-amber-100 text-amber-800 ring-amber-200',
    'baja'    => 'bg-gray-100 text-gray-800 ring-gray-200',
  ];
  $cls = $map[mb_strtolower($p)] ?? $map['baja'];
  return '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.$cls.'">'.h(ucfirst($p)).'</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Tareas (Kanban)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <style>
    .kanban-col{min-width:280px}
    .kanban-card{border:1px solid #e5e7eb;border-radius:12px;background:#fff;box-shadow:0 1px 2px rgb(0 0 0 / 0.05)}
    .chip{border:1px solid #e5e7eb;border-radius:9999px;padding:.125rem .5rem;font-size:.75rem}
    .avatar{width:24px;height:24px;border-radius:9999px;background:#e5e7eb;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#374151}
    .dropzone{min-height:20px}
    .scrollbar-thin::-webkit-scrollbar{height:8px;width:8px}
    .scrollbar-thin::-webkit-scrollbar-thumb{background:#e5e7eb;border-radius:8px}
    .inp{border:1px solid #e5e7eb;border-radius:.5rem;padding:.5rem .75rem}
    .drag-handle{cursor:grab}
    .drag-handle:active{cursor:grabbing}
  </style>
</head>
<body class="bg-gray-100">
  <?php include '../includes/sidebar_admin.php'; ?>

  <div class="p-4 lg:ml-64">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <h2 class="text-2xl font-bold flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.5" d="M4 7h16M4 12h16M4 17h10"/></svg>
        Tareas (Kanban)
      </h2>
      <button class="bg-blue-600 text-white px-4 py-2 rounded inline-flex items-center gap-2" onclick="abrirModalNueva()">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.5" d="M12 5v14M5 12h14"/></svg>
        Nueva tarea
      </button>
    </div>

    <div class="grid auto-cols-[280px] grid-flow-col gap-4 overflow-x-auto scrollbar-thin pb-4">
      <?php foreach ($COLUMNAS as $estado => $tituloCol): ?>
        <div class="kanban-col">
          <div class="flex items-center justify-between mb-2">
            <div class="text-sm font-semibold text-gray-700"><?= h($tituloCol) ?></div>
          </div>
          <div class="dropzone space-y-3 p-1" id="col-<?= h($estado) ?>" data-estado="<?= h($estado) ?>">
            <?php foreach ($tareas as $t): if ($t['estado_kanban'] !== $estado) continue; ?>
              <div class="kanban-card p-3" data-id="<?= (int)$t['id'] ?>">
                <div class="flex items-center justify-between">
                  <div class="text-xs text-gray-500 font-mono"><?= h($t['ticket'] ?: '—') ?></div>
                  <div class="flex items-center gap-2">
                    <?= badge_prioridad($t['prioridad']) ?>
                    <button type="button" class="drag-handle text-gray-400 hover:text-gray-600" title="Arrastrar">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <circle cx="5" cy="6" r="1"/><circle cx="10" cy="6" r="1"/><circle cx="15" cy="6" r="1"/>
                        <circle cx="5" cy="10" r="1"/><circle cx="10" cy="10" r="1"/><circle cx="15" cy="10" r="1"/>
                      </svg>
                    </button>
                  </div>
                </div>
                <div class="mt-1 font-semibold text-gray-900"><?= h($t['titulo']) ?></div>
                <?php if (!empty($t['fecha_entrega'])): ?>
                  <div class="mt-1 text-xs text-gray-600">
                    Entrega:
                    <span class="<?= (strtotime($t['fecha_entrega'])<strtotime(date('Y-m-d')) && $t['estado_kanban']!=='completada')?'text-red-600 font-medium':'' ?>">
                      <?= h(fecha_corta($t['fecha_entrega'])) ?>
                    </span>
                  </div>
                <?php endif; ?>
                <div class="mt-2 flex items-center justify-between">
                  <div class="flex -space-x-1">
                    <?php
                      $names = array_filter(explode(',', (string)$t['asignados_nombres']));
                      foreach ($names as $n):
                        $ini = mb_substr(trim($n), 0, 2);
                    ?>
                      <div class="avatar ring-2 ring-white" title="<?= h($n) ?>"><?= h(mb_strtoupper($ini)) ?></div>
                    <?php endforeach; ?>
                  </div>
                  <div class="text-xs text-gray-500 flex items-center gap-2">
                    <?php if ((int)$t['checklist_total'] > 0): ?>
                      <span class="chip"><?= (int)$t['checklist_done'] ?>/<?= (int)$t['checklist_total'] ?></span>
                    <?php endif; ?>
                    <?php if ((int)$t['adjuntos_total'] > 0): ?>
                      <span class="chip">Adj: <?= (int)$t['adjuntos_total'] ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                  <button class="px-3 py-1 text-xs rounded border border-gray-200 hover:bg-gray-50"
                          onclick='abrirModalEditar(<?= json_encode($t, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>Editar</button>
                  <button class="px-3 py-1 text-xs rounded border border-red-200 text-red-600 hover:bg-red-50"
                          onclick="confirmEliminar(<?= (int)$t['id'] ?>)">Eliminar</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Modal (responsive) -->
  <div id="modalTarea" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="cerrarModal()"></div>
    <div class="relative mx-auto sm:mt-10 bg-white w-full h-full sm:h-auto sm:max-h-[90vh] sm:max-w-3xl rounded-none sm:rounded-lg shadow-lg flex flex-col">
      <div class="p-4 border-b flex items-center justify-between">
        <h3 id="modalTitle" class="text-xl font-bold">Nueva tarea</h3>
        <button class="text-gray-500 hover:text-black" onclick="cerrarModal()">✕</button>
      </div>

      <div class="p-4 overflow-y-auto flex-1">
        <form id="formTarea" class="grid grid-cols-1 md:grid-cols-12 gap-4" enctype="multipart/form-data">
          <input type="hidden" name="action" value="crear">
          <input type="hidden" name="id">

          <div class="md:col-span-12">
            <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
            <input type="text" name="titulo" class="inp w-full" required>
          </div>

          <div class="md:col-span-12">
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
            <textarea name="descripcion" class="inp w-full" rows="4" placeholder="Detalles, alcance, enlaces…"></textarea>
          </div>

          <div class="md:col-span-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
            <select name="prioridad" class="inp w-full" required>
              <?php foreach ($prioridades as $p): ?><option value="<?= h($p) ?>"><?= ucfirst($p) ?></option><?php endforeach; ?>
            </select>
          </div>

          <div class="md:col-span-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Equipo</label>
            <select name="equipo" class="inp w-full" required>
              <?php foreach ($equipos as $e): ?><option value="<?= h($e) ?>"><?= ucfirst($e) ?></option><?php endforeach; ?>
            </select>
          </div>

          <div class="md:col-span-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha inicio</label>
            <input type="date" name="fecha_inicio" class="inp w-full">
          </div>
          <div class="md:col-span-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha entrega</label>
            <input type="date" name="fecha_entrega" class="inp w-full">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Estimado (h)</label>
            <input type="number" step="0.1" min="0" name="estimado_horas" class="inp w-full">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Real (h)</label>
            <input type="number" step="0.1" min="0" name="real_horas" class="inp w-full">
          </div>

          <!-- Asignados -->
          <div class="md:col-span-12">
            <label class="block text-sm font-medium text-gray-700 mb-1">Asignados</label>
            <div id="asignadosWrap" class="flex flex-wrap gap-2 mb-2"></div>
            <input type="text" id="usuarioBuscar" class="inp w-full" placeholder="Escribe 2+ letras para buscar usuarios…" autocomplete="off">
            <div id="acUsuarios" class="hidden relative bg-white border border-gray-200 rounded mt-1 shadow max-h-72 overflow-auto z-50"></div>
          </div>

          <!-- Checklist -->
          <div class="md:col-span-12">
            <div class="flex items-center justify-between mb-1">
              <label class="block text-sm font-medium text-gray-700">Checklist</label>
              <button type="button" class="text-sm px-2 py-1 rounded border border-gray-200 hover:bg-gray-50" onclick="agregarChecklist()">Añadir sección</button>
            </div>
            <div id="checklists" class="space-y-3"></div>
          </div>

          <!-- Adjuntos -->
          <div class="md:col-span-12">
            <label class="block text-sm font-medium text-gray-700 mb-1">Adjuntos</label>
            <input type="file" name="adjuntos[]" class="w-full" multiple>
            <div id="adjuntosExistentes" class="mt-2 text-sm text-gray-600"></div>
          </div>
        </form>
      </div>

      <div class="p-4 border-t flex justify-end gap-2">
        <button type="button" class="px-4 py-2 rounded border border-gray-200" onclick="cerrarModal()">Cancelar</button>
        <button type="button" class="bg-green-600 text-white px-4 py-2 rounded" onclick="submitForm()">Guardar</button>
      </div>
    </div>
  </div>

  <script>
    // ---------- Kanban drag & drop ----------
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.dropzone').forEach(col => {
        new Sortable(col, {
          group: 'kanban',
          animation: 150,
          handle: '.drag-handle',
          ghostClass: 'opacity-50',
          onEnd: onDrop
        });
      });
    });

    async function onDrop(evt){
      const toCol  = evt.to;
      const estado = toCol.dataset.estado;
      const tareaId = evt.item.dataset.id;
      const ids = Array.from(toCol.querySelectorAll('.kanban-card')).map(el => el.dataset.id);
      try{
        const fd = new FormData();
        fd.append('action','mover');
        fd.append('id', tareaId);
        fd.append('estado_kanban', estado);
        fd.append('orden_json', JSON.stringify(ids));
        const res = await fetch('../controllers/tareas_controller.php', { method:'POST', body: fd });
        const json = await res.json();
        if(!json.success){ alert(json.message || 'No se pudo mover'); }
      }catch(e){ alert('Error de red'); }
    }

    // ---------- Modal ----------
    const modal = document.getElementById('modalTarea');
    const form  = document.getElementById('formTarea');
    const modalTitle = document.getElementById('modalTitle');

    function abrirModalNueva(){
      modalTitle.textContent = 'Nueva tarea';
      form.reset();
      form.action.value = 'crear';
      form.id.value = '';
      document.getElementById('adjuntosExistentes').innerHTML = '';
      limpiarAsignados();
      document.getElementById('checklists').innerHTML = '';
      modal.classList.remove('hidden'); modal.classList.add('flex');
    }
    function abrirModalEditar(t){
      modalTitle.textContent = 'Editar tarea ' + (t.ticket || '');
      form.reset();
      form.action.value = 'actualizar';
      form.id.value = t.id || '';
      form.titulo.value = t.titulo || '';
      form.descripcion.value = t.descripcion || '';
      form.prioridad.value = t.prioridad || 'media';
      form.equipo.value = t.equipo || 'desarrollo';
      form.fecha_inicio.value  = t.fecha_inicio ? t.fecha_inicio.substring(0,10) : '';
      form.fecha_entrega.value = t.fecha_entrega ? t.fecha_entrega.substring(0,10) : '';
      form.estimado_horas.value = t.estimado_horas || '';
      form.real_horas.value     = t.real_horas || '';
      cargarAsignados(t.id);
      cargarChecklists(t.id);
      cargarAdjuntos(t.id);
      modal.classList.remove('hidden'); modal.classList.add('flex');
    }
    function cerrarModal(){
      modal.classList.add('hidden'); modal.classList.remove('flex');
    }

    async function submitForm(){
      const checkData = serializarChecklists();
      const fd = new FormData(form);
      fd.append('checklists_json', JSON.stringify(checkData));
      document.querySelectorAll('#asignadosWrap .chip-user').forEach(chip=>fd.append('asignados[]', chip.dataset.id));
      try{
        const res = await fetch('../controllers/tareas_controller.php', { method:'POST', body: fd });
        const json = await res.json();
        if(json.success){ location.reload(); } else { alert(json.message || 'Error al guardar'); }
      }catch{ alert('Error de red'); }
    }

    // ---------- Asignados ----------
    const usuariosAC = {timer:null, list:document.getElementById('acUsuarios'), input:document.getElementById('usuarioBuscar'), wrap:document.getElementById('asignadosWrap')};
    function limpiarAsignados(){ usuariosAC.wrap.innerHTML = ''; }
    function addChipUsuario(id, nombre){
      if ([...usuariosAC.wrap.querySelectorAll('.chip-user')].some(x=>x.dataset.id==id)) return;
      const chip = document.createElement('div');
      chip.className = 'chip-user inline-flex items-center gap-2 chip';
      chip.dataset.id = id;
      chip.innerHTML = `<span class="avatar">${(nombre||'U').substring(0,2).toUpperCase()}</span><span>${nombre}</span><button type="button" class="text-gray-500 hover:text-black" onclick="this.parentElement.remove()">×</button>`;
      usuariosAC.wrap.appendChild(chip);
    }
    usuariosAC.input.addEventListener('input', ()=>{
      const q = usuariosAC.input.value.trim();
      if (usuariosAC.timer) clearTimeout(usuariosAC.timer);
      if (q.length < 2){ usuariosAC.list.classList.add('hidden'); usuariosAC.list.innerHTML=''; return; }
      usuariosAC.timer = setTimeout(async ()=>{
        try{
          const fd = new FormData(); fd.append('action','buscar_usuarios'); fd.append('q', q);
          const res = await fetch('../controllers/tareas_controller.php', { method:'POST', body: fd });
          const json = await res.json();
          usuariosAC.list.innerHTML = '';
          if (json.success && Array.isArray(json.data) && json.data.length){
            json.data.forEach(u=>{
              const item = document.createElement('div');
              item.className = 'px-3 py-2 hover:bg-gray-50 cursor-pointer';
              item.textContent = `${u.nombre} · ${u.email}`;
              item.addEventListener('click', ()=>{
                addChipUsuario(u.id, u.nombre);
                usuariosAC.list.classList.add('hidden'); usuariosAC.list.innerHTML='';
                usuariosAC.input.value='';
              });
              usuariosAC.list.appendChild(item);
            });
            usuariosAC.list.classList.remove('hidden');
          }else{
            usuariosAC.list.classList.remove('hidden');
            usuariosAC.list.innerHTML = '<div class="px-3 py-2 text-gray-500">Sin resultados…</div>';
          }
        }catch{}
      }, 250);
    });
    document.addEventListener('click', (e)=> {
      if (!usuariosAC.list.contains(e.target) && e.target!==usuariosAC.input) {
        usuariosAC.list.classList.add('hidden'); usuariosAC.list.innerHTML='';
      }
    });

    async function cargarAsignados(tareaId){
      const fd = new FormData(); fd.append('action','get_asignados'); fd.append('id', tareaId);
      const res = await fetch('../controllers/tareas_controller.php', { method:'POST', body: fd });
      const json = await res.json();
      limpiarAsignados();
      if(json.success && Array.isArray(json.data)){ json.data.forEach(u => addChipUsuario(u.id, u.nombre)); }
    }

    // ---------- Checklists ----------
    function agregarChecklist(){
      const wrap = document.getElementById('checklists');
      const box = document.createElement('div');
      box.className = 'rounded border border-gray-200 p-3';
      box.innerHTML = `
        <input type="text" class="inp w-full mb-2" placeholder="Título de la sección">
        <div class="space-y-2"></div>
        <button type="button" class="text-sm px-2 py-1 rounded border border-gray-200 hover:bg-gray-50" onclick="agregarItem(this)">Añadir ítem</button>
      `;
      wrap.appendChild(box);
    }
    function agregarItem(btn){
      const cont = btn.previousElementSibling;
      const row = document.createElement('div');
      row.className = 'flex items-center gap-2';
      row.innerHTML = `<input type="checkbox" class="h-4 w-4" /> <input type="text" class="inp flex-1" placeholder="Descripción del ítem"> <button type="button" onclick="this.parentElement.remove()" class="text-gray-500 hover:text-black">×</button>`;
      cont.appendChild(row);
    }
    function serializarChecklists(){
      const out = [];
      document.querySelectorAll('#checklists > div').forEach(section=>{
        const titulo = section.querySelector('input[type="text"]').value.trim();
        const items = [];
        section.querySelectorAll('div > .flex').forEach(item=>{
          const done = item.querySelector('input[type="checkbox"]').checked ? 1 : 0;
          const texto = item.querySelector('input[type="text"]').value.trim();
          if (texto) items.push({texto, done});
        });
        if (titulo || items.length){ out.push({titulo, items}); }
      });
      return out;
    }
    async function cargarChecklists(tareaId){
      const fd = new FormData(); fd.append('action','get_checklists'); fd.append('id', tareaId);
      const res = await fetch('../controllers/tareas_controller.php', { method:'POST', body: fd });
      const json = await res.json();
      const wrap = document.getElementById('checklists');
      wrap.innerHTML = '';
      if (json.success && Array.isArray(json.data)){
        json.data.forEach(sec=>{
          agregarChecklist();
          const last = wrap.lastElementChild;
          last.querySelector('input[type="text"]').value = sec.titulo || '';
          const cont = last.querySelector('div.space-y-2');
          cont.innerHTML = '';
          (sec.items||[]).forEach(it=>{
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2';
            row.innerHTML = `<input type="checkbox" class="h-4 w-4" ${it.done?'checked':''}/> <input type="text" class="inp flex-1" value="${(it.texto||'').replace(/"/g,'&quot;')}" placeholder="Descripción del ítem"> <button type="button" onclick="this.parentElement.remove()" class="text-gray-500 hover:text-black">×</button>`;
            cont.appendChild(row);
          });
        });
      }
    }

    // ---------- Adjuntos ----------
    async function cargarAdjuntos(tareaId){
      const fd = new FormData(); fd.append('action','get_adjuntos'); fd.append('id', tareaId);
      const res = await fetch('../controllers/tareas_controller.php', { method:'POST', body: fd });
      const json = await res.json();
      const box = document.getElementById('adjuntosExistentes');
      box.innerHTML = '';
      if (json.success && Array.isArray(json.data)){
        json.data.forEach(a=>{
          const row = document.createElement('div');
          row.innerHTML = `<a class="underline" href="${a.url}" target="_blank">${a.nombre}</a> <button type="button" class="text-red-600 ml-2" onclick="eliminarAdjunto(${a.id})">Eliminar</button>`;
          box.appendChild(row);
        });
      }
    }
    async function eliminarAdjunto(id){
      if(!confirm('¿Eliminar adjunto?')) return;
      const fd = new FormData(); fd.append('action','adjunto_eliminar'); fd.append('adjunto_id', id);
      const res = await fetch('../controllers/tareas_controller.php', { method:'POST', body: fd });
      const json = await res.json();
      if(json.success){ cerrarModal(); location.reload(); } else { alert(json.message||'No se pudo eliminar'); }
    }

    // ---------- Eliminar tarea ----------
    async function confirmEliminar(id){
      if(!confirm('¿Eliminar esta tarea?')) return;
      const fd = new FormData(); fd.append('action','eliminar'); fd.append('id', id);
      const res = await fetch('../controllers/tareas_controller.php', { method:'POST', body: fd });
      const json = await res.json();
      if(json.success){ location.reload(); } else { alert(json.message||'No se pudo eliminar'); }
    }
  </script>
</body>
</html>
