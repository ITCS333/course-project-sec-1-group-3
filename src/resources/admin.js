let resources = [];

const resourceFormEl = document.getElementById('resource-form');
const resourcesTbodyEl = document.getElementById('resources-tbody');

function createResourceRow(resource) {
  const tr = document.createElement('tr');
  const tdTitle = document.createElement('td');
  tdTitle.textContent = resource.title;
  const tdDesc = document.createElement('td');
  tdDesc.textContent = resource.description;
  const tdLink = document.createElement('td');
  tdLink.textContent = resource.link;
  const tdActions = document.createElement('td');
  const editBtn = document.createElement('button');
  editBtn.className = 'edit-btn';
  editBtn.dataset.id = String(resource.id);
  editBtn.textContent = 'Edit';
  const deleteBtn = document.createElement('button');
  deleteBtn.className = 'delete-btn';
  deleteBtn.dataset.id = String(resource.id);
  deleteBtn.textContent = 'Delete';
  tdActions.appendChild(editBtn);
  tdActions.appendChild(deleteBtn);
  tr.appendChild(tdTitle);
  tr.appendChild(tdDesc);
  tr.appendChild(tdLink);
  tr.appendChild(tdActions);
  return tr;
}

function renderTable(arr) {
  if (arr === undefined) arr = resources;
  resourcesTbodyEl.innerHTML = '';
  arr.forEach(resource => {
    resourcesTbodyEl.appendChild(createResourceRow(resource));
  });
}

function handleAddResource(event) {
  event.preventDefault();
  const title = document.getElementById('resource-title').value;
  const description = document.getElementById('resource-description').value;
  const link = document.getElementById('resource-link').value;
  const btn = document.getElementById('add-resource');

  if (btn.dataset.editId) {
    const id = parseInt(btn.dataset.editId);
    fetch('./api/index.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, title, description, link }),
    })
      .then(r => r.json())
      .then(result => {
        if (result.success) {
          const idx = resources.findIndex(r => r.id === id);
          if (idx !== -1) resources[idx] = { id, title, description, link };
          renderTable();
          resourceFormEl.reset();
          btn.textContent = 'Add Resource';
          delete btn.dataset.editId;
        }
      });
  } else {
    fetch('./api/index.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, description, link }),
    })
      .then(r => r.json())
      .then(result => {
        if (result.success) {
          resources.push({ id: result.id, title, description, link });
          renderTable();
          resourceFormEl.reset();
        }
      });
  }
}

function handleTableClick(event) {
  const target = event.target;
  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;
    fetch(`./api/index.php?id=${id}`, { method: 'DELETE' })
      .then(r => r.json())
      .then(result => {
        if (result.success) {
          resources = resources.filter(r => String(r.id) !== String(id));
          renderTable();
        }
      });
  } else if (target.classList.contains('edit-btn')) {
    const id = target.dataset.id;
    const resource = resources.find(r => String(r.id) === String(id));
    if (resource) {
      document.getElementById('resource-title').value = resource.title;
      document.getElementById('resource-description').value = resource.description;
      document.getElementById('resource-link').value = resource.link;
      const btn = document.getElementById('add-resource');
      btn.textContent = 'Update Resource';
      btn.dataset.editId = id;
    }
  }
}

async function loadAndInitialize() {
  if (loadAndInitialize._listenersAttached) return;
  loadAndInitialize._listenersAttached = true;
  const response = await fetch('./api/index.php');
  const result = await response.json();
  resources = result.data || [];
  renderTable();
  resourceFormEl.addEventListener('submit', handleAddResource);
  resourcesTbodyEl.addEventListener('click', handleTableClick);
}
loadAndInitialize._listenersAttached = false;

loadAndInitialize();
