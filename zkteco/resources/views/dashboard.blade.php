<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dynamic Attendance Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        <input type="date" id="filter-date" class="form-control" value="{{ date('Y-m-d') }}">
        <input type="month" id="filter-month" class="form-control d-none" value="{{ date('Y-m') }}">
        <input type="text" id="filter-user" class="form-control d-none" placeholder="User ID">
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
            </tr>
        </thead>
    </table>
</div>

<div class="toast-container position-fixed"></div>

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
            { data: 'work_time', render: d => d ? d : '' }
        ],
        order: [[1, 'desc']],
        lengthMenu: [[25, 50, 100, 1000000], [25, 50, 100, "All"]],
        pageLength: 25
    });

    $('.sidebar a').click(function(e){
        e.preventDefault();
        updateFilters($(this).data('type'));
        table.ajax.reload();
    });

    $('#apply-filter').click(function(){ table.ajax.reload(); });

    // first check last punch
    $.getJSON('/api/check-latest', function(res){
        if (res && res.id) lastCheckId = res.id;
    });

    // check every 10 seconds
    setInterval(checkNewPunch, 1000);
});
</script>
</body>
</html>
