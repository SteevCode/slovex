# Solvex Report — Hostel Management System User Guide

Solvex Report is a hostel complaint and maintenance management application for college students, hostel wardens, and maintenance technicians. This guide details the complete operation for each of the three user roles.

---

## 1. Student Portal (`/student/dashboard.php`)

### Registration & Profile Setup
1. **Student Registration (`/register.php`):**
   - Register with your university Register Number, university email, and a secure password (minimum 8 characters).
   - Click the verification link sent to your email to activate your account.
2. **Room Allocation Setup (`/student/profile.php`):**
   - Log in and navigate to **Profile**.
   - Select your **Hostel Building** (e.g. Boys Hostel Block A, Boys Hostel Block B, Girls Hostel).
   - Enter your assigned **Room Number** (e.g. `A-204`).
   - Click **Save Hostel Details**. This information will automatically link to all future maintenance requests.

### Submitting a Maintenance Complaint (`/student/report.php`)
The hostel intake form is a streamlined single-page form:
1. **Category Selection:** Choose from the 6 standard categories:
   - Plumbing and Water Supply
   - Electrical Problems
   - Cleaning and Sanitation
   - Room and Furniture Maintenance
   - Wi-Fi and Internet
   - Mess and Food Facilities
2. **Hostel Location & Room:**
   - Your registered room is pre-selected by default.
   - For issues in shared facilities (Dining Hall, Common Washroom, Reading Room), select the specific area from the dropdown.
3. **Priority:**
   - `Low`: Routine wear and tear.
   - `Medium`: Normal repair (Standard 48-hour SLA).
   - `High`: Significant disruption (no power, running water leak).
   - `Critical`: Urgent safety hazard or major flooding.
4. **Problem Title & Description:**
   - Enter a concise title (e.g. "Ceiling fan speed regulator broken").
   - Detail the problem in up to 1500 characters.
5. **Photos (Optional):** Attach up to 2 images (JPG, PNG, WebP up to 5MB each).
6. **Submit:** Your ticket reference (`SVX-YYYY-NNNNN`) is generated instantly with status `pending`.

### Tracking & Resolution Verification (`/complaint.php?ref=...`)
- **My Complaints (`/student/my_complaints.php`):** View status tabs: *All, Open, In Progress, Resolved, Closed*.
- **Timeline & Remarks:** Read maintenance progress updates and remarks from the warden and staff.
- **Confirming the Fix:**
  - When staff marks work completed, status changes to `resolved`.
  - Review the repair and click **"Confirm Resolution / Accept"** &rarr; closes ticket permanently (`closed_by = 'student'`).
  - If the problem persists, click **"Still Broken / Reopen"** &rarr; enter an explanation note to return the ticket to the active queue.

---

## 2. Hostel Warden / Admin Portal (`/admin/dashboard.php`)

The Warden is the central administrator overseeing all hostel facilities.

### Executive Overview Dashboard
- Monitor real-time status counters: Open, Pending, In Progress, Overdue SLA, and Resolved this Month.
- Inspect the **Intake Trends** chart and **Category Breakdown** chart.
- Quick operational triage lists:
  - *Unassigned Complaints >24 Hours*
  - *Overdue SLA Violations*
  - *Critical Priority Open Requests*

### Complaint Triage & Assignment (`/admin/complaints.php`)
1. Filter complaints by status (`pending`, `assigned`, `in_progress`, `resolved`), hostel building, category, priority, or reference search.
2. Open ticket details (`/staff/complaint.php?id=X`):
   - **Assign Staff:** Select an active maintenance technician from the dropdown. The ticket status automatically transitions to `assigned`.
   - **Adjust Priority:** Elevate priority to `high` or `critical` if required.
   - **Add Administrative Remarks:** Enter notes visible to staff or student.

### User & Staff Management (`/admin/users.php`)
- **Create Maintenance Staff Logins:** Enter staff code, full name, email, department, and initial temporary password.
- **Enable / Disable Accounts:** Disable graduated students or former staff without deleting historical complaint records.
- **Trigger Password Resets:** Issue password reset links when students forget their credentials.

### Master Configuration (`/admin/master.php`)
- **Hostel Blocks & Rooms:** Add new hostel blocks, floors, rooms, and common amenity locations.
- **Categories & SLAs:** Adjust target turnaround working days (1 to 5 days) for each category.

---

## 3. Maintenance Staff Portal (`/staff/queue.php`)

Maintenance technicians handle physical repair tasks assigned by the warden.

### Work Queue
- View tickets assigned to your department and queue.
- Use the **"Mine"** tab to filter tickets specifically assigned to you.
- Review problem title, hostel building, room number, and student contact info.

### Work Execution & Status Updates (`/staff/complaint.php?id=X`)
1. **Start Work:** Click **"Change Status &rarr; In Progress"** when physical inspection or work begins.
2. **Review Photos:** Inspect student-uploaded photos of the defective fixture or leak.
3. **Internal Notes:** Document parts ordered or notes for fellow staff (hidden from students).
4. **Mark Resolved:**
   - Once repair is completed, select **"Change Status &rarr; Resolved"**.
   - A mandatory repair note must be entered (e.g. *"Replaced faulty copper valve and verified water flow with student"*).
   - The student is immediately notified to verify the fix.

---

## 4. The 5-Stage Complaint Lifecycle

```
┌─────────────────────────────────────────────────────────────┐
│ 1. PENDING: Student submits complaint from hostel room     │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. ASSIGNED: Warden reviews & assigns maintenance staff    │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. IN PROGRESS: Technician begins repair & inspection       │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. RESOLVED: Technician completes work & enters repair note │
└──────────────────────────────┬──────────────────────────────┘
                               │
                ┌──────────────┴──────────────┐
                ▼                             ▼
┌───────────────────────────────┐ ┌───────────────────────────┐
│ 5A. CLOSED: Student confirms  │ │ 5B. REOPENED: If problem  │
│ satisfactory resolution       │ │ persists, student reopens │
└───────────────────────────────┘ └───────────────────────────┘
```
