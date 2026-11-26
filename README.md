# CarePulse Clinic

CarePulse is a PHP and MySQL clinic appointment system. Patients can find a doctor, book a consultation and open their appointment slip. Doctors manage their schedules and consultation notes, and admins manage doctors and specialties.

## Local setup

Choose [Windows with XAMPP](#windows-with-xampp) or [Ubuntu with Apache and MySQL](#ubuntu-with-apache-and-mysql). Both walkthroughs start with the published GitHub version and a fresh local database. If you already have a CarePulse database, back it up before importing anything.

### Windows with XAMPP

1. Install [XAMPP for Windows](https://www.apachefriends.org/download.html). Open the XAMPP Control Panel and start **Apache** and **MySQL**. Check that [XAMPP opens on localhost](http://localhost/). XAMPP labels its bundled MariaDB service **MySQL**. [XAMPP Windows guide](https://www.apachefriends.org/faq_windows)
2. Download the repository using **Code → Download ZIP** on GitHub. Extract it and place the project files in `C:\xampp\htdocs\clinic`. Confirm that the entry file is `C:\xampp\htdocs\clinic\index.php`.
3. Open [phpMyAdmin](http://localhost/phpmyadmin/), select the **Import** tab on the server home page, and choose `C:\xampp\htdocs\clinic\database\schema.sql`. Click **Import**. The published schema creates `clinic_db` and adds demo data.
4. Open [CarePulse](http://localhost/clinic/) and sign in with one of the [demo accounts](#demo-accounts).
5. If your database uses a custom port or password, update `C:\xampp\htdocs\clinic\.env` using [.env.example](.env.example) for the setting names. Include Apache's port in the browser address if you changed it. For example, use `http://localhost:8080/clinic/` when Apache listens on port 8080.

### Ubuntu with Apache and MySQL

1. Install Apache, PHP, the database driver and MySQL. Run these commands in a terminal on the Ubuntu machine that will run the app. [Ubuntu PHP guide](https://ubuntu.com/server/docs/programming-php/) · [Ubuntu MySQL guide](https://ubuntu.com/server/docs/how-to/databases/install-mysql/)

   ```bash
   sudo apt update
   sudo apt install git apache2 libapache2-mod-php php-cli php-mysql php-mbstring mysql-server
   sudo systemctl enable --now apache2 mysql
   ```

2. Clone the repository and copy the published files into Apache’s default web directory.

   ```bash
   git clone https://github.com/ahmad-fayyaz626/online-clinic-appointment.git
   sudo install -d -m 755 /var/www/html/clinic
   git -C online-clinic-appointment archive HEAD | sudo tar -x -C /var/www/html/clinic
   ```

3. Import the schema as the database administrator, then open the MySQL prompt to create a dedicated application account.

   ```bash
   sudo mysql < /var/www/html/clinic/database/schema.sql
   sudo mysql
   ```

   Run these statements at the MySQL prompt. Replace `YOUR_LOCAL_DB_PASSWORD` with a password you choose. Use the same password in step 4.

   ```sql
   CREATE USER 'carepulse'@'127.0.0.1' IDENTIFIED BY 'YOUR_LOCAL_DB_PASSWORD';
   GRANT SELECT, INSERT, UPDATE, DELETE ON clinic_db.* TO 'carepulse'@'127.0.0.1';
   EXIT;
   ```

4. Run `sudoedit /var/www/html/clinic/.env` and set the following values. Save the file, then restrict it so Apache can read it through the `www-data` group.

   ```dotenv
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=clinic_db
   DB_USER=carepulse
   DB_PASS=YOUR_LOCAL_DB_PASSWORD
   APP_ENV=development
   ```

   ```bash
   sudo chown root:www-data /var/www/html/clinic/.env
   sudo chmod 640 /var/www/html/clinic/.env
   ```

5. Run `sudoedit /etc/apache2/conf-available/carepulse.conf` and add this configuration for local browser access. [Ubuntu Apache guide](https://ubuntu.com/server/docs/how-to/web-services/install-apache2/)

   ```apache
   <Directory "/var/www/html/clinic">
       Options -Indexes
       AllowOverride All
       Require local
       <FilesMatch "(?i)^(\.env(\..*)?|.*\.sql)$">
           Require all denied
       </FilesMatch>
   </Directory>
   ```

   Enable the configuration and reload Apache.

   ```bash
   sudo a2enmod rewrite
   sudo a2enconf carepulse
   sudo apache2ctl configtest
   sudo systemctl reload apache2
   ```

6. Open [CarePulse](http://localhost/clinic/) in a browser on the Ubuntu machine and use a [demo account](#demo-accounts). If the app shows a connection error, use `systemctl status apache2 mysql` to check both services and verify the values in `.env`.

### Demo accounts

These accounts are included in the published schema. Use them with local demo data.

| Demo role | Email | Password |
| --- | --- | --- |
| Admin | `admin@clinic.com` | `Admin@123` |
| Doctor | `sara.khan@clinic.com` | `Doctor@123` |
| Patient | `ali@example.com` | `Patient@123` |

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
| MySQL | Store the booked slot and actual check-in, start, and finish times. An indexed generated column enforces one active consultation per doctor. Reception events record the appointment, staff account and time of the action. | `database/schema.sql`, `database/migrations/` |

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

Follow the operating system walkthrough above with the updated application files. Use these database import steps for this version.

1. Create an empty database named `clinic_db` using `utf8mb4`. On Windows, create it from **New** in phpMyAdmin. On Ubuntu, run `sudo mysql -e "CREATE DATABASE clinic_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`.
2. Import `database/schema.sql` into the selected database. On Ubuntu, run `sudo mysql clinic_db < /var/www/html/clinic/database/schema.sql`.
3. To load the demo accounts above, import `database/demo.sql` after the schema, using the same database. On Ubuntu, run `sudo mysql clinic_db < /var/www/html/clinic/database/demo.sql`.

To demo the live workflow, sign in to all three roles in separate browser profiles or browsers. Open the patient’s appointment slip for today, check in its token as admin, and start and finish the consultation as the doctor once the booked time has arrived.

### Upgrade an existing database to the live queue

Back up your existing database and use a maintenance window for the schema upgrade. MySQL commits each of these DDL statements independently. Select the existing CarePulse database. If the live queue is already installed, apply `database/migrations/002_reception_corrections.sql`. For a pre-queue database, apply `database/migrations/001_live_queue.sql` first. A fresh install of the updated schema includes both migrations.

Historical `completed` visits stay in appointment history. If they lack finish timestamps, they contribute neither to the live seen count nor to the timing sample. Existing users sign in again after the upgrade because sessions now track account role and password changes.

### Production deployment

Use HTTPS and a security-supported PHP release. Set `APP_ENV=production`, set the clinic time zone, and use a dedicated database user with only the app’s read and write privileges. Apply migrations separately using a database administrator. Use a private, writable session directory and shared session storage if the app has multiple web instances.

The fresh schema contains no accounts. Create a trusted account under restricted access, then promote that specific user from a database administrator session.

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-admin@example.com' AND role = 'patient';
```

Use test accounts only with disposable local data. For an upgrade, reset any previously shared or demo credentials before opening access. Production mode rejects the three demo passwords. The app now authenticates every admin through the database and invalidates sessions after a stored password or role changes.

The included Apache rules block direct requests for dotfiles, application internals, and database files. Enable equivalent restrictions if serving through another web server. Verify those rules behind HTTPS and rate-limit sign-in at the web server or reverse proxy before exposing the app. Use encrypted backups and restrict access to visit information and logs.
