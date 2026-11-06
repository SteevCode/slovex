# Solvex Report — 6-Minute Live Demo Script

**Objective:** Demonstrate the end-to-end problem reporting lifecycle across all three roles (Student &rarr; Staff &rarr; Administrator) during live submission or evaluation.

---

### Step 1: Student Reports a Problem (Minutes 0:00 – 1:30)
1. **Login as Student:**
   - URL: `http://127.0.0.1:8000/login.php`
   - Email: `student@solvex.edu` | Password: `Student@123`
2. **Navigate to Report Flow:**
   - Click **"Report a Problem"** button.
   - **Step 1 (WHAT):** Select *"Plumbing / Water"* (visual category card showing SLA: 3 days, Public). Notice the emergency banner warning for Electrical or Safety categories. Click *Next*.
   - **Step 2 (WHERE):** Select *Academic Block &rarr; Floor 2 &rarr; Room 201*. Click *"Use My Location"* to demonstrate GPS coordinate acquisition. Click *Next*.
   - **Step 3 (DETAILS):** Title: *"Broken water tap leaking on 2nd floor washroom"*. Description with live counter. Drag and drop photo.
   - Click **Submit Report**.
3. **Reference Generation:**
   - View generated ticket reference `SVX-2026-00046`. Click **"View timeline"** to show immutable audit trail and "Open for 0 days" badge.

---

### Step 2: Staff Triages & Resolves Ticket (Minutes 1:30 – 3:30)
1. **Login as Staff (Maintenance):**
   - URL: `http://127.0.0.1:8000/login.php`
   - Email: `staff@solvex.edu` | Password: `Staff@123`
2. **View Department Queue (`/staff/queue.php`):**
   - Demonstrate quick tabs: *Unassigned (1)*, *Mine*, *Overdue* (highlighted in red).
   - Click ticket `SVX-2026-00046` to enter **Staff Workspace**.
3. **Perform Operational Actions:**
   - Assign to self (*"Assign to Me"*).
   - Change status to **In Progress** (demonstrate optimistic concurrency).
   - Post an **Internal Note**: *"Technician dispatched with replacement brass washer"* (highlight dark badge showing student cannot see this).
   - Mark as **Resolved**: Provide resolution note *"Replaced leaking washer, tested water pressure"*.
   - Show reporter identity box with *"Open in Google Maps"* GPS link.

---

### Step 3: Student Accepts Resolution (Minutes 3:30 – 4:30)
1. **Return to Student Session (`student@solvex.edu`):**
   - Go to Student Dashboard (`/student/dashboard.php`).
   - Highlight the **"Needs Your Action"** yellow alert card prompting student verification.
   - Click **Review & Verify**.
   - Review timeline showing the technician's resolution note.
   - Click **"Accept Resolution"**.
   - Notice status updates to **Closed (closed_by = student)**.

---

### Step 4: Administrator Oversight & Accountability (Minutes 4:30 – 6:00)
1. **Login as Super Admin:**
   - Email: `admin@solvex.edu` | Password: `Admin@123`
2. **Admin Command Center (`/admin/dashboard.php`):**
   - Show 6 Stripe-style metric cards: Overdue SLA count, Critical Open, and Average Resolution Time.
   - Highlight interactive Chart.js graphs (Category distribution and 30-day intake trend).
   - Show operational monitoring lists: Overdue, Unassigned > 24h, and Rejections.
3. **Accountability Leaderboard (`/admin/accountability.php`):**
   - Show the public leaderboard of longest unresolved public reports (highlighting transparent days-open chips).
   - Show Department Accountability Table comparing open counts and reopen rates.

---

### Anticipated Q&A

- **Q: How is student privacy protected in public tickets?**  
  *A:* On the public feed and leaderboard, all PII (name, register number, email, GPS coordinates, room number) is redacted and replaced with *"Anonymous student"*. Only authorized staff of that department and super admins can view reporter identity.

- **Q: What prevents staff from falsely closing tickets?**  
  *A:* Staff can only mark tickets as *Resolved*. Only the reporting student can mark it *Closed*. If staff marks work done prematurely, the student can click *"Still Broken"* to reopen the ticket with a note, triggering an alert to the administrator. If the student takes no action for 5 days, the system lazy auto-closes the ticket with `closed_by = auto`.

- **Q: How does Solvex prevent duplicate tickets during major outages?**  
  *A:* Pre-submission similarity detection checks open reports in the same location and category from the last 14 days, prompting users to click *"I am also affected"* instead of creating duplicates.

