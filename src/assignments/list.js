const assignmentListSection = document.getElementById('assignment-list-section');

function createAssignmentArticle(assignment) {
  const article = document.createElement('article');
  const h2 = document.createElement('h2');
  h2.textContent = assignment.title;
  const pDue = document.createElement('p');
  pDue.textContent = `Due: ${assignment.due_date}`;
  const pDesc = document.createElement('p');
  pDesc.textContent = assignment.description;
  const a = document.createElement('a');
  a.href = `details.html?id=${assignment.id}`;
  a.textContent = 'View Details & Discussion';
  article.appendChild(h2);
  article.appendChild(pDue);
  article.appendChild(pDesc);
  article.appendChild(a);
  return article;
}

async function loadAssignments() {
  const response = await fetch('./api/index.php');
  const result = await response.json();
  assignmentListSection.innerHTML = '';
  (result.data || []).forEach(assignment => {
    assignmentListSection.appendChild(createAssignmentArticle(assignment));
  });
}

loadAssignments();
