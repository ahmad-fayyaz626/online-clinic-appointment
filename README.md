# CarePulse Clinic

CarePulse is a PHP and MySQL/MariaDB clinic appointment system. Patients can find a doctor, book a consultation and open their appointment slip. Doctors manage their schedules and consultation notes, and admins manage doctors and specialties.

## Run the published app with XAMPP

1. Place the project in `C:\xampp\htdocs\clinic` and start Apache and MySQL from the XAMPP Control Panel.
2. Import [database/schema.sql](database/schema.sql) through phpMyAdmin. The published schema creates `clinic_db` and includes demo accounts. Use a fresh local database for the demo.
3. Open [CarePulse on localhost](http://localhost/clinic/). If you use a custom database connection, check [.env.example](.env.example) for the supported settings.

## Live queue update

The live queue and mobile redesign described below are prepared for the next application update. The published GitHub code currently supports the appointment workflow above.

| Role | Appointment-day workspace |
| --- | --- |
| Patient | See the next action, place in line and estimated wait after check-in. Open the appointment slip to find the token. |
| Doctor | Start the next eligible consultation, finish it with notes and see the day’s completed visits. |
| Reception and admin | Check in by token, monitor each doctor’s queue, undo mistaken arrivals and review missed visits. |

The update also adds a mobile menu, a responsive footer, and appointment history that stacks on narrow screens.

### Architecture

| Layer | Responsibility | Files |
| --- | --- | --- |
| Browser | Responsive clinic navigation, role-specific dashboards, reception controls and a refresh every 12 seconds while the tab is visible. The navigation and queue forms work with JavaScript disabled. | `includes/header.php`, `includes/footer.php`, `includes/queue_cards.php`, `assets/css/clinic.css`, `assets/js/main.js`, `assets/js/queue.js` |
| PHP controllers | Authenticate the session, authorize the role and resource, verify form tokens, and return HTML or redirect after a write. The live endpoint gets its scope from the signed-in account. | `config/db.php`, `queue_action.php`, `queue_status.php`, `book.php`, `cancel_appointment.php`, `doctor_update_status.php`, `doctor_complete_appointment.php` |
| Queue service | Check appointment ownership and valid transitions, order arrivals, estimate waits, and serialize changes that affect the same doctor. Booking also takes a patient-row lock to prevent overlapping slots across doctors. | `includes/clinic_queue.php` |
| MySQL or MariaDB | Store the booked slot and actual check-in, start, and finish times. An indexed generated column enforces one active consultation per doctor. Reception events record the appointment, staff account and time of the action. | `database/schema.sql`, `database/migrations/` |

### Appointment-day flow

1. The patient books a time slot and gets a `CP-…` token on their appointment slip.
2. Reception enters that token on the admin dashboard on the appointment date. The visit joins that doctor’s queue and the patient sees their own position and estimated wait.
3. Checked-in visits are ordered by booked time, then arrival time and appointment ID. The doctor can start the first waiting visit once its booked time is reached. Patients who have yet to arrive keep their booking and their absence does not block later checked-in patients.
4. The doctor completes the consultation with the existing notes form. The next patient’s position updates and the doctor’s and clinic’s **Seen today** counts increase. A consultation still in progress at midnight stays with its doctor until it finishes.

Reception can open **Review check-ins and missed visits** to undo an arrival before the consultation starts. A booking can be marked missed after 15 minutes if the patient has yet to check in. If they arrive later that day, restore the booking and check in as usual. A missed visit stays in the appointment record.

“Waiting” counts checked-in visits that have yet to start. “In consultation” is a separate count. “Seen today” counts visits that actually finished during the clinic’s local day. Queue event timestamps are stored in UTC. Appointment times and the daily counts use `CLINIC_TIMEZONE`, which defaults to `Asia/Karachi`.

### Estimated waits

Each doctor starts with a configurable 30–45-minute consultation range. Admins can change it under **Consultation timing defaults**. Once there are at least eight usable visits, CarePulse uses the middle range from up to 24 recent visits within 60 days. Recorded visits under two minutes or over two hours are excluded from the timing sample.

The estimate adds the remaining time of any current consultation and the expected duration of checked-in visits ahead. It accounts for gaps between booked times and respects the patient’s own booked time. For example, if visits usually take 30–45 minutes, an ongoing visit started 20 minutes ago, and one waiting patient ahead is already due, the estimate is 40–70 minutes unless the booked times push it later. Once an ongoing visit reaches the top of the doctor’s usual range, the card shows **Delayed** and keeps the patient’s position visible.

### Local setup for the live queue update

Use PHP with `pdo_mysql` and `mbstring`, plus MySQL or MariaDB with InnoDB. XAMPP works for local development. The CLI server below is for trusted local use.

1. Create a database named `clinic_db` with `utf8mb4` and import [database/schema.sql](database/schema.sql) into it. In phpMyAdmin, select the database before importing.
2. Configure the process environment using [.env.example](.env.example). The default XAMPP settings work locally with the app’s defaults. When using a different user or database, supply `DB_USER`, `DB_PASS`, and `DB_NAME`.
3. Start Apache and open the project URL. For the local CLI server, run `php -S 127.0.0.1:8000` from the project root. Give PHP a writable session directory. Supply any credentials in environment variables when using the CLI server.
4. For a local demo, import `database/demo.sql` after the schema into a fresh development database.

| Demo role | Email | Password |
| --- | --- | --- |
| Admin | `admin@clinic.com` | `Admin@123` |
| Doctor | `sara.khan@clinic.com` | `Doctor@123` |
| Patient | `ali@example.com` | `Patient@123` |

To demo the live workflow, sign in to all three roles in separate browser profiles or browsers. Open the patient’s appointment slip for today, check in its token as admin, and start and finish the consultation as the doctor once the booked time has arrived.

### Upgrade an existing database to the live queue

Back up your existing database and use a maintenance window for the schema upgrade. MySQL and MariaDB commit each DDL statement independently. Select the existing CarePulse database. If the live queue is already installed, apply `database/migrations/002_reception_corrections.sql`. For a pre-queue database, apply `database/migrations/001_live_queue.sql` first. A fresh install of the updated schema includes both migrations.

Historical `completed` visits stay in appointment history. If they lack finish timestamps, they contribute neither to the live seen count nor to the timing sample. Existing users sign in again after the upgrade because sessions now track account role and password changes.

### Production deployment

Use HTTPS and a security-supported PHP release. Set `APP_ENV=production`, set the clinic time zone, and use a dedicated database user with only the app’s read and write privileges. Apply migrations separately using a database administrator. Use a private, writable session directory and shared session storage if the app has multiple web instances.

The fresh schema contains no accounts. Create a trusted account under restricted access, then promote that specific user from a database administrator session.

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-admin@example.com' AND role = 'patient';
```

Use test accounts only with disposable local data. For an upgrade, reset any previously shared or demo credentials before opening access. Production mode rejects the three demo passwords. The app now authenticates every admin through the database and invalidates sessions after a stored password or role changes.

The included Apache rules block direct requests for dotfiles, application internals, and database files. Enable equivalent restrictions if serving through another web server. Verify those rules behind HTTPS and rate-limit sign-in at the web server or reverse proxy before exposing the app. Use encrypted backups and restrict access to visit information and logs.
