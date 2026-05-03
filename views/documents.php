<?php
require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
require_once __DIR__ . '/layout.php';
start_layout('Documents');
?>

<div id="alert-box"></div>

<div class="flex-between mb-4">
  <div class="form-group" style="margin:0;min-width:220px">
    <select id="trip-select" class="form-control" onchange="loadDocuments()">
      <option value="">— Select Trip —</option>
    </select>
  </div>
  <div style="display:flex;gap:8px">
    <button class="btn btn-secondary btn-sm text-base" style="padding: 0.5rem 1rem; margin-right: 0.5rem;" onclick="openModal('modal-visa')">Visa Check</button>
    <button class="btn btn-primary" style="margin-left: 0;" onclick=" openModal('modal-upload')">+ Upload Doc</button>
  </div>
</div>

<div id="docs-wrap"></div>

<div class="modal-overlay hidden" id="modal-upload">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Upload Document</h3><button class="modal-close" onclick="closeModal('modal-upload')">×</button>
    </div>
    <div class="modal-body">
      <div id="upload-alert"></div>
      <form id="form-upload">
        <div class="form-group">
          <label>Document Type</label>
          <select name="type" class="form-control text-base">
            <option value="passport">Passport</option>
            <option value="ticket">Ticket</option>
            <option value="visa">Visa</option>
            <option value="insurance">Insurance</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Visibility</label>
          <select name="visibility" class="form-control text-base">
            <option value="private">Private (only me + leader)</option>
            <option value="group">Group (all members)</option>
          </select>
        </div>
        <div class="form-group"><label>File (PDF, Image, or DOCX, max 10 MB)</label><input type="file" name="file" style="padding: 0.5rem 1rem;" class="form-control text-base" accept=".pdf,.jpg,.jpeg,.png,.docx" required></div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-upload">Upload</button>
      </form>
    </div>
  </div>
</div>

<div class="modal-overlay hidden" id="modal-visa">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Visa Requirement Check</h3><button class="modal-close" onclick="closeModal('modal-visa')">×</button>
    </div>
    <div class="modal-body">
      <div id="visa-alert"></div>
      <div class="grid-2">
        <div class="form-group"><label>Nationality (2-letter)</label><input type="text" id="visa-nat" class="form-control" maxlength="2" placeholder="EG"></div>
        <div class="form-group"><label>Destination (2-letter)</label><input type="text" id="visa-dest" class="form-control" maxlength="2" placeholder="US"></div>
      </div>
      <button class="btn btn-primary btn-block" onclick="checkVisa()">Check</button>
      <div id="visa-result" class="mt-3"></div>
    </div>
  </div>
</div>

<script>
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
      loadDocuments();
    }
  }

  async function loadDocuments() {
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) return;
    const res = await API.get('documents', {
      action: 'list',
      trip_id: tripId
    });
    const wrap = document.getElementById('docs-wrap');
    if (!res.success) {
      wrap.innerHTML = `<div class="alert alert-error">${escHtml(res.message)}</div>`;
      return;
    }
    const docs = res.data || [];
    if (!docs.length) {
      wrap.innerHTML = '<div class="card empty-state"><div class="icon">📁</div>No documents yet. Upload one!</div>';
      return;
    }
    const typeIcons = {
      passport: '🛂',
      ticket: '🎫',
      visa: '📋',
      insurance: '🛡',
      other: '📄'
    };
    wrap.innerHTML = '<div class="card"><div class="table-wrap"><table>' +
      '<thead><tr><th>File</th><th>Type</th><th>Visibility</th><th>Actions</th></tr></thead>' +
      '<tbody>' + docs.map(d => {
        const meta = d.metadata || {};
        const icon = typeIcons[d.type] || '📄';
        return '<tr>' +
          '<td class="text-gray-400">' + icon + ' ' + escHtml(meta.original_name || 'Document #' + d.id) + '</td>' +
          '<td><span class="badge badge-blue">' + escHtml(d.type) + '</span></td>' +
          '<td><span class="badge ' + (d.visibility === 'private' ? 'badge-gray' : 'badge-green') + '">' + escHtml(d.visibility) + '</span></td>' +
          '<td>' +
          '<a href="/api/documents.php?action=download&doc_id=' + d.id + '" class="btn btn-secondary btn-sm" target="_blank" style="margin-right: 1rem;">⬇ Download</a>' +
          '<button class="btn btn-danger btn-sm" onclick="deleteDoc(' + d.id + ')">Delete</button>' +
          '</td>' +
          '</tr>';
      }).join('') + '</tbody></table></div></div>';
  }

  document.getElementById('form-upload').addEventListener('submit', async e => {
    e.preventDefault();
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) {
      showAlert('#upload-alert', 'Select a trip first.');
      return;
    }
    const btn = document.getElementById('btn-upload');
    setLoading(btn, true);
    const fd = new FormData(e.target);
    fd.append('action', 'upload');
    fd.append('trip_id', tripId);
    const res = await API.upload('documents', fd);
    setLoading(btn, false);
    if (res.success) {
      closeModal('modal-upload');
      e.target.reset();
      loadDocuments();
    } else showAlert('#upload-alert', res.message);
  });

  async function deleteDoc(docId) {
    if (!confirm('Delete this document?')) return;
    const res = await API.post('documents', {
      action: 'delete',
      doc_id: docId
    });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) loadDocuments();
  }

  async function checkVisa() {
    const nat = document.getElementById('visa-nat').value.trim().toUpperCase();
    const dest = document.getElementById('visa-dest').value.trim().toUpperCase();
    if (!nat || !dest) {
      showAlert('#visa-alert', 'Both fields required.');
      return;
    }
    const res = await API.get('documents', {
      action: 'visa_check',
      nationality: nat,
      destination: dest
    });
    const resultEl = document.getElementById('visa-result');
    if (res.success) {
      const d = res.data;
      resultEl.innerHTML = '';
      resultEl.appendChild(buildAlert(
        d.visa_required ? 'alert-error' : 'alert-success',
        d.visa_required ? '🔴 Visa Required' : '🟢 No Visa Required',
        d.note
      ));
    } else {
      resultEl.innerHTML = '';
      resultEl.appendChild(buildAlert('alert-error', '❌ Error', res.message));
    }
  }

  loadTrips();
</script>

<?php end_layout(); ?>