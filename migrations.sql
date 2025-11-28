create database PMS;
use PMS;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

select * from roles;


CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    photo LONGBLOB,
    mobile VARCHAR(20),
    email VARCHAR(150) UNIQUE,
    description TEXT,
    doj DATE,
    dob DATE,
    role_id INT NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_user_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

select * from users;

CREATE TABLE credential (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_cred_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
);

select * from credential;

CREATE TABLE consultants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialization VARCHAR(150) NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_consultant_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
);

select * from consultants;

CREATE TABLE user_session (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    refresh_token TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,

    CONSTRAINT fk_session_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
);

select * from consultants;

INSERT INTO roles (role_name, status, created_at, updated_at)
VALUES
('Administrator', 'active', NOW(), NOW()),
('Receptionist', 'active', NOW(), NOW()),
('Consultant', 'active', NOW(), NOW());


INSERT INTO users (name, mobile, email, role_id, status, created_at, updated_at)
VALUES ('Admin', '9999999999', 'admin@test.com', 1, 'active', NOW(), NOW());


INSERT INTO credential (user_id, username, password_hash, updated_on)
VALUES (
  7,  
  'siva', 
  '$2y$10$DEqs80nAod8khBt1fxaUxeVCrH/CWDvrVxC5jEoRBntnVmOqT5S02',
  NOW()
);


CREATE TABLE patients (
  id INT AUTO_INCREMENT PRIMARY KEY,

  visitor_date DATETIME,
  name VARCHAR(255) NOT NULL,
  father_spouse_name VARCHAR(255),
  mobile_no VARCHAR(20),
  email VARCHAR(255),

  date_of_birth DATE,
  age INT,
  gender VARCHAR(20),
  marital_status VARCHAR(30),
  blood_group VARCHAR(10),

  address TEXT,
  city VARCHAR(100),
  state VARCHAR(100),
  occupation VARCHAR(100),

  patient_type VARCHAR(50),

  referred_by VARCHAR(255),
  referred_person_mobile VARCHAR(20),

  consultant_id INT,
  consultant_doctor VARCHAR(255),

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_consultant
    FOREIGN KEY (consultant_id) REFERENCES consultants(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);

select * from patients;

CREATE TABLE IF NOT EXISTS medical_information (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,

  main_complaints TEXT,
  other_complaint_1 VARCHAR(255),
  other_complaint_2 VARCHAR(255),
  other_complaint_3 VARCHAR(255),

  medicine_1 VARCHAR(255),
  medicine_1_date DATE,
  medicine_2 VARCHAR(255),
  medicine_2_date DATE,
  medicine_3 VARCHAR(255),
  medicine_3_date DATE,

  pre_case_file VARCHAR(255),
  case_file VARCHAR(255),
  report_file VARCHAR(255),

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_medinfo_patient
    FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE CASCADE,
  UNIQUE KEY uniq_medinfo_patient (patient_id)
);

select * from medical_information;