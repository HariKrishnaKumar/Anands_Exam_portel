🎓 Exam Portal

«A comprehensive web-based examination and student performance analysis platform for managing online assessments, students, tests, evaluations, and performance analytics.»

📌 Overview

Exam Portal is a full-stack web application designed to simplify and digitize the examination process for educational institutions.

The platform provides administrators with tools to manage colleges, courses, batches, students, examinations, questions, and results. Students can participate in online examinations through a secure and structured interface, while the system records submissions and examination activity for further analysis.

The project also includes a dedicated Python Flask analytics service for calculating PCI (Performance/Competency Index) scores and generating performance analytics.

---

✨ Key Features

👨‍💼 Administration

- Admin authentication
- College management
- Course and batch management
- Student management
- Examination management
- Question management
- Test scheduling
- Student performance monitoring

🧑‍🎓 Student Examination

- Student registration and authentication
- Online examination interface
- Multiple question types
- MCQ-based questions
- Coding questions
- Explanation/descriptive questions
- Examination timer
- Test submission
- Automatic submission handling
- Guest/QR-based examination access

📊 Evaluation & Analytics

- Examination submission tracking
- Question-level answer storage
- Marks management
- Student performance analysis
- PCI score calculation
- Performance bands
- Batch-level performance analysis
- Student performance history
- Analytical chart data
- Tab-switch monitoring

🔔 System Features

- Notification support
- OTP-related functionality
- Soft-delete support
- System error notifications
- Database migrations
- Test data seeding

---

🏗️ Architecture

The application follows a modular architecture consisting of three major layers:

                    ┌─────────────────────┐
                    │      Frontend       │
                    │ HTML / CSS / JS     │
                    └──────────┬──────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │    PHP Backend      │
                    │   Apache / XAMPP    │
                    └──────────┬──────────┘
                               │
                  ┌────────────┴────────────┐
                  │                         │
                  ▼                         ▼
        ┌──────────────────┐      ┌──────────────────┐
        │      MySQL       │      │   Flask API      │
        │    Database      │      │ PCI / Analytics  │
        └──────────────────┘      └────────┬─────────┘
                                           │
                                           ▼
                                  ┌──────────────────┐
                                  │ Performance      │
                                  │ Analytics        │
                                  └──────────────────┘

---

🛠️ Technology Stack

Layer| Technology
Frontend| HTML5, CSS3, JavaScript
Backend| PHP
Web Server| Apache
Local Development| XAMPP
Database| MySQL 8.0+
Database Administration| phpMyAdmin
Analytics API| Python, Flask
Python Database Driver| mysql-connector-python
Testing| Playwright
Package Management| npm / pip

---

📋 Requirements

Before running the project, install the following:

Required

