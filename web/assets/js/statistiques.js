// Données factices par QCM — à remplacer par un vrai appel API (GET /api/qcms/{id}/statistiques.php)
const statsParQcm = {
  '1': {
    titre: 'Examen Final L3',
    copies: 45,
    moyenne: 12.8,
    reussite: 30,
    echec: 15,
    anomalies: 1,
    distribution: [2, 6, 20, 12, 5],
    questionsRatees: [
      { numero: 7, taux: 64 },
      { numero: 5, taux: 47 },
      { numero: 12, taux: 39 },
    ]
  },
  '2': {
    titre: 'INFO301',
    copies: 31,
    moyenne: 10.1,
    reussite: 16,
    echec: 15,
    anomalies: 1,
    distribution: [3, 9, 12, 5, 2],
    questionsRatees: [
      { numero: 12, taux: 78 },
      { numero: 19, taux: 51 },
      { numero: 4, taux: 33 },
    ]
  },
  '3': {
    titre: 'Contrôle continu L1',
    copies: 60,
    moyenne: 13.9,
    reussite: 49,
    echec: 11,
    anomalies: 1,
    distribution: [1, 5, 20, 25, 9],
    questionsRatees: [
      { numero: 3, taux: 58 },
      { numero: 8, taux: 29 },
      { numero: 15, taux: 22 },
    ]
  },
  '5': {
    titre: 'Examen Rattrapage L2',
    copies: 22,
    moyenne: 11.6,
    reussite: 14,
    echec: 8,
    anomalies: 1,
    distribution: [1, 4, 9, 6, 2],
    questionsRatees: [
      { numero: 9, taux: 45 },
      { numero: 2, taux: 36 },
    ]
  }
};

let chartDistribution, chartReussite;

document.addEventListener('DOMContentLoaded', () => {

  chartDistribution = new Chart(document.getElementById('chartDistribution'), {
    type: 'bar',
    data: {
      labels: ['0-4', '5-9', '10-13', '14-16', '17-20'],
      datasets: [{
        label: 'Nombre d\'étudiants',
        data: [],
        backgroundColor: '#6C5DD3',
        borderRadius: 6,
        maxBarThickness: 40
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  chartReussite = new Chart(document.getElementById('chartReussite'), {
    type: 'doughnut',
    data: {
      labels: ['Réussite (≥10)', 'Échec (<10)'],
      datasets: [{
        data: [],
        backgroundColor: ['#2ED47A', '#FF754C']
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
    }
  });

  const select = document.getElementById('qcm-select');
  select.addEventListener('change', () => afficherStats(select.value));

  // Affichage initial avec le premier QCM de la liste
  afficherStats(select.value);
});

function afficherStats(qcmId) {
  const data = statsParQcm[qcmId];
  if (!data) return;

  document.getElementById('page-sub-text').textContent = `Session : ${data.titre}`;
  document.getElementById('stat-copies').textContent = data.copies;
  document.getElementById('stat-moyenne').textContent = data.moyenne + '/20';
  document.getElementById('stat-reussite').textContent = data.reussite;
  document.getElementById('stat-echec').textContent = data.echec;
  document.getElementById('stat-anomalies').textContent = data.anomalies;

  chartDistribution.data.datasets[0].data = data.distribution;
  chartDistribution.update();

  chartReussite.data.datasets[0].data = [data.reussite, data.echec];
  chartReussite.update();

  const tbody = document.getElementById('questions-ratees-body');
  tbody.innerHTML = data.questionsRatees.map(q => `
    <tr>
      <td><strong>Q${q.numero}</strong></td>
      <td>
        <div class="taux-bar-wrap">
          <div class="taux-bar" style="width:${q.taux}%"></div>
          <span class="taux-label">${q.taux}%</span>
        </div>
      </td>
    </tr>
  `).join('');
}