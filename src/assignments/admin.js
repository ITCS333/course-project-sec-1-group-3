let assignments = [];

const assignmentFormEl = document.getElementById('assignment-form');
const assignmentsTbodyEl = document.getElementById('assignments-tbody');

function createAssignmentRow(assignment) {
  const tr = document.createElement('tr');
  const tdTitle = document.createElement('td');
  tdTitle.textContent = assignment.title;
  const tdDue = document.createElement('td');
  tdDue.textContent = assignment.due_date;
  const tdDesc = document.createElement('td');
  tdDesc.textContent = assignment.description;
  const tdActions = document.createElement('td');
  const editBtn = document.createElement('button');
  editBtn.className = 'edit-btn';
  editBtn.dataset.id = String(assignment.id);
  editBtn.textContent = 'Edit';
  const deleteBtn = document.createElement('button');
  deleteBtn.className = 'delete-btn';
  deleteBtn.dataset.id = String(assignment.id);
  deleteBtn.textContent = 'Delete';
  tdActions.appendChild(editBtn);
  tdActions.appendChild(deleteBtn);
  tr.appendChild(tdTitle);
  tr.appendChild(tdDue);
  tr.appendChild(tdDesc);
  tr.appendChild(tdActions);
  return tr;
}

function renderTable() {
  assignmentsTbodyEl.innerHTML = '';
  assignments.forEach(assignment => {
    assignmentsTbodyEl.appendChild(createAssignmentRow(assignment));
  });
}

async function handleAddAssignment(event) {
  event.preventDefault();
  const title = document.getElementById('assignment-title').value;
  const due_date = document.getElementById('assignment-due-date').value;
  const description = document.getElementById('assignment-description').value;
  const filesText = document.getElementById('assignment-files').value;
  const files = filesText.split('\n').map(s => s.trim()).filter(Boolean);
  const btn = document.getElementById('add-assignment');

  if (btn.dataset.editId) {
    await handleUpdateAssignment(parseInt(btn.dataset.editId), { title, due_date, description, files });
  } else {
    const response = await fetch('./api/index.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, due_date, description, files }),
    });
    const result = await response.json();
    if (result.success) {
      assignments.push({ id: result.id, title, due_date, description, files });
      renderTable();
      assignmentFormEl.reset();
    }
  }
}

async function handleUpdateAssignment(id, fields) {
  const response = await fetch('./api/index.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, ...fields }),
  });
  const result = await response.json();
  if (result.success) {
    const idx = assignments.findIndex(a => a.id === id);
    if (idx !== -1) assignments[idx] = { id, ...fields };
    renderTable();
    assignmentFormEl.reset();
    const btn = document.getElementById('add-assignment');
    btn.textContent = 'Add Assignment';
    delete btn.dataset.editId;
  }
}

async function handleTableClick(event) {
  const target = event.target;
  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;
    const response = await fetch(`./api/index.php?id=${id}`, { method: 'DELETE' });
    const result = await response.json();
    if (result.success) {
      assignments = assignments.filter(a => String(a.id) !== String(id));
      renderTable();
    }
  } else if (target.classList.contains('edit-btn')) {
    const id = target.dataset.id;
    const assignment = assignments.find(a => String(a.id) === String(id));
    if (assignment) {
      document.getElementById('assignment-title').value = assignment.title;
      document.getElementById('assignment-due-date').value = assignment.due_date;
      document.getElementById('assignment-description').value = assignment.description;
      document.getElementById('assignment-files').value = (assignment.files || []).join('\n');
      const btn = document.getElementById('add-assignment');
      btn.textContent = 'Update Assignment';
      btn.dataset.editId = id;
    }
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result = await response.json();
  assignments = result.data || [];
  renderTable();
  assignmentFormEl.addEventListener('submit', handleAddAssignment);
  assignmentsTbodyEl.addEventListener('click', handleTableClick);
}

loadAndInitialize();
