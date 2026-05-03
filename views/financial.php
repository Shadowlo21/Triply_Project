<?php
require_once __DIR__ . '/../config/bootstrap.php';
$currentUser = Auth::current();
if (!$currentUser) {
  header('Location: /?page=login');
  exit;
}
require_once __DIR__ . '/layout.php';
start_layout('Financial');
?>

<div id="alert-box"></div>

<div class="flex-between mb-4">
  <div class="form-group" style="margin:0;min-width:220px">
    <select id="trip-select" class="form-control" onchange="loadFinancial()">
      <option value="">— Select Trip —</option>
    </select>
  </div>
  <div style="display:flex;gap:8px">
    <button class="btn btn-secondary btn-sm" onclick="loadSettlement()">Settlement</button>
    <button class="btn btn-secondary btn-sm" id="btn-report" onclick="generateReport()" style="display:none">📊 Report</button>
    <button class="btn btn-primary" onclick="openModal('modal-add-expense')">+ Add Expense</button>
  </div>
</div>

<div class="grid-3 mb-4" id="fin-stats" style="display:none">
  <div class="card">
    <div class="stat-value text-gray-400" id="stat-total">0</div>
    <div class="stat-label">Total Spent</div>
  </div>
  <div class="card">
    <div class="stat-value text-gray-400" id="stat-budget">—</div>
    <div class="stat-label">Budget Limit</div>
  </div>
  <div class="card">
    <div class="stat-value text-gray-400" id="stat-pct">0%</div>
    <div class="stat-label">Budget Used</div>
    <div style="height:6px;background:var(--border);border-radius:3px;margin-top:8px">
      <div id="budget-bar" style="height:100%;background:var(--primary);border-radius:3px;width:0%;transition:width .4s"></div>
    </div>
  </div>
</div>

<div id="expenses-wrap"></div>

<div id="settlement-wrap" style="display:none" class="mt-4"></div>

