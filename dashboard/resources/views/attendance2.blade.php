<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
<title>Attendance2 - Punch Timestamps</title>
<link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { height: 100%; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    background: linear-gradient(135deg, #f0f4f8 0%, #d9e8f5 50%, #f0f4f8 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    color: #1e293b;
}

/* Header Navigation - Light Glass Effect */
.navbar-header {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(100, 116, 139, 0.1);
    padding: 0.75rem 1.5rem;
    color: #1e293b;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.navbar-header .logo { font-size: 1.1rem; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 0.4rem; letter-spacing: -0.3px; }
.navbar-header .logo i { color: #0284c7; }
.navbar-header .date-time { font-size: 0.75rem; opacity: 0.7; }

/* Content - Full Height */
.content {
    margin-top: 56px;
    padding: 0;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.content-wrapper {
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(100, 116, 139, 0.1);
    border-radius: 0;
    padding: 0.8rem;
    box-shadow: none;
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* Tables */
.table {
    border-radius: 0;
    overflow: hidden;
    margin-bottom: 0;
    font-size: 0.85rem;
    flex: 1;
}
.table thead {
    background: rgba(255, 255, 255, 0.8);
    color: #1e293b;
    border-bottom: 2px solid rgba(100, 116, 139, 0.15);
}
.table thead th {
    padding: 0.6rem 0.6rem;
    font-weight: 600;
    border: none;
}
.table tbody td {
    padding: 0.5rem 0.6rem;
    vertical-align: middle;
    border-color: rgba(100, 116, 139, 0.08);
}
.table tbody tr {
    transition: all 0.2s;
    border-bottom: 1px solid rgba(100, 116, 139, 0.08);
    background: rgba(255, 255, 255, 0.2);
}
.table tbody tr:hover {
    background: rgba(2, 132, 199, 0.08);
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 80px;
    right: 1rem;
    z-index: 2000;
}
.toast {
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(100, 116, 139, 0.1);
    backdrop-filter: blur(8px);
    font-size: 0.85rem;
}
.toast.bg-success { background: rgba(34, 197, 94, 0.85) !important; }

/* Scrollbar styling */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: rgba(100, 116, 139, 0.08); }
::-webkit-scrollbar-thumb { background: rgba(2, 132, 199, 0.4); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(2, 132, 199, 0.6); }

/* Ensure DataTables stretch to container width and recalc reliably */
.dataTable, #attendanceTable, #userTable { width: 100% !important; }
</style>
</head>
<body>

<!-- Header Navigation -->
<nav class="navbar-header">
    <a href="/" style="font: inherit; color: inherit; text-decoration: none;">
    <div class="logo">
        <i class="bi bi-clock-history"></i>Attendance2 - Punch Timestamps
    </div>
    </a>
    <div class="date-time" id="currentDateTime"></div>
</nav>

<!-- Main Content -->
<div class="content">
    <div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Device Punch Timestamps</h4>
        <a href="/" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <table id="attendanceTable" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>User</th>
                <th>Timestamp</th>
                <th>Status</th>
                <th>Punch</th>
                <th>VerifyID</th>
            </tr>
        </thead>
    </table>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
let table;

$(document).ready(function(){
    // include CSRF token for all AJAX POST requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Load user directory from server first, then initialize DataTable
    loadUserFromServer();
});

// Load user directory from server and then initialize DataTable
function loadUserFromServer() {
    $.ajax({ url: '/api/users', method: 'GET', dataType: 'json', cache: false })
        .done(function(res){
            if (Array.isArray(res)) {
                window.userDirectory = res;
            } else {
                window.userDirectory = [];
            }
            // Now initialize DataTable after user data is loaded
            initializeDataTable();
        }).fail(function(){
            window.userDirectory = [];
            showToast('Error', 'Failed to load users from server.');
            // Still initialize DataTable even if user loading fails
            initializeDataTable();
        });
}

function initializeDataTable() {
    // Initialize DataTable
    table = $('#attendanceTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: '/api/attendance2-summary',
            xhrFields: {
                withCredentials: true
            },
            data: function(d){
                // Add any additional data if needed
            }
        },
        columns: [
            { 
                data: 'user_id', 
                title: 'User',
                render: function(data, type, row) {
                    // try to find user info from client-side directory
                    try {
                        var sid = parseInt(data, 10);
                        var s = (window.userDirectory || []).find(x => parseInt(x.id,10) === sid);
                        if (s) {
                            // show two-line format: ID Name then Designation, Department
                            var dept = s.department || s.dept || '';
                            var title = s.title || '';
                            return `<div><strong>${s.name} (${data})</strong><br><small class="text-muted">${title}${dept?(', '+dept):''}</small></div>`;
                        }
                    } catch (e) { }
                    return data;
                }
            },
            { data: 'timestamp', title: 'Timestamp' },
            { data: 'status', title: 'Status' },
            { data: 'punch', title: 'Punch' },
            {
                data: 'status',
                title: 'VerifyID',
                render: function(data, type, row) {
                    // Map status codes to short labels
                    if (data == 1 || data === '1') return 'FINID';
                    if (data == 4 || data === '4') return 'RFID';
                    return 'N/A';
                }
            }
        ],
        order: [[1, 'desc']],
        lengthMenu: [[50, 100, 200, 1000000], [50, 100, 200, "All"]],
        pageLength: 50
    });

    // Update current date/time
    function updateDateTime() {
        const now = new Date();
        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        document.getElementById('currentDateTime').textContent = now.toLocaleString('en-US', options);
    }

    updateDateTime();
    setInterval(updateDateTime, 1000);
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
        // ensure a toast container exists
        if (!$('.toast-container').length) {
            $('body').append('<div class="toast-container position-fixed top-0 end-0 p-3"></div>');
        }
        $('.toast-container').append(toastHTML);
        const toastElement = new bootstrap.Toast(document.getElementById(toastId));
        toastElement.show();
        // auto-remove after 5 seconds
        setTimeout(() => {
            toastElement.dispose();
            $('#' + toastId).remove();
        }, 5000);
}
</script>
</body>
</html>