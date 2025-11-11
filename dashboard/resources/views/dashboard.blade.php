<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
<title>Dynamic Attendance Dashboard</title>
<link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { font-family: 'Inter', sans-serif; background: #f1f3f6; }
.sidebar {
    width: 220px; position: fixed; top: 0; bottom: 0;
    background: #343a40; color: #fff; padding-top: 20px;
}
.sidebar a { color: #fff; text-decoration: none; display: block; padding: 10px 20px; }
.sidebar a:hover { background: #495057; }
.content { margin-left: 220px; padding: 20px; }
.filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:1rem; align-items:center }
.filters input, .filters select { min-width:120px; max-width:220px; }

/* make date and month inputs compact */
#filter-date, #filter-month { width: 140px !important; }
#filter-user-select, #filter-user { width: 180px !important; }

/* Toast popup */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 2000;
}
</style>
</head>
<body>

<div class="sidebar">
    <a href="#" data-type="daily">Daily</a>
    <a href="#" data-type="monthly">Monthly</a>
    <a href="#" data-type="user">User-wise</a>
    <a href="#" id="logoutBtn" class="text-danger">Logout</a>
</div>

<div class="content">
    <div class="d-flex align-items-center gap-2" style="margin-bottom: 1rem;">
        <div class="input-group flex-nowrap" style="width:auto;">
            <button class="btn btn-outline-secondary prev-day" title="Previous day" style="display:none;"><i class="bi bi-chevron-left"></i></button>
            <input type="date" id="filter-date" class="form-control" value="{{ date('Y-m-d') }}" style="width:140px;">
            <button class="btn btn-outline-secondary next-day" title="Next day" style="display:none;"><i class="bi bi-chevron-right"></i></button>
        </div>

        <div class="input-group flex-nowrap" style="width:auto;">
            <button class="btn btn-outline-secondary prev-month" title="Previous month" style="display:none;"><i class="bi bi-chevron-left"></i></button>
            <input type="month" id="filter-month" class="form-control d-none" value="{{ date('Y-m') }}" style="width:140px;">
            <button class="btn btn-outline-secondary next-month" title="Next month" style="display:none;"><i class="bi bi-chevron-right"></i></button>
        </div>

        <div class="input-group flex-nowrap" style="width:auto;">
            <input type="text" id="filter-user" class="form-control d-none" placeholder="User ID" style="width:180px;">
            <select id="filter-user-select" class="form-select d-none" style="width:180px;"></select>
        </div>

        <button id="apply-filter" class="btn btn-primary">Apply</button>
        <button id="copy-daily" class="btn btn-outline-secondary d-none">Copy</button>
        <button id="export-daily" class="btn btn-outline-success d-none">Export to CSV</button>
    </div>

    <table id="attendanceTable" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>User</th>
                <th>Date</th>
                <th>First Punch</th>
                <th>Last Punch</th>
                <th>Work Time</th>
                <!-- <th>Punch Type</th> -->
                <th>VerifyID</th>
                <th>Actions</th>
            </tr>
        </thead>
    </table>
</div>

<div class="toast-container position-fixed"></div>

<!-- hidden logout form -->
<form id="logoutForm" method="POST" action="/logout" style="display:none;">
    @csrf
</form>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="edit-user-id">
                    <input type="hidden" id="edit-date">
                    <div class="mb-3">
                        <label class="form-label">First Punch</label>
                        <input type="time" class="form-control" id="edit-first-punch" step="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Punch</label>
                        <input type="time" class="form-control" id="edit-last-punch" step="1">
                    </div>
                    <!-- <div class="mb-3">
                        <label class="form-label">Punch</label>
                        <input type="text" class="form-control" id="edit-punch">
                    </div> -->
                    <div class="mb-3">
                        <label class="form-label">VerifyID (1 For FINGERPRINT, 4 For RFID)</label>
                        <input type="text" class="form-control" id="edit-status">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveEdit">Save changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this attendance record?
                <input type="hidden" id="delete-user-id">
                <input type="hidden" id="delete-date">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
let table;
let currentType = 'daily';
let lastCheckId = 0; // last known entry ID
// Polling interval (ms) for /api/check-latest. Lower values = more frequent checks.
// Be careful lowering too far: very frequent polling increases DB/load. Default 2000ms = 2s.
const CHECK_LATEST_INTERVAL_MS = 6000;

function loadUsersIntoSelect() {
    $.getJSON('/api/users').done(function(users){
        const sel = $('#filter-user-select');
        sel.empty().append('<option value="">All Users</option>');
        users.forEach(u => sel.append(`<option value="${u.id}">${u.id}${u.name?(' - '+u.name):''}</option>`));
    }).fail(function(){
        // endpoint not available -> keep text input as fallback
    });
}

function navigateDay(offset) {
    const d = new Date($('#filter-date').val());
    d.setDate(d.getDate() + offset);
    $('#filter-date').val(d.toISOString().slice(0,10));
}

function navigateMonth(offset) {
    const cur = $('#filter-month').val();
    if (!cur) return;
    const parts = cur.split('-');
    const y = parseInt(parts[0],10);
    const m = parseInt(parts[1],10) - 1;
    const dt = new Date(y, m + offset, 1);
    const mm = String(dt.getMonth()+1).padStart(2,'0');
    $('#filter-month').val(`${dt.getFullYear()}-${mm}`);
}

function updateFilters(type){
    currentType = type;

    // show/hide inputs
    $('#filter-date').toggleClass('d-none', type !== 'daily');
    $('#filter-month').toggleClass('d-none', type !== 'monthly');
    $('#filter-user').toggleClass('d-none', type !== 'user');
    $('#filter-user-select').toggleClass('d-none', type !== 'user');

    // show prev/next controls only for relevant types
    $('.prev-day, .next-day').toggle(type === 'daily');
    $('.prev-month, .next-month').toggle(type === 'monthly');

    // copy/export only for daily
    $('#copy-daily, #export-daily').toggleClass('d-none', type !== 'daily');

    if (type === 'user') loadUsersIntoSelect();
}

function showToast(title, message){
    const toastId = 'toast-' + Date.now();
    const toastHTML = `
      <div id="${toastId}" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">
            <strong>${title}</strong><br>${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>`;
    $('.toast-container').append(toastHTML);
    const toastEl = new bootstrap.Toast(document.getElementById(toastId), { delay: 5000 });
    toastEl.show();
}

function checkNewPunch(){
    // Use $.ajax with cache:false to avoid cached responses from proxies
    $.ajax({
        url: '/api/check-latest',
        method: 'GET',
        dataType: 'json',
        cache: false,
        data: { last_id: lastCheckId }
    }).done(function(res){
        // Defensive extraction: API might return {id:...} or {data:{id:...}} or {latest:{id:...}}
        function extractId(r){
            if (!r) return 0;
            if (r.id) return parseInt(r.id,10) || 0;
            if (r.latest && r.latest.id) return parseInt(r.latest.id,10) || 0;
            if (r.data && r.data.id) return parseInt(r.data.id,10) || 0;
            // try first element if array
            if (Array.isArray(r) && r.length && r[0].id) return parseInt(r[0].id,10) || 0;
            return 0;
        }

        const id = extractId(res);
        console.debug('check-latest response', res, 'extractedId', id, 'lastCheckId', lastCheckId);

        // If we haven't initialized lastCheckId yet (fresh page or initial failure),
        // set it to the server-provided id but DO NOT show a toast — this prevents
        // showing the current latest row as "new" on every poll when the server
        // falls back to returning the most recent row.
        if (!lastCheckId || lastCheckId === 0) {
            lastCheckId = id;
            return;
        }

        if (id && id > lastCheckId) {
            lastCheckId = id;
            // Build a friendly message. If specific fields exist, show them; otherwise show a generic notice.
            const user = res.user_id || res.user || null;
            const time = res.time || res.timestamp || null;
            const date = res.date || null;
            let message = '';
            if (user || time || date) {
                // prefer a punch-style message when details available
                message = `User ${user || 'Unknown'}` + (time ? ` recorded at ${time}` : '') + (date ? ` on ${date}` : '');
            } else {
                message = `New record added (id: ${id})`;
            }
            showToast('New Data', message);
            if (table && table.ajax && typeof table.ajax.reload === 'function') {
                table.ajax.reload(null, false); // reload data silently
            }
        }
    }).fail(function(xhr, status, err){
        // ignore 204/no-content or silent failures, but log others for debugging
        if (xhr && xhr.status && xhr.status !== 204) {
            console.debug('check-latest request failed', xhr.status, status, err);
        }
    });
}

$(document).ready(function(){
    // include CSRF token for all AJAX POST requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // if any AJAX returns 401, redirect to login
    $(document).ajaxError(function(event, jqxhr){
        if (jqxhr && jqxhr.status === 401) {
            window.location = '/login';
        }
    });

    table = $('#attendanceTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: '/api/attendance-summary',
            data: function(d){
                d.type = currentType;
                d.date = $('#filter-date').val();
                d.month = $('#filter-month').val();
                d.user = $('#filter-user').val();
            }
        },
        columns: [
            { data: 'user_id' },
            { data: 'date' },
            { data: 'first_punch' },
            { data: 'last_punch', render: d => d ? d : '' },
            { data: 'work_time', render: d => d ? d : '' },
            // { data: 'punch', render: d => d ? d : '' },
            { data: 'status', render: function(data, type, row) {
                // Map status codes to short labels for the dashboard
                // 1 -> FP, 4 -> RF, otherwise show 'Other'
                if (data == 1 || data === '1') return 'FINGERID';
                if (data == 4 || data === '4') return 'RFID';
                return 'Other';
            } },
            { 
                data: null,
                render: function(data, type, row) {
                    return `
                        <button class="btn btn-sm btn-primary edit-btn" data-user="${row.user_id}" data-date="${row.date}">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-danger delete-btn" data-user="${row.user_id}" data-date="${row.date}">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    `;
                }
            }
        ],
        order: [[1, 'desc']],
        lengthMenu: [[25, 50, 100, 1000000], [25, 50, 100, "All"]],
        pageLength: 25
    });


    // initialize filter UI for current view
    updateFilters(currentType);

    $('.sidebar a').click(function(e){
        e.preventDefault();
        $('.sidebar a').removeClass('active');
        $(this).addClass('active');
        updateFilters($(this).data('type'));
        table.ajax.reload();
    });

    // Prev/Next handlers
    $('.prev-day').click(function(){ navigateDay(-1); table.ajax.reload(); });
    $('.next-day').click(function(){ navigateDay(1); table.ajax.reload(); });
    $('.prev-month').click(function(){ navigateMonth(-1); table.ajax.reload(); });
    $('.next-month').click(function(){ navigateMonth(1); table.ajax.reload(); });

    $('#apply-filter').click(function(){
        if (!$('#filter-user-select').hasClass('d-none')){
            $('#filter-user').val($('#filter-user-select').val());
        }
        table.ajax.reload();
    });

    // Logout button handling: submit hidden POST form to /logout
    $('#logoutBtn').click(function(e){
        e.preventDefault();
        $('#logoutForm').submit();
    });

    $('#apply-filter').click(function(){ table.ajax.reload(); });

    // first check last punch (store numeric id) and only start polling after we have attempted
    $.ajax({ url: '/api/check-latest', method: 'GET', dataType: 'json', cache: false })
        .done(function(res){
            lastCheckId = res && res.id ? (parseInt(res.id, 10) || 0) : lastCheckId;
            // start polling after initial successful fetch
            setInterval(checkNewPunch, CHECK_LATEST_INTERVAL_MS);
        }).fail(function(xhr, status){
            console.debug('initial check-latest failed', status, xhr && xhr.status);
            // even on failure, start polling so we can recover later
            setInterval(checkNewPunch, CHECK_LATEST_INTERVAL_MS);
        });

    // Handle Edit button click
    $('#attendanceTable').on('click', '.edit-btn', function() {
        const row = table.row($(this).closest('tr')).data();
        $('#edit-user-id').val(row.user_id);
        $('#edit-date').val(row.date);
        $('#edit-first-punch').val(row.first_punch);
        $('#edit-last-punch').val(row.last_punch);
        $('#edit-punch').val(row.punch);
        $('#edit-status').val(row.status);
        $('#editModal').modal('show');
    });

    // Handle Save Edit
    $('#saveEdit').click(function() {
        const data = {
            user_id: $('#edit-user-id').val(),
            date: $('#edit-date').val(),
            first_punch: $('#edit-first-punch').val(),
            last_punch: $('#edit-last-punch').val(),
            punch: $('#edit-punch').val(),
            status: $('#edit-status').val()
        };

        $.ajax({
            url: '/api/attendance/update',
            method: 'POST',
            data: data,
            success: function(response) {
                $('#editModal').modal('hide');
                showToast('Success', 'Attendance record updated successfully');
                table.ajax.reload();
            },
            error: function(xhr) {
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to update attendance record';
                showToast('Error', msg);
            }
        });
    });

    // Handle Delete button click
    $('#attendanceTable').on('click', '.delete-btn', function() {
        const row = table.row($(this).closest('tr')).data();
        $('#delete-user-id').val(row.user_id);
        $('#delete-date').val(row.date);
        $('#deleteModal').modal('show');
    });

    // Handle Delete Confirmation
    $('#confirmDelete').click(function() {
        const data = {
            user_id: $('#delete-user-id').val(),
            date: $('#delete-date').val()
        };

        $.ajax({
            url: '/api/attendance/delete',
            method: 'POST',
            data: data,
            success: function(response) {
                $('#deleteModal').modal('hide');
                showToast('Success', 'Attendance record deleted successfully');
                table.ajax.reload();
            },
            error: function(xhr) {
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete attendance record';
                showToast('Error', msg);
            }
        });
    });


    // Handle Copy Daily button (client-side, all filtered rows, robust)
    $('#copy-daily').click(function() {
        let lines = [];
        let header = [];
        $('#attendanceTable thead th').each(function(){
            if ($(this).text().trim() !== 'Actions') header.push($(this).text().trim());
        });
        lines.push(header);
        // Use DataTables API to get all filtered rows, not just visible page
        let data = table.rows({ search: 'applied' }).data();
        for (let i = 0; i < data.length; i++) {
            let row = data[i];
            lines.push([
                row.user_id,
                row.date,
                row.first_punch,
                row.last_punch,
                row.work_time,
                row.punch,
                row.status
            ]);
        }
        let text = lines.map(cols => cols.join('\t')).join('\n');
        // Fallback for clipboard API
        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('Copied', 'All filtered data copied to clipboard');
            }, function() {
                fallbackCopy(text);
                showToast('Copied', 'All filtered data copied to clipboard');
            });
        } else {
            fallbackCopy(text);
            showToast('Copied', 'All filtered data copied to clipboard');
        }
    });

    // Handle Export Daily button (client-side, all filtered rows)
    $('#export-daily').click(function() {
        let header = [];
        $('#attendanceTable thead th').each(function(){
            if ($(this).text().trim() !== 'Actions') header.push($(this).text().trim());
        });
        let rows = [];
        table.rows({ search: 'applied' }).every(function(){
            let row = this.data();
            rows.push([
                row.user_id,
                row.date,
                row.first_punch,
                row.last_punch,
                row.work_time,
                row.punch,
                row.status
            ]);
        });
        // Create a CSV string
        let csv = '';
        csv += header.join(',') + '\r\n';
        rows.forEach(function(row){
            csv += row.map(val => '"' + (val ? String(val).replace(/"/g, '""') : '') + '"').join(',') + '\r\n';
        });
        // Download as .csv (Excel will open it)
        let blob = new Blob([csv], {type: 'text/csv'});
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'attendance_daily_export.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast('Exported', 'CSV file downloaded');
    });

    // Handle Export Daily button
    $('#export-daily').click(function() {
        const date = $('#filter-date').val();
    });
});
</script>
</body>
</html>
