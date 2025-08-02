# Text-to-Speech Management System

This project is a **web-based management platform** that integrates with OpenAI's TTS (Text-to-Speech) models.
It allows you to manage users, voice profiles, and generate audio files from text — both interactively and in batch mode via CSV.

## Features

* **User Management**
  Create, edit, and manage system users, including fine-grained permissions and password recovery via email.
* **Voice Profile Management**
  Define TTS profiles with custom voices, models, pitch, volume, and style instructions.
* **Text-to-Speech Generation**
  Generate audio from text in real-time or queue large CSV batches for background processing.
* **Email Configuration**
  Set up SMTP for notifications (e.g., password resets, batch job completions).
* **Debug Logging**
  Debug logs per user, when permission 16 is enabled.

---

## 1. Installation

### Requirements

* PHP 8.1+
* MySQL 5.7+ or MariaDB
* Composer (for PHPMailer)
* Apache or Nginx web server
* OpenAI API key

---

### Steps for VPS Installation

1. **Clone or upload the project**

   ```bash
   cd /var/www/
   git clone https://your-repo-url tts-dashboard
   cd tts-dashboard
   ```

2. **Install PHP dependencies**

   ```bash
   composer require phpmailer/phpmailer
   ```

3. **Create MySQL Database**

   ```sql
   CREATE DATABASE tts_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   GRANT ALL PRIVILEGES ON tts_dashboard.* TO 'tts_user'@'localhost' IDENTIFIED BY 'yourpassword';
   FLUSH PRIVILEGES;
   ```

4. **Import the schema**

   ```bash
   mysql -u tts_user -p tts_dashboard < database.sql
   ```

5. **Configure database connection**
   Edit `config/database.php`:

   ```php
   $host     = 'localhost';
   $dbname   = 'tts_dashboard';
   $user     = 'tts_user';
   $password = 'yourpassword';
   ```

6. **Set up environment variables**
   Add your OpenAI API key:

   ```bash
   export OPENAI_API_KEY="sk-xxxxx"
   ```

   Or configure it in your hosting control panel.

7. **Set permissions for writable folders**

   ```bash
   chmod -R 755 logs jobs
   ```

8. **Configure Apache/Nginx**
   Point the document root to the project directory.

9. **Access the system**
   Navigate to `http://your-server/login/login.php`
   Default credentials:

   * **Username:** admin
   * **Password:** admin123

---

### Shared Hosting Setup

* Upload the project files via FTP.
* Set the document root to the `/` directory of the project.
* Create a MySQL database from the hosting control panel.
* Import `database.sql`.
* Update `config/database.php`.
* Use your hosting’s PHP version (8.1+).
* Set environment variable `OPENAI_API_KEY` (if the control panel allows) or hardcode your API key in `config/openai.php`.

---

## 2. System Architecture

The project is structured into the following main folders:

### `/config`

* **database.php** – Provides `getPDO()` to connect to MySQL with PDO.
* **openai.php** – Provides `openai_request()` helper for OpenAI API calls.

### `/crons`

* **cron\_process\_csv\_jobs.php** – Cron job that processes queued CSV batch TTS jobs and generates a ZIP of audio files.

### `/layouts`

* **layout.php** – Main layout with sidebar and header, including menu rendering based on user permissions.
* **menu\_map.php** – Defines sidebar menu items mapped to permission positions.

### `/login`

* **login.php** – Login page.
* **logout.php** – Session logout.
* **forgot\_password.php** – Sends password reset email with a token.
* **reset\_password.php** – Form to reset password with a token.

### `/models`

* **login\_model.php** – Contains functions:

  * `verifyCredentials()`
  * `startSession()`
  * `closeSession()`
  * `validateModuleAccess()`

### `/modules`

#### **Users**

* **users.php** – Manage users and permissions.
* **get\_user.php** – Fetch user details as JSON.
* **save\_user.php** – Add/update users.
* **change\_password.php** – Change your own password.
* **update\_password.php** – Process password changes.

#### **Voice Profiles**

* **voice\_profiles.php** – Create and manage voice profiles.
* **get\_voice\_profile.php** – Fetch voice profile as JSON.
* **save\_voice\_profile.php** – Save a voice profile.
* **delete\_voice\_profile.php** – Delete a profile.
* **get\_voices.php** – Return available OpenAI voices.

#### **TTS (Text-to-Speech)**

* **tts.php** – Direct TTS UI and CSV batch tab.
* **generate\_tts.php** – Generate audio from text.
* **process\_csv.php** – Parse CSV file upload.
* **queue\_csv\_job.php** – Queue CSV job for cron processing.

#### **Email**

* **email\_settings.php** – Configure SMTP settings and send test emails.

### `/utils`

* **helpers.php** – Permission checks, debug logging.
* **email.php** – PHPMailer email utilities.
* **email\_forgot.php** – PHPMailer email for password reset.

### `/logs`

* Debug and error logs are stored here.

### `/jobs`

* Contains CSV job files and generated ZIP outputs.

---

## 3. Permissions

Each user has a 20-character string (e.g., `SSSSNNNNNNNNNNNNNNNN`) where:

* `S` = Enabled
* `N` = Disabled

Position mapping:

1. Dashboard
2. Text-to-Speech
3. Voice Profiles
4. Users
5. Change Password
6. Email Settings
7. Import CSV
8. Debug Mode

Position 20 (index 19) = **User disabled flag**.

---

## 4. Cron Job Setup

To process batch CSV jobs automatically:

```bash
* * * * * /usr/bin/php /path_to_web_site/crons/cron_process_csv_jobs.php >/dev/null 2>&1
```

---

## 5. Default Admin

Default user:

* **Username:** admin
* **Password:** admin123 (change immediately)

---

## 6. Security Recommendations

* Use HTTPS
* Secure the `/logs` and `/jobs` directories with `.htaccess`
* Keep your OpenAI API key safe (use environment variables)
* Regularly update passwords and dependencies.

---

## 7. License

This project is open source and completely free to use, distribute, and modify.

---

## 8. Future Improvements

* Multi-language support
* Enhanced error reporting
* Multi-tenant architecture
* Role-based permission management

---

## Developed by
This project is developed and maintained by the **VitalPBX Team**.
