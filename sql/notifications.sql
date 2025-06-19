-- Create notification types table
CREATE TABLE IF NOT EXISTS notification_types (
    type_id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(100) NOT NULL,
    icon_class VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default notification types
INSERT INTO notification_types (type, title, icon_class) VALUES
('project_assignment', 'Project Assignment', 'fas fa-tasks'),
('leave_approval', 'Leave Approval', 'fas fa-calendar-check'),
('attendance_reminder', 'Attendance Reminder', 'fas fa-clock'),
('salary_payment', 'Salary Payment', 'fas fa-money-bill'),
('task_deadline', 'Task Deadline', 'fas fa-calendar-alt');

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
