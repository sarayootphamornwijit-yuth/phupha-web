-- ====================================================================
-- โครงสร้างฐานข้อมูลสำหรับ ชมรมเพื่อนภูผา (Phupha Club)
-- มหาวิทยาลัยราชภัฏเชียงใหม่ (ปีการศึกษา 2569)
-- รวมคำสั่งทั้งหมดไว้ในไฟล์เดียว ครบทั้ง 11 ตารางหลัก และ Master Data
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

-- --------------------------------------------------------------------
-- 0. เคลียร์ตารางที่ไม่ได้ใช้งานในระบบ (12 ตารางตกค้าง)
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `department_tasks`;
DROP TABLE IF EXISTS `base_summaries`;
DROP TABLE IF EXISTS `department_summaries`;
DROP TABLE IF EXISTS `activity_schedules`;
DROP TABLE IF EXISTS `activity_bases`;
DROP TABLE IF EXISTS `activity_roles`;
DROP TABLE IF EXISTS `activity_departments`;
DROP TABLE IF EXISTS `certificate_requests`;
DROP TABLE IF EXISTS `activity_documents`;
DROP TABLE IF EXISTS `advisor_feedbacks`;
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `inventory_items`;

-- --------------------------------------------------------------------
-- 1. ตารางคณะ (faculties) - Master Data
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faculties` (
    `faculty_id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_name` VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 2. ตารางสาขาวิชา (majors) - Master Data
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `majors` (
    `major_id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `major_name` VARCHAR(150) NOT NULL,
    KEY `idx_faculty` (`faculty_id`),
    CONSTRAINT `fk_majors_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- นำเข้า Master Data: 9 คณะ และ 77 สาขาวิชา มรภ.เชียงใหม่ ครบถ้วน
-- --------------------------------------------------------------------
INSERT IGNORE INTO `faculties` (`faculty_id`, `faculty_name`) VALUES
(1, 'คณะครุศาสตร์'),
(2, 'คณะวิทยาศาสตร์และเทคโนโลยี'),
(3, 'คณะวิทยาการจัดการ'),
(4, 'คณะมนุษยศาสตร์และสังคมศาสตร์'),
(5, 'คณะเทคโนโลยีการเกษตร'),
(6, 'คณะพยาบาลศาสตร์'),
(7, 'วิทยาลัยนานาชาติ'),
(8, 'วิทยาลัยแม่ฮ่องสอน'),
(9, 'วิทยาลัยพัฒนาเศรษฐกิจและเทคโนโลยีชุมชนแห่งเอเชีย (adiCET)');

INSERT IGNORE INTO `majors` (`major_id`, `faculty_id`, `major_name`) VALUES
-- คณะครุศาสตร์ (faculty_id = 1)
(1, 1, 'สาขาวิชาการศึกษาปฐมวัย'),
(2, 1, 'สาขาวิชาการประถมศึกษา'),
(3, 1, 'สาขาวิชาการศึกษาพิเศษ'),
(4, 1, 'สาขาวิชาจิตวิทยาการศึกษาและการแนะแนว'),
(5, 1, 'สาขาวิชาภาษาไทย'),
(6, 1, 'สาขาวิชาภาษาอังกฤษ'),
(7, 1, 'สาขาวิชาภาษาจีน'),
(8, 1, 'สาขาวิชาสังคมศึกษา'),
(9, 1, 'สาขาวิชาคณิตศาสตร์'),
(10, 1, 'สาขาวิชาวิทยาศาสตร์ทั่วไป'),
(11, 1, 'สาขาวิชาฟิสิกส์'),
(12, 1, 'สาขาวิชาเคมี'),
(13, 1, 'สาขาวิชาชีววิทยา'),
(14, 1, 'สาขาวิชาคอมพิวเตอร์ศึกษา'),
(15, 1, 'สาขาวิชาพลศึกษา'),
(16, 1, 'สาขาวิชาศิลปศึกษา'),
(17, 1, 'สาขาวิชาดนตรีศึกษา'),
(18, 1, 'สาขาวิชานาฏศิลป์ศึกษา'),
(19, 1, 'สาขาวิชาเกษตรศาสตร์ (ครุศาสตร์)'),
(20, 1, 'สาขาวิชาอุตสาหกรรมศิลป์'),

-- คณะวิทยาศาสตร์และเทคโนโลยี (faculty_id = 2)
(21, 2, 'สาขาวิชาวิทยาการคอมพิวเตอร์'),
(22, 2, 'สาขาวิชาเทคโนโลยีสารสนเทศ'),
(23, 2, 'สาขาวิชาเทคโนโลยีคอมพิวเตอร์และดิจิทัล'),
(24, 2, 'สาขาวิชาเคมี'),
(25, 2, 'สาขาวิชาชีววิทยา'),
(26, 2, 'สาขาวิชาคณิตศาสตร์'),
(27, 2, 'สาขาวิชาวิทยาการข้อมูลเชิงสถิติ'),
(28, 2, 'สาขาวิชาวิทยาศาสตร์สิ่งแวดล้อม'),
(29, 2, 'สาขาวิชาคหกรรมศาสตร์'),
(30, 2, 'สาขาวิชาการออกแบบผลิตภัณฑ์'),
(31, 2, 'สาขาวิชาเทคโนโลยีสถาปัตยกรรม'),
(32, 2, 'สาขาวิชาเทคโนโลยีวิศวกรรมการก่อสร้าง'),
(33, 2, 'สาขาวิชาเทคโนโลยีอุตสาหกรรม'),
(34, 2, 'สาขาวิชาอาชีวอนามัยและความปลอดภัย'),
(35, 2, 'สาขาวิชาสาธารณสุขศาสตร์'),
(36, 2, 'สาขาวิชาวิศวกรรมโยธา'),

-- คณะวิทยาการจัดการ (faculty_id = 3)
(37, 3, 'สาขาวิชาการจัดการ'),
(38, 3, 'สาขาวิชาการตลาด'),
(39, 3, 'สาขาวิชาการบัญชี'),
(40, 3, 'สาขาวิชาการบริหารทรัพยากรมนุษย์'),
(41, 3, 'สาขาวิชาคอมพิวเตอร์ธุรกิจและเทคโนโลยีธุรกิจดิจิทัล'),
(42, 3, 'สาขาวิชาการเป็นผู้ประกอบการ'),
(43, 3, 'สาขาวิชาพาณิชย์ดิจิทัล'),
(44, 3, 'สาขาวิชานิเทศศาสตร์'),
(45, 3, 'สาขาวิชาเศรษฐศาสตร์'),
(46, 3, 'สาขาวิชาการจัดการโลจิสติกส์และซัพพลายเชน'),

-- คณะมนุษยศาสตร์และสังคมศาสตร์ (faculty_id = 4)
(47, 4, 'สาขาวิชาภาษาไทย'),
(48, 4, 'สาขาวิชาภาษาอังกฤษ'),
(49, 4, 'สาขาวิชาภาษาอังกฤษธุรกิจ'),
(50, 4, 'สาขาวิชาภาษาจีน'),
(51, 4, 'สาขาวิชาภาษาญี่ปุ่น'),
(52, 4, 'สาขาวิชาภาษาเกาหลี'),
(53, 4, 'สาขาวิชาภาษาฝรั่งเศสเพื่ออุตสาหกรรมการบริการ'),
(54, 4, 'สาขาวิชานิติศาสตร์'),
(55, 4, 'สาขาวิชารัฐประศาสนศาสตร์'),
(56, 4, 'สาขาวิชาการพัฒนาชุมชน'),
(57, 4, 'สาขาวิชาการท่องเที่ยวและการโรงแรม'),
(58, 4, 'สาขาวิชาสารสนเทศศาสตร์'),
(59, 4, 'สาขาวิชาภูมิศาสตร์และภูมิสารสนเทศ'),
(60, 4, 'สาขาวิชาศิลปะและการออกแบบ'),
(61, 4, 'สาขาวิชาศิลปะการแสดง'),
(62, 4, 'สาขาวิชาดนตรีสากล'),

-- คณะเทคโนโลยีการเกษตร (faculty_id = 5)
(63, 5, 'สาขาวิชาเกษตรศาสตร์'),
(64, 5, 'สาขาวิชาเทคโนโลยีการอาหาร'),
(65, 5, 'สาขาวิชาสัตวศาสตร์'),

-- คณะพยาบาลศาสตร์ (faculty_id = 6)
(66, 6, 'สาขาวิชาพยาบาลศาสตร์'),

-- วิทยาลัยนานาชาติ (faculty_id = 7)
(67, 7, 'สาขาวิชาภาษาอังกฤษเพื่อการสื่อสารระหว่างประเทศ (หลักสูตรนานาชาติ)'),
(68, 7, 'สาขาวิชาการจัดการธุรกิจระหว่างประเทศ (หลักสูตรนานาชาติ)'),
(69, 7, 'สาขาวิชาภาษาจีนธุรกิจ (หลักสูตรนานาชาติ)'),
(70, 7, 'สาขาวิชาภาษาไทยเพื่อธุรกิจสำหรับชาวต่างชาติ (หลักสูตรนานาชาติ)'),
(71, 7, 'สาขาวิชาการจัดการธุรกิจการบิน (หลักสูตรนานาชาติ)'),

-- วิทยาลัยแม่ฮ่องสอน (faculty_id = 8)
(72, 8, 'สาขาวิชาการศึกษาปฐมวัย (พื้นที่แม่ฮ่องสอน)'),
(73, 8, 'สาขาวิชาการประถมศึกษา (พื้นที่แม่ฮ่องสอน)'),
(74, 8, 'สาขาวิชาการจัดการ (พื้นที่แม่ฮ่องสอน)'),
(75, 8, 'สาขาวิชาการท่องเที่ยวและการโรงแรม (พื้นที่แม่ฮ่องสอน)'),
(76, 8, 'สาขาวิชาสาธารณสุขชุมชน (พื้นที่แม่ฮ่องสอน)'),

-- วิทยาลัยพัฒนาเศรษฐกิจและเทคโนโลยีชุมชนแห่งเอเชีย (adiCET) (faculty_id = 9)
(77, 9, 'สาขาวิชาเทคโนโลยีพลังงานและสิ่งแวดล้อมชุมชน');

-- --------------------------------------------------------------------
-- 3. ตารางผู้ใช้งาน (users)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `title` VARCHAR(10) NOT NULL DEFAULT '',
    `name` VARCHAR(255) NOT NULL,
    `user_type` ENUM('student', 'outsider') DEFAULT 'student',
    `occupation` VARCHAR(255) DEFAULT NULL,
    `faculty_id` INT DEFAULT NULL,
    `major_id` INT DEFAULT NULL,
    `faculty` VARCHAR(255) DEFAULT NULL,
    `major` VARCHAR(255) DEFAULT NULL,
    `student_id` VARCHAR(50) DEFAULT NULL,
    `age` INT DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `interests` TEXT DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('member', 'committee', 'leader', 'advisor', 'admin') DEFAULT 'member',
    `committee_position` VARCHAR(50) DEFAULT NULL,
    `academic_year` TINYINT DEFAULT NULL,
    `generation` INT DEFAULT NULL,
    `points` INT DEFAULT 0,
    `profile_picture` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_user_faculty` (`faculty_id`),
    KEY `idx_user_major` (`major_id`),
    CONSTRAINT `fk_users_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`faculty_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_users_major` FOREIGN KEY (`major_id`) REFERENCES `majors` (`major_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 4. ตารางกิจกรรม (activities)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activities` (
    `activity_id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `cover_image` VARCHAR(255) DEFAULT NULL,
    `facebook_link` VARCHAR(255) DEFAULT NULL,
    `activity_type` ENUM('single_day', 'multi_day') DEFAULT 'single_day',
    `category` ENUM('volunteer', 'sport', 'byaniyan', 'other') DEFAULT 'volunteer',
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME DEFAULT NULL,
    `location_name` VARCHAR(255) DEFAULT NULL,
    `meeting_point` VARCHAR(255) DEFAULT NULL,
    `meeting_time` DATETIME DEFAULT NULL,
    `max_participants` INT DEFAULT 0,
    `points` INT DEFAULT 0,
    `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    `report_status` ENUM('draft', 'submitted', 'approved', 'rejected') DEFAULT 'draft',
    `venue_contact_user_id` INT DEFAULT NULL,
    `advisor_id` INT DEFAULT NULL,
    `self_checkin_enabled` TINYINT(1) DEFAULT 0,
    `objectives` TEXT DEFAULT NULL,
    `targets` TEXT DEFAULT NULL,
    `methods` TEXT DEFAULT NULL,
    `budget_source` VARCHAR(100) DEFAULT NULL,
    `survey_details` TEXT DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_venue_contact` (`venue_contact_user_id`),
    KEY `idx_advisor` (`advisor_id`),
    CONSTRAINT `fk_activities_venue_contact` FOREIGN KEY (`venue_contact_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_activities_advisor` FOREIGN KEY (`advisor_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 5. ตารางยานพาหนะ (vehicles)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicles` (
    `vehicle_id` INT AUTO_INCREMENT PRIMARY KEY,
    `activity_id` INT NOT NULL,
    `vehicle_type` VARCHAR(100) NOT NULL,
    `license_plate` VARCHAR(50) NOT NULL,
    `driver_name` VARCHAR(100) NOT NULL,
    `driver_phone` VARCHAR(50) DEFAULT NULL,
    `capacity` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_vehicle_activity` (`activity_id`),
    CONSTRAINT `fk_vehicles_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`activity_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 6. ตารางการลงทะเบียนกิจกรรม (user_activities)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_activities` (
    `user_activity_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `activity_id` INT NOT NULL,
    `status` ENUM('registered', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'registered',
    `check_in_time` DATETIME DEFAULT NULL,
    `check_out_time` DATETIME DEFAULT NULL,
    `travel_method` VARCHAR(50) DEFAULT NULL,
    `vehicle_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ua_user` (`user_id`),
    KEY `idx_ua_activity` (`activity_id`),
    KEY `idx_ua_vehicle` (`vehicle_id`),
    CONSTRAINT `fk_ua_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ua_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`activity_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ua_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 7. ตารางประเมินผลกิจกรรม (evaluations)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evaluations` (
    `evaluation_id` INT AUTO_INCREMENT PRIMARY KEY,
    `activity_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `q1` TINYINT NOT NULL DEFAULT 0,
    `q2` TINYINT NOT NULL DEFAULT 0,
    `q3` TINYINT NOT NULL DEFAULT 0,
    `q4` TINYINT NOT NULL DEFAULT 0,
    `q5` TINYINT NOT NULL DEFAULT 0,
    `q6` TINYINT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_activity_eval` (`user_id`, `activity_id`),
    KEY `idx_eval_activity` (`activity_id`),
    CONSTRAINT `fk_eval_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`activity_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_eval_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 8. ตารางบัญชีรายรับ-รายจ่าย (budget_transactions)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `budget_transactions` (
    `transaction_id` INT AUTO_INCREMENT PRIMARY KEY,
    `activity_id` INT NOT NULL,
    `transaction_type` ENUM('income', 'expense', 'allocation') NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `receipt_file` VARCHAR(255) DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_budget_activity` (`activity_id`),
    KEY `idx_budget_creator` (`created_by`),
    CONSTRAINT `fk_budget_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`activity_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_budget_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 9. ตารางข่าวประชาสัมพันธ์ (news)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `news` (
    `news_id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `author_id` INT NOT NULL,
    `is_published` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_news_author` (`author_id`),
    CONSTRAINT `fk_news_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 10. ตารางเกียรติบัตร (certificates)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certificates` (
    `certificate_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `activity_id` INT DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `date_issued` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_cert_user` (`user_id`),
    KEY `idx_cert_activity` (`activity_id`),
    CONSTRAINT `fk_cert_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cert_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`activity_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 11. ตารางตั้งค่าระบบ (club_settings)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `club_settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` VARCHAR(255) NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลตั้งค่าระบบเริ่มต้น: เกณฑ์คะแนนจิตอาสารับเกียรติบัตร (50 แต้ม)
INSERT IGNORE INTO `club_settings` (`setting_key`, `setting_value`) VALUES 
('certificate_point_threshold', '50');

-- --------------------------------------------------------------------
-- บัญชีผู้ดูแลระบบเริ่มต้น (Admin)
-- รหัสผ่านเริ่มต้น: admin123
-- --------------------------------------------------------------------
INSERT IGNORE INTO `users` (`username`, `password`, `name`, `role`) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- เปิดการตรวจสอบ Foreign Key กลับมาทำงานตามปกติ
SET FOREIGN_KEY_CHECKS = 1;
