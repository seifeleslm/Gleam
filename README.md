# Gleam – Full Stack Project
## Egypt's Children Care & Support Platform

---

## 📁 Project Structure

```
gleam/
├── index.html              ← Frontend website (open in browser)
├── config/
│   └── db.php              ← Database credentials (edit this first!)
├── api/
│   ├── auth.php            ← Register & Login  →  POST /api/auth.php?action=register|login
│   ├── providers.php       ← Providers list    →  GET  /api/providers.php
│   ├── dashboard.php       ← Provider stats    →  GET  /api/dashboard.php
│   ├── reports.php         ← Reports           →  GET/POST /api/reports.php
│   ├── subscriptions.php   ← Subscriptions     →  GET/POST/PUT /api/subscriptions.php
│   ├── reviews.php         ← Ratings & Reviews →  GET/POST /api/reviews.php
│   └── profile.php         ← Profile details   →  GET/POST /api/profile.php?action=doctor|nurse|teacher|coach
├── includes/
│   └── auth.php            ← JWT token helpers
└── gleam_database.sql      ← Full MySQL schema (run this first!)
```

---

## ⚙️ Setup Instructions

### 1. Create the Database
```sql
CREATE DATABASE gleam_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gleam_db;
-- then run gleam_database.sql
```
Or in terminal:
```bash
mysql -u root -p gleam_db < gleam_database.sql
```

### 2. Configure Database Credentials
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gleam_db');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

### 3. Place Files on Server
Put the entire `gleam/` folder inside your web server root:
- **XAMPP**: `C:/xampp/htdocs/gleam/`
- **WAMP**:  `C:/wamp64/www/gleam/`
- **Linux**: `/var/www/html/gleam/`

### 4. Update API Base URL in index.html
Find this line in `index.html` and update:
```js
const API = {
  base: 'http://localhost/gleam/api',  // ← change to your server
  ...
};
```

### 5. Open the Website
Visit: `http://localhost/gleam/index.html`

---

## 🔌 API Reference

All endpoints return JSON. Protected routes require:
```
Authorization: Bearer <token>
```

### Auth
| Method | Endpoint | Body | Description |
|--------|----------|------|-------------|
| POST | `/api/auth.php?action=register` | `{email, password, role}` | Create account |
| POST | `/api/auth.php?action=login` | `{email, password}` | Login → returns JWT |

### Providers
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/providers.php` | List all providers |
| GET | `/api/providers.php?job=doctor` | Filter by job type |
| GET | `/api/providers.php?id=X` | Single provider details |
| POST | `/api/providers.php?action=create` | Create profile (auth) |
| PUT | `/api/providers.php?action=update` | Update profile (auth) |

### Dashboard (auth required)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/dashboard.php` | Stats: clients, earnings, reports, subs |

### Reports (auth required)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/reports.php` | Provider's reports |
| POST | `/api/reports.php?action=create` | Create & send report |

### Subscriptions (auth required)
| Method | Endpoint | Body | Description |
|--------|----------|------|-------------|
| GET | `/api/subscriptions.php` | — | List subs (filter `?status=active`) |
| POST | `/api/subscriptions.php?action=create` | `{child_id, parent_id, start_date, end_date, price_egp}` | New subscription |
| PUT | `/api/subscriptions.php?action=update&id=X` | `{status: paused|cancelled}` | Update status |

### Reviews
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/reviews.php?provider_id=X` | Public reviews |
| POST | `/api/reviews.php?action=create` | Submit review (auth) |

### Profile Details (auth required)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/profile.php` | Full profile |
| POST | `/api/profile.php?action=doctor` | Doctor details |
| POST | `/api/profile.php?action=nurse` | Nurse details |
| POST | `/api/profile.php?action=teacher` | Teacher details |
| POST | `/api/profile.php?action=coach` | Coach details |

---

## 🗄️ Database Tables

| Table | Purpose |
|-------|---------|
| `users` | Auth credentials |
| `providers` | Provider profiles |
| `provider_jobs` | Job types per provider |
| `provider_availability` | Available days |
| `doctor_details` + `doctor_specializations` | Doctor-specific data |
| `nurse_details` + `nurse_services` | Nurse-specific data |
| `teacher_details` + `teacher_subjects` + `teacher_special_needs_experience` | Teacher data |
| `coach_details` + `coach_sports` | Coach-specific data |
| `parents` + `children` | Parent/child profiles |
| `subscriptions` | Active/paused/expired subscriptions |
| `reports` + `report_recipients` + `report_attachments` | Reports system |
| `reviews` | Ratings & review text |
| `provider_service_details` | Pricing & hours |

---

## 🔒 Security Notes
- Change `JWT_SECRET` in `config/db.php` before going live
- Use HTTPS in production
- Set proper MySQL user permissions (not root)
- Add CSRF protection for production forms
