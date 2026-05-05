<?php
require_once __DIR__ . '/layout.php';
start_layout('Dashboard');
?>

<div id="alert-box"></div>

<div class="grid-4 mb-4" id="stats-grid">
  <div class="card">
    <div class="stat-value text-gray-400" id="stat-trips">—</div>
    <div class="stat-label">My Trips</div>
  </div>
  <div class="card">
    <div class="stat-value text-gray-400" id="stat-activities">—</div>
    <div class="stat-label">Activities</div>
  </div>
  <div class="card">
    <div class="stat-value text-gray-400" id="stat-expenses">—</div>
    <div class="stat-label">Total Spent</div>
  </div>
  <div class="card">
    <div class="stat-value text-gray-400" id="stat-polls">—</div>
    <div class="stat-label">Open Polls</div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <h3>My Trips</h3>
      <a href="/?page=trips" class="btn btn-secondary btn-sm">View all</a>
    </div>
    <div id="dash-trips">
      <div class="empty-state">
        <div class="icon">🗺</div>Loading…
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3>Upcoming Activities</h3>
      <a href="/?page=itinerary" class="btn btn-secondary btn-sm">View all</a>
    </div>
    <div id="dash-activities">
      <div class="empty-state">
        <div class="icon">📅</div>Loading…
      </div>
    </div>
  </div>
</div>

<script>
  (async () => {
    const tripsRes = await API.get('trips', {
      action: 'list'
    });
    if (!tripsRes.success) return;
    const trips = (tripsRes.data || []).filter(t => t.my_status === 'accepted');

    document.getElementById('stat-trips').textContent = trips.length;

    const statusColor = {
      active: 'badge-green',
      planning: 'badge-yellow',
      completed: 'badge-blue',
      settled: 'badge-red'
    };
    const tripsEl = document.getElementById('dash-trips');
    if (!trips.length) {
      tripsEl.innerHTML = '<div class="empty-state"><div class="icon">🗺</div>No trips yet. <a href="/?page=trips">Create one</a></div>';
    } else {
      tripsEl.innerHTML = trips.slice(0, 5).map(t => `
      <div class="flex-between" style="padding:10px 0; border-bottom:3px solid var(--border)">
        <div>
          <a href="/?page=trips&trip_id=${t.id}"><strong class="text-gray-400">${escHtml(t.title)}</strong></a>
          <div class="text-sm text-gray-600">${escHtml(t.destination)} · ${fmtDate(t.start_date)}</div>
        </div>
        <span class="badge ${statusColor[t.status] || 'badge-gray'}">${escHtml(t.status)}</span>
      </div>`).join('');
    }

    // Load activities for the first active trip
    const activeTrip = trips.find(t => t.status === 'active') || trips[0];
    if (activeTrip) {
      const itRes = await API.get('itinerary', {
        action: 'list',
        trip_id: activeTrip.id
      });
      const acts = (itRes.data || []).filter(a => a.status !== 'cancelled').slice(0, 5);
      document.getElementById('stat-activities').textContent = acts.length;
      const actsEl = document.getElementById('dash-activities');
      if (!acts.length) {
        actsEl.innerHTML = '<div class="empty-state"><div class="icon">📅</div>No activities yet.</div>';
      } else {
        actsEl.innerHTML = acts.map(a => `
        <div style="padding:10px 0; border-bottom:3px solid var(--border)">
          <strong class="text-sm text-gray-400">${escHtml(a.title)}</strong>
          <div class="text-sm text-gray-600">${fmtDateTime(a.datetime)} · ${escHtml(a.location || '—')}</div>
        </div>`).join('');
      }

      // Expenses stat
      const finRes = await API.get('financial', {
        action: 'list',
        trip_id: activeTrip.id
      });
      if (finRes.success) {
        document.getElementById('stat-expenses').textContent =
          (finRes.data.total_spent || 0).toFixed(0) + ' ' + (finRes.data.currency || '');
      }

      // Polls stat
      const pollRes = await API.get('social', {
        action: 'polls',
        trip_id: activeTrip.id
      });
      if (pollRes.success) {
        const open = (pollRes.data || []).filter(p => p.status === 'open').length;
        document.getElementById('stat-polls').textContent = open;
      }
    }
  })();
</script>

<?php end_layout(); ?>