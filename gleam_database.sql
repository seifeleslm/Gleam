-- ============================================
-- GLEAM APP - Complete Database Schema
-- ============================================

-- ============================================
-- 1. USERS & AUTHENTICATION
-- ============================================

CREATE TABLE users (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    email           VARCHAR(255) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('provider', 'parent', 'admin') NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- 2. SERVICE PROVIDERS (Doctors, Nurses, Teachers, Coaches)
-- ============================================

CREATE TABLE providers (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    user_id             INT NOT NULL,
    full_name           VARCHAR(255) NOT NULL,
    phone               VARCHAR(20),
    gender              ENUM('male', 'female'),
    governorate         VARCHAR(100),
    city_area           VARCHAR(100),
    profile_photo       VARCHAR(500),
    about_me            TEXT,
    years_experience    ENUM('1-3', '3-5', '5-10', '10+'),
    national_id_doc     VARCHAR(500),       -- uploaded ID verification file
    rating              DECIMAL(2,1) DEFAULT 0.0,
    total_reviews       INT DEFAULT 0,
    is_verified         BOOLEAN DEFAULT FALSE,
    is_active           BOOLEAN DEFAULT TRUE,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Provider job types (a provider can have multiple roles)
CREATE TABLE provider_jobs (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    job_type    ENUM('doctor', 'nurse', 'teacher', 'coach') NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    UNIQUE (provider_id, job_type)
);

-- Provider availability days
CREATE TABLE provider_availability (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    day         ENUM('Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri') NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    UNIQUE (provider_id, day)
);

-- Provider skills & certificates
CREATE TABLE provider_certificates (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    provider_id     INT NOT NULL,
    title           VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500),
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

-- ============================================
-- 3. DOCTOR-SPECIFIC DETAILS
-- ============================================

CREATE TABLE doctor_details (
    id                      INT PRIMARY KEY AUTO_INCREMENT,
    provider_id             INT NOT NULL UNIQUE,
    license_number          VARCHAR(100),
    license_document        VARCHAR(500),
    workplace_type          ENUM('clinic', 'hospital', 'both'),
    workplace_address       TEXT,
    clinic_price            DECIMAL(10,2),
    home_visit_price        DECIMAL(10,2),
    online_session_price    DECIMAL(10,2),
    working_hours_start     TIME,
    working_hours_end       TIME,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE TABLE doctor_specializations (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    provider_id     INT NOT NULL,
    specialization  ENUM('pediatrics_general', 'child_neurology', 'general_medicine') NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    UNIQUE (provider_id, specialization)
);

-- ============================================
-- 4. NURSE-SPECIFIC DETAILS
-- ============================================

CREATE TABLE nurse_details (
    id                      INT PRIMARY KEY AUTO_INCREMENT,
    provider_id             INT NOT NULL UNIQUE,
    nurse_type              ENUM('vaccination_nurse', 'home_care_nurse') NOT NULL,
    certification_training  VARCHAR(255),
    ministry_certified      BOOLEAN DEFAULT FALSE,
    certificate_file        VARCHAR(500),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE TABLE nurse_services (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    service     ENUM('vaccination', 'temperature_check', 'newborn_care', 'home_care', 'medication_guidance') NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    UNIQUE (provider_id, service)
);

-- ============================================
-- 5. TEACHER-SPECIFIC DETAILS
-- ============================================

CREATE TABLE teacher_details (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    provider_id     INT NOT NULL UNIQUE,
    teacher_type    ENUM('shadow_teacher', 'behavior_support', 'special_needs_learning', 'general_subjects_kg_primary') NOT NULL,
    education_degree ENUM('bachelor', 'diploma', 'certified_only'),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE TABLE teacher_subjects (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    subject     ENUM('arabic', 'math', 'english', 'science', 'social_studies', 'behavior_education') NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    UNIQUE (provider_id, subject)
);

CREATE TABLE teacher_special_needs_experience (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    condition   ENUM('autism', 'adhd', 'downs') NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    UNIQUE (provider_id, condition)
);

-- ============================================
-- 6. COACH-SPECIFIC DETAILS
-- ============================================

CREATE TABLE coach_details (
    id                          INT PRIMARY KEY AUTO_INCREMENT,
    provider_id                 INT NOT NULL UNIQUE,
    training_location           ENUM('home_visits', 'club', 'private_academy'),
    sports_certificate          VARCHAR(500),
    sports_license              VARCHAR(500),
    experience_special_needs    BOOLEAN DEFAULT FALSE,
    session_duration_30         BOOLEAN DEFAULT FALSE,
    session_duration_45         BOOLEAN DEFAULT FALSE,
    session_duration_60         BOOLEAN DEFAULT FALSE,
    price_per_session           DECIMAL(10,2),
    price_per_month             DECIMAL(10,2),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

CREATE TABLE coach_sports (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    sport       ENUM('football', 'basketball', 'volleyball', 'swimming', 'special_needs_sports', 'handball', 'martial_arts') NOT NULL,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    UNIQUE (provider_id, sport)
);

-- ============================================
-- 7. PARENTS & CHILDREN
-- ============================================

CREATE TABLE parents (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NOT NULL,
    full_name   VARCHAR(255) NOT NULL,
    phone       VARCHAR(20),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE children (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    parent_id   INT NOT NULL,
    full_name   VARCHAR(255) NOT NULL,
    photo       VARCHAR(500),
    date_of_birth DATE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
);

-- ============================================
-- 8. SUBSCRIPTIONS
-- ============================================

CREATE TABLE subscriptions (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    provider_id     INT NOT NULL,
    child_id        INT NOT NULL,
    parent_id       INT NOT NULL,
    start_date      DATE NOT NULL,
    end_date        DATE NOT NULL,
    price_egp       DECIMAL(10,2) NOT NULL,
    status          ENUM('active', 'paused', 'expired', 'cancelled') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES providers(id),
    FOREIGN KEY (child_id) REFERENCES children(id),
    FOREIGN KEY (parent_id) REFERENCES parents(id)
);

-- ============================================
-- 9. REPORTS
-- ============================================

CREATE TABLE reports (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    provider_id     INT NOT NULL,
    child_id        INT NOT NULL,
    report_type     ENUM('health', 'educational', 'behavioral') NOT NULL,
    symptoms        TEXT,
    behavior        TEXT,
    notes           TEXT,
    recommendations TEXT,
    scheduled_send  TIMESTAMP NULL,
    sent_at         TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES providers(id),
    FOREIGN KEY (child_id) REFERENCES children(id)
);

-- Report recipients
CREATE TABLE report_recipients (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    report_id   INT NOT NULL,
    recipient   ENUM('parent', 'doctor', 'psychologist', 'nurse', 'trainer') NOT NULL,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    UNIQUE (report_id, recipient)
);

-- Report attachments
CREATE TABLE report_attachments (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    report_id       INT NOT NULL,
    attachment_type ENUM('file', 'image', 'audio', 'video') NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    file_name       VARCHAR(255),
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

-- ============================================
-- 10. RATINGS & REVIEWS
-- ============================================

CREATE TABLE reviews (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    provider_id     INT NOT NULL,
    parent_id       INT NOT NULL,
    rating          TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text     TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES providers(id),
    FOREIGN KEY (parent_id) REFERENCES parents(id),
    UNIQUE (provider_id, parent_id)   -- one review per parent per provider
);

-- ============================================
-- 11. PROVIDER SERVICE PRICING
-- ============================================

CREATE TABLE provider_service_details (
    id                          INT PRIMARY KEY AUTO_INCREMENT,
    provider_id                 INT NOT NULL UNIQUE,
    session_price               DECIMAL(10,2),
    monthly_subscription_price  DECIMAL(10,2),
    working_hours_start         TIME,
    working_hours_end           TIME,
    coverage_area               VARCHAR(255),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
);

-- ============================================
-- USEFUL VIEWS
-- ============================================

-- Provider dashboard summary
CREATE VIEW provider_dashboard AS
SELECT
    p.id                                        AS provider_id,
    p.full_name,
    p.rating,
    COUNT(DISTINCT s.id)                        AS active_subscriptions,
    COUNT(DISTINCT r.id)                        AS reports_sent,
    SUM(CASE WHEN s.status = 'active' THEN s.price_egp ELSE 0 END) AS monthly_earnings,
    COUNT(DISTINCT s.child_id)                  AS total_clients
FROM providers p
LEFT JOIN subscriptions s ON s.provider_id = p.id
LEFT JOIN reports r       ON r.provider_id = p.id
GROUP BY p.id, p.full_name, p.rating;

-- Subscriptions expiring in next 7 days
CREATE VIEW subscriptions_expiring_soon AS
SELECT s.*, p.full_name AS provider_name, c.full_name AS child_name, par.full_name AS parent_name
FROM subscriptions s
JOIN providers p  ON p.id = s.provider_id
JOIN children c   ON c.id = s.child_id
JOIN parents par  ON par.id = s.parent_id
WHERE s.status = 'active'
  AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY);
