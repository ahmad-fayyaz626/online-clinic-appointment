# Online Clinic Management System (CarePulse Clinic)

A secure, 3-tier web application built with **PHP**, **MySQL**, **HTML5**, **CSS3**, and **Vanilla JavaScript** providing end-to-end appointment scheduling, doctor schedule management, clinical diagnosis recording, and clinic administration.

---

## 1. Project Proposal & Overview

### 1.1 Problem Statement
Traditional clinical appointment workflows rely on manual telephone calls, physical queues, and paper records. This leads to double-booking errors, long waiting room delays, poor doctor schedule visibility, and lost prescription histories. 

### 1.2 Proposed Solution (CarePulse Clinic)
**CarePulse Clinic** is an automated, web-based healthcare management platform designed to streamline clinical operations through three dedicated portals:
1. **Patient Portal:** Allows patients to view specialist profiles, check real-time slot availability, book and cancel appointments, and download printable consultation slips.
2. **Doctor Portal:** Provides consulting physicians with an isolated schedule view, patient history, and digital prescription recording tools.
3. **Admin Management Panel:** Offers clinic administrators complete control over doctor profiles (CRUD), medical specialties, and clinic-wide appointment analytics.

### 1.3 Key Objectives
- **Zero Scheduling Conflicts:** Enforce automated validation rules preventing patient and doctor double-booking.
- **Role-Based Access Control (RBAC):** Strict authentication and session isolation across Patients, Doctors, and Administrators.
- **Cross-Platform Compatibility:** Run seamlessly on both **Windows (XAMPP)** and **Linux (LAMP / WSL Ubuntu)** environments with zero code changes.
- **Enhanced User Experience:** Universal password show/hide toggle buttons, custom responsive scrollbars, and AJAX-driven appointment slot checks.

---

## 2. System Architecture (3-Tier)

The application adheres to an industry-standard **3-Tier Architecture**:

```
+-------------------------------------------------------------+
|             1. Presentation Layer (Frontend)                |
|  - Semantic HTML5 & Modern CSS3 (Plus Jakarta Sans palette) |
|  - Vanilla JavaScript for AJAX slot validation & UI toggles |
+-------------------------------------------------------------+
                              |
                              v
+-------------------------------------------------------------+
|          2. Application Logic Layer (Backend - PHP)         |
|  - Session-based Role Authentication & RBAC Middleware      |
|  - BCRYPT Password Hashing (`password_hash` / `verify`)     |
|  - PDO Prepared Statements (100% SQL Injection Protection)  |
|  - Transactional Business Logic & Flash Notifications       |
+-------------------------------------------------------------+
                              |
                              v
+-------------------------------------------------------------+
|            3. Data Storage Layer (MySQL / MariaDB)          |
|  - 4 Normalized Relational Tables with Foreign Keys         |
|  - Cascade Deletion & Referential Integrity                 |
+-------------------------------------------------------------+
```

---

## 3. Database Design & Relational Schema

The database `clinic_db` is normalized across 4 relational tables:

1. **`specialties`**: Medical disciplines (Cardiology, Dermatology, Neurology, Pediatrics, Orthopedics, Dentistry, etc.).
2. **`users`**: Central authentication table storing user credentials, contact details, and roles (`patient`, `doctor`, `admin`) with BCRYPT hashed passwords.
3. **`doctors`**: Professional medical profiles linked 1-to-1 with `users` (`user_id` foreign key), storing qualification, experience years, and consultation fee.
4. **`appointments`**: Patient-doctor appointment bookings containing appointment date, time slot, status (`booked`, `cancelled`, `completed`), visit reason, and doctor's prescription notes (`doctor_notes`).

---

## 4. Module & Feature Breakdown

### 4.1 Patient Portal
- **Account Registration & Secure Login:** User signup with phone number validation and password confirmation.
- **Universal Password Visibility:** Eye toggle button to reveal or conceal passwords across all forms.
- **Doctor Directory & Filtering:** Browse active doctors with real-time category filtering by medical specialty.
- **AJAX Live Slot Checking:** Slot picker dynamically queries `check_slots.php` to prevent selecting already booked consultation times.
- **Patient Dashboard (`dashboard.php`):** View past and upcoming appointments with status filtering (`all`, `booked`, `cancelled`, `completed`).
- **Non-Destructive Cancellation:** Cancel bookings without deleting records, keeping audit trails intact.
- **Printable Consultation Slip (`appointment_slip.php`):** Clean, printable A4 appointment receipt featuring clinic branding, unique token number (`CP-XXXXXX`), appointment details, consultation fee, and doctor signature line.
- **Profile Settings (`profile.php`):** Update personal name, contact phone, login email address, and change password securely.

