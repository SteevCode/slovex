# Solvex Report — Comprehensive User Guide

Solvex Report is an institutional problem reporting, department triage, and civic accountability platform built for college campuses and hostel facilities. This guide walks students, staff members, and system administrators through their respective roles.

---

## 1. Student Portal (`/student/dashboard.php`)

### Overview & Account Registration
1. **Pre-Authorized Registry:** Registration requires a valid University Register Number found in the institution's `student_master` database.
2. **Email Verification:** A single-use activation link is dispatched upon registration. Once confirmed, you can log in using your student email or register number.

### Step-by-Step Problem Intake (`/student/report.php`)

The reporting wizard is structured into three clear stages:

#### Step 1: WHAT (Problem Category)
- Select the applicable issue category from the visual ledger (e.g., Plumbing, Civil, Electrical, Wi-Fi, Food Quality).
- Each category card clearly displays its standard **SLA target resolution time** (e.g., 24 hours, 2 days, 3 days).
- Urgent safety hazards (e.g. Electrical sparks, Harassment) trigger immediate emergency contact call banners.

#### Step 2: WHERE (Campus Location & GPS)
- **Cascading Selectors:** Pick Campus/Hostel &rarr; Building &rarr; Floor &rarr; Specific Room/Lab.
- **Geolocation:** Click *"Use My Current Location"* to capture precise GPS latitude and longitude (automatically sanitized for peer privacy).

#### Step 3: DETAILS (Description & Photos)
- **Title & Description:** Enter a brief title and a detailed description (live 1500-character counter).
- **Photo Uploads:** Drag and drop up to 3 JPG, PNG, or WebP images. The upload pipeline automatically strips GPS, camera, and device metadata before storage.
- **Duplicate Prevention:** Solvex automatically scans open complaints filed within the preceding 14 days in the same location. If a match is detected, you can click *"I am also affected"* instead of filing a duplicate report.
- **Visibility Toggle:** Choose between **Public** (visible anonymously on campus feed) or **Private** (restricted to you and department staff).

### Tracking & Confirming Resolution
- **My Complaints (`/student/my_complaints.php`):** View all submitted tickets, filter by status, and inspect real-time audit event timelines.
- **Accepting Resolution:** When staff marks work completed, the ticket enters `resolved` status. The student can click:
  - **"Accept Resolution":** Confirms satisfactory completion and permanently closes the ticket (`closed_by = 'student'`).
  - **"Still Broken":** Reopens the ticket with a required explanation note for department review.

---

## 2. Staff Resolution Queue (`/staff/queue.php`)

Department staff members (Maintenance, Electrical, IT, Mess Administration, Hostel Warden, Security) are restricted to tickets belonging strictly to their assigned department.

### Queue Triage & Filtering
- **Quick Filters:** Filter by *Unassigned*, *Assigned to Me*, *Overdue SLA*, or *On Hold*.
- **Search & Filter Bar:** Instant search by reference ID (`SVX-YYYY-NNNNN`), keyword, priority, and date range.
- **Civic Ageing Chips:** Color-coded clock chips highlight tickets approaching or exceeding SLA targets.

### Ticket Workspace (`/staff/complaint.php?id=X`)
- **Self-Assignment:** Claim unassigned complaints or reassign them to team colleagues.
- **Status Progression:**
  - **In Progress:** Mark that technicians have started physical work.
  - **On Hold:** Temporarily pause work pending spare parts or scheduled vendor visits (requires mandatory future hold date and reason).
  - **Resolved:** Mark work finished with a mandatory summary note detailing the repair.
  - **Rejected:** Refuse inappropriate or out-of-scope complaints with written justification.
- **Confidential Internal Notes:** Post internal remarks visible exclusively to department staff and administrators. Students and public feeds never receive these notes.
- **Clarification Requests:** Post questions directly to the student reporter. The student receives an in-app notification.
- **Duplicate Linking:** Link duplicate reports to a parent ticket. Both records remain preserved in the audit database.

---

## 3. Administrator Control Center (`/admin/dashboard.php`)

System administrators maintain global institutional oversight.

### Analytics & Live Metrics
- **Executive Metric Cards:** Total open tickets, unassigned tickets >24 hours, active SLA violations, critical incidents, and 30-day resolution totals.
- **Interactive Visualizations:** 30-day intake vs. resolution trends and category volume distribution charts.

### Global Incident Management (`/admin/complaints.php`)
- Cross-department lookup, advanced multi-parameter search, and force-reassignment capabilities.
- Force-closure with mandatory administrative justification logged to the immutable audit ledger.

### User Administration (`/admin/users.php`)
- Create new department staff accounts with generated temporary credentials.
- Disable accounts (blocks login immediately while preserving historical ticket assignments).
- Trigger manual password reset links.
- Safeguard: The system prevents disabling or demoting the last active administrator.

### Master Data Configuration (`/admin/master.php`)
- Full CRUD management of categories, SLA target days, departments, and location trees.
- Non-destructive soft-disables ensure historical complaints remain readable.

### Student Registry CSV Import (`/admin/import.php`)
- Upload institutional student registers (`register_no, full_name, email, department, year`).
- Two-stage import: Validates every row for format and duplicate constraints before committing transactions.

### Reports & Accountability (`/admin/reports.php` & `/admin/accountability.php`)
- **CSV Data Exports:** Export filtered complaint records with an authorized reporter identity toggle.
- **Public Accountability Leaderboard:** Displays campus-wide metrics, department reopen rates, and the longest unresolved public issues.

---

## 4. Notifications & User Preferences

- **In-App Notification Center:** Accessible via the top navigation bell. Displays status changes, staff inquiries, and resolution notices.
- **Theme Switcher:** Toggle between crisp Light paper mode and high-contrast Dark mode using the sun/moon icon in the navigation bar. Preferences persist across sessions via `localStorage`.

---

## 5. Frequently Asked Questions & Troubleshooting

### Why is my complaint marked "Private"?
Certain sensitive categories (such as Safety / Harassment or Lost & Found) are automatically enforced as Private by administrative policy to protect student confidentiality.

### Can staff see my identity on public complaints?
Department staff and administrators can view reporter identity for legitimate campus service triage. However, peer students on the public feed only see `"Anonymous student"` and never receive your name, room number, or GPS coordinates.

### What happens if I forget my password?
Navigate to `/forgot.php`, enter your registered student or staff email, and follow the password reset link. In local development mode, a debug link is provided directly on the page.
