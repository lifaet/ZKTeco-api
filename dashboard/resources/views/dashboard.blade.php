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

/* Sidebar - Light Glass Effect */
.sidebar {
    width: 180px;
    position: fixed;
    top: 56px;
    bottom: 0;
    background: rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(8px);
    border-right: 1px solid rgba(100, 116, 139, 0.1);
    color: #475569;
    padding: 0.75rem 0;
    overflow-y: auto;
    z-index: 999;
    transition: transform 0.3s ease;
}
.sidebar.hidden {
    transform: translateX(-100%);
}
.sidebar-toggle {
    display: none;
    background: none;
    border: none;
    color: #1e293b;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    transition: all 0.2s ease;
}
.sidebar-toggle:hover {
    color: #0284c7;
}
.sidebar a {
    color: #475569;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 1rem;
    transition: all 0.2s ease;
    border-left: 2px solid transparent;
    font-size: 0.85rem;
}
.sidebar a:hover {
    background: rgba(2, 132, 199, 0.08);
    border-left-color: #0284c7;
    color: #0284c7;
}
.sidebar a.active {
    background: rgba(2, 132, 199, 0.12);
    border-left-color: #0284c7;
    color: #0284c7;
    font-weight: 500;
}
.sidebar hr { border-color: rgba(100, 116, 139, 0.1); margin: 0.5rem 0; }
.sidebar a.logout-btn { color: #dc2626; }
.sidebar a.logout-btn:hover { color: #b91c1c; background: rgba(220, 38, 38, 0.08); }

/* Content - Full Height */
.content {
    margin-left: 180px;
    margin-top: 56px;
    margin-bottom: 40px;
    padding: 0;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    transition: margin-left 0.3s ease;
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

/* Filters - Light Glass Effect */
.filters {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin-bottom: 0.8rem;
    align-items: center;
    padding: 0.8rem;
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(100, 116, 139, 0.1);
    border-radius: 6px;
    backdrop-filter: blur(8px);
    flex-shrink: 0;
}
.filters input,
.filters select {
    min-width: 90px;
    max-width: 180px;
    padding: 0.4rem 0.6rem;
    border: 1px solid rgba(100, 116, 139, 0.2);
    border-radius: 5px;
    font-size: 0.8rem;
    background: rgba(255, 255, 255, 0.7);
    color: #1e293b;
    backdrop-filter: blur(4px);
    transition: all 0.2s;
}
.filters input:focus,
.filters select:focus {
    border-color: #0284c7;
    box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
    outline: none;
    background: rgba(255, 255, 255, 0.9);
}

#filter-date, #filter-month { width: 160px !important; }
#filter-user-select, #filter-user { width: 140px !important; }

/* Buttons */
.btn {
    border-radius: 5px;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
    font-size: 0.8rem;
    padding: 0.4rem 0.8rem;
}
.btn-primary {
    background: rgba(2, 132, 199, 0.9);
    color: #fff;
    border: 1px solid rgba(2, 132, 199, 0.4);
    backdrop-filter: blur(4px);
}
.btn-primary:hover {
    background: rgba(2, 132, 199, 1);
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
}
.btn-outline-secondary {
    border: 1px solid rgba(100, 116, 139, 0.3);
    color: #475569;
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(4px);
}
.btn-outline-secondary:hover {
    background: rgba(2, 132, 199, 0.1);
    border-color: #0284c7;
    color: #0284c7;
}
.btn-outline-success {
    background: rgba(34, 197, 94, 0.8);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #fff;
    backdrop-filter: blur(4px);
}
.btn-outline-success:hover {
    background: rgba(34, 197, 94, 0.95);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
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
.toast.bg-danger { background: rgba(220, 38, 38, 0.85) !important; }
.toast.bg-info { background: rgba(2, 132, 199, 0.85) !important; }
.toast .toast-body { color: #fff; }

/* Footer */
footer {
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(8px);
    border-top: 1px solid rgba(100, 116, 139, 0.1);
    color: #64748b;
    padding: 0.75rem 1.5rem;
    font-size: 0.75rem;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 998;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
footer .copyright {
    margin-left: 180px;
}
footer .maintenance {
    margin-right: 0;
}
footer .maintenance a {
    color: #0284c7;
    text-decoration: none;
    transition: color 0.2s;
}
footer .maintenance a:hover {
    color: #0369a1;
    text-decoration: underline;
}


/* Responsive */
@media (max-width: 768px) {
    .sidebar-toggle { display: block; }
    .sidebar { width: 160px; transform: translateX(-160px); }
    .sidebar.active { transform: translateX(0); }
    .content { margin-left: 0; margin-bottom: 40px; }
    .navbar-header { padding: 0.5rem 0.75rem; }
    .navbar-header .logo { font-size: 0.95rem; }
    .navbar-header .logo i { display: none; }
    .navbar-header .date-time { display: none; }
    .filters { gap: 0.4rem; padding: 0.6rem; font-size: 0.75rem; }
    .filters input, .filters select { font-size: 0.7rem; padding: 0.3rem 0.5rem; }
    #filter-date, #filter-month { width: 90px !important; }
    #filter-user-select, #filter-user { width: 110px !important; }
    .btn { font-size: 0.7rem; padding: 0.3rem 0.6rem; }
    .table { font-size: 0.75rem; }
    .table thead th, .table tbody td { padding: 0.4rem 0.4rem; }
    footer { padding: 0.6rem 1rem; font-size: 0.7rem; }
    footer .copyright { margin-left: 0; }
}

@media (max-width: 480px) {
    .sidebar-toggle { display: block; }
    .sidebar { width: 140px; transform: translateX(-140px); }
    .sidebar.active { transform: translateX(0); }
    .content { margin-left: 0; margin-bottom: 40px; }
    .navbar-header { padding: 0.4rem 0.5rem; }
    .navbar-header .logo { font-size: 0.85rem; }
    .sidebar a { font-size: 0.75rem; padding: 0.5rem 0.75rem; gap: 0.4rem; }
    .sidebar a i { font-size: 0.9rem; }
    .filters { padding: 0.5rem; gap: 0.3rem; }
    .filters input, .filters select { font-size: 0.65rem; }
    .btn { font-size: 0.65rem; padding: 0.25rem 0.5rem; }
    .table { font-size: 0.7rem; }
    .table thead th, .table tbody td { padding: 0.3rem 0.3rem; }
    footer { padding: 0.5rem 0.75rem; font-size: 0.65rem; }
    footer .copyright { margin-left: 0; }
}

/* Scrollbar styling */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: rgba(100, 116, 139, 0.08); }
::-webkit-scrollbar-thumb { background: rgba(2, 132, 199, 0.4); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(2, 132, 199, 0.6); }

/* Ensure DataTables stretch to container width and recalc reliably */
.dataTable, #attendanceTable, #staffTable { width: 100% !important; }
</style>
</head>
<body>

<!-- Header Navigation -->
<nav class="navbar-header">
    <button class="sidebar-toggle" id="sidebarToggle" title="Menu"><i class="bi bi-list"></i></button>
    <a href="/" style="font: inherit; color: inherit; text-decoration: none;">
    <div class="logo">
        <i class="bi bi-clock-history"></i>Attendance Dashboard
    </div>
    </a>
    <div class="date-time" id="currentDateTime"></div>
</nav>

<!-- Sidebar Navigation -->
<div class="sidebar" id="sidebar">
    <a href="#" data-type="daily" class="active"><i class="bi bi-calendar-day"></i> Daily</a>
    <a href="#" data-type="monthly"><i class="bi bi-calendar-month"></i> Monthly</a>
    <a href="#" data-type="user"><i class="bi bi-person-circle"></i> User-wise</a>
    <a href="#" data-type="staff"><i class="bi bi-people"></i> Staff Directory</a>
    <hr>
    <a href="#" id="logoutBtn" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="content">
    <div class="content-wrapper">
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
        <button id="addAttendanceBtn" class="btn btn-warning">Add Attendance</button>
        <button id="copy-daily" class="btn btn-outline-secondary d-none">Copy</button>
        <button id="export-daily" class="btn btn-outline-success d-none">Export to CSV</button>
    </div>

    <div id="attendanceSection">
    <table id="attendanceTable" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>User</th>
                <th>Date</th>
                <th>First Punch</th>
                <th>Last Punch</th>
                <th>Work Time</th>
                <th>Type</th>
                <th>VerifyID</th>
                <th>Actions</th>
            </tr>
        </thead>
    </table>
    </div>

    <!-- Staff Directory Section (client-side) -->
    <div id="staffSection" class="d-none">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Staff Directory</h5>
            <button id="addStaffBtn" class="btn btn-primary btn-sm">Add Staff</button>
        </div>

        <table id="staffTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Title</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <!-- Staff modals moved to page-level for proper stacking (see bottom of file) -->
    </div>

    </div>
</div>

<!-- Maintenance Credit -->
<!-- Moved to footer -->

<!-- Footer -->
<footer>
    <div class="copyright">&copy; 2025 Attendance Management System</div>
    <div class="maintenance" style="margin-left: auto;">Maintenance by <a href="https://lifaet.github.io">ZIM</a></div>
</footer>

<!-- hidden logout form -->
<form id="logoutForm" method="POST" action="/logout" style="display:none;">
    @csrf
</form>

<!-- Add/Edit Staff Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add / Edit Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="staffForm">
                    <input type="hidden" id="staff-id">
                    <div class="mb-3">
                        <label class="form-label">ID</label>
                        <input type="text" id="staff-input-id" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" id="staff-input-name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" id="staff-input-title" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" id="staff-input-dept" class="form-control">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" id="staff-input-active" class="form-check-input" checked>
                        <label class="form-check-label" for="staff-input-active">Active</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveStaffBtn" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Staff Modal -->
<div class="modal fade" id="staffDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this staff member?
                <input type="hidden" id="staff-delete-id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmDeleteStaff" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

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
                    <input type="hidden" id="edit-original-punch">
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

<!-- staff UI moved to separate blade (/staff) -->

            <!-- Add Attendance Modal -->
            <div class="modal fade" id="addAttendanceModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Attendance (Manual)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addAttendanceForm">
                                <div class="mb-3">
                                    <label class="form-label">Staff</label>
                                    <select id="add-user-select" class="form-select">
                                        <option value="">Select staff</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Date</label>
                                    <input type="date" id="add-date" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">First Punch</label>
                                    <input type="time" step="1" id="add-first-punch" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Last Punch (optional)</label>
                                    <input type="time" step="1" id="add-last-punch" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">VerifyID (optional)</label>
                                    <input type="text" id="add-status" class="form-control" placeholder="1 for fingerprint, 4 for RFID">
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" id="saveAddAttendance" class="btn btn-primary">Add Attendance</button>
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
const CHECK_LATEST_INTERVAL_MS = 4000;

// Update current date and time in header
function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
}
updateDateTime();
setInterval(updateDateTime, 1000); // update every second

// Mobile sidebar toggle
$('#sidebarToggle').click(function(e){
    e.preventDefault();
    $('#sidebar').toggleClass('active');
});

// Close sidebar when clicking on a menu item
$('.sidebar a').click(function(e){
    if ($(window).width() <= 768) {
        $('#sidebar').removeClass('active');
    }
});

// Staff directory will be loaded from the server; start empty.
window.staffDirectory = [];

function loadUsersIntoSelect() {
    const sel = $('#filter-user-select');
    sel.empty().append('<option value="">All Users</option>');

    // populate with client-side staff directory first (so names show)
    (window.staffDirectory || []).forEach(s => {
        sel.append(`<option value="${s.id}">${s.id} - ${s.name}</option>`);
    });

    // then try to fetch additional users from server and append any missing ones
    $.getJSON('/api/users').done(function(users){
        const existing = new Set((window.staffDirectory || []).map(s => String(s.id)));
        users.forEach(u => {
            const uid = String(u.id || u.user_id || u);
            if (!existing.has(uid)) {
                sel.append(`<option value="${uid}">${uid}</option>`);
                existing.add(uid);
            }
        });
    }).fail(function(){
        // endpoint not available -> we already populated from staffDirectory
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
        // ensure a toast container exists (some other partials create it, but not always)
        if ($('.toast-container').length === 0) {
                $('body').append('<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>');
        }
        $('.toast-container').append(toastHTML);
    const toastEl = new bootstrap.Toast(document.getElementById(toastId), { delay: 2000 });
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
            { data: 'user_id', render: function(data, type, row) {
                // try to find staff info from client-side directory
                try {
                    var sid = parseInt(data, 10);
                    var s = (window.staffDirectory || []).find(x => parseInt(x.id,10) === sid);
                    if (s) {
                        // show two-line format: ID Name then Designation, Department
                        var dept = s.department || s.dept || '';
                        var title = s.title || '';
                        return `<div><strong>${s.name} (${data})</strong><br><small class="text-muted">${title}${dept?(', '+dept):''}</small></div>`;
                    }
                } catch (e) { }
                return data;
            } },
            { data: 'date' },
            { data: 'first_punch', render: function(data, type, row) {
                if (row.is_absent) {
                    return '<strong style="color: #dc2626;">' + data + '</strong>';
                }
                return data;
            } },
            { data: 'last_punch', render: d => d ? d : '' },
            { data: 'work_time', render: d => d ? d : '' },
            { data: 'punch', render: function(d, type, row) {
                // treat numeric/string 255 as automatic machine-sourced
                if (d == 255 || d === '255') return '<span class="badge bg-info text-white">Auto</span>';
                if (d) return '<span class="badge bg-warning text-dark">Manual</span>';
                return '';
            } },
            { data: 'status', render: function(data, type, row) {
                // Map status codes to short labels for the dashboard
                // 1 -> FP, 4 -> RF, otherwise show 'Other'
                if (data == 1 || data === '1') return 'FINID';
                if (data == 4 || data === '4') return 'RFID';
                return 'N/A';
            } },
            { 
                data: null,
                render: function(data, type, row) {
                    if (row.is_absent) {
                        return ''; // No actions for absent rows
                    }
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


    // initialize filter UI for current view via URL hash (deep-linking)
    // Supported hashes: #daily (default), #monthly, #user, #staff
    function applyView(view) {
        const selected = view || 'daily';
        currentType = selected;
        $('.sidebar a').removeClass('active');
        // mark matching sidebar item active
        $('.sidebar a').each(function(){ if ($(this).data('type') === selected) $(this).addClass('active'); });
        // show/hide main sections
        if (selected === 'staff') {
            $('#attendanceSection').addClass('d-none');
            $('#staffSection').removeClass('d-none');
        } else {
            $('#staffSection').addClass('d-none');
            $('#attendanceSection').removeClass('d-none');
        }
        updateFilters(selected);
        if (selected !== 'staff' && table && table.ajax && typeof table.ajax.reload === 'function') {
            table.ajax.reload();
            // Delay adjust so DOM reflow/animations complete before recalculating widths
            setTimeout(function(){
                try { if (table && table.columns && typeof table.columns.adjust === 'function') { table.columns.adjust().draw(false); } } catch(e) { console.debug('columns.adjust failed', e); }
            }, 120);
        }
    }

    // set view from current hash (strip leading '#')
    function setViewFromHash() {
        const hash = (window.location.hash || '').replace(/^#/, '');
        applyView(hash || 'daily');
    }

    // clicking sidebar items updates the URL hash — hashchange handles the actual view change
    $('.sidebar a').click(function(e){
        const selected = $(this).data('type');
        if (typeof selected !== 'undefined') {
            e.preventDefault();
            // update hash which creates a history entry and fires hashchange
            window.location.hash = selected;
        }
        // if link has no data-type, allow normal navigation
    });

    // respond to back/forward and manual hash changes
    window.addEventListener('hashchange', setViewFromHash);

    // initialize on load
    setViewFromHash();

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
        // store original punch value (machine/preset marker like 255)
        $('#edit-original-punch').val(row.punch);
        $('#edit-status').val(row.status);
        $('#editModal').modal('show');
    });

    // Handle Save Edit
    $('#saveEdit').click(function() {
        // Determine punch to send: if the original punch was 255 (machine auto),
        // mark it as manual when a human edits.
        const originalPunch = String($('#edit-original-punch').val() || '');
        const punchToSend = (originalPunch === '255' || originalPunch == 255) ? 'manual' : originalPunch;

        const data = {
            user_id: $('#edit-user-id').val(),
            date: $('#edit-date').val(),
            first_punch: $('#edit-first-punch').val(),
            last_punch: $('#edit-last-punch').val(),
            punch: punchToSend,
            status: $('#edit-status').val()
        };

        $.ajax({
            url: '/api/attendance/update',
            method: 'POST',
            data: data,
            success: function(response) {
                console.debug('saveEdit: ajax success', response);
                $('#editModal').modal('hide');
                showToast('Success', 'Attendance record updated successfully');
                // Delay slightly so modal hide animation completes, then reload table robustly
                setTimeout(function(){
                    try {
                        if (table && table.ajax && typeof table.ajax.reload === 'function') {
                            console.debug('saveEdit: calling table.ajax.reload via instance');
                            table.ajax.reload(null, false);
                            try { if (table.columns && typeof table.columns.adjust === 'function') table.columns.adjust().draw(false); } catch(e){}
                            return;
                        }
                    } catch(e) { console.debug('saveEdit: table.reload failed', e); }
                    try {
                        console.debug('saveEdit: calling fallback DataTable selector reload');
                        $('#attendanceTable').DataTable().ajax.reload(null, false);
                        $('#attendanceTable').DataTable().columns.adjust().draw(false);
                    } catch(e) { console.debug('saveEdit: fallback reload failed', e); }
                }, 150);
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
                console.debug('confirmDelete: ajax success', response);
                $('#deleteModal').modal('hide');
                showToast('Success', 'Attendance record deleted successfully');
                setTimeout(function(){
                    try {
                        if (table && table.ajax && typeof table.ajax.reload === 'function') {
                            console.debug('confirmDelete: calling table.ajax.reload via instance');
                            table.ajax.reload(null, false);
                            try { if (table.columns && typeof table.columns.adjust === 'function') table.columns.adjust().draw(false); } catch(e){}
                            return;
                        }
                    } catch(e) { console.debug('confirmDelete: table.reload failed', e); }
                    try {
                        console.debug('confirmDelete: calling fallback DataTable selector reload');
                        $('#attendanceTable').DataTable().ajax.reload(null, false);
                        $('#attendanceTable').DataTable().columns.adjust().draw(false);
                    } catch(e) { console.debug('confirmDelete: fallback reload failed', e); }
                }, 150);
            },
            error: function(xhr) {
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete attendance record';
                showToast('Error', msg);
            }
        });
    });

    // Prevent the Edit form from submitting and causing a page reload on Enter.
    $('#editForm').on('submit', function(e){
        e.preventDefault();
        $('#saveEdit').click();
    });

    // If user presses Enter while the edit modal is focused, trigger save instead of submitting the page.
    $('#editModal').on('keydown', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#saveEdit').click();
        }
    });

    // Ensure we reload the table if modal hide completes and previous save/delete flagged a refresh
    var __needsAttendanceReload = false;
    $('#saveEdit, #confirmDelete').on('click', function(){ __needsAttendanceReload = true; });
    $('#editModal, #deleteModal').on('hidden.bs.modal', function(){
        if (!__needsAttendanceReload) return;
        __needsAttendanceReload = false;
        try {
            if (table && table.ajax && typeof table.ajax.reload === 'function') {
                console.debug('hidden.bs.modal: reloading table');
                table.ajax.reload(null, false);
                try { if (table.columns && typeof table.columns.adjust === 'function') table.columns.adjust().draw(false); } catch(e){}
                return;
            }
        } catch(e){ console.debug('hidden.bs.modal: reload via instance failed', e); }
        try { $('#attendanceTable').DataTable().ajax.reload(null, false); $('#attendanceTable').DataTable().columns.adjust().draw(false); } catch(e){ console.debug('hidden.bs.modal: fallback reload failed', e); }
    });

    // Similarly, bind Enter in delete modal to confirm delete to avoid accidental page submit/reload.
    $('#deleteModal').on('keydown', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#confirmDelete').click();
        }
    });


    // Handle Copy Daily button (client-side, all filtered rows, robust)
    $('#copy-daily').click(function() {
        let lines = [];
        let header = [];
        $('#attendanceTable thead th').each(function(){
            if ($(this).text().trim() !== 'Actions') header.push($(this).text().trim());
        });
        // ensure Name, Designation and Department columns present after User
        if (header.indexOf('Name') === -1) {
            const userIdx = header.indexOf('User');
            const insertAt = userIdx >= 0 ? userIdx + 1 : 1;
            header.splice(insertAt, 0, 'Name');
        }
        if (header.indexOf('Designation') === -1) {
            const userIdx = header.indexOf('User');
            const insertAt = userIdx >= 0 ? userIdx + 2 : 2;
            header.splice(insertAt, 0, 'Designation');
        }
        if (header.indexOf('Department') === -1) {
            const userIdx = header.indexOf('User');
            const insertAt = userIdx >= 0 ? userIdx + 3 : 3;
            header.splice(insertAt, 0, 'Department');
        }
        lines.push(header);
        // Use DataTables API to get all filtered rows, not just visible page
        let data = table.rows({ search: 'applied' }).data();
        for (let i = 0; i < data.length; i++) {
            let row = data[i];
            // find staff info
            let name = '';
            let desig = '';
            let dept = '';
            try {
                const sid = parseInt(row.user_id, 10);
                const s = (window.staffDirectory || []).find(x => parseInt(x.id,10) === sid);
                if (s) { name = s.name; desig = s.title; dept = s.dept || s.department || ''; }
            } catch (e) {}

            lines.push([
                row.user_id,
                name,
                desig,
                dept,
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
        // ensure Name, Designation and Department columns present after User
        if (header.indexOf('Name') === -1) {
            const userIdx = header.indexOf('User');
            const insertAt = userIdx >= 0 ? userIdx + 1 : 1;
            header.splice(insertAt, 0, 'Name');
        }
        if (header.indexOf('Designation') === -1) {
            const userIdx = header.indexOf('User');
            const insertAt = userIdx >= 0 ? userIdx + 2 : 2;
            header.splice(insertAt, 0, 'Designation');
        }
        if (header.indexOf('Department') === -1) {
            const userIdx = header.indexOf('User');
            const insertAt = userIdx >= 0 ? userIdx + 3 : 3;
            header.splice(insertAt, 0, 'Department');
        }

        let rows = [];
        table.rows({ search: 'applied' }).every(function(){
            let row = this.data();
            let name = '';
            let desig = '';
            let dept = '';
            try {
                const sid = parseInt(row.user_id, 10);
                const s = (window.staffDirectory || []).find(x => parseInt(x.id,10) === sid);
                if (s) { name = s.name; desig = s.title; dept = s.dept || s.department || ''; }
            } catch (e) {}

            rows.push([
                row.user_id,
                name,
                desig,
                dept,
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

    // --- Staff directory: prefer server-side storage via API, fallback to localStorage ---
    function loadStaffFromServer() {
        $.ajax({ url: '/api/staff', method: 'GET', dataType: 'json', cache: false })
            .done(function(res){
                if (Array.isArray(res)) {
                    window.staffDirectory = res;
                } else {
                    window.staffDirectory = [];
                }
                renderStaffTable();
            }).fail(function(){
                window.staffDirectory = [];
                renderStaffTable();
                showToast('Error', 'Failed to load staff from server. Changes will not be saved.');
            });
    }

    function saveStaffToLocalCache(){
        // no-op: persistence is server-side only now
    }

    function renderStaffTable() {
        const tbody = $('#staffTable tbody');
        if (!tbody.length) return; // no staff table on this page
        tbody.empty();
        (window.staffDirectory || []).forEach(function(s){
            const id = s.id;
            const name = s.name || '';
            const title = s.title || '';
            const dept = s.dept || s.department || '';
            const active = s.active !== false; // default to true if not set
            const statusBadge = active 
                ? '<span class="badge bg-success">Active</span>' 
                : '<span class="badge bg-secondary">Inactive</span>';
            const tr = $(
                '<tr data-id="'+id+'">' +
                '<td>' + id + '</td>' +
                '<td>' + $('<div>').text(name).html() + '</td>' +
                '<td>' + $('<div>').text(title).html() + '</td>' +
                '<td>' + $('<div>').text(dept).html() + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' +
                    '<button class="btn btn-sm btn-outline-primary staff-edit" data-id="'+id+'"><i class="bi bi-pencil"></i></button> ' +
                    '<button class="btn btn-sm btn-outline-danger staff-delete" data-id="'+id+'"><i class="bi bi-trash"></i></button>' +
                '</td>' +
                '</tr>'
            );
            tbody.append(tr);
        });

        // initialize or re-draw DataTable for staff list
        if ($.fn.DataTable.isDataTable('#staffTable')) {
            try { $('#staffTable').DataTable().destroy(); } catch(e){}
        }
        const st = $('#staffTable').DataTable({
            paging: true,
            searching: true,
            info: true,
            lengthChange: false,
            pageLength: 25,
            order: [[0, 'asc']]
        });
        try { if (st && st.columns && typeof st.columns.adjust === 'function') st.columns.adjust().draw(false); } catch(e) { console.debug('staff columns.adjust failed', e); }
        // also ensure attendance table recalculates after staff table operations
        setTimeout(function(){
            try { if (table && table.columns && typeof table.columns.adjust === 'function') table.columns.adjust().draw(false); } catch(e) { console.debug('attendance columns.adjust failed', e); }
        }, 150);
    }

    // wire buttons
    $('#addStaffBtn').click(function(){
        $('#staff-id').val('');
        $('#staff-input-id').val('');
        $('#staff-input-name').val('');
        $('#staff-input-title').val('');
        $('#staff-input-dept').val('');
        $('#staff-input-active').prop('checked', true); // default to active
        $('#staffModal').modal('show');
    });

    // Save (Add / Edit)
    $('#saveStaffBtn').click(function(){
        const originalId = String($('#staff-id').val() || '').trim();
        const idVal = String($('#staff-input-id').val()).trim();
        const name = String($('#staff-input-name').val() || '').trim();
        const title = String($('#staff-input-title').val() || '').trim();
        const dept = String($('#staff-input-dept').val() || '').trim();
        const active = $('#staff-input-active').is(':checked');

        if (!idVal || !name) {
            showToast('Error', 'Please provide at least ID and Name');
            return;
        }

        // ensure staffDirectory exists in-memory
        window.staffDirectory = window.staffDirectory || [];

        // edit mode
        if (originalId) {
            // call PUT /api/staff/{id}
            $.ajax({
                url: '/api/staff/' + encodeURIComponent(originalId),
                method: 'PUT',
                contentType: 'application/json',
                data: JSON.stringify({ name: name, title: title, department: dept, active: active })
            }).done(function(updated){
                // update local copy with server response and refresh UI
                const idx = window.staffDirectory.findIndex(x => String(x.id) === String(originalId));
                if (idx !== -1) window.staffDirectory[idx] = updated;
                renderStaffTable();
                $('#staffModal').modal('hide');
                showToast('Saved', 'Staff member updated');
                if (table && table.ajax && typeof table.ajax.reload === 'function') table.ajax.reload(null, false);
            }).fail(function(xhr){
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to update staff member on server';
                showToast('Error', msg);
            });
            return;
        }

        // create mode
        $.ajax({
            url: '/api/staff',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: isNaN(idVal) ? idVal : Number(idVal), name: name, title: title, department: dept, active: active })
        }).done(function(created){
            window.staffDirectory = window.staffDirectory || [];
            window.staffDirectory.push(created);
            renderStaffTable();
            $('#staffModal').modal('hide');
            showToast('Saved', 'Staff member added');
            if (table && table.ajax && typeof table.ajax.reload === 'function') table.ajax.reload(null, false);
        }).fail(function(xhr){
            const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to add staff member on server';
            showToast('Error', msg);
        });
    });

    // Edit / Delete handlers delegated
    $('#staffTable tbody').on('click', '.staff-edit', function(){
        const id = $(this).data('id');
        const s = (window.staffDirectory || []).find(x => String(x.id) === String(id));
        if (!s) return showToast('Error', 'Staff member not found');
        $('#staff-id').val(s.id);
        $('#staff-input-id').val(s.id);
        $('#staff-input-name').val(s.name || '');
        $('#staff-input-title').val(s.title || '');
        $('#staff-input-dept').val(s.dept || s.department || '');
        $('#staff-input-active').prop('checked', s.active !== false);
        $('#staffModal').modal('show');
    });

    $('#staffTable tbody').on('click', '.staff-delete', function(){
        const id = $(this).data('id');
        $('#staff-delete-id').val(id);
        $('#staffDeleteModal').modal('show');
    });

    $('#staffTable tbody').on('click', '.staff-toggle', function(){
        const id = $(this).data('id');
        const s = (window.staffDirectory || []).find(x => String(x.id) === String(id));
        if (!s) return showToast('Error', 'Staff member not found');
        
        const newActive = !s.active; // toggle active status
        $.ajax({
            url: '/api/staff/' + encodeURIComponent(id),
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ active: newActive })
        }).done(function(response){
            // Update local staff directory
            s.active = newActive;
            renderStaffTable();
            showToast('Success', 'Staff member ' + (newActive ? 'activated' : 'deactivated'));
            // Reload attendance table to reflect changes
            if (table && table.ajax && typeof table.ajax.reload === 'function') table.ajax.reload(null, false);
        }).fail(function(xhr){
            const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to update staff status';
            showToast('Error', msg);
        });
    });

    $('#confirmDeleteStaff').click(function(){
        const id = String($('#staff-delete-id').val());
        $.ajax({ url: '/api/staff/' + encodeURIComponent(id), method: 'DELETE' }).done(function(){
            window.staffDirectory = (window.staffDirectory || []).filter(x => String(x.id) !== id);
            renderStaffTable();
            $('#staffDeleteModal').modal('hide');
            showToast('Deleted', 'Staff member removed');
            if (table && table.ajax && typeof table.ajax.reload === 'function') table.ajax.reload(null, false);
        }).fail(function(xhr){
            const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete staff member on server';
            showToast('Error', msg);
        });
    });

    // Initialize staff list from server and render
    loadStaffFromServer();

    // Add Attendance button handler
    $('#addAttendanceBtn').click(function(){
        const sel = $('#add-user-select');
        sel.empty().append('<option value="">Select staff</option>');
        (window.staffDirectory || []).forEach(s => {
            sel.append(`<option value="${s.id}">${s.id} - ${s.name || ''}</option>`);
        });
        // try to fetch any users not present in staffDirectory
        $.getJSON('/api/users').done(function(users){
            const existing = new Set((window.staffDirectory || []).map(s => String(s.id)));
            users.forEach(u => {
                const uid = String(u.id || u.user_id || u);
                if (!existing.has(uid)) sel.append(`<option value="${uid}">${uid}</option>`);
            });
        }).fail(function(){});

        $('#add-date').val($('#filter-date').val() || new Date().toISOString().slice(0,10));
        $('#add-first-punch').val('');
        $('#add-last-punch').val('');
        $('#add-status').val('');
        $('#addAttendanceModal').modal('show');
    });

    // Save new attendance
    $('#saveAddAttendance').click(function(){
        const user_id = $('#add-user-select').val();
        const date = $('#add-date').val();
        const first_punch = $('#add-first-punch').val();
        const last_punch = $('#add-last-punch').val();
        const status = $('#add-status').val();

        if (!user_id || !date || !first_punch) {
            showToast('Error', 'Please select staff and provide date and first punch');
            return;
        }

        $.ajax({
            url: '/api/attendance/add',
            method: 'POST',
            data: {
                user_id: user_id,
                date: date,
                first_punch: first_punch,
                last_punch: last_punch,
                status: status
            }
        }).done(function(res){
            $('#addAttendanceModal').modal('hide');
            showToast('Success', 'Attendance added successfully');
            setTimeout(function(){ try { if (table && table.ajax && typeof table.ajax.reload === 'function') { table.ajax.reload(null, false); table.columns.adjust().draw(false); return; } } catch(e){} try { $('#attendanceTable').DataTable().ajax.reload(null, false); $('#attendanceTable').DataTable().columns.adjust().draw(false); } catch(e){} }, 150);
        }).fail(function(xhr){
            const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to add attendance';
            showToast('Error', msg);
        });
    });

});
</script>
</body>
</html>