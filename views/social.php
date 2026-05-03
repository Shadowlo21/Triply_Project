<?php
require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
require_once __DIR__ . '/layout.php';
start_layout('Polls');
?>

<div id="alert-box"></div>

<div class="flex-between mb-4">
  <div class="form-group" style="margin:0;min-width:220px">
    <select id="trip-select" class="form-control" onchange="loadPolls()">
      <option value="">— Select Trip —</option>
    </select>
  </div>
  <button class="btn btn-primary" onclick="openModal('modal-create-poll')">+ Create Poll</button>
</div>

<div id="polls-wrap"></div>

<div id="results-wrap" style="display:none" class="mt-4"></div>

<div class="modal-overlay hidden" id="modal-create-poll">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Create Poll</h3><button class="modal-close" onclick="closeModal('modal-create-poll')">×</button>
    </div>
    <div class="modal-body">
      <div id="poll-alert"></div>
      <form id="form-create-poll">
        <div class="form-group"><label>Question</label><input type="text" name="question" class="form-control" required></div>
        <div class="grid-2">
          <div class="form-group">
            <label>Type</label>
            <select name="type" class="form-control">
              <option value="general">General</option>
              <option value="destination">Destination</option>
              <option value="activity">Activity</option>
              <option value="accommodation">Accommodation</option>
            </select>
          </div>
          <div class="form-group"><label>Deadline (optional)</label><input type="datetime-local" name="deadline" class="form-control"></div>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="is_anonymous" id="chk-anon">
          <label for="chk-anon" style="margin:0">Anonymous voting</label>
        </div>
        <div class="form-group">
          <label>Options <span class="text-sm text-gray-500">(one per line, min 2)</span></label>
          <textarea name="options_text" id="options-text" class="form-control" rows="4" placeholder="Option A&#10;Option B&#10;Option C" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-create-poll">Create Poll</button>
      </form>
    </div>
  </div>
</div>

