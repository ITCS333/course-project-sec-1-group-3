const weekListSection = document.getElementById('week-list-section');

function createWeekArticle(week) {
  const article = document.createElement('article');

  const h2 = document.createElement('h2');
  h2.textContent = week.title;

  const startDate = document.createElement('p');
  startDate.textContent = 'Starts on: ' + week.start_date;

  const desc = document.createElement('p');
  desc.textContent = week.description;

  const link = document.createElement('a');
  link.href = 'details.html?id=' + week.id;
  link.textContent = 'View Details & Discussion';

  article.appendChild(h2);
  article.appendChild(startDate);
  article.appendChild(desc);
  article.appendChild(link);

  return article;
}

async function loadWeeks() {
  const response = await fetch('./api/index.php');
  const result   = await response.json();

  weekListSection.innerHTML = '';

  result.data.forEach(week => {
    weekListSection.appendChild(createWeekArticle(week));
  });
}

loadWeeks();
