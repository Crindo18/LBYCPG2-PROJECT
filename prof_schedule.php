<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professor') {
    header('Location: login.php');
    exit();
}

$professor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$professor_name = $stmt->get_result()->fetch_assoc()['full_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - Professor Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #6a1b9a; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #4a148c; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.8; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #BA68C8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #6a1b9a; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #6a1b9a; border-bottom-color: #6a1b9a; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #6a1b9a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #6a1b9a; box-shadow: 0 0 0 3px rgba(106, 27, 154, 0.1); }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #6a1b9a; color: white; }
        .btn-primary:hover { background: #8e24aa; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-small { padding: 6px 12px; font-size: 13px; }
        
        .calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-top: 20px; }
        .calendar-header { font-weight: 600; text-align: center; padding: 10px; background: #f8f9fa; border-radius: 5px; }
        .calendar-day { min-height: 100px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 5px; background: white; }
        .calendar-day.other-month { opacity: 0.3; }
        .calendar-day-number { font-weight: 600; color: #6a1b9a; margin-bottom: 5px; }
        .calendar-day.today { background: #e3f2fd; border-color: #2196F3; }
        
        .time-slot { padding: 5px 8px; margin: 3px 0; background: #d4edda; border-radius: 3px; font-size: 12px; cursor: pointer; }
        .time-slot.booked { background: #f8d7da; }
        .time-slot:hover { opacity: 0.8; }
        
        .schedule-list { display: grid; gap: 15px; }
        .schedule-item { padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .schedule-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .schedule-date { font-weight: 600; color: #6a1b9a; }
        .schedule-time { color: #666; margin: 5px 0; }
        .schedule-student { color: #333; font-weight: 500; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge.available { background: #d4edda; color: #155724; }
        .badge.booked { background: #fff3cd; color: #856404; }
        .badge.pending { background: #f8d7da; color: #721c24; }
        
        .month-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .month-nav button { padding: 8px 16px; background: #6a1b9a; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .month-nav h4 { color: #6a1b9a; font-size: 20px; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .appointment-requests { display: grid; gap: 15px; }
        .request-card { padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .request-actions { display: flex; gap: 10px; margin-top: 15px; }
        
        .loading { text-align: center; padding: 40px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Professor Portal</h2>
                <p><?php echo htmlspecialchars($professor_name); ?></p>
            </div>
            <nav class="sidebar-menu">
                <a href="prof_dashboard.php" class="menu-item">Dashboard</a>
                <a href="prof_advisees.php" class="menu-item">My Advisees</a>
                <a href="prof_study_plans.php" class="menu-item">Study Plans</a>
                <a href="prof_acadadvising.php" class="menu-item">Academic Advising</a>
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item">Email System</a>
                <a href="prof_schedule.php" class="menu-item active">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Schedule Management</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div id="alertContainer"></div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('calendar')">Calendar View</button>
                <button class="tab-btn" onclick="switchTab('add')">Add Availability</button>
                <button class="tab-btn" onclick="switchTab('appointments')">Appointments</button>
                <button class="tab-btn" onclick="switchTab('requests')">Meeting Requests</button>
            </div>
            
            <!-- Calendar Tab -->
            <div id="calendar-tab" class="tab-content active">
                <div class="content-card">
                    <div class="month-nav">
                        <button onclick="previousMonth()">← Previous</button>
                        <h4 id="currentMonth"></h4>
                        <button onclick="nextMonth()">Next →</button>
                    </div>
                    <div class="calendar" id="calendar"></div>
                </div>
            </div>
            
            <!-- Add Availability Tab -->
            <div id="add-tab" class="tab-content">
                <div class="content-card">
                    <h3>Set Available Meeting Times</h3>
                    <form id="availabilityForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date *</label>
                                <input type="date" id="availDate" required>
                            </div>
                            <div class="form-group">
                                <label>Start Time *</label>
                                <input type="time" id="availStartTime" required>
                            </div>
                            <div class="form-group">
                                <label>End Time *</label>
                                <input type="time" id="availEndTime" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Slot</button>
                        </div>
                    </form>
                    
                    <div style="margin-top: 30px;">
                        <h3>Your Available Slots</h3>
                        <div class="schedule-list" id="availableSlots">
                            <div class="loading">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Appointments Tab -->
            <div id="appointments-tab" class="tab-content">
                <div class="content-card">
                    <h3>Scheduled Appointments</h3>
                    <div class="schedule-list" id="appointmentsList">
                        <div class="loading">Loading...</div>
                    </div>
                </div>
            </div>
            
            <!-- Requests Tab -->
            <div id="requests-tab" class="tab-content">
                <div class="content-card">
                    <h3>Pending Meeting Requests</h3>
                    <div class="appointment-requests" id="requestsList">
                        <div class="loading">Loading...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentDate = new Date();
        let schedules = [];
        
        document.addEventListener('DOMContentLoaded', function() {
            renderCalendar();
            loadSchedules();
            loadAppointments();
            loadRequests();
            
            document.getElementById('availabilityForm').addEventListener('submit', addAvailability);
            
            // Set min date to today
            document.getElementById('availDate').min = new Date().toISOString().split('T')[0];
        });
        
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            
            document.getElementById('currentMonth').textContent = `${monthNames[month]} ${year}`;
            
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const daysInPrevMonth = new Date(year, month, 0).getDate();
            
            const calendar = document.getElementById('calendar');
            let html = '';
            
            // Day headers
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            days.forEach(day => {
                html += `<div class="calendar-header">${day}</div>`;
            });
            
            // Previous month days
            for (let i = firstDay - 1; i >= 0; i--) {
                html += `<div class="calendar-day other-month"><div class="calendar-day-number">${daysInPrevMonth - i}</div></div>`;
            }
            
            // Current month days
            const today = new Date();
            for (let day = 1; day <= daysInMonth; day++) {
                const isToday = today.getDate() === day && today.getMonth() === month && today.getFullYear() === year;
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                
                const daySchedules = schedules.filter(s => s.available_date === dateStr);
                let slotsHtml = '';
                daySchedules.forEach(schedule => {
                    const time = schedule.available_time.substring(0, 5);
                    const cssClass = schedule.is_booked ? 'booked' : '';
                    slotsHtml += `<div class="time-slot ${cssClass}" onclick="viewSchedule(${schedule.id})">${time}</div>`;
                });
                
                html += `
                    <div class="calendar-day ${isToday ? 'today' : ''}">
                        <div class="calendar-day-number">${day}</div>
                        ${slotsHtml}
                    </div>
                `;
            }
            
            calendar.innerHTML = html;
        }
        
        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        }
        
        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        }
        
        function addAvailability(e) {
            e.preventDefault();
            
            const date = document.getElementById('availDate').value;
            const startTime = document.getElementById('availStartTime').value;
            const endTime = document.getElementById('availEndTime').value;
            
            if (startTime >= endTime) {
                showAlert('End time must be after start time', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_availability');
            formData.append('date', date);
            formData.append('start_time', startTime);
            formData.append('end_time', endTime);
            
            fetch('prof_schedule_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(`${data.count} time slot(s) added successfully!`, 'success');
                    document.getElementById('availabilityForm').reset();
                    loadSchedules();
                    renderCalendar();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function loadSchedules() {
            fetch('prof_schedule_api.php?action=get_schedules')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        schedules = data.schedules;
                        renderSchedules();
                        renderCalendar();
                    }
                });
        }
        
        function renderSchedules() {
            const container = document.getElementById('availableSlots');
            
            const upcoming = schedules.filter(s => !s.is_booked && new Date(s.available_date) >= new Date());
            
            if (upcoming.length === 0) {
                container.innerHTML = '<p style="text-align:center; color:#999;">No available slots set</p>';
                return;
            }
            
            let html = '';
            upcoming.forEach(schedule => {
                html += `
                    <div class="schedule-item">
                        <div class="schedule-header">
                            <div>
                                <div class="schedule-date">${formatDate(schedule.available_date)}</div>
                                <div class="schedule-time">Time: ${schedule.available_time.substring(0, 5)}</div>
                            </div>
                            <button class="btn btn-danger btn-small" onclick="deleteSlot(${schedule.id})">Delete</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function loadAppointments() {
            fetch('prof_schedule_api.php?action=get_appointments')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderAppointments(data.appointments);
                    }
                });
        }
        
        function renderAppointments(appointments) {
            const container = document.getElementById('appointmentsList');
            
            if (appointments.length === 0) {
                container.innerHTML = '<p style="text-align:center; color:#999;">No scheduled appointments</p>';
                return;
            }
            
            let html = '';
            appointments.forEach(appt => {
                html += `
                    <div class="schedule-item">
                        <div class="schedule-header">
                            <div>
                                <div class="schedule-date">${formatDate(appt.available_date)}</div>
                                <div class="schedule-time">Time: ${appt.available_time.substring(0, 5)}</div>
                                <div class="schedule-student">Student: ${appt.student_name} (${appt.id_number})</div>
                            </div>
                            <span class="badge booked">Booked</span>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function loadRequests() {
            fetch('prof_schedule_api.php?action=get_requests')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderRequests(data.requests);
                    }
                });
        }
        
        function renderRequests(requests) {
            const container = document.getElementById('requestsList');
            
            if (requests.length === 0) {
                container.innerHTML = '<p style="text-align:center; color:#999;">No pending requests</p>';
                return;
            }
            
            let html = '';
            requests.forEach(req => {
                html += `
                    <div class="request-card">
                        <div><strong>Student:</strong> ${req.student_name} (${req.id_number})</div>
                        <div style="margin: 10px 0;"><strong>Study Plan:</strong> ${req.term}</div>
                        <div style="color: #666; font-size: 14px;">Submitted: ${formatDateTime(req.submission_date)}</div>
                        <div class="request-actions">
                            <button class="btn btn-success btn-small" onclick="scheduleForStudent(${req.student_id}, ${req.plan_id})">Schedule Meeting</button>
                            <button class="btn btn-danger btn-small" onclick="rejectRequest(${req.plan_id})">Decline</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function scheduleForStudent(studentId, planId) {
            // This would open a modal to select available slot
            const slot = prompt('Enter slot ID to assign:');
            if (!slot) return;
            
            const formData = new FormData();
            formData.append('action', 'assign_slot');
            formData.append('student_id', studentId);
            formData.append('plan_id', planId);
            formData.append('slot_id', slot);
            
            fetch('prof_schedule_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Meeting scheduled!', 'success');
                    loadSchedules();
                    loadAppointments();
                    loadRequests();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function rejectRequest(planId) {
            if (!confirm('Decline this meeting request?')) return;
            
            const formData = new FormData();
            formData.append('action', 'reject_request');
            formData.append('plan_id', planId);
            
            fetch('prof_schedule_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Request declined', 'success');
                    loadRequests();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function deleteSlot(slotId) {
            if (!confirm('Delete this time slot?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_slot');
            formData.append('slot_id', slotId);
            
            fetch('prof_schedule_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Slot deleted', 'success');
                    loadSchedules();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function viewSchedule(scheduleId) {
            const schedule = schedules.find(s => s.id === scheduleId);
            if (schedule) {
                if (schedule.is_booked) {
                    alert(`Booked by: ${schedule.student_name || 'Unknown'}`);
                } else {
                    alert('Available slot');
                }
            }
        }
        
        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `<div class="alert ${type}">${message}</div>`;
            setTimeout(() => container.innerHTML = '', 5000);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr + 'T00:00:00');
            return date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        }
        
        function formatDateTime(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }
    </script>
</body>
</html>