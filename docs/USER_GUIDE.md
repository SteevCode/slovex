# Solvex Report — User Guide

Welcome to **Solvex Report**, the campus incident reporting and transparent resolution tracking portal.

---

## 1. Student Portal (`/student/dashboard.php`)
- **Dashboard Overview:** Displays your open reports, tickets in progress, and tickets marked *Resolved* requiring your confirmation.
- **Reporting an Issue (`/student/report.php`):**
  1. **WHAT:** Select an issue category (visual cards with SLA days and public/private badges). Safety and electrical issues show immediate emergency call banners.
  2. **WHERE:** Cascading location selects (Campus/Hostel &rarr; Building &rarr; Floor &rarr; Specific room) + optional "Use My Location" GPS button.
  3. **DETAILS:** Title (max 100 chars), Description (live 1500 char counter), up to 3 drag-and-drop photos (stripped of GPS/EXIF), urgency checkbox, and visibility setting.
  - **Duplicate Prevention:** Before submitting, Solvex detects if matching open tickets were reported in the last 14 days and allows you to click *"I am also affected"* instead of creating duplicate records.
- **My Complaints (`/student/my_complaints.php`):** Filter by status tabs, search, and track resolution timelines.
- **Public Feed (`/feed.php`):** See civic campus problems anonymously, view photos, and toggle *"I am also affected"*.
- **Accepting Resolution:** When staff marks work done, click *"Accept Resolution"* to officially close the ticket, or *"Still Broken"* to reopen with notes.

---

## 2. Staff Resolution Queue (`/staff/queue.php`)
- **Queue Triage:** Sticky filter bar with live search, status, priority, category, and date filters.
- **Quick Tabs:** *Unassigned*, *Mine*, *Overdue* (highlighted in red when exceeding SLA targets), and *On Hold*.
- **Ticket Workspace (`/staff/complaint.php?id=X`):**
  - **Assignment:** Assign ticket to yourself or a department colleague.
  - **Status Transitions:** Change status following strict state machine rules (In Progress, On Hold with future date, Resolved with note).
  - **Internal Notes:** Post confidential staff notes (flagged with dark shield, 100% invisible to students).
  - **Ask Student:** Request clarification; directly notifies student.
  - **Mark Duplicate & Reassign:** Reassign to other campus departments or link duplicates.
  - **Reporter PII & GPS:** Authorized staff can view student name, register number, and click *"Open in Google Maps"*.

---

## 3. Administrator Control Center (`/admin/dashboard.php`)
- **Live Metrics:** Open tickets, unassigned tickets over 24 hours, overdue SLA violations, critical incidents, and monthly closed counts.
- **Analytics Charts:** 30-day intake vs. resolution trends and category distribution charts powered by Chart.js.
- **Complaint Management (`/admin/complaints.php`):** Global ticket lookup across all campus departments.
- **User Management (`/admin/users.php`):** Create staff accounts with temporary passwords, enable/disable accounts, and trigger password reset links.
- **Master Data (`/admin/master.php`):** Full CRUD for categories, SLA target days, departments, and campus location tree. Soft-disable preserves old tickets.
- **Student Master Import (`/admin/import.php`):** Upload college CSV registry, preview row-by-row validation, and execute upserts.
- **CSV Reports (`/admin/reports.php`):** Export complaint metrics to CSV with optional authorized reporter identity toggle.
- **Public Accountability (`/admin/accountability.php`):** Public leaderboard displaying longest unresolved public reports and department reopen rates.

