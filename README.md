# Online Clinic Management System (CarePulse Clinic)

A secure, 3-tier web application built with PHP, MySQL, HTML, CSS, and Vanilla JavaScript for patient account management, doctor directory browsing, appointment scheduling, and dashboard cancellation.

---

## Architecture Overview (3-Tier)

- **Presentation Layer (Frontend):** Modern, semantic HTML5, custom CSS (Plus Jakarta Sans, medical palette, fully responsive), and Vanilla JavaScript for real-time slot checking.
- **Application Logic Layer (Backend):** PHP with session-based authentication, password hashing (`PASSWORD_BCRYPT`), prepared SQL statements (PDO), and strict business rule enforcement.
- **Data Storage Layer (Database):** MySQL relational database with foreign key constraints and normalization.

---

## Database Design & Tables

The system is normalized across 4 relational tables:

1. **`specialties`**: Medical categories (Cardiology, Dermatology, Neurology, etc.).
2. **`users`**: User credentials and roles (`patient`, `doctor`, `admin`) with hashed passwords.
3. **`doctors`**: Specialist profiles (linked 1-to-1 with a user account) containing qualification, experience years, and consultation fee.
4. **`appointments`**: Patient-doctor appointment bookings with date, time, status (`booked`, `cancelled`, `completed`), and reason for visit.

---

## Key Features Implemented

1. **Patient Portal:**
   - Account signup and secure authentication with password visibility toggle.
   - Doctor directory browsing with real-time specialty filtering.
   - Appointment booking with AJAX live slot availability checking.
   - Patient dashboard with status filters and non-destructive cancellation.
   - Printable consultation slip / appointment receipt with clinic branding and token number.

2. **Doctor Portal:**
   - Independent doctor authentication and isolated doctor schedule view.
   - Real-time statistics: Total Patients Seen, Today's Appointments, Booked, and Completed.
   - Patient contact details (phone, email) and visit reasons.
   - Consultation completion workflow with medical diagnosis and prescription recording.

3. **Admin Management Panel:**
   - Clinic-wide operational metrics: Active Doctors, Registered Patients, and Total Bookings.
   - Doctor CRUD: Add new specialist (with automatic user creation in SQL transaction), edit profile/fees, and safe cascade deletion.
   - Medical Specialties CRUD: Add, edit, and organize clinic departments.
   - Master list of recent clinic appointments with statuses.

4. **Security & Business Rules:**
   - **Patient Overlap Prevention:** A patient cannot book two appointments at the exact same date and time.
   - **Doctor Double-Booking Prevention:** A doctor cannot have two active appointments at the same time slot.
   - **Data Privacy & Ownership Verification:** Doctors only see their own patients; patients only see and manage their own bookings.
   - **Role-Based Access Control (RBAC):** Strict session validation protecting admin, doctor, and patient routes.
   - **Password Security:** BCRYPT password hashing and protection against session fixation.

---

## Setup & Running Instructions

### 1. Windows (XAMPP)

1. Make sure Apache and MySQL are running in XAMPP Control Panel.
2. Clone or copy the project into your `htdocs` directory (e.g., `C:\xampp\htdocs\clinic`).
3. Import the database schema:
   - Open phpMyAdmin (`http://localhost/phpmyadmin/`), create a database named `clinic_db`, and import `database/schema.sql`.
   - Or run via command line:
     ```bash
     C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
     ```
4. Access the application in your browser:
   ```
   http://localhost/clinic/
   ```

---

### 2. Linux Demonstration Setup (LAMP / Built-in Server)

All internal URLs, assets, and includes use relative paths and standard directory separators (`__DIR__ . '/...'`), ensuring 100% cross-platform compatibility on Linux.

#### Option A: Using PHP Built-in Server (Fastest for Demo)
1. Ensure `php` and `mysql-server` (or MariaDB) are installed.
2. Import the database schema:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
3. Run the development server from the project root:
   ```bash
   php -S 0.0.0.0:8000
   ```
4. Open your browser: `http://localhost:8000/`

#### Option B: Using Apache on Linux
1. Copy or clone the project folder into `/var/www/html/clinic`:
   ```bash
   sudo cp -r /path/to/project /var/www/html/clinic
   sudo chown -R www-data:www-data /var/www/html/clinic
   ```
2. Configure database credentials in `config/db.php` or pass environment variables:
   - `DB_HOST` (default: `127.0.0.1`)
   - `DB_NAME` (default: `clinic_db`)
   - `DB_USER` (default: `root`)
   - `DB_PASS` (default: `""` or your Linux MySQL root password)
3. Access in browser: `http://localhost/clinic/`

---

## Pre-seeded Test Accounts

| Account Type | Email | Password | Details |
| --- | --- | --- | --- |
| **Admin** | `admin@clinic.com` | `Admin@123` | Clinic Admin (Full Doctor CRUD & Metrics) |
| **Doctor** | `sara.khan@clinic.com` | `Doctor@123` | Dr. Sara Khan (Cardiology - Schedule & Patient Management) |
| **Doctor** | `ahmed.raza@clinic.com` | `Doctor@123` | Dr. Ahmed Raza (Dentistry) |
| **Doctor** | `ayesha.malik@clinic.com` | `Doctor@123` | Dr. Ayesha Malik (Neurology) |
| **Patient** | `ali@example.com` | `Patient@123` | Ali Khan (Pre-registered patient) |

*You can also create a new patient account anytime via the **Create Account** button on the website, or add new doctors via the Admin Panel.*