<script>
  let currentPollId = null;

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
      loadPolls();
    }
  }

  async function loadPolls() {
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) return;
    document.getElementById('results-wrap').style.display = 'none';
    const res = await API.get('social', {
      action: 'polls',
      trip_id: tripId
    });
    const wrap = document.getElementById('polls-wrap');
    if (!res.success) {
      wrap.innerHTML = `<div class="alert alert-error">${escHtml(res.message)}</div>`;
      return;
    }
    const polls = res.data || [];
    if (!polls.length) {
      wrap.innerHTML = '<div class="card empty-state"><div class="icon">🗳</div>No polls yet. Create the first one!</div>';
      return;
    }
    wrap.innerHTML = polls.map(p => `
    <div class="card mb-3">
      <div class="flex-between">
        <div>
          <strong class="text-lg text-white">${escHtml(p.question)}</strong>
          <span class="badge ${p.status === 'open' ? 'badge-green' : 'badge-red'} ml-2">${escHtml(p.status)}</span>
          ${p.is_anonymous ? '<span class="badge badge-gray ml-1">anonymous</span>' : ''}
        </div>
        <div style="display:flex;gap:6px">
          ${p.status === 'open' ? `<button class="btn btn-primary btn-sm" onclick="openVote(${p.id})">Vote</button>` : ''}
          <button class="btn btn-secondary btn-sm" onclick="loadResults(${p.id})">Results</button>
          <button class="btn btn-danger btn-sm" onclick="closePoll(${p.id}, ${p.status})">Close</button>
        </div>
      </div>
      <div class="text-sm text-gray-400 mt-2">
        ${p.option_count} option(s)
        ${p.deadline ? ' · Deadline: ' + fmtDateTime(p.deadline) : ''}
      </div>
    </div>`).join('');
  }
  let currentResultsPollId = null;
  async function openVote(pollId) {
    currentPollId = pollId;
    // Fetch results to show options
    const res = await API.get('social', {
      action: 'results',
      poll_id: pollId
    });
    if (!res.success) {
      showAlert('#alert-box', res.message);
      return;
    }
    document.querySelectorAll('[id^="vote-panel-"]').forEach(el => el.remove());
    currentPollId = pollId;
    const {
      results
    } = res.data;
    const optionsHtml = results.map(o => `
    <div class="poll-option text-gray-400" onclick="castVote(${o.option_id}, this)">
      <div style="flex:1"><strong>${escHtml(o.option_text)}</strong></div>
    </div>`).join('');
    const votePanel = document.createElement('div');
    votePanel.id = 'vote-panel-' + pollId;
    votePanel.className = 'card mt-3';
    votePanel.innerHTML = `<div class="card-header"><h3>Cast Vote</h3><button class="btn btn-secondary btn-sm" onclick="this.closest('[id]').remove()">Cancel</button></div><div>${optionsHtml}</div>`;
    document.getElementById('polls-wrap').prepend(votePanel);
  }

  async function castVote(optionId, el) {
    el.closest('[id]').querySelectorAll('.poll-option').forEach(o => o.classList.remove('voted'));
    el.classList.add('voted');
    const res = await API.post('social', {
      action: 'vote',
      poll_id: currentPollId,
      option_id: optionId
    });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) {
      el.closest('[id]').remove();
      loadPolls();
    }
  }

  async function loadResults(pollId) {
    const res = await API.get('social', {
      action: 'results',
      poll_id: pollId
    });
    const wrap = document.getElementById('results-wrap');
    if (!res.success) {
      wrap.innerHTML = `<div class="alert alert-error">${escHtml(res.message)}</div>`;
      return;
    }
    if (currentResultsPollId === pollId && wrap.style.display === 'block') {
      wrap.style.display = 'none';
      currentResultsPollId = null;
      return;
    }
    wrap.style.display = 'block';
    currentResultsPollId = pollId;
    const {
      results,
      winner_option_id,
      status
    } = res.data;
    const totalVotes = results.reduce((s, r) => s + (r.vote_count || 0), 0);
    wrap.innerHTML = `<div class="card">
    <div class="card-header">
      <h3>Results <span class="badge ${status === 'open' ? 'badge-green' : 'badge-gray'}">${escHtml(status)}</span></h3>
    </div>
    ${results.map(r => {
      const pct = totalVotes ? Math.round((r.vote_count / totalVotes) * 100) : 0;
      const isWinner = r.option_id === winner_option_id;
      return `<div class="mb-3">
        <div class="flex-between mb-1">
          <span class="text-gray-400 text-sm">${isWinner ? '🏆 ' : ''}${escHtml(r.option_text)}</span>
          <span class="text-sm text-gray-500">${r.vote_count} vote(s) · ${pct}%</span>
        </div>
        <div class="poll-bar"><div class="poll-bar-fill" style="width:${pct}%"></div></div>
        ${r.voters && r.voters.length ? `<div class="text-sm text-gray-500 mt-1">Voters: ${r.voters.map(v => escHtml(v)).join(', ')}</div>` : ''}
      </div>`;
    }).join('')}
  </div>`;
    wrap.scrollIntoView({
      behavior: 'smooth'
    });
  }

  async function closePoll(pollId, status) {
    if (status !== 'open') {
      showAlert('#alert-box', 'This poll is already closed.');
      return;
    }
    if (!confirm('Close this poll? Voting will end.')) return;
    const res = await API.post('social', {
      action: 'close_poll',
      poll_id: pollId
    });
    showAlert('#alert-box', res.message, res.success ? 'success' : 'error');
    if (res.success) loadPolls();
  }

  document.getElementById('form-create-poll').addEventListener('submit', async e => {
    e.preventDefault();
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) {
      showAlert('#poll-alert', 'Select a trip first.');
      return;
    }
    const btn = document.getElementById('btn-create-poll');
    setLoading(btn, true);
    const fd = new FormData(e.target);
    const optionsText = fd.get('options_text') || '';
    const options = optionsText.split('\n').map(s => s.trim()).filter(Boolean);
    if (options.length < 2) {
      showAlert('#poll-alert', 'At least 2 options required.');
      setLoading(btn, false);
      return;
    }

    const formData = new FormData();
    formData.append('action', 'create_poll');
    formData.append('trip_id', tripId);
    formData.append('question', fd.get('question'));
    formData.append('type', fd.get('type'));
    formData.append('deadline', fd.get('deadline') || '');
    formData.append('is_anonymous', fd.get('is_anonymous') ? '1' : '');
    options.forEach(o => formData.append('options[]', o));

    const res = await API.upload('social', formData);
    setLoading(btn, false);
    if (res.success) {
      closeModal('modal-create-poll');
      e.target.reset();
      loadPolls();
    } else showAlert('#poll-alert', res.message);
  });

  loadTrips();
</script>

<?php end_layout(); ?>