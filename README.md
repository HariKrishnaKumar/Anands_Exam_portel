# 🎓 Exam Portal

> A comprehensive web-based examination and student performance analysis platform for managing online assessments, students, tests, evaluations, and performance analytics.

![License](https://img.shields.io/badge/License-GPLv3-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![Python](https://img.shields.io/badge/Python-3.x-3776AB?logo=python&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql&logoColor=white)

---

## 📌 Overview

Exam Portal is a full-stack web application designed to simplify and digitize the examination process for educational institutions.

- **Administrators** get tools to manage colleges, courses, batches, students, examinations, questions, and results.
- **Students** take online examinations through a secure, structured interface — submissions and activity are recorded automatically.
- A dedicated **Python Flask analytics service** calculates PCI (Performance/Competency Index) scores and generates performance analytics.

---

## ✨ Key Features

### 👨‍💼 Administration
- Admin authentication & college management
- Course, batch, student & question management
- Test scheduling and examination management
- Student performance monitoring & live exam monitoring

### 🧑‍🎓 Student Examination
- Student registration, authentication & OTP verification
- Online examination interface with timer
- Multiple question types — MCQ, coding, descriptive
- Automatic submission handling & guest/QR-based access

### 📊 Evaluation & Analytics
- Submission tracking & marks management
- PCI score calculation with performance bands
- Batch-level analysis & student performance history
- Analytical chart data & tab-switch monitoring

### 🔔 System Features
- Notification support & system error notifications
- OTP functionality & soft-delete support
- Database migrations & test data seeding

---

## 🏗️ Architecture

```
                ┌─────────────────────┐
                │      Frontend       │
                │   HTML / CSS / JS   │
                └──────────┬──────────┘
                           ▼
                ┌─────────────────────┐
                │    PHP Backend      │
                │  Built-in server /  │
                │       Apache        │
                └──────────┬──────────┘
              ┌────────────┴────────────┐
              ▼                         ▼
    ┌──────────────────┐      ┌──────────────────┐
    │      MySQL       │      │   Flask API      │
    │    Database      │      │  PCI / Analytics │
    └──────────────────┘      └────────┬─────────┘
                                       ▼
                             ┌──────────────────┐
                             │   Performance    │
                             │    Analytics     │
                             └──────────────────┘
```

---

## 🛠️ Technology Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP |
| Web Server | PHP built-in server / Apache |
| Database | MySQL 8.0+ |
| DB Administration | phpMyAdmin |
| Analytics API | Python, Flask |
| Python DB Driver | mysql-connector-python |
| Testing | Playwright |
| Package Management | npm / pip |

---

## 📋 Requirements

**Required**

- [PHP 8.x](https://www.php.net/downloads) — or XAMPP (includes PHP + MySQL)
- MySQL 8.0+
- Python 3.x

**Optional**

- Node.js + npm — for Playwright automated tests
- Git
- VS Code or any code editor

---

## 🚀 Quick Start

> **Prerequisite:** MySQL is running. Copy `.env.example` → `.env` and fill in your values first (see [Environment Configuration](#️-environment-configuration-env)).

**1. Clone, configure & run the PHP server:**

```bash
git clone https://github.com/HariKrishnaKumar/Anands_Exam_portel.git
cd Anands_Exam_portel
copy .env.example .env        # Windows  |  cp .env.example .env  (Linux/macOS)
php -S localhost:8000 router.php
```

➡ Open **http://localhost:8000**

**2. Import the database (first time only):**

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS test_platform"
mysql -u root -p test_platform < sql/schema.sql
```

*(or import `sql/schema.sql` via phpMyAdmin)*

**3. Start the analytics service (optional):**

```bash
cd src/python
python -m venv venv && venv\Scripts\activate    # Windows | source venv/bin/activate (Linux/macOS)
pip install -r requirements.txt
python app.py
```

---

## ⚙️ Environment Configuration (.env)

All confidential values — database credentials, SMTP/app passwords — live in a `.env` file at the project root. **This file is gitignored; real secrets are never committed.**

```bash
copy .env.example .env         # Windows
cp .env.example .env           # Linux / macOS
```

Then edit `.env`:

```ini
DB_ENV=local

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=test_platform
DB_USER=root
DB_PASS=

MAIL_DRIVER=smtp
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your@gmail.com
SMTP_PASSWORD=your-16-char-gmail-app-password
SMTP_FROM=your@gmail.com
SMTP_FROM_NAME=Test Platform
```

> 📌 **Notes**
> - Gmail SMTP requires an [App Password](https://myaccount.google.com/apppasswords), not your regular password.
> - The loader (`src/php/config/env.php`) reads real environment variables **first**, then falls back to `.env` — so on hosting platforms (Oracle Cloud, Render, etc.) set the same keys as platform environment variables instead of using a file.
> - Never hardcode credentials in `db.php` or `mail.php`.

---

## 🗄️ Database Setup

1. Create a database named `test_platform`
2. Import [`sql/schema.sql`](sql/schema.sql)

The schema creates all core tables: administrators, colleges, courses, batches, students, tests, questions, submissions, student answers, tab-switch logs, PCI records, and guest entries.

Additional migrations are available in `sql/` — college wizard, notifications, OTP, soft-delete, stream categories, batch sections, hybrid evaluation, and more. For a fresh installation, start with `sql/schema.sql`, then apply migrations as needed.

---

## 🐍 Python Analytics Service

The Flask-based service handles PCI calculation and analytics.

```bash
cd src/python
pip install -r requirements.txt
python app.py
```

Health check → http://127.0.0.1:5000/health

### API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| GET | `/health` | Service health check |
| POST | `/api/pci/calculate` | Calculate PCI for a submission |
| GET | `/api/pci/batch/<test_id>` | PCI results for a test |
| GET | `/api/pci/student/<student_id>` | Student PCI history |
| GET | `/api/charts/test/<test_id>` | Test chart data |

The PHP app communicates with this service via `PYTHON_API_URL` (default: `http://127.0.0.1:5000`).

---

## 🧪 Testing

```bash
npm install
npx playwright install
npm test                  # run test suite
npm run test:headed       # headed mode
npm run test:report       # view HTML report
```

---

## 📁 Project Structure

```
Exam_portel/
│
├── .env.example               # Environment template (copy to .env)
├── router.php                 # Router for PHP built-in server
│
├── assets/                    # Stylesheets & images
│   ├── css/
│   └── img/
│
├── sql/                       # Schema & migrations
│   ├── schema.sql             # ← Import this first
│   └── migration_*.sql
│
├── src/
│   ├── php/
│   │   ├── api/               # AJAX/JSON endpoints
│   │   ├── config/            # env.php · db.php · mail.php
│   │   ├── includes/          # Auth, session, mailer, helpers
│   │   └── public/            # Entry pages (login, signup, dashboards…)
│   │       ├── admin/
│   │       └── student/
│   │
│   └── python/
│       ├── analysis/          # PCI engine & charts
│       ├── app.py             # Flask API entry point
│       └── requirements.txt
│
└── README.md
```

---

## 🔐 Default Administrator

| Field | Value |
|---|---|
| Email | `admin@testplatform.com` |
| Password | `admin123` |

> ⚠️ **Change the default password immediately after first login. Never use default credentials in production.**

---

## 🔒 Security Considerations

Before deploying to production:

- ✅ Store credentials in environment variables (`.env`) — never commit secrets
- ✅ Change all default credentials; use a strong MySQL password
- ✅ Enable HTTPS & configure secure session cookies
- ✅ Use prepared SQL statements & validate all user input
- ✅ Restrict database permissions & disable PHP error output
- ✅ Review CORS and API access policies
- ✅ Keep PHP, Python, MySQL, Node.js and dependencies updated

---

## 🐛 Troubleshooting

| Problem | Fix |
|---|---|
| Database connection failed | Verify host/port/user/password in `.env` match your MySQL setup |
| 404 / Page not found (built-in server) | Run from the project root: `php -S localhost:8000 router.php` |
| 404 / Page not found (XAMPP) | Ensure Apache is running and `BASE_URL` in `src/php/config/db.php` matches your folder name |
| Flask API not available | Start it: `python src/python/app.py`, then check `/health` |
| OTP email never arrives | Use a fresh Gmail App Password; check spam folder |
| Playwright errors | `npm install && npx playwright install` |

---

## 🤝 Contributing

Contributions are welcome!

```bash
git checkout -b feature/new-feature
git add .
git commit -m "Add new feature"
git push origin feature/new-feature
```

Then open a Pull Request.

---

## 📜 License

This project is licensed under the **[GNU General Public License v3.0](LICENSE)**.

```
Exam Portal — Copyright (C) 2026 HariKrishnaKumar

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

See the full license text in [LICENSE](LICENSE).

---

## 👨‍💻 Author

**HariKrishnaKumar**

GitHub Repository: [Anands_Exam_portel](https://github.com/HariKrishnaKumar/Anands_Exam_portel)

⭐ If you find this project useful, consider giving the repository a star!
