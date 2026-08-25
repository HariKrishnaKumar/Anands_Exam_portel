# 🚀 Deployment Guide — Exam Portal on the Internet (100% Free)

This guide takes your Exam Portal (PHP + MariaDB/MySQL + Flask analytics) from
**localhost XAMPP** to a **live public URL** using two free services:

| Part | Service | Cost | Credit card |
|---|---|---|---|
| Web app (Apache + PHP + Flask in one container) | [Render.com](https://render.com) — Free Web Service | ₹0 | ❌ Not needed |
| Database (MySQL) | [Aiven.io](https://aiven.io) — Free plan | ₹0 | ❌ Not needed |

**Final result:** `https://your-app-name.onrender.com` — accessible from anywhere.

---

## 📐 Architecture (what runs where)

```
GitHub repo ──auto-deploy──▶ Render FREE Web Service (1 Docker container)
                               │
                               ├─ Apache + PHP site   ← PUBLIC (*.onrender.com)
                               └─ gunicorn Flask :5000 ← internal localhost only
                                        ▲                  ▲
                             PDO over TLS│                  │mysql-connector over TLS
                                        └──▶ Aiven FREE MySQL ◀── schema.sql imported here
```

**Why one container?** Your PHP code calls Flask via `PYTHON_API_URL`
(default `http://127.0.0.1:5000`). Keeping both inside the same container means
that URL keeps working unchanged, and Render only needs to expose one port.

---

## ✅ Prerequisites (do these once)

1. **GitHub account** — your repo already exists:
   `https://github.com/03Hari-Krishna-Kumar-div/Exam_portel`
2. **Render account** — sign up at https://render.com with **"Sign in with GitHub"**
   (easiest, lets Render see your repos).
3. **Aiven account** — sign up at https://console.aiven.io/signup (email or Google).
   No credit card asked.
4. **A fresh Gmail App Password** — needed for OTP emails (Step 1 below).
5. Locally installed (you already have these from XAMPP):
   - Git (`git --version`)
   - XAMPP's MySQL client: `C:\xampp\mysql\bin\mysql.exe` (used for DB import)

---

# STEP 0 — 🔴 Security fix BEFORE deploying (5 min)

> ⚠️ **Do not skip this.** Your repo currently contains a real Gmail App Password
> in `src/php/config/mail.php` line 43. Anyone on the internet can read your
> GitHub repo and use it to send email as you. Revoke it first.

1. Go to https://myaccount.google.com/security
2. Make sure **2-Step Verification is ON** (required for app passwords).
3. Go to https://myaccount.google.com/apppasswords
4. Find the existing app password entry and **Delete/revoke it**.
5. Click **"+ Create app password"** → name it `examportal-render` → copy the new
   **16-character password** (e.g. `abcd efgh ijkl mnop`). You will paste it into
   Render's dashboard in Step 5 — **never into the code again**.

---

# STEP 1 — 💻 Code changes (15 min)

Three small edits make the app cloud-ready. All local config keeps working.

### 1.1 — `src/php/config/mail.php` — move SMTP secrets to environment variables

Replace these lines:

```php
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USERNAME', 'test.dev.hari0003@gmail.com');
define('SMTP_PASSWORD', '[REVOKED - do not reuse]');      // ← LEAKED — remove!
define('SMTP_FROM',     'test.dev.hari0003@gmail.com');
define('SMTP_FROM_NAME', 'Test Platform');
```

with:

```php
define('SMTP_HOST',     getenv('SMTP_HOST')     ?: 'smtp.gmail.com');
define('SMTP_PORT',     getenv('SMTP_PORT')     ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM',     getenv('SMTP_FROM')     ?: 'noreply@example.com');
define('SMTP_FROM_NAME','Test Platform');
```

*(Local XAMPP keeps working: temporarily set values back here, or leave empty
locally if you don't test emails.)*

### 1.2 — `src/php/config/db.php` — allow URL paths from environment

Find (around line 37):

```php
if (php_sapi_name() === 'cli-server') {
    define('BASE_URL', '');
    define('ASSETS_URL', '/assets');
} else {
    define('BASE_URL', '/test-platform/src/php/public');
    define('ASSETS_URL', '/test-platform/assets');
}
```

Replace with (adds env overrides used by Render, keeps XAMPP behavior identical):

```php
if (getenv('BASE_URL') !== false || getenv('ASSETS_URL') !== false) {
    // Cloud deploy (Render/Docker): docroot = project root
    define('BASE_URL',   getenv('BASE_URL')   ?: '/src/php/public');
    define('ASSETS_URL', getenv('ASSETS_URL') ?: '/assets');
} elseif (php_sapi_name() === 'cli-server') {
    define('BASE_URL', '');
    define('ASSETS_URL', '/assets');
} else {
    // XAMPP / Apache under htdocs
    define('BASE_URL', '/test-platform/src/php/public');
    define('ASSETS_URL', '/test-platform/assets');
}
```

Then find (around line 52):

```php
$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
```

Replace with (adds TLS support required by Aiven):

```php
$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
if (getenv('MYSQL_SSL')) {
    // Encrypt connection (Aiven requires TLS). Debian image ships this CA bundle.
    $options[PDO::MYSQL_ATTR_SSL_CA]                 = '/etc/ssl/certs/ca-certificates.crt';
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}
$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
```

### 1.3 — `src/python/app.py` — no change expected

`mysql-connector-python` automatically negotiates TLS when the server requires
it. If later (Step 7) you see a handshake error, edit `DB_CONFIG` usage:

```python
conn = mysql.connector.connect(**DB_CONFIG, ssl_verify_cert=False)
```

---

# STEP 2 — 📦 Create deployment files (20 min)

Create these **4 files in the project root** (`C:\Users\ADMIN\Desktop\TEST\`):

### 2.1 — `Dockerfile`

```dockerfile
FROM php:8.2-apache

# ── PHP: database driver ─────────────────────────────────────
RUN docker-php-ext-install pdo pdo_mysql

# ── Python + Flask analytics service ─────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        python3 python3-pip \
    && rm -rf /var/lib/apt/lists/*

COPY src/python/requirements.txt /tmp/requirements.txt
RUN pip3 install --no-cache-dir --break-system-packages -r /tmp/requirements.txt \
    && rm /tmp/requirements.txt

# ── App code (repo root becomes the web root, so /assets URLs work as-is) ──
COPY . /var/www/html/

# ── Apache config ────────────────────────────────────────────
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
ENV PYTHON_API_URL=http://127.0.0.1:5000

EXPOSE 80
CMD ["bash", "/var/www/html/deploy/start.sh"]
```

### 2.2 — `deploy/apache.conf`

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html

    DirectoryIndex index.php index.html
    Options -Indexes

    # Block access to config/secrets
    <DirectoryMatch "/(config|storage)/">
        Require all denied
    </DirectoryMatch>

    # Send visitors from "/" to the login page
    RedirectMatch 302 ^/$ /src/php/public/index.php

    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

### 2.3 — `deploy/start.sh`

```bash
#!/usr/bin/env bash
set -e

PORT="${PORT:-80}"

# Render tells us which port to listen on (default 10000)
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf 2>/dev/null \
  || echo "Listen ${PORT}" > /etc/apache2/ports.conf

echo "[start] launching Flask analytics API on 127.0.0.1:5000 ..."
cd /var/www/html/src/python
gunicorn --bind 127.0.0.1:5000 --workers 2 --timeout 120 app:app &

echo "[start] launching Apache on port ${PORT} ..."
exec apache2-foreground
```

### 2.4 — `.dockerignore` (keeps the image small and fast to build)

```
.git
node_modules
tests
playwright-report
test-results
qa-run.mjs
playwright.config.js
graphify-out
strix_runs
docs
*.png
*.log
crash.txt
```

---

# STEP 3 — 🗄️ Create the free database on Aiven (10 min)

1. Log in at **https://console.aiven.io**
2. Click **Create service** (or **Start free plan** → choose **MySQL**)
3. Fill in:
   - **Service name:** `examportal-db` (any name)
   - **Cloud/Region:** pick closest to your users — e.g. `Google Cloud (asia-south1)` for India
   - **Plan:** **Free** (marked "Free plan — no credit card")
4. Click **Create service** → wait ~5–10 minutes until status shows **RUNNING** ☘️
5. On the service page, open **Overview → Connection information** and note down:

   | Field | Example |
   |---|---|
   | Host | `mysql-xxxx-examportal-db.a.aivencloud.com` |
   | Port | `12345` *(Aiven uses a custom port — copy it exactly)* |
   | User | `avnadmin` |
   | Password | (click the eye 👁 to reveal) |
   | Default DB | `defaultdb` |

6. Download the CA certificate: **Overview** tab → **CA certificate** button →
   save as `ca.pem` (keep it handy for the import below).

### 3.1 — Import your schema + migrations

Open **Command Prompt** in the project folder and run (replace HOST/PORT/PASSWORD):

```bat
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set H=your-host.aivencloud.com
set P=12345
set PW=YOUR_AVNADMIN_PASSWORD

:: 1) Main schema
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb < sql\schema.sql

:: 2) Migrations — run one by one; if one says "Duplicate column", it's
::    already included in schema.sql → just skip it and run the next.
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb < sql\migration_otp.sql
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb < sql\migration_notifications.sql
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb < sql\migration_softdelete.sql
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb < sql\migration_college_wizard.sql
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb < sql\migration_batch_sections.sql
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb < sql\migration_stream_categories.sql
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb < sql\migration_students_updated_at.sql
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb < sql\migration_test_builder_wizard.sql
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb < sql\migration_unverified.sql
```

**GUI alternative:** install [DBeaver Community](https://dbeaver.io/) (free),
connect with the details above (+ enable SSL), then right-click database →
*Execute script* for each SQL file.

### 3.2 — Verify import worked

```bat
%MYSQL% --host %H% --port %P% --user avnadmin -p%PW% --ssl-ca=ca.pem defaultdb -e "SHOW TABLES;"
```

You should see the full list of tables (students, tests, submissions, …).

> 💡 **Tip:** also seed an admin account now (run your usual seed script against
> Aiven, e.g. `sql/seed_test_data.php` pointed at production creds), so you can
> log in immediately after deploy.

---

# STEP 4 — ⬆️ Push everything to GitHub (5 min)

In PowerShell at the project folder:

```powershell
git add Dockerfile deploy/ src/php/config/mail.php src/php/config/db.php .dockerignore
git commit -m "Add Docker deployment for Render + Aiven (env-based config)"
git push origin main
```

*(If your branch is `master`, push to `master` instead — check with `git branch`.)*

---

# STEP 5 — 🌍 Deploy on Render (10 min)

1. Go to **https://dashboard.render.com** → sign in with GitHub
2. Click **New +** → **Web Service**
3. Pick your repository **`Exam_portel`** → click **Connect**
   *(If it's missing: click "Configure account" and grant Render access.)*
4. Fill the form:

   | Field | Value |
   |---|---|
   | Name | `exam-portal` (becomes `exam-portal.onrender.com`) |
   | Region | Singapore (closest free region for India) |
   | Runtime | **Docker** (auto-detected from your Dockerfile) |
   | Instance Type | **Free** |

5. Scroll to **Environment Variables** → add ALL of these
   (values from Aiven Step 3 + Gmail Step 0):

   ```
   DB_ENV          = production
   DB_HOST         = your-host.aivencloud.com
   DB_PORT         = 12345
   DB_NAME         = defaultdb
   DB_USER         = avnadmin
   DB_PASS         = your-avnadmin-password
   MYSQL_SSL       = 1
   BASE_URL        = /src/php/public
   ASSETS_URL      = /assets
   PYTHON_API_URL  = http://127.0.0.1:5000
   SMTP_HOST       = smtp.gmail.com
   SMTP_PORT       = 587
   SMTP_USERNAME   = you@gmail.com
   SMTP_PASSWORD   = your-new-16-char-app-password
   SMTP_FROM       = you@gmail.com
   ```

6. Click **Create Web Service** → build takes ~5 minutes (watch the logs live)
7. When logs show `✓ Deploy live`, open **`https://exam-portal.onrender.com`**

---

# STEP 6 — ✅ Verification checklist

Test in this order — each proves one layer works:

| # | Test | What it proves |
|---|---|---|
| 1 | Open `https://…onrender.com` → redirects to login page **with CSS styling** | Apache + assets path ✓ |
| 2 | Log in as admin | PHP ↔ Aiven DB over TLS ✓ |
| 3 | Open Students/Colleges pages | Data reads ✓ |
| 4 | Create a student, take a short test as that student | Writes ✓ |
| 5 | Open admin **Analytics/Reports** → charts render | PHP → Flask → DB pipeline ✓ |
| 6 | Sign up a new student → OTP email arrives | Gmail SMTP ✓ |

If all 6 pass — **your site is fully live on the internet** 🎉

---

# STEP 7 — 🔧 Troubleshooting

| Symptom | Likely cause → Fix |
|---|---|
| First visit takes 30–60 s | Normal: Render **free sleeps after 15 min idle**, next visit wakes it. Keep a tab open during demos. |
| `SQLSTATE[HY000] [2002]` in logs | Wrong `DB_PORT` (Aiven ports are custom!) or TLS issue → recheck Step 3 values; ensure `MYSQL_SSL=1` |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong `DB_USER`/`DB_PASS` → re-copy from Aiven (eye icon 👁) |
| Pages load but **no CSS** | `ASSETS_URL` wrong → must be `/assets` (docroot = repo root) |
| Links 404 | `BASE_URL` wrong → must be `/src/php/public` |
| Analytics charts blank | Flask down → Render Shell/logs: check gunicorn started; verify `PYTHON_API_URL=http://127.0.0.1:5000` |
| OTP email never arrives | Check Render Logs for SMTP error; confirm fresh App Password (not your normal Gmail password); check spam folder |
| Aiven DB won't connect at all after days unused | Free DB **hibernates** → open Aiven console, power service back on |
| Deploy fails at build | Read the failing log line; usually a typo in Dockerfile paths or a file excluded by `.dockerignore` |

---

# 💰 Free-tier limits (what "free" means)

| Limit | Render Free | Aiven Free |
|---|---|---|
| Sleeps when idle | After 15 min (cold start ~30–60 s) | Hibernates when unused (wake manually) |
| Monthly hours | 750 hrs (~enough for 24/7 one service) | Always available on free plan |
| Bandwidth | 100 GB/mo | — |
| Storage | Ephemeral (code only — DB lives on Aiven ✓) | 5 GB data |
| Custom domain | ✅ Supported, free SSL | — |
| Credit card | ❌ Not required | ❌ Not required |

---

# 🎨 Optional extras (later)

- **Custom domain:** buy `yourdomain.com` (~₹200–800/yr) → Render Dashboard →
  Settings → Custom Domains → follow DNS instructions (free HTTPS included).
- **Keep-it-warm:** use a free cron service (cron-job.org) to ping your URL every
  10 min to prevent sleep — acceptable for demos, against the spirit of the free
  tier for long-term use.
- **Real production upgrade path:** Render paid instance ($7/mo, no sleep) +
  Aiven paid plan, same setup, zero code changes needed.

---

*Generated for Exam Portal (`03Hari-Krishna-Kumar-div/Exam_portel`) — Render + Aiven free-tier deployment.*
