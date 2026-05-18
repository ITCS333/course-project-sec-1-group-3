let currentAssignmentId = null;
let currentComments = [];

const assignmentTitleEl = document.getElementById('assignment-title');
const assignmentDueDateEl = document.getElementById('assignment-due-date');
const assignmentDescriptionEl = document.getElementById('assignment-description');
const assignmentFilesListEl = document.getElementById('assignment-files-list');
const commentListEl = document.getElementById('comment-list');
const commentFormEl = document.getElementById('comment-form');
const newCommentInputEl = document.getElementById('new-comment');

function getAssignmentIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

function renderAssignmentDetails(assignment) {
  assignmentTitleEl.textContent = assignment.title;
  assignmentDueDateEl.textContent = `Due: ${assignment.due_date}`;
  assignmentDescriptionEl.textContent = assignment.description;
  assignmentFilesListEl.innerHTML = '';
  (assignment.files || []).forEach(url => {
    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = url;
    a.textContent = url;
    li.appendChild(a);
    assignmentFilesListEl.appendChild(li);
  });
}

function createCommentArticle(comment) {
  const article = document.createElement('article');
  const p = document.createElement('p');
  p.textContent = comment.text;
  const footer = document.createElement('footer');
  footer.textContent = `Posted by: ${comment.author}`;
  article.appendChild(p);
  article.appendChild(footer);
  return article;
}

function renderComments() {
  commentListEl.innerHTML = '';
  currentComments.forEach(comment => {
    commentListEl.appendChild(createCommentArticle(comment));
  });
}

async function handleAddComment(event) {
  event.preventDefault();
  const commentText = newCommentInputEl.value.trim();
  if (!commentText) return;
  const response = await fetch('./api/index.php?action=comment', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      assignment_id: currentAssignmentId,
      author: 'Student',
      text: commentText,
    }),
  });
  const result = await response.json();
  if (result.success) {
    currentComments.push(result.data);
    renderComments();
    newCommentInputEl.value = '';
  }
}

async function initializePage() {
  currentAssignmentId = getAssignmentIdFromURL();
  if (!currentAssignmentId) {
    assignmentTitleEl.textContent = 'Assignment not found.';
    return;
  }
  const [assignResponse, commentsResponse] = await Promise.all([
    fetch(`./api/index.php?id=${currentAssignmentId}`),
    fetch(`./api/index.php?action=comments&assignment_id=${currentAssignmentId}`),
  ]);
  const assignResult = await assignResponse.json();
  const commentsResult = await commentsResponse.json();
  currentComments = commentsResult.data || [];
  if (assignResult.success && assignResult.data) {
    renderAssignmentDetails(assignResult.data);
    renderComments();
    commentFormEl.addEventListener('submit', handleAddComment);
  } else {
    assignmentTitleEl.textContent = 'Assignment not found.';
  }
}

initializePage();
