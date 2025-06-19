<?php
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin':
            return 'danger';
        case 'hr':
            return 'info';
        case 'manager':
            return 'warning';
        case 'employee':
            return 'success';
        default:
            return 'secondary';
    }
}

function getProjectStatusBadgeClass($status) {
    switch ($status) {
        case 'planning':
            return 'info';
        case 'ongoing':
            return 'primary';
        case 'completed':
            return 'success';
        case 'on-hold':
            return 'warning';
        default:
            return 'secondary';
    }
}

function getActivityTypeBadgeClass($type) {
    switch ($type) {
        case 'login':
            return 'info';
        case 'logout':
            return 'secondary';
        case 'create':
            return 'success';
        case 'update':
            return 'primary';
        case 'delete':
            return 'danger';
        case 'leave_update':
            return 'warning';
        case 'attendance_update':
            return 'info';
        default:
            return 'secondary';
    }
}

function getLeaveStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'approved':
            return 'success';
        case 'rejected':
            return 'danger';
        default:
            return 'secondary';
    }
} 