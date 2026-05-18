let weeks = [];

const weekForm  = document.getElementById('week-form');
const weeksTbody = document.getElementById('weeks-tbody');

function createWeekRow(week) {
  const tr = document.createElement('tr');

  const tdTitle = document.createElement('td');
  tdTitle.textContent = week.title;

  const tdDate = document.createElement('td');
  tdDate.textContent = week.start_date;

  const tdDesc = document.createElement('td');
  tdDesc.textContent = week.description;

  const tdActions = document.createElement('td');

  const editBtn = document.createElement('button');
  editBtn.className    = 'edit-btn';
  editBtn.dataset.id   = week.id;
  editBtn.textContent  = 'Edit';

  const deleteBtn = document.createElement('button');
  deleteBtn.className   = 'delete-btn';
  deleteBtn.dataset.id  = week.id;
  deleteBtn.textContent = 'Delete';

  tdActions.appendChild(editBtn);
  tdActions.appendChild(deleteBtn);

  tr.appendChild(tdTitle);
  tr.appendChild(tdDate);
  tr.appendChild(tdDesc);
  tr.appendChild(tdActions);

  return tr;
}

function renderTable() {
  weeksTbody.innerHTML = '';
  weeks.forEach(week => {
    weeksTbody.appendChild(createWeekRow(week));
  });
}

async function handleAddWeek(event) {
  event.preventDefault();

  const title       = document.getElementById('week-title').value;
  const start_date  = document.getElementById('week-start-date').value;
  const description = document.getElementById('week-description').value;
  const links       = document.getElementById('week-links').value
    .split('\n')
    .map(l => l.trim())
    .filter(l => l !== '');

  const addBtn = document.getElementById('add-week');

  if (addBtn.dataset.editId) {
    await handleUpdateWeek(parseInt(addBtn.dataset.editId), { title, start_date, description, links });
  } else {
    const response = await fetch('./api/index.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ title, start_date, description, links })
    });
    const result = await response.json();

    if (result.success) {
      weeks.push({ id: result.id, title, start_date, description, links });
      renderTable();
      weekForm.reset();
    }
  }
}

async function handleUpdateWeek(id, fields) {
  const response = await fetch('./api/index.php', {
    method:  'PUT',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id, ...fields })
  });
  const result = await response.json();

  if (result.success) {
    weeks = weeks.map(w => w.id === id ? { ...w, ...fields } : w);
    renderTable();
    weekForm.reset();

    const addBtn = document.getElementById('add-week');
    addBtn.textContent = 'Add Week';
    delete addBtn.dataset.editId;
  }
}

async function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains('delete-btn')) {
    const id = parseInt(target.dataset.id);
    const response = await fetch('./api/index.php?id=' + id, { method: 'DELETE' });
    const result   = await response.json();

    if (result.success) {
      weeks = weeks.filter(w => w.id !== id);
      renderTable();
    }
  }

  if (target.classList.contains('edit-btn')) {
    const id   = parseInt(target.dataset.id);
    const week = weeks.find(w => w.id === id);

    document.getElementById('week-title').value       = week.title;
    document.getElementById('week-start-date').value  = week.start_date;
    document.getElementById('week-description').value = week.description;
    document.getElementById('week-links').value       = (week.links || []).join('\n');

    const addBtn = document.getElementById('add-week');
    addBtn.textContent     = 'Update Week';
    addBtn.dataset.editId  = id;
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result   = await response.json();

  weeks = result.data;
  renderTable();

  weekForm.addEventListener('submit', handleAddWeek);
  weeksTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();