<div class="modal-overlay hidden" id="modal-add-expense">
  <div class="triply-modal">
    <div class="modal-header">
      <h3>Add Expense</h3><button class="modal-close" onclick="closeModal('modal-add-expense')">×</button>
    </div>
    <div class="modal-body">
      <div id="exp-alert"></div>
      <form id="form-add-expense">
        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
        <div class="grid-2">
          <div class="form-group"><label>Amount</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" required></div>
          <div class="form-group">
            <label>Currency</label>
            <select name="currency" class="form-control">
              <option value="EGP">EGP</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
              <option value="GBP">GBP</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Type</label>
          <select name="type" class="form-control">
            <option value="general">General</option>
            <option value="food">Food</option>
            <option value="transport">Transport</option>
            <option value="accommodation">Accommodation</option>
            <option value="activity">Activity</option>
          </select>
        </div>
        <div class="form-group">
          <label>Split</label>
          <select name="split_type" id="split-type" class="form-control" onchange="toggleCustomSplit()">
            <option value="equal">Equal (all members)</option>
            <option value="custom">Custom amounts</option>
          </select>
        </div>
        <div id="custom-split-wrap" class="form-group" style="display:none">
          <label class="text-sm">Custom amount per member</label>
          <div id="custom-split-items" class="text-sm" style="display:flex;flex-direction:column;gap:6px"></div>
          <div class="text-sm text-gray-400 mt-1" id="custom-split-sum"></div>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="btn-add-exp">Add Expense</button>
      </form>
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
      loadFinancial();
    }
  }

  async function loadFinancial() {
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) return;
    document.getElementById('settlement-wrap').style.display = 'none';
    document.getElementById('btn-report').style.display = 'inline-block';
    const res = await API.get('financial', {
      action: 'list',
      trip_id: tripId
    });
    if (!res.success) return;
    const {
      expenses,
      total_spent,
      budget_limit,
      currency
    } = res.data;

    document.getElementById('fin-stats').style.display = 'grid';
    document.getElementById('stat-total').textContent = (total_spent || 0).toFixed(2) + ' ' + currency;
    document.getElementById('stat-budget').textContent = budget_limit ? budget_limit.toFixed(2) + ' ' + currency : 'Not set';
    if (budget_limit) {
      const pct = Math.min(100, ((total_spent / budget_limit) * 100)).toFixed(1);
      document.getElementById('stat-pct').textContent = pct + '%';
      const bar = document.getElementById('budget-bar');
      bar.style.width = pct + '%';
      bar.style.background = pct > 90 ? 'var(--danger)' : pct > 70 ? 'var(--warning)' : 'var(--primary)';
    }

    const wrap = document.getElementById('expenses-wrap');
    if (!expenses.length) {
      wrap.innerHTML = '<div class="card empty-state"><div class="icon">💰</div>No expenses yet.</div>';
      return;
    }
    wrap.innerHTML = `<div class="card"><div class="card-header"><h3>Expenses</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Title</th><th>Amount</th><th>Type</th><th>Paid By</th><th>Splits</th><th>Date</th></tr></thead>
      <tbody>${expenses.map(e => `
        <tr>
          <td><strong class="text-sm text-gray-600">${escHtml(e.title)}</strong></td>
          <td class="text-gray-600">${(e.converted_amount || e.amount).toFixed(2)} ${escHtml(currency)}</td>
          <td><span class="badge badge-blue">${escHtml(e.type)}</span></td>
          <td class="text-sm text-gray-600">${escHtml(e.paid_by_name)}</td>
          <td class="text-sm text-gray-600">${(e.splits || []).map(s => escHtml(s.name) + ': ' + (s.amount||0).toFixed(2)).join(', ')}</td>
          <td class="text-sm text-gray-600">${fmtDate(e.created_at)}</td>
        </tr>`).join('')}
      </tbody></table></div></div>`;
  }

  async function loadSettlement() {
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) {
      showAlert('#alert-box', 'Select a trip first.');
      return;
    }
    const res = await API.get('financial', {
      action: 'settlement',
      trip_id: tripId
    });
    const wrap = document.getElementById('settlement-wrap');
    wrap.style.display = 'block';
    if (!res.success) {
      wrap.innerHTML = `<div class="alert alert-error">${escHtml(res.message)}</div>`;
      return;
    }
    const {
      transactions,
      currency
    } = res.data;
    if (!transactions.length) {
      wrap.innerHTML = '<div class="card empty-state"><div class="icon">✅</div>All settled! No transactions needed.</div>';
      return;
    }
    wrap.innerHTML = `<div class="card">
    <div class="card-header"><h3>Settlement Transactions</h3></div>
    <div class="table-wrap"><table>
      <thead><tr><th>From</th><th>To</th><th>Amount</th></tr></thead>
      <tbody>${transactions.map(t => `
        <tr>
          <td>${escHtml(t.from_name || String(t.from))}</td>
          <td>${escHtml(t.to_name   || String(t.to))}</td>
          <td><strong>${(+t.amount).toFixed(2)} ${escHtml(currency)}</strong></td>
        </tr>`).join('')}
      </tbody></table></div></div>`;
    wrap.scrollIntoView({
      behavior: 'smooth'
    });
  }

  let tripMembersCache = [];

  async function toggleCustomSplit() {
    const wrap = document.getElementById('custom-split-wrap');
    const isCustom = document.getElementById('split-type').value === 'custom';
    wrap.style.display = isCustom ? 'block' : 'none';
    if (!isCustom) return;

    const tripId = document.getElementById('trip-select').value;
    if (!tripId) return;

    const res = await API.get('trips', {
      action: 'members',
      trip_id: tripId
    });
    if (!res.success) {
      showAlert('#exp-alert', res.message);
      return;
    }
    tripMembersCache = res.data || [];

    const itemsEl = document.getElementById('custom-split-items');
    itemsEl.innerHTML = tripMembersCache.map(m => `
      <div style="display:flex;align-items:center;gap:8px">
        <span style="flex:1">${escHtml(m.name || m.email)}</span>
        <input type="number" step="0.01" min="0" value="0"
               class="form-control custom-amt" data-uid="${m.id}"
               style="width:110px;margin:0" oninput="updateSplitSum()">
      </div>`).join('');
    updateSplitSum();
  }

  function updateSplitSum() {
    const total = parseFloat(document.querySelector('[name=amount]').value) || 0;
    let sum = 0;
    document.querySelectorAll('.custom-amt').forEach(i => sum += parseFloat(i.value) || 0);
    const el = document.getElementById('custom-split-sum');
    const diff = (total - sum).toFixed(2);
    el.textContent = `Sum: ${sum.toFixed(2)} / ${total.toFixed(2)} (diff: ${diff})`;
    el.style.color = Math.abs(total - sum) < 0.01 ? 'var(--success)' : 'var(--danger)';
  }

  document.querySelector('[name=amount]').addEventListener('input', updateSplitSum);

  document.getElementById('form-add-expense').addEventListener('submit', async e => {
    e.preventDefault();
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) {
      showAlert('#exp-alert', 'Select a trip first.');
      return;
    }
    const btn = document.getElementById('btn-add-exp');
    const fd = new FormData(e.target);
    const splitType = fd.get('split_type');

    const payload = {
      action: 'add',
      trip_id: tripId,
      title: fd.get('title'),
      amount: fd.get('amount'),
      currency: fd.get('currency'),
      type: fd.get('type'),
      split_type: splitType,
    };

    if (splitType === 'custom') {
      const inputs = [...document.querySelectorAll('.custom-amt')];
      if (!inputs.length) {
        showAlert('#exp-alert', 'Open the custom split section first.');
        return;
      }
      const memberIds = [],
        amounts = [];
      let sum = 0;
      inputs.forEach(i => {
        memberIds.push(i.dataset.uid);
        const v = parseFloat(i.value) || 0;
        amounts.push(v.toFixed(2));
        sum += v;
      });
      const total = parseFloat(fd.get('amount')) || 0;
      if (Math.abs(sum - total) > 0.01) {
        showAlert('#exp-alert', `Custom amounts must sum to ${total.toFixed(2)}, got ${sum.toFixed(2)}.`);
        return;
      }
      payload.member_ids = memberIds;
      payload.amounts = amounts;
    }

    setLoading(btn, true);
    const res = await API.post('financial', payload);
    setLoading(btn, false);
    if (res.success) {
      closeModal('modal-add-expense');
      e.target.reset();
      document.getElementById('custom-split-wrap').style.display = 'none';
      document.getElementById('custom-split-items').innerHTML = '';
      loadFinancial();
    } else showAlert('#exp-alert', res.message);
  });

  function generateReport() {
    const tripId = document.getElementById('trip-select').value;
    if (!tripId) {
      showAlert('#alert-box', 'Select a trip first.');
      return;
    }
    window.open('/report.php?trip_id=' + tripId + '&print=1', '_blank');
  }

  loadTrips();
</script>

<?php end_layout(); ?>