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
    <a href="#" id="logoutBtn" class="text-danger">Logout</a>
</div>

<div class="content">
    <div class="filters">
        <input type="date" id="filter-date" class="form-control" value="{{ date('Y-m-d') }}">
        <input type="month" id="filter-month" class="form-control d-none" value="{{ date('Y-m') }}">
        <input type="text" id="filter-user" class="form-control d-none" placeholder="User ID">
        <button id="apply-filter" class="btn btn-primary">Apply</button>
        <button id="copy-daily" class="btn btn-outline-secondary d-none">Copy</button>
        <button id="export-daily" class="btn btn-outline-success d-none">Export to CSV</button>
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

function updateFilters(type){
    currentType = type;
    $('#filter-date').toggleClass('d-none', type !== 'daily');
    $('#filter-month').toggleClass('d-none', type !== 'monthly');
    $('#filter-user').toggleClass('d-none', type !== 'user');

    // Show/hide copy/export buttons only for daily
    $('#copy-daily, #export-daily').toggleClass('d-none', type !== 'daily');
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


    // Show copy/export buttons if default is daily
    if (currentType === 'daily') {
        $('#copy-daily, #export-daily').removeClass('d-none');
    }

    $('.sidebar a').click(function(e){
        e.preventDefault();
        updateFilters($(this).data('type'));
        table.ajax.reload();
    });

    // Logout button handling: submit hidden POST form to /logout
    $('#logoutBtn').click(function(e){
        e.preventDefault();
        $('#logoutForm').submit();
    });

    $('#apply-filter').click(function(){ table.ajax.reload(); });

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
