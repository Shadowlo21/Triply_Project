<?php
require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
require_once __DIR__ . '/layout.php';
start_layout('Itinerary');
?>

<div id="alert-box"></div>

<div class="flex-between mb-4">
  <div class="form-group" style="margin:0;min-width:220px">
    <select id="trip-select" class="form-control" onchange="loadItinerary()">
      <option value="">— Select Trip —</option>
    </select>
  </div>
  <div style="display:flex;gap:8px">
    <button class="btn btn-secondary btn-sm" onclick="checkConflicts()">Check Conflicts</button>
    <button class="btn btn-secondary btn-sm" onclick="openModal('modal-versions')">History</button>
    <button class="btn btn-primary" onclick="openModal('modal-add-activity')">+ Add Activity</button>
  </div>
</div>

<div id="conflicts-bar"></div>
<div id="itinerary-wrap"></div>

<div class="modal-overlay hidden" id="modal-add-activity">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Add Activity</h3><button class="modal-close" onclick="closeModal('modal-add-activity')">×</button>
    </div>
    <div class="modal-body">
      <div id="add-alert"></div>
      <form id="form-add-activity">
        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
        <div class="form-group"><label>Location</label><input type="text" name="location" class="form-control"></div>
        <div class="grid-2">
          <div class="form-group"><label>Date & Time</label><input type="datetime-local" name="datetime" class="form-control" required></div>
          <div class="form-group"><label>Duration (min)</label><input type="number" name="duration_min" class="form-control" value="60" min="5"></div>
        </div>
        <div class="grid-2">
          <div class="form-group"><label>Lat</label><input type="number" name="lat" class="form-control" step="any"></div>
          <div class="form-group"><label>Lng</label><input type="number" name="lng" class="form-control" step="any"></div>
        </div>
        <div class="form-group">
          <label>Transport Mode</label>
          <select name="transport_mode" class="form-control">
            <option value="car">Car</option>
            <option value="bus">Bus</option>
            <option value="train">Train</option>
            <option value="flight">Flight</option>
            <option value="walk">Walk</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-add-act">Add Activity</button>
      </form>
    </div>
  </div>
</div>

<div class="modal-overlay hidden" id="modal-versions">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Itinerary History</h3><button class="modal-close" onclick="closeModal('modal-versions')">×</button>
    </div>
    <div class="modal-body">
      <div id="versions-list">Loading…</div>
    </div>
  </div>
</div>

<div class="modal-overlay hidden" id="modal-comment">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Comments</h3><button class="modal-close" onclick="closeModal('modal-comment')">×</button>
    </div>
    <div class="modal-body">
      <div id="comments-list" class="mb-3"></div>
      <div class="form-group"><textarea id="comment-text" class="form-control" rows="3" placeholder="Add a comment…"></textarea></div>
      <button class="btn btn-primary btn-block" onclick="postComment()">Post Comment</button>
    </div>
  </div>
</div>

