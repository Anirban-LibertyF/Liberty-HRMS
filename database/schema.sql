-- Liberty Office schema (MySQL / MariaDB)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_log, notifications, documents, payslips, announcements, work_logs,
  week_offs, leave_requests, leave_types, holidays, regularizations, attendance, punch_events, users, shifts;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  label VARCHAR(40) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  required_min INT NOT NULL DEFAULT 420,
  break_allow_min INT NOT NULL DEFAULT 60,
  grace_min INT NOT NULL DEFAULT 15
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  emp_code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  profile_photo VARCHAR(255) NULL,
  role ENUM('employee','manager','admin') NOT NULL DEFAULT 'employee',
  department VARCHAR(80) NOT NULL DEFAULT '',
  team VARCHAR(80) NOT NULL DEFAULT '',
  designation VARCHAR(120) NOT NULL DEFAULT '',
  manager_id INT NULL,
  shift_id INT NOT NULL DEFAULT 1,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  dob DATE NULL,
  gender VARCHAR(20) NOT NULL DEFAULT '',
  blood_group VARCHAR(5) NOT NULL DEFAULT '',
  address VARCHAR(300) NOT NULL DEFAULT '',
  emergency_name VARCHAR(120) NOT NULL DEFAULT '',
  emergency_phone VARCHAR(30) NOT NULL DEFAULT '',
  employment_type VARCHAR(30) NOT NULL DEFAULT 'Full-time',
  work_location VARCHAR(80) NOT NULL DEFAULT 'Headquarters',
  joined_on DATE NOT NULL,
  base_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (shift_id) REFERENCES shifts(id),
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE punch_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  work_date DATE NOT NULL,
  event_type ENUM('in','out','break_start','break_end') NOT NULL,
  event_time DATETIME NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  location VARCHAR(120) NOT NULL DEFAULT '',
  source VARCHAR(30) NOT NULL DEFAULT 'Web Portal',
  INDEX (user_id, work_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  work_date DATE NOT NULL,
  status ENUM('present','half_day') NOT NULL DEFAULT 'present',
  first_in DATETIME NULL,
  last_out DATETIME NULL,
  worked_min INT NOT NULL DEFAULT 0,
  break_min INT NOT NULL DEFAULT 0,
  overtime_min INT NOT NULL DEFAULT 0,
  is_late TINYINT(1) NOT NULL DEFAULT 0,
  ip_in VARCHAR(45) NOT NULL DEFAULT '',
  ip_out VARCHAR(45) NOT NULL DEFAULT '',
  loc_in VARCHAR(120) NOT NULL DEFAULT '',
  loc_out VARCHAR(120) NOT NULL DEFAULT '',
  regularized TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_user_day (user_id, work_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE regularizations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  work_date DATE NOT NULL,
  req_in TIME NOT NULL,
  req_out TIME NOT NULL,
  reason VARCHAR(300) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  review_note VARCHAR(300) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE holidays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  holiday_date DATE NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  note VARCHAR(160) NOT NULL DEFAULT 'Corporate Paid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE week_offs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  off_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_off (user_id, off_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leave_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  badge VARCHAR(40) NOT NULL,
  quota DECIMAL(5,1) NOT NULL DEFAULT 0,
  is_paid TINYINT(1) NOT NULL DEFAULT 1,
  color VARCHAR(10) NOT NULL DEFAULT '#006d5b',
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leave_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  leave_type_id INT NOT NULL,
  from_date DATE NOT NULL,
  end_date DATE NOT NULL,
  days DECIMAL(4,1) NOT NULL,
  half_day TINYINT(1) NOT NULL DEFAULT 0,
  reason VARCHAR(300) NOT NULL,
  doc_name VARCHAR(200) NULL,
  doc_path VARCHAR(300) NULL,
  status ENUM('draft','pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  applied_at DATETIME NOT NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  review_note VARCHAR(300) NOT NULL DEFAULT '',
  INDEX (user_id, from_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE work_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  log_date DATE NOT NULL,
  content TEXT NOT NULL,
  tag VARCHAR(120) NOT NULL DEFAULT '',
  attach_name VARCHAR(200) NULL,
  attach_path VARCHAR(300) NULL,
  status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  review_note VARCHAR(300) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_user_log (user_id, log_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dept VARCHAR(60) NOT NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  posted_on DATE NOT NULL,
  created_by INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payslips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  pay_year SMALLINT NOT NULL,
  pay_month TINYINT NOT NULL,
  basic DECIMAL(12,2) NOT NULL,
  hra DECIMAL(12,2) NOT NULL,
  allowances DECIMAL(12,2) NOT NULL,
  overtime_pay DECIMAL(12,2) NOT NULL DEFAULT 0,
  pf DECIMAL(12,2) NOT NULL,
  tax DECIMAL(12,2) NOT NULL,
  prof_tax DECIMAL(12,2) NOT NULL,
  lop_days DECIMAL(4,1) NOT NULL DEFAULT 0,
  lop_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_pay DECIMAL(12,2) NOT NULL,
  generated_at DATETIME NOT NULL,
  UNIQUE KEY uq_slip (user_id, pay_year, pay_month),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  title VARCHAR(200) NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'General',
  file_name VARCHAR(200) NOT NULL,
  file_path VARCHAR(300) NOT NULL,
  uploaded_by INT NULL,
  uploaded_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message VARCHAR(300) NOT NULL,
  link VARCHAR(200) NOT NULL DEFAULT '',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX (user_id, is_read),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  icon VARCHAR(20) NOT NULL DEFAULT 'check',
  title VARCHAR(200) NOT NULL,
  detail VARCHAR(300) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  INDEX (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