### 4.2 Doctor Portal
- **Role-Based Redirection:** Automatic landing on the Doctor Workspace (`doctor_dashboard.php`) upon login.
- **Isolated Practice Schedule:** Doctors only access and manage appointments assigned to them.
- **Real-Time Clinical Metrics:** Quick summary cards displaying *Total Patients*, *Today's Appointments*, *Active Booked*, and *Completed Consultations*.
- **Patient Contact & Reason for Visit:** View patient phone numbers, emails, and reasons for consultation.
- **Clinical Consultation Notes & Prescriptions (`doctor_complete_appointment.php`):** Complete an appointment by recording clinical diagnosis, medical advice, and prescription notes.
- **Appointment Status Controls:** Mark visits as `completed` or `cancelled` with doctor ownership verification.
- **Doctor Profile Editing (`profile.php`):** Update consultation fee, contact phone, login email, and password.

### 4.3 Administrator Panel
- **Clinic Metrics Dashboard (`admin_dashboard.php`):** Real-time overview of total registered doctors, patients, and clinic-wide appointments.
- **Complete Doctor CRUD:**
  - **Add Specialist (`admin_doctor_add.php`):** Atomically register a new doctor user and professional profile in a single SQL transaction.
  - **Edit Specialist (`admin_doctor_edit.php`):** Modify doctor name, login email, contact phone, specialty, qualification, experience, consultation fee, and reset password.
  - **Delete Specialist (`admin_doctor_delete.php`):** Safely remove doctors with database cascade integrity.
- **Medical Specialties CRUD (`admin_specialties.php`):** Add, update, and manage medical departments with doctor assignment safety guards.
- **Master Clinic Appointments View:** Overview of recent appointments across all doctors with live status indicators.
- **Public Directory Preview:** Instant navigation toggle allowing administrators to preview the patient-facing directory.

---

## 5. Security & Business Rules

1. **Double-Booking Prevention:** A doctor cannot have two active appointments at the same date and time slot.
2. **Patient Overlap Prevention:** A patient cannot schedule overlapping appointments with different doctors at the same time.
3. **Strict Data Privacy:** Doctors cannot view appointments belonging to other physicians; patients can only view and manage their own appointments.
4. **BCRYPT Password Security:** All user credentials are encrypted using `PASSWORD_BCRYPT` with automated rehash validation.
5. **Session Fixation Defense:** Sessions are regenerated via `session_regenerate_id(true)` upon authentication.
6. **SQL Injection Defense:** 100% of database interactions utilize prepared statements with parameterized inputs.
7. **XSS Protection:** All user-supplied data rendered in HTML is sanitized through `htmlspecialchars()` with `ENT_QUOTES`.

---

## 6. Environment Configuration (`.env.example`)

