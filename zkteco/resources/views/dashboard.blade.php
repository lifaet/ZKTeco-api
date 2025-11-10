<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
<title>Dynamic Attendance Dashboard</title>
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
.filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:1rem; }
.filters input, .filters select { min-width:150px; }

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
</div>

<div class="content">
    <div class="filters">
        <div class="input-group" style="min-width:260px;">
            <button class="btn btn-outline-secondary prev-day" title="Previous day" style="display:none;"><i class="bi bi-chevron-left"></i></button>
            <input type="date" id="filter-date" class="form-control" value="{{ date('Y-m-d') }}">
            <button class="btn btn-outline-secondary next-day" title="Next day" style="display:none;"><i class="bi bi-chevron-right"></i></button>
        </div>

        <div class="input-group" style="min-width:220px;">
            <button class="btn btn-outline-secondary prev-month" title="Previous month" style="display:none;"><i class="bi bi-chevron-left"></i></button>
            <input type="month" id="filter-month" class="form-control d-none" value="{{ date('Y-m') }}">
            <button class="btn btn-outline-secondary next-month" title="Next month" style="display:none;"><i class="bi bi-chevron-right"></i></button>
        </div>

        <div class="input-group" style="min-width:220px;">
            <input type="text" id="filter-user" class="form-control d-none" placeholder="User ID">
            <select id="filter-user-select" class="form-select d-none"></select>
        </div>

        <button id="apply-filter" class="btn btn-primary">Apply</button>
    </div>

    <table id="attendanceTable" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>User</th>
                <th>Date</th>
                <th>First</th>
                <th>Last</th>
                <th>Work Time</th>
                <th>Punch</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
    </table>
</div>

<div class="toast-container position-fixed"></div>

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
                    <div class="mb-3">
                        <label class="form-label">Punch</label>
                        <input type="text" class="form-control" id="edit-punch">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
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

function loadUsersIntoSelect() {
    // Attempt to fetch users; if endpoint not available, leave text input as fallback
    $.getJSON('/api/users').done(function(users){
        const sel = $('#filter-user-select');
        sel.empty().append('<option value="">All Users</option>');
        users.forEach(u => sel.append(`<option value="${u.id}">${u.id}${u.name?(' - '+u.name):''}</option>`));
    }).fail(function(){
        // no-op: fallback to text input
    });
}

function navigateDay(offset) {
    const d = new Date($('#filter-date').val());
    d.setDate(d.getDate() + offset);
    $('#filter-date').val(d.toISOString().slice(0,10));
}

function navigateMonth(offset) {
    // month input uses YYYY-MM
    const cur = $('#filter-month').val();
    if (!cur) return;
    const parts = cur.split('-');
    let y = parseInt(parts[0],10);
    let m = parseInt(parts[1],10) - 1; // zero-based
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

    // if user view, try loading users into select (non-blocking)
    if (type === 'user') {
        loadUsersIntoSelect();
    }
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
    $.getJSON('/api/check-latest', function(res){
        if (res && res.id && res.id > lastCheckId) {
            lastCheckId = res.id;
            showToast("New Punch Recorded", `User ${res.user_id} punched at ${res.time} on ${res.date}`);
            table.ajax.reload(null, false); // reload data silently
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
            { data: 'punch', render: d => d ? d : '' },
            { data: 'status', render: d => d ? d : '' },
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
        // if a user-select is visible, copy its value to filter-user for backend compatibility
        if (!$('#filter-user-select').hasClass('d-none')){
            const v = $('#filter-user-select').val();
            $('#filter-user').val(v);
        }
        table.ajax.reload();
    });

    // Ensure filters UI matches default view
    updateFilters(currentType);

    // first check last punch
    $.getJSON('/api/check-latest', function(res){
        if (res && res.id) lastCheckId = res.id;
    });

    // check every 10 seconds
    setInterval(checkNewPunch, 10000);

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
});
</script>
</body>
</html>