- PHP 8.x (https://www.php.net/downloads) — or XAMPP (includes PHP)
- MySQL 8.0+ (standalone, or included with XAMPP)
- Python 3.x

Optional

- Node.js and npm — required for Playwright automated tests
- Git — recommended for cloning the repository
- Visual Studio Code or another code editor

---

🚀 Installation & Setup

1. Clone the Repository

git clone https://github.com/HariKrishnaKumar/Anands_Exam_portel.git

Navigate into the project:

cd Anands_Exam_portel

2. Run with the PHP Built-in Server (Recommended — no htdocs needed)

You do NOT need to move the project into "htdocs". The project can run from any custom location using PHP's built-in development server together with "router.php".

From the project root:

php -S localhost:8000 router.php

Then open:

http://localhost:8000

The built-in server automatically uses an empty "BASE_URL" (see "src/php/config/db.php"), so paths work from any folder.

Stop the server with Ctrl+C.

---

3. Alternative: Configure XAMPP

Install and open XAMPP Control Panel.

Start the following services:

Apache
MySQL

Both services must be running before accessing the application.

---

4. Move the Project to "htdocs" (XAMPP only)

Copy the project into the XAMPP "htdocs" directory.

Windows

C:\xampp\htdocs\

For the current configuration, the recommended folder name is:

C:\xampp\htdocs\test-platform\

Therefore, the final path should be:

C:\xampp\htdocs\test-platform\

«Important: The repository is named "Exam_portel", but the current PHP configuration uses "/test-platform" as the Apache base path. If you use another folder name, update "BASE_URL" in "src/php/config/db.php".»

---

🗄️ Database Setup

4. Open phpMyAdmin

Open:

http://localhost/phpmyadmin/

Create a database named:

test_platform

The project's database schema already contains the database creation statement, so you can also import it directly.

---

5. Import the Database Schema

In phpMyAdmin:

1. Select the "test_platform" database.
2. Click Import.
3. Select:

sql/schema.sql

4. Click Go.

The schema creates the core database structure required by the application, including tables for:

- Administrators
- Colleges
- Courses
- Batches
- Students
- Tests
- Questions
- Submissions
- Student answers
- Tab-switch logs
- PCI records
- Guest entries

The database uses MySQL 8.0+, "InnoDB", and "utf8mb4".

---

⚙️ Environment Configuration (.env)

6. Configure Database & Mail Secrets

All confidential values (database credentials, SMTP/app passwords) are stored in a ".env" file at the project root. This file is GITIGNORED — real secrets are never committed to Git.

Copy the provided template:

# Windows (PowerShell)
copy .env.example .env

# Linux / macOS
cp .env.example .env

Then edit ".env" and fill in your values:

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

Notes:

- For Gmail SMTP you MUST use a Gmail App Password (not your regular password): https://myaccount.google.com/apppasswords
- The loader is implemented in "src/php/config/env.php" and reads real environment variables first, then falls back to ".env" — so on hosting platforms (Oracle Cloud, Render, etc.) you can set the same keys as platform environment variables instead of using a file.
- Never hardcode credentials in "src/php/config/db.php" or "src/php/config/mail.php".

---

🌐 Running the PHP Application

7. Start the Application

Option A — PHP built-in server (any folder):

php -S localhost:8000 router.php

Then open:

http://localhost:8000

Option B — XAMPP/Apache:

With Apache and MySQL running, open:

http://localhost/test-platform/src/php/public/

If you keep the original project folder name instead of "test-platform", update the "BASE_URL" configuration in:

src/php/config/db.php

and use the corresponding URL.

---

🐍 Python Analytics Service

The project contains a separate Flask-based service responsible for PCI calculation and analytics.

The Python service is located at:

src/python/

The main application is:

src/python/app.py

The required dependencies are defined in:

src/python/requirements.txt

Current dependencies include:

Flask==3.0.0
mysql-connector-python==8.2.0
gunicorn==21.2.0

---

8. Create a Python Virtual Environment

From the project root:

cd src/python

Create the environment:

Windows

python -m venv venv

Activate it:

venv\Scripts\activate

Linux / macOS

python3 -m venv venv

Activate:

source venv/bin/activate

---

9. Install Python Dependencies

pip install -r requirements.txt

---

10. Start the Flask Service

Run:

python app.py

The PHP application is configured to communicate with the Python service through:

http://127.0.0.1:5000

The API provides endpoints for health checks, PCI calculation, student performance history, batch analytics, and chart data.

Health Check

Open:

http://127.0.0.1:5000/health

A successful service should return a response indicating that the "pci-analysis" service is running.

---

🔌 API Endpoints

The Flask analytics service currently exposes endpoints including:

Method| Endpoint| Description
"GET"| "/health"| Service health check
"POST"| "/api/pci/calculate"| Calculate PCI for a submission
"GET"| "/api/pci/batch/<test_id>"| Retrieve PCI results for a test
"GET"| "/api/pci/student/<student_id>"| Retrieve student PCI history
"GET"| "/api/charts/test/<test_id>"| Retrieve test chart data

---

🧪 Testing

The project includes Playwright-based automated testing.

Install Node Dependencies

From the project root:

npm install

Install Playwright browsers:

npx playwright install

Run the test suite:

npm test

Run tests in headed mode:

npm run test:headed

View the Playwright report:

npm run test:report

The project's "package.json" currently defines these Playwright commands.

---

📁 Project Structure

Exam_portel/
│
├── .env.example          ← environment template (copy to .env)
├── assets/
│
├── docs/
│
├── sql/
│   ├── schema.sql
│   ├── migration_college_wizard.sql
│   ├── migration_notifications.sql
│   ├── migration_otp.sql
│   ├── migration_softdelete.sql
│   ├── migration_stream_categories.sql
│   ├── migration_students_updated_at.sql
│   ├── migration_unverified.sql
│   └── seed_test_data.php
│
├── src/
│   │
│   ├── php/
│   │   ├── api/
│   │   ├── config/
│   │   │   ├── env.php    ← .env loader (no Composer needed)
│   │   │   ├── db.php
│   │   │   └── mail.php
│   │   ├── includes/
│   │   └── public/
│   │
│   └── python/
│       ├── analysis/
│       ├── app.py
│       └── requirements.txt
│
├── package.json
├── router.php            ← router for the PHP built-in server
└── README.md

---

🗃️ Database Migrations

Additional database migrations are available in:

sql/

These migrations provide additional functionality such as:

- College wizard support
- Notifications
- OTP functionality
- Soft deletion
- Stream categories
- Student timestamp updates
- Unverified user handling

For a fresh installation, start with:

sql/schema.sql

Apply additional migrations according to the features/version required by your deployment.

---

🔐 Default Administrator

The database schema contains a default administrator account:

Email:    admin@testplatform.com
Password: admin123

«⚠️ Security: Change the default password immediately after the first login. Never use the default credentials in a production environment.»

---

🔒 Security Considerations

Before deploying the application to production:

- Change all default credentials.
- Configure a strong MySQL password.
- Store credentials using environment variables.
- Do not commit secrets or passwords to Git.
- Enable HTTPS.
- Configure secure session cookies.
- Validate all user input.
- Use prepared SQL statements.
- Restrict database permissions.
- Disable unnecessary PHP error output in production.
- Configure the Flask service securely.
- Review CORS and API access policies.
- Keep PHP, Python, MySQL, Node.js, and dependencies updated.

---

🐛 Troubleshooting

Database Connection Failed

Check:

Host:     127.0.0.1
Port:     3306
Database: test_platform
Username: root
Password: your MySQL password

Then verify:

src/php/config/db.php

---

404 / Page Not Found

Verify that the project is inside:

C:\xampp\htdocs\

and that Apache is running.

For the default configuration:

C:\xampp\htdocs\test-platform\

open:

http://localhost/test-platform/src/php/public/

---

Flask API Not Available

Check whether the Python service is running:

python src/python/app.py

Then test:

http://127.0.0.1:5000/health

---

Python Dependency Error

Run:

cd src/python
pip install -r requirements.txt

---

Playwright Test Error

Run:

npm install
npx playwright install

Then:

npm test

---

📈 Performance Analysis

The platform includes a dedicated PCI analysis component.

The Python service processes examination performance data and calculates:

- MCQ performance
- Coding performance
- Explanation performance
- Overall PCI score
- Performance bands
- Student performance history
- Batch-level statistics
- Distribution data
- Chart data

The PHP application communicates with the Python service through the configured:

PYTHON_API_URL

which defaults to:

http://127.0.0.1:5000

---

🚀 Quick Start

For developers who want to get the application running quickly:

# 1. Clone
git clone https://github.com/HariKrishnaKumar/Anands_Exam_portel.git

# 2. Enter project
cd Anands_Exam_portel

# 3. Create .env from the template and fill in your values
copy .env.example .env    # Windows  (cp on Linux/macOS)

# 4. Start MySQL (XAMPP or standalone service)

# 5. Import database
# sql/schema.sql via phpMyAdmin or: mysql -u root -p test_platform < sql/schema.sql

# 6. Start the PHP server (no htdocs needed)
php -S localhost:8000 router.php

# 7. Install Python dependencies (analytics service, optional)
cd src/python
python -m venv venv

# Windows
venv\Scripts\activate

pip install -r requirements.txt

# 8. Start Python API (in a second terminal)
python app.py

Then open:

http://localhost:8000

---

📌 Development Notes

The application supports both:

- Local XAMPP/Apache deployment
- PHP development-server style routing

The repository also includes "router.php", which provides routing support for the PHP built-in development server and handles application paths, assets, APIs, and clean URLs.

For a standard student/developer setup, XAMPP + Apache + MySQL + Python Flask is recommended.

---

🤝 Contributing

Contributions, improvements, and bug fixes are welcome.

To contribute:

1. Fork the repository.
2. Create a feature branch.
3. Make your changes.
4. Test the changes locally.
5. Commit your changes.
6. Push the branch.
7. Open a Pull Request.

Example:

git checkout -b feature/new-feature

git add .

git commit -m "Add new feature"

git push origin feature/new-feature

---

📜 License

This project is currently maintained as an academic/development project.

Refer to the repository for the latest project information and licensing details.

---

👨‍💻 Author

03Hari-Krishna-Kumar-div

GitHub Repository:

https://github.com/03Hari-Krishna-Kumar-div/Exam_portel

---

⭐ Support

If you find this project useful, consider giving the repository a ⭐ on GitHub.

Built for modern, efficient, and data-driven online examinations.
