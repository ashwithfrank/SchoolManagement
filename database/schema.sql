-- AlphaOmega School Management System - baseline schema (version 1)
-- Engine: InnoDB, charset utf8mb4. Compatible with MariaDB 10.4+ (XAMPP) and MySQL 5.7+/8.x.
-- Money columns are DECIMAL(12,2) rupees. Dates are DATE; timestamps are DATETIME.
-- Statements are separated by a semicolon at the end of a line.

CREATE TABLE schema_migrations (
  version     INT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  applied_at  DATETIME NOT NULL,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE app_settings (
  setting_key   VARCHAR(60) NOT NULL,
  setting_value TEXT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE counters (
  name   VARCHAR(40) NOT NULL,
  value  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE roles (
  id    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code  VARCHAR(20) NOT NULL,
  name  VARCHAR(40) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE role_permissions (
  role_id     TINYINT UNSIGNED NOT NULL,
  permission  VARCHAR(40) NOT NULL,
  PRIMARY KEY (role_id, permission),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employees (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_code VARCHAR(30) NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  phone         VARCHAR(20) NULL,
  email         VARCHAR(150) NULL,
  address       VARCHAR(300) NULL,
  designation   VARCHAR(80) NULL,
  start_date    DATE NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employee_code (employee_code),
  KEY ix_employee_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username       VARCHAR(40) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  full_name      VARCHAR(120) NOT NULL,
  role_id        TINYINT UNSIGNED NOT NULL,
  employee_id    INT UNSIGNED NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at  DATETIME NULL,
  created_at     DATETIME NOT NULL,
  updated_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_employee (employee_id),
  KEY ix_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id),
  CONSTRAINT fk_users_employee FOREIGN KEY (employee_id) REFERENCES employees (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60) NOT NULL,
  ip            VARCHAR(45) NOT NULL,
  attempted_at  DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_la_user (username, attempted_at),
  KEY ix_la_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE academic_years (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(20) NOT NULL,
  start_date   DATE NOT NULL,
  end_date     DATE NOT NULL,
  active_flag  TINYINT(1) NULL DEFAULT NULL COMMENT '1 for the single active year, NULL otherwise',
  created_at   DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_year_name (name),
  UNIQUE KEY uq_year_active (active_flag),
  CONSTRAINT ck_year_dates CHECK (end_date > start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE classes (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(20) NOT NULL,
  sort_order  SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_class_name (name),
  UNIQUE KEY uq_class_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sections (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id   SMALLINT UNSIGNED NOT NULL,
  name       VARCHAR(20) NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_section (class_id, name),
  UNIQUE KEY uq_section_class (id, class_id),
  CONSTRAINT fk_section_class FOREIGN KEY (class_id) REFERENCES classes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE section_coordinators (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  section_id       INT UNSIGNED NOT NULL,
  employee_id      INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coord (academic_year_id, section_id),
  KEY ix_coord_emp (employee_id),
  CONSTRAINT fk_coord_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id),
  CONSTRAINT fk_coord_section FOREIGN KEY (section_id) REFERENCES sections (id),
  CONSTRAINT fk_coord_emp FOREIGN KEY (employee_id) REFERENCES employees (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subjects (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(80) NOT NULL,
  code       VARCHAR(20) NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subject_name (name),
  UNIQUE KEY uq_subject_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employee_subjects (
  employee_id INT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (employee_id, subject_id),
  CONSTRAINT fk_es_emp FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_es_sub FOREIGN KEY (subject_id) REFERENCES subjects (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE teaching_assignments (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  employee_id      INT UNSIGNED NOT NULL,
  section_id       INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assign (academic_year_id, section_id, subject_id),
  KEY ix_assign_emp (employee_id, academic_year_id),
  CONSTRAINT fk_ta_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id),
  CONSTRAINT fk_ta_emp FOREIGN KEY (employee_id) REFERENCES employees (id),
  CONSTRAINT fk_ta_section FOREIGN KEY (section_id) REFERENCES sections (id),
  CONSTRAINT fk_ta_subject FOREIGN KEY (subject_id) REFERENCES subjects (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE students (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_code     VARCHAR(20) NOT NULL,
  admission_no     VARCHAR(30) NOT NULL,
  sats_no          VARCHAR(30) NULL,
  full_name        VARCHAR(120) NOT NULL,
  gender           ENUM('male','female','other') NOT NULL,
  date_of_birth    DATE NOT NULL,
  admission_date   DATE NOT NULL,
  father_name      VARCHAR(120) NULL,
  mother_name      VARCHAR(120) NULL,
  guardian_name    VARCHAR(120) NULL,
  phone_primary    VARCHAR(20) NOT NULL,
  phone_secondary  VARCHAR(20) NULL,
  email            VARCHAR(150) NULL,
  addr_house       VARCHAR(100) NULL,
  addr_street      VARCHAR(100) NULL,
  addr_locality    VARCHAR(100) NULL,
  addr_village     VARCHAR(100) NULL,
  addr_post_office VARCHAR(100) NULL,
  addr_taluk       VARCHAR(100) NULL,
  addr_district    VARCHAR(100) NULL,
  addr_state       VARCHAR(100) NULL,
  addr_pin         CHAR(6) NULL,
  photo_file       VARCHAR(60) NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  notes            TEXT NULL,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL,
  updated_at       DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_code (student_code),
  UNIQUE KEY uq_student_adm (admission_no),
  UNIQUE KEY uq_student_sats (sats_no),
  KEY ix_student_name (full_name),
  KEY ix_student_active (is_active),
  CONSTRAINT fk_student_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE enrollments (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id         INT UNSIGNED NOT NULL,
  academic_year_id   INT UNSIGNED NOT NULL,
  class_id           SMALLINT UNSIGNED NOT NULL,
  section_id         INT UNSIGNED NOT NULL,
  roll_no            SMALLINT UNSIGNED NULL,
  status             ENUM('active','promoted','repeated','completed','left') NOT NULL DEFAULT 'active',
  promoted_from_id   INT UNSIGNED NULL,
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL,
  updated_at         DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enroll_student_year (student_id, academic_year_id),
  UNIQUE KEY uq_enroll_roll (academic_year_id, section_id, roll_no),
  KEY ix_enroll_section (academic_year_id, section_id),
  KEY ix_enroll_class (academic_year_id, class_id),
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES students (id),
  CONSTRAINT fk_enroll_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id),
  CONSTRAINT fk_enroll_section FOREIGN KEY (section_id, class_id) REFERENCES sections (id, class_id),
  CONSTRAINT fk_enroll_from FOREIGN KEY (promoted_from_id) REFERENCES enrollments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE buses (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bus_code       VARCHAR(20) NOT NULL,
  vehicle_no     VARCHAR(20) NOT NULL,
  driver_name    VARCHAR(120) NULL,
  driver_phone   VARCHAR(20) NULL,
  insurance_expiry DATE NULL,
  fc_expiry      DATE NULL,
  capacity       SMALLINT UNSIGNED NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL,
  updated_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bus_code (bus_code),
  UNIQUE KEY uq_bus_vehicle (vehicle_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transport_assignments (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  bus_id           INT UNSIGNED NOT NULL,
  pickup_location  VARCHAR(150) NULL,
  distance_km      DECIMAL(6,2) NULL,
  fee              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  start_date       DATE NOT NULL,
  end_date         DATE NULL,
  status           ENUM('active','ended','cancelled') NOT NULL DEFAULT 'active',
  active_flag      TINYINT(1) NULL DEFAULT NULL COMMENT '1 while active: one active assignment per student per year',
  end_reason       VARCHAR(200) NULL,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL,
  updated_at       DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ta_active (student_id, academic_year_id, active_flag),
  KEY ix_ta_bus (bus_id, academic_year_id, status),
  KEY ix_ta_year (academic_year_id, status),
  CONSTRAINT fk_tra_student FOREIGN KEY (student_id) REFERENCES students (id),
  CONSTRAINT fk_tra_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id),
  CONSTRAINT fk_tra_bus FOREIGN KEY (bus_id) REFERENCES buses (id),
  CONSTRAINT ck_tra_fee CHECK (fee >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fee_structures (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  class_id         SMALLINT UNSIGNED NOT NULL,
  academic_fee     DECIMAL(12,2) NOT NULL,
  updated_by       INT UNSIGNED NULL,
  updated_at       DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fs (academic_year_id, class_id),
  CONSTRAINT fk_fs_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id),
  CONSTRAINT fk_fs_class FOREIGN KEY (class_id) REFERENCES classes (id),
  CONSTRAINT ck_fs_fee CHECK (academic_fee >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per student per year: the academic fee assessed to that student (snapshot of the structure,
-- editable with a reason). Transport fees are assessed from transport_assignments.
CREATE TABLE student_fee_assessments (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  category         ENUM('academic') NOT NULL DEFAULT 'academic',
  amount           DECIMAL(12,2) NOT NULL,
  source           ENUM('structure','manual') NOT NULL DEFAULT 'structure',
  note             VARCHAR(200) NULL,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL,
  updated_at       DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sfa (student_id, academic_year_id, category),
  KEY ix_sfa_year (academic_year_id),
  CONSTRAINT fk_sfa_student FOREIGN KEY (student_id) REFERENCES students (id),
  CONSTRAINT fk_sfa_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id),
  CONSTRAINT ck_sfa_amt CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fee_adjustments (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  category         ENUM('academic','transport') NOT NULL,
  amount           DECIMAL(12,2) NOT NULL,
  reason           VARCHAR(200) NOT NULL,
  status           ENUM('active','void') NOT NULL DEFAULT 'active',
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL,
  voided_by        INT UNSIGNED NULL,
  voided_at        DATETIME NULL,
  void_reason      VARCHAR(200) NULL,
  PRIMARY KEY (id),
  KEY ix_fa_student (student_id, academic_year_id, status),
  CONSTRAINT fk_fa_student FOREIGN KEY (student_id) REFERENCES students (id),
  CONSTRAINT fk_fa_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id),
  CONSTRAINT ck_fa_amt CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  receipt_no       VARCHAR(30) NOT NULL,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  category         ENUM('academic','transport') NOT NULL,
  amount           DECIMAL(12,2) NOT NULL,
  payment_date     DATE NOT NULL,
  method           ENUM('cash','upi','cheque','bank_transfer','card','other') NOT NULL,
  reference_no     VARCHAR(60) NULL,
  notes            VARCHAR(300) NULL,
  status           ENUM('valid','reversed') NOT NULL DEFAULT 'valid',
  idempotency_key  CHAR(32) NOT NULL,
  recorded_by      INT UNSIGNED NOT NULL,
  created_at       DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_receipt (receipt_no),
  UNIQUE KEY uq_pay_idem (idempotency_key),
  KEY ix_pay_student (student_id, academic_year_id, status),
  KEY ix_pay_date (payment_date),
  KEY ix_pay_year (academic_year_id, category, status),
  CONSTRAINT fk_pay_student FOREIGN KEY (student_id) REFERENCES students (id),
  CONSTRAINT fk_pay_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id),
  CONSTRAINT fk_pay_user FOREIGN KEY (recorded_by) REFERENCES users (id),
  CONSTRAINT ck_pay_amt CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A reversal never edits the amount of the original payment; it marks it reversed and records who/why.
CREATE TABLE payment_reversals (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id   INT UNSIGNED NOT NULL,
  reason       VARCHAR(300) NOT NULL,
  reversed_by  INT UNSIGNED NOT NULL,
  reversed_at  DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rev_payment (payment_id),
  CONSTRAINT fk_rev_payment FOREIGN KEY (payment_id) REFERENCES payments (id),
  CONSTRAINT fk_rev_user FOREIGN KEY (reversed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exams (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year_id INT UNSIGNED NOT NULL,
  name             VARCHAR(80) NOT NULL,
  exam_date        DATE NULL,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exam (academic_year_id, name),
  CONSTRAINT fk_exam_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE exam_papers (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id     INT UNSIGNED NOT NULL,
  class_id    SMALLINT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  max_marks   DECIMAL(6,2) NOT NULL,
  min_marks   DECIMAL(6,2) NOT NULL,
  updated_by  INT UNSIGNED NULL,
  updated_at  DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_paper (exam_id, class_id, subject_id),
  CONSTRAINT fk_ep_exam FOREIGN KEY (exam_id) REFERENCES exams (id),
  CONSTRAINT fk_ep_class FOREIGN KEY (class_id) REFERENCES classes (id),
  CONSTRAINT fk_ep_subject FOREIGN KEY (subject_id) REFERENCES subjects (id),
  CONSTRAINT ck_ep_marks CHECK (max_marks > 0 AND min_marks >= 0 AND min_marks <= max_marks)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- scored_marks NULL with is_absent = 0 is not stored (a missing mark simply has no row).
CREATE TABLE marks (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_paper_id INT UNSIGNED NOT NULL,
  enrollment_id INT UNSIGNED NOT NULL,
  scored_marks  DECIMAL(6,2) NULL,
  is_absent     TINYINT(1) NOT NULL DEFAULT 0,
  entered_by    INT UNSIGNED NOT NULL,
  updated_by    INT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mark (exam_paper_id, enrollment_id),
  KEY ix_mark_enrollment (enrollment_id),
  CONSTRAINT fk_mk_paper FOREIGN KEY (exam_paper_id) REFERENCES exam_papers (id),
  CONSTRAINT fk_mk_enroll FOREIGN KEY (enrollment_id) REFERENCES enrollments (id),
  CONSTRAINT fk_mk_entered FOREIGN KEY (entered_by) REFERENCES users (id),
  CONSTRAINT fk_mk_updated FOREIGN KEY (updated_by) REFERENCES users (id),
  CONSTRAINT ck_mk_absent CHECK ((is_absent = 1 AND scored_marks IS NULL) OR (is_absent = 0 AND scored_marks IS NOT NULL AND scored_marks >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NULL,
  username     VARCHAR(60) NULL,
  action       VARCHAR(60) NOT NULL,
  entity_type  VARCHAR(40) NULL,
  entity_id    VARCHAR(40) NULL,
  details      TEXT NULL,
  ip_address   VARCHAR(45) NULL,
  created_at   DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_audit_time (created_at),
  KEY ix_audit_action (action),
  KEY ix_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE backups (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename       VARCHAR(160) NOT NULL,
  directory      VARCHAR(300) NOT NULL,
  kind           ENUM('manual','safety','imported') NOT NULL DEFAULT 'manual',
  is_encrypted   TINYINT(1) NOT NULL DEFAULT 0,
  includes_photos TINYINT(1) NOT NULL DEFAULT 1,
  size_bytes     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sha256         CHAR(64) NOT NULL,
  schema_version INT UNSIGNED NOT NULL,
  note           VARCHAR(200) NULL,
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_backup_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