<script>
  let currentActivityId = null;

  async function loadTrips() {
    const res = await API.get('trips', {
      action: 'list'
    });
    const sel = document.getElementById('trip-select');
    (res.data || []).filter(t => t.my_status === 'accepted').forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.id;
      opt.textContent = t.title;
      sel.appendChild(opt);
    });
    if (sel.options.length > 1) {
      sel.selectedIndex = 1;
      loadItinerary();
    }
  }

  async function loadItinerary() {
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) return;
    const res = await API.get('itinerary', {
      action: 'list',
      trip_id: tripId
    });
    const wrap = document.getElementById('itinerary-wrap');
    if (!res.success) {
      wrap.innerHTML = `<div class="alert alert-error">${escHtml(res.message)}</div>`;
      return;
    }
    const acts = res.data || [];
    if (!acts.length) {
      wrap.innerHTML = '<div class="card empty-state"><div class="icon">📅</div>No activities yet. Add the first one!</div>';
      return;
    }
    const statusBadge = {
      confirmed: 'badge-green',
      draft: 'badge-yellow',
      cancelled: 'badge-red'
    };
    wrap.innerHTML = `<div class="card"><div class="timeline">${acts.map(a => `
    <div class="timeline-item">
      <div class="timeline-dot ${a.status}"></div>
      <div class="card" style="margin-left:4px;padding:14px">
        <div class="flex-between text-gray-400">
          <div>
            <strong>${escHtml(a.title)}</strong>
            <span class="badge ${statusBadge[a.status] || 'badge-gray'} ml-2">${escHtml(a.status)}</span>
          </div>
          <div style="display:flex;gap:6px">
            <button class="btn btn-secondary btn-sm" onclick="openComments(${a.id})">💬</button>
            <button class="btn btn-secondary btn-sm" onclick="rsvp(${a.id}, 'in')">✅ In</button>
            <button class="btn btn-secondary btn-sm" onclick="rsvp(${a.id}, 'out')">❌ Out</button>
          </div>
        </div>
        <div class="text-sm mt-2 text-gray-500">
          📍 ${escHtml(a.location || '—')} &nbsp;·&nbsp; 🕐 ${fmtDateTime(a.datetime)}
          &nbsp;·&nbsp; ${a.duration_min} min &nbsp;·&nbsp; ${escHtml(a.transport_mode || '')}
        </div>
      </div>
    </div>`).join('')}</div></div>`;
  }

  async function checkConflicts() {
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) return;
    const res = await API.get('itinerary', {
      action: 'conflicts',
      trip_id: tripId
    });
    const bar = document.getElementById('conflicts-bar');
    if (!res.success) return;
    const conflicts = res.data || [];
    if (!conflicts.length) {
      bar.innerHTML = '<div class="alert alert-success">No scheduling conflicts found.</div>';
    } else {
      bar.innerHTML = `<div class="alert alert-error">⚠ ${conflicts.length} conflict(s) detected:<br>` +
        conflicts.map(c => `${escHtml(c.a.title)} overlaps with ${escHtml(c.b.title)}`).join('<br>') + '</div>';
    }
    setTimeout(() => bar.innerHTML = '', 6000);
  }

  document.getElementById('form-add-activity').addEventListener('submit', async e => {
    e.preventDefault();
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) {
      showAlert('#add-alert', 'Select a trip first.');
      return;
    }
    const btn = document.getElementById('btn-add-act');
    setLoading(btn, true);
    const fd = new FormData(e.target);
    const res = await API.post('itinerary', {
      action: 'add',
      trip_id: tripId,
      title: fd.get('title'),
      location: fd.get('location'),
      datetime: fd.get('datetime'),
      duration_min: fd.get('duration_min'),
      lat: fd.get('lat'),
      lng: fd.get('lng'),
      transport_mode: fd.get('transport_mode'),
    });
    setLoading(btn, false);
    if (res.success) {
      closeModal('modal-add-activity');
      e.target.reset();
      loadItinerary();
    } else showAlert('#add-alert', res.message);
  });

  async function loadVersions() {
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) return;
    const res = await API.get('itinerary', {
      action: 'versions',
      trip_id: tripId
    });
    const el = document.getElementById('versions-list');
    if (!res.success || !res.data.length) {
      el.innerHTML = '<p class="text-muted">No history yet.</p>';
      return;
    }
    el.innerHTML = `<div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Note</th><th>By</th><th>When</th><th></th></tr></thead>
    <tbody>${res.data.map(v => `
      <tr>
        <td>${v.id}</td><td>${escHtml(v.note || '')}</td>
        <td>${escHtml(v.changed_by_email || v.changed_by)}</td>
        <td class="text-sm">${fmtDateTime(v.changed_at)}</td>
        <td><button class="btn btn-danger btn-sm" onclick="rollback(${v.id})">Rollback</button></td>
      </tr>`).join('')}
    </tbody></table></div>`;
  }

  async function rollback(versionId) {
    if (!confirm('Rollback to this version? Current state will be saved first.')) return;
    const res = await API.post('itinerary', {
      action: 'rollback',
      version_id: versionId
    });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) {
      closeModal('modal-versions');
      loadItinerary();
    }
  }

  async function rsvp(activityId, status) {
    const res = await API.post('itinerary', {
      action: 'rsvp',
      activity_id: activityId,
      status
    });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
  }

  async function openComments(activityId) {
    currentActivityId = activityId;
    openModal('modal-comment');
    const res = await API.get('itinerary', {
      action: 'comments',
      activity_id: activityId
    });
    const el = document.getElementById('comments-list');
    if (!res.success || !res.data.length) {
      el.innerHTML = '<p class="text-gray-600">No comments yet.</p>';
      return;
    }
    el.innerHTML = res.data.map(c => `
    <div style="padding:8px 0;border-bottom:1px solid var(--border)">
      <strong>${escHtml(c.email)}</strong> <span class="text-sm text-gray-500 ml-1">${fmtDateTime(c.created_at)}</span>
      <p class="ml-2 text-gray-300"> - ${escHtml(c.content)}</p>
    </div>`).join('');
  }

  async function postComment() {
    const content = document.getElementById('comment-text').value.trim();
    if (!content || !currentActivityId) return;
    const res = await API.post('itinerary', {
      action: 'comment',
      activity_id: currentActivityId,
      content
    });
    if (res.success) {
      document.getElementById('comment-text').value = '';
      openComments(currentActivityId);
    }
  }

  document.getElementById('modal-versions').addEventListener('click', e => {
    if (e.target.closest('.modal') && !e.target.classList.contains('modal-overlay')) return;
  });
  // Load versions when modal opens
  document.querySelector('[onclick="openModal(\'modal-versions\')"]').addEventListener('click', loadVersions);

  loadTrips();
</script>

<?php end_layout(); ?>