// public_html/assets/js/app.js

// -----------------------------------------------------------------------------
// Глобальное состояние
// -----------------------------------------------------------------------------
const state = {
  entity: 'deals',   // 'deals' | 'contacts'
  list: [],
  selectedId: null
};

// -----------------------------------------------------------------------------
// Ссылки на элементы
// -----------------------------------------------------------------------------
const els = {
  menu: document.getElementById('menu'),
  list: document.getElementById('list'),
  content: document.getElementById('content'),
  addBtn: document.getElementById('addBtn'),
  editBtn: document.getElementById('editBtn'),
  deleteBtn: document.getElementById('deleteBtn'),
  switcher: document.getElementById('entitySwitcher'),
  modal: document.getElementById('modal'),
};

// -----------------------------------------------------------------------------
// Инициализация
// -----------------------------------------------------------------------------
init();

function init(){
  els.menu.addEventListener('click', onMenuClick);
  els.switcher.addEventListener('change', e => switchEntity(e.target.value));
  els.addBtn.addEventListener('click', () => openForm('create'));
  els.editBtn.addEventListener('click', () => state.selectedId && openForm('update'));
  els.deleteBtn.addEventListener('click', delSelected);

  loadList();
  startLiveUpdates(); // онлайн-синхронизация между устройствами (SSE)
}

// -----------------------------------------------------------------------------
// Переключение сущностей
// -----------------------------------------------------------------------------
function onMenuClick(e){
  const li = e.target.closest('li[data-entity]');
  if(!li) return;
  switchEntity(li.dataset.entity);
}

function switchEntity(entity){
  state.entity = entity;
  state.selectedId = null;
  document.querySelectorAll('.menu li').forEach(li => li.classList.toggle('active', li.dataset.entity===entity));
  if (els.switcher) els.switcher.value = entity;
  renderContentPlaceholder();
  loadList();
}

// -----------------------------------------------------------------------------
// Загрузка и рендер списков / карточек
// -----------------------------------------------------------------------------
async function loadList(){
  try{
    const res = await fetch(`./api/${state.entity}.php`, { cache: 'no-store' });
    const data = await res.json();
    state.list = Array.isArray(data) ? data : [];
  }catch(_){
    state.list = [];
  }
  renderList();
}

function renderList(){
  els.list.innerHTML = '';
  state.list.forEach(item => {
    const div = document.createElement('div');
    div.className = 'list-item' + (item.id===state.selectedId?' active':'');
    div.textContent = state.entity==='deals'
      ? `#${item.id} — ${item.title} — ${Number(item.amount).toLocaleString()}`
      : `#${item.id} — ${(item.first_name || '')} ${(item.last_name||'')}`.trim();
    div.onclick = () => selectItem(item.id);
    els.list.appendChild(div);
  });
}

async function selectItem(id){
  state.selectedId = id;
  renderList();
  try{
    const res = await fetch(`./api/${state.entity}.php?id=${id}`, { cache: 'no-store' });
    const item = await res.json();
    renderCard(item);
  }catch(_){
    renderContentPlaceholder();
  }
}

function renderContentPlaceholder(){
  els.content.innerHTML = `<div class="placeholder">Выберите элемент из списка</div>`;
}

function renderCard(item){
  const html = (state.entity==='deals')
    ? `
      <h2>${escapeHTML(item.title)} <span class="idchip">#${item.id}</span></h2>
      <div class="kv"><div>Сумма</div><div>${Number(item.amount).toLocaleString()}</div></div>
      <div class="kv"><div>Контакты</div>
        <div>${(item.contacts||[]).map(c=>`<span class="tag">#${c.id} ${escapeHTML(c.first_name)} ${escapeHTML(c.last_name||'')}</span>`).join('')||'—'}</div>
      </div>
    `
    : `
      <h2>${escapeHTML(item.first_name)} ${escapeHTML(item.last_name||'')} <span class="idchip">#${item.id}</span></h2>
      <div class="kv"><div>Сделки</div>
        <div>${(item.deals||[]).map(d=>`<span class="tag">#${d.id} ${escapeHTML(d.title)}</span>`).join('')||'—'}</div>
      </div>
    `;
  els.content.innerHTML = html;
}

// -----------------------------------------------------------------------------
// Модалка: создание/редактирование
// -----------------------------------------------------------------------------
function openForm(mode){
  const isDeal = state.entity==='deals';
  const title = mode==='create'
    ? `Новая ${isDeal?'сделка':'контакт'}`
    : `Изменить #${state.selectedId}`;

  const fields = isDeal
    ? `
      <div class="form-row"><label>Наименование*</label><input id="f_title" class="input" /></div>
      <div class="form-row"><label>Сумма</label><input id="f_amount" type="number" step="0.01" class="input" /></div>
      <div class="form-row"><label>Контакты (ID, через запятую)</label><input id="f_links" class="input" placeholder="например: 1,2,3"/></div>
    `
    : `
      <div class="form-row"><label>Имя*</label><input id="f_first" class="input" /></div>
      <div class="form-row"><label>Фамилия</label><input id="f_last" class="input" /></div>
      <div class="form-row"><label>Сделки (ID, через запятую)</label><input id="f_links" class="input" placeholder="например: 4,7"/></div>
    `;

  els.modal.innerHTML = `
    <div class="card">
      <h3>${title}</h3>
      ${fields}
      <div class="footer">
        <button class="btn" onclick="closeModal()">Отмена</button>
        <button id="saveBtn" class="btn primary" onclick="submitForm('${mode}')">Сохранить</button>
      </div>
    </div>`;
  els.modal.classList.remove('hidden');

  if(mode==='update' && state.selectedId){
    fetch(`./api/${state.entity}.php?id=${state.selectedId}`)
      .then(r=>r.json()).then(item=>{
        if(isDeal){
          document.getElementById('f_title').value  = item.title;
          document.getElementById('f_amount').value = item.amount;
          document.getElementById('f_links').value  = (item.contacts||[]).map(c=>c.id).join(',');
        } else {
          document.getElementById('f_first').value = item.first_name;
          document.getElementById('f_last').value  = item.last_name || '';
          document.getElementById('f_links').value = (item.deals||[]).map(d=>d.id).join(',');
        }
      });
  }
}

