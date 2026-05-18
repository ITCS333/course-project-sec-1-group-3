let currentResourceId = null;
let currentComments = [];

const resourceTitleEl = document.getElementById('resource-title');
const resourceDescriptionEl = document.getElementById('resource-description');
const resourceLinkEl = document.getElementById('resource-link');
const commentListEl = document.getElementById('comment-list');
const commentFormEl = document.getElementById('comment-form');
const newCommentInputEl = document.getElementById('new-comment');

function getResourceIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

function renderResourceDetails(resource) {
  resourceTitleEl.textContent = resource.title;
  resourceDescriptionEl.textContent = resource.description;
  resourceLinkEl.setAttribute('href', resource.link);
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
      resource_id: currentResourceId,
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
  currentResourceId = getResourceIdFromURL();
  if (!currentResourceId) {
    resourceTitleEl.textContent = 'Resource not found.';
    return;
  }
  const [resResponse, commentsResponse] = await Promise.all([
    fetch(`./api/index.php?id=${currentResourceId}`),
    fetch(`./api/index.php?resource_id=${currentResourceId}&action=comments`),
  ]);
  const resResult = await resResponse.json();
  const commentsResult = await commentsResponse.json();
  currentComments = commentsResult.data || [];
  if (resResult.success && resResult.data) {
    renderResourceDetails(resResult.data);
    renderComments();
    commentFormEl.addEventListener('submit', handleAddComment);
  } else {
    resourceTitleEl.textContent = 'Resource not found.';
  }
}

initializePage();