A sample configuration file [`.env.example`](file:///.env.example) is provided in the root directory. You can copy it to `.env` or set environment variables in your web server:

```env
# Database Connection Settings
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=clinic_db

# Windows (XAMPP Default):
DB_USER=root
DB_PASS=

# Linux (LAMP / WSL Ubuntu Default):
# DB_USER=clinic_user
# DB_PASS=apna_password

APP_ENV=development
```

The database configuration in `config/db.php` includes an **automatic dual-environment fallback**: it first attempts standard Windows XAMPP credentials (`root` / empty password) and automatically falls back to Linux LAMP credentials (`clinic_user` / `apna_password`) if the initial connection fails.

---

## 7. Setup & Installation Guide

### Option 1: Windows Setup (XAMPP)

1. **Install XAMPP** (with Apache and MySQL).
2. **Start Services:** Open XAMPP Control Panel and start **Apache** and **MySQL**.
3. **Deploy Project:** Clone or copy this repository into your XAMPP web root:
   ```
   C:\xampp\htdocs\clinic
   ```
4. **Import Database:**
   - Open phpMyAdmin in your browser: `http://localhost/phpmyadmin/`
   - Create a new database named `clinic_db` (Collation: `utf8mb4_unicode_ci`).
   - Import the file located at: `database/schema.sql`
   - *Alternatively, via Windows Command Prompt:*
     ```cmd
     C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\clinic\database\schema.sql
     ```
5. **Open Application:** Launch your web browser and navigate to:
   ```
   http://localhost/clinic/
   ```

---

### Option 2: Linux Setup (LAMP / WSL / Ubuntu)

1. **Install Prerequisites:**
   ```bash
   sudo apt update
   sudo apt install -y apache2 mysql-server php php-mysql libapache2-mod-php
   ```

2. **Deploy Project:**
   Clone or copy the project files into the Apache document root:
   ```bash
   sudo cp -r /path/to/project /var/www/html/clinic
   sudo chown -R www-data:www-data /var/www/html/clinic
   sudo chmod -R 755 /var/www/html/clinic
   ```

3. **Configure MySQL Database & User:**
   Log in to MySQL as root:
   ```bash
   sudo mysql
   ```
   Execute the following SQL commands to create the database and dedicated user:
   ```sql
   CREATE DATABASE IF NOT EXISTS clinic_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER IF NOT EXISTS 'clinic_user'@'localhost' IDENTIFIED BY 'apna_password';
   GRANT ALL PRIVILEGES ON clinic_db.* TO 'clinic_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

4. **Import Database Schema:**
   ```bash
   mysql -u clinic_user -papna_password clinic_db < /var/www/html/clinic/database/schema.sql
   ```

5. **Restart Apache & Access:**
   ```bash
   sudo systemctl restart apache2
   ```
   Open your browser and navigate to:
   ```
   http://localhost/clinic/
   ```

---

### Option 3: Quick Demo via PHP Built-in Server

If you wish to run a quick demonstration without configuring Apache:
```bash
cd /path/to/clinic
php -S 0.0.0.0:8000
```
Then access `http://localhost:8000/` in your browser.

---

## 8. Pre-Seeded Test Accounts

The database comes pre-seeded with accounts for all user roles:

| Role | Email Address | Password | Account Details & Responsibilities |
| :--- | :--- | :--- | :--- |
| **👑 Admin** | `admin@clinic.com` | `Admin@123` | Clinic Administrator (Doctor CRUD, Specialties, Analytics) |
| **👨‍⚕️ Doctor** | `sara.khan@clinic.com` | `Doctor@123` | Dr. Sara Khan (Cardiology - Schedule & Prescriptions) |
| **👨‍⚕️ Doctor** | `ahmed.raza@clinic.com` | `Doctor@123` | Dr. Ahmed Raza (Dentistry) |
| **👨‍⚕️ Doctor** | `ayesha.malik@clinic.com` | `Doctor@123` | Dr. Ayesha Malik (Neurology) |
| **👨‍⚕️ Doctor** | `usman.tariq@clinic.com` | `Doctor@123` | Dr. Usman Tariq (Pediatrics) |
| **👨‍⚕️ Doctor** | `fatima.noor@clinic.com` | `Doctor@123` | Dr. Fatima Noor (Dermatology) |
| **👨‍⚕️ Doctor** | `bilal.hassan@clinic.com` | `Doctor@123` | Dr. Bilal Hassan (Orthopedics) |
| **🧑 Patient** | `ali@example.com` | `Patient@123` | Ali Khan (Pre-registered patient with sample bookings) |

*Note: New patient accounts can also be created anytime using the **Create Account** button on the website.*

---

## 9. Project File Structure

```
clinic/
├── .env.example                  # Environment configuration template
├── .gitignore                    # Git ignore file for sensitive files
├── README.md                     # Comprehensive project documentation & proposal
├── index.php                     # Public homepage & Doctor Directory with specialty filters
├── login.php                     # Multi-role authentication endpoint with password toggle
├── signup.php                    # Patient registration form with validation
├── logout.php                    # Session termination & flash cleanup
├── dashboard.php                 # Patient appointment dashboard & status tracking
├── book.php                      # Appointment booking form with conflict validation
├── check_slots.php               # AJAX endpoint for real-time doctor slot availability
├── cancel_appointment.php        # Non-destructive patient appointment cancellation handler
├── appointment_slip.php          # Printable A4 medical appointment receipt / slip
├── profile.php                   # Profile management (name, email, phone, fee, password)
├── admin_dashboard.php           # Clinic admin metrics, doctor CRUD table, recent bookings
├── admin_doctor_add.php          # Admin form to register new doctors (transactional)
├── admin_doctor_edit.php         # Admin form to edit doctor info, email, and password
├── admin_doctor_delete.php       # Safe cascade deletion of doctor profiles
├── admin_specialties.php         # CRUD management for medical specialties
├── doctor_dashboard.php          # Doctor schedule, metrics, and patient queue
├── doctor_complete_appointment.php # Doctor consultation completion & prescription notes form
├── doctor_update_status.php      # Server endpoint to update appointment status
├── config/
│   └── db.php                    # Dual-environment PDO database connection & auth helpers
├── database/
│   └── schema.sql                # Complete SQL schema with tables and seed data
├── assets/
│   ├── css/
│   │   └── style.css             # Unified medical styling system, responsive grid & scrollbar
│   └── js/
│       └── main.js               # Frontend JavaScript, AJAX slot checker, password toggle
└── includes/
    ├── header.php                # Dynamic role-based navigation bar & flash alerts
    └── footer.php                # Global footer & universal password visibility script
```

---

## 10. License & Academic Attribution
Developed as an academic software engineering project for **CarePulse Clinic Management**. All code is written cleanly using native PHP, MySQL, and standards-compliant web technologies for straightforward presentation and defense.