function closeModal(){
  els.modal.classList.add('hidden');
  els.modal.innerHTML = '';
}

// -----------------------------------------------------------------------------
// Сохранение (create/update) + мгновенное обновление UI
// -----------------------------------------------------------------------------
async function submitForm(mode){
  const isDeal = state.entity==='deals';

  const fTitle  = document.getElementById('f_title');
  const fAmount = document.getElementById('f_amount');
  const fFirst  = document.getElementById('f_first');
  const fLast   = document.getElementById('f_last');
  const fLinks  = document.getElementById('f_links');

  const payload = isDeal
    ? { title: fTitle ? fTitle.value.trim() : '', amount: Number((fAmount && fAmount.value) ? fAmount.value : 0), contact_ids: parseIds(fLinks ? fLinks.value : '') }
    : { first_name: fFirst ? fFirst.value.trim() : '', last_name: fLast ? fLast.value.trim() : '', deal_ids: parseIds(fLinks ? fLinks.value : '') };

  if(isDeal && !payload.title){ if(fTitle) fTitle.classList.add('error'); return; }
  if(!isDeal && !payload.first_name){ if(fFirst) fFirst.classList.add('error'); return; }

  const url    = `./api/${state.entity}.php${mode==='update'?`?id=${state.selectedId}`:''}`;
  const method = mode==='create' ? 'POST' : 'PUT';

  const btn = document.getElementById('saveBtn');
  if (btn) btn.disabled = true;

  const res = await fetch(url,{
    method,
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });

  if(!res.ok){
    let msg = 'Ошибка сохранения';
    try {
      const text = await res.text();
      try { const j = JSON.parse(text); msg += j && j.error ? `: ${j.error}` : `: ${text.slice(0,200)}`; }
      catch { msg += `: ${text.slice(0,200)}`; }
    } catch {}
    if (btn) btn.disabled = false;
    alert(msg);
    return;
  }

  let newId = null;
  try { const j = await res.json(); if (j && typeof j.id !== 'undefined') newId = j.id; } catch {}

  closeModal();

  if (mode === 'create') {
    await loadList();
    if (newId) await selectItem(newId);
  } else {
    const keepId = state.selectedId;
    await loadList();
    if (keepId) await selectItem(keepId);
  }
}

// -----------------------------------------------------------------------------
// Удаление
// -----------------------------------------------------------------------------
async function delSelected(){
  if(!state.selectedId) return;
  if(!confirm('Удалить элемент?')) return;

  const url = `./api/${state.entity}.php?id=${state.selectedId}`;
  const res = await fetch(url,{method:'DELETE'});

  if(res.ok){
    state.selectedId = null;
    await loadList();
    renderContentPlaceholder();
  } else {
    let msg = 'Ошибка удаления';
    try { const j = await res.json(); if (j && j.error) msg += `: ${j.error}`; } catch {}
    alert(msg);
  }
}

// -----------------------------------------------------------------------------
// Утилиты
// -----------------------------------------------------------------------------
function parseIds(s){ return (s||'').split(',').map(x=>+x.trim()).filter(Boolean); }
function escapeHTML(s){
  return (s ?? '').replace(/[&<>"']/g, c => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[c]));
}

// -----------------------------------------------------------------------------
// LIVE SYNC через Server-Sent Events (SSE)
// -----------------------------------------------------------------------------
let es = null;
let syncTimer = null;

function startLiveUpdates(){
  // восстановим last_id из localStorage
  let lastId = Number(localStorage.getItem('sync_last_id') || 0);

  try { if (es) es.close(); } catch(_) {}
  es = new EventSource(`./api/sse.php?last_id=${lastId}`);

  const handle = async (e) => {
    if (e && e.lastEventId) localStorage.setItem('sync_last_id', e.lastEventId);

    const evtName = e.type;              // напр., 'deals.update'
    const [evtEntity] = evtName.split('.');

    if (syncTimer) clearTimeout(syncTimer);
    syncTimer = setTimeout(async () => {
      if (evtEntity === state.entity) {
        await loadList();
      }
      if (state.selectedId) {
        await selectItem(state.selectedId);
      }
    }, 200);
  };

  [
    'deals.create','deals.update','deals.delete',
    'contacts.create','contacts.update','contacts.delete'
  ].forEach(evt => es.addEventListener(evt, handle));

  es.addEventListener('error', () => {
    // браузер переподключится сам
  });
}
