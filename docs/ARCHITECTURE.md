# Solvex Report — Architecture

## 1. Overview
Solvex Report is a complaint management system for college and hostel environments.
Students report problems, staff resolve them, admins oversee everything.

## 2. Stack
- HTML5, CSS3 (Bootstrap 5 + custom theme), vanilla JavaScript
- PHP 8+ (PDO, sessions), MySQL/MariaDB
- Runs on XAMPP locally, free PHP hosting in production

## 3. Reference Format
`SVX-YYYY-NNNNN` (e.g., SVX-2026-00001)

## 4. User Roles
- **Student**: Reports problems, tracks status, marks resolved
- **Staff**: Manages queue for their department, updates status
- **Admin**: Full oversight, analytics, user management

## 5. Departments
1. Maintenance
2. Electrical
3. IT
4. Mess Administration
5. Hostel Warden
6. Security

## 6. Categories (with defaults)
| # | Category | Department | Priority | Visibility | Force Private | Sensitive | Target Days | Location Req |
|---|----------|-----------|----------|-----------|--------------|-----------|-------------|-------------|
| 1 | Plumbing / Water | Maintenance | medium | public | no | no | 3 | yes |
| 2 | Furniture / Civil | Maintenance | medium | public | no | no | 5 | yes |
| 3 | Electrical Issue | Electrical | high | public | no | no | 2 | yes |
| 4 | Internet / Wi-Fi | IT | high | public | no | no | 1 | yes |
| 5 | Computer / Printer | IT | medium | public | no | no | 3 | yes |
| 6 | Food Quality | Mess Administration | medium | public | no | no | 1 | no |
| 7 | Mess Hygiene | Mess Administration | high | public | no | no | 2 | no |
| 8 | Hostel Issue | Hostel Warden | medium | public | no | no | 3 | yes |
| 9 | Lost & Found | Security | low | private | yes | no | 7 | no |
| 10 | Safety / Harassment | Security | critical | private | yes | yes | 1 | no |

## 7. Complaint Lifecycle
submitted → assigned → in_progress → resolved → closed
Branches: on_hold, reopened, rejected, duplicate

## 8. Phases
1. Foundation (skeleton, brand, DB, auth, landing)
2. Student complaint flow
3. Staff queue and admin dashboard
4. Polish, notifications, analytics
