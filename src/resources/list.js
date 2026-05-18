const resourceListSection = document.getElementById('resource-list-section');

function createResourceArticle(resource) {
  const article = document.createElement('article');
  const h2 = document.createElement('h2');
  h2.textContent = resource.title;
  const p = document.createElement('p');
  p.textContent = resource.description;
  const a = document.createElement('a');
  a.href = `details.html?id=${resource.id}`;
  a.textContent = 'View Resource & Discussion';
  article.appendChild(h2);
  article.appendChild(p);
  article.appendChild(a);
  return article;
}

async function loadResources() {
  const response = await fetch('./api/index.php');
  const result = await response.json();
  resourceListSection.innerHTML = '';
  (result.data || []).forEach(resource => {
    resourceListSection.appendChild(createResourceArticle(resource));
  });
}

loadResources();
