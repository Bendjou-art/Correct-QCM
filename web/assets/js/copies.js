const searchInput = document.getElementById('search-input');
const filterTabs = document.querySelectorAll('.filter-tab');
const rows = document.querySelectorAll('.copie-row');
const noResults = document.getElementById('no-results');

let activeFilter = 'tous';

filterTabs.forEach(tab => {
  tab.addEventListener('click', () => {
    filterTabs.forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    activeFilter = tab.dataset.filter;
    applyFilters();
  });
});

searchInput.addEventListener('input', applyFilters);

function applyFilters() {
  const query = searchInput.value.trim().toLowerCase();
  let visibleCount = 0;

  rows.forEach(row => {
    let matchesFilter = true;
    if (activeFilter === 'anomalie') {
      matchesFilter = row.dataset.anomalie === '1';
    } else if (activeFilter !== 'tous') {
      matchesFilter = row.dataset.statut === activeFilter;
    }

    const matchesSearch = row.dataset.search.includes(query);
    const visible = matchesFilter && matchesSearch;

    row.style.display = visible ? '' : 'none';
    if (visible) visibleCount++;
  });

  noResults.style.display = visibleCount === 0 ? 'block' : 'none';
}