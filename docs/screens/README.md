# Solvex Report — Visual Proof & Screenshot Archive

This directory contains automated, headless-captured screenshots verifying the user interfaces, responsive layouts, role perspectives, and triage queues across desktop, tablet, and mobile viewports.

---

## 📸 Captured Screenshots

| File | Viewport | Role | Description |
|---|---|---|---|
| [`landing-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/landing-1280.png) | 1280×800 (Desktop) | Public / Guest | Hero section with vector illustration, live stats counter, 3-step workflow, and floating preview ticket. |
| [`landing-360.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/landing-360.png) | 360×780 (Mobile) | Public / Guest | Responsive mobile view with stacked CTA buttons and mobile navigation. |
| [`login-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/login-1280.png) | 1280×800 (Desktop) | Public / Auth | 2-column split desktop authentication layout with `auth-side.webp` vector graphic and SLA guarantee notice. |
| [`register-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/register-1280.png) | 1280×800 (Desktop) | Student Auth | 3-step registration onboarding with strength meter and register number verification. |
| [`feed-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/feed-1280.png) | 1280×800 (Desktop) | Student / Peer | Public complaint feed with anonymous card sanitization, ageing counters ("X days open"), and "I am also affected" action. |
| [`feed-360.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/feed-360.png) | 360×780 (Mobile) | Student / Peer | Mobile stacked feed cards with full-width action buttons. |
| [`report-step1-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/report-step1-1280.png) | 1280×800 (Desktop) | Student | 3-step intake wizard: Step 1 WHAT with visual category tag cards and emergency safety warning. |
| [`report-step1-360.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/report-step1-360.png) | 360×780 (Mobile) | Student | Touch-friendly category cards for smartphone browsers. |
| [`student-dashboard-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/student-dashboard-1280.png) | 1280×800 (Desktop) | Student | Student personal dashboard with "Needs Your Action" banner, quick stats, and recent complaints table. |
| [`complaint-detail-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/complaint-detail-1280.png) | 1280×800 (Desktop) | Student / Staff | Complaint detail workspace with append-only chronological event audit timeline and public discussion. |
| [`staff-queue-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/staff-queue-1280.png) | 1280×800 (Desktop) | Department Staff | Jira/Zendesk style department queue with sticky filter bar, quick tabs (All, Unassigned, Mine, Overdue, On Hold). |
| [`staff-queue-768.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/staff-queue-768.png) | 768×1024 (Tablet) | Department Staff | Responsive tablet table view with overdue ticket row highlighting. |
| [`staff-workspace-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/staff-workspace-1280.png) | 1280×800 (Desktop) | Department Staff | Staff resolution workspace with state machine transition buttons, assignment, internal notes, and duplicate linking. |
| [`admin-dashboard-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/admin-dashboard-1280.png) | 1280×800 (Desktop) | Administrator | Stripe-style KPI cards, 30-day intake trend chart, category breakdown, and operational triage lists. |
| [`admin-dashboard-768.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/admin-dashboard-768.png) | 768×1024 (Tablet) | Administrator | Tablet chart rendering and stacked triage lists. |
| [`admin-leaderboard-1280.png`](file:///f:/main/chumma/steve.code/projects/Solvex%20Report/docs/screens/admin-leaderboard-1280.png) | 1280×800 (Desktop) | Public / Admin | Civic accountability leaderboard ranking longest unresolved issues and department SLA performance metrics. |

---

### Generating New Screenshots
To re-capture all screens automatically at any time:
```powershell
python scripts/capture_screens.py
```
This script boots a headless instance of Chrome/Edge, loads authenticated demo states, and exports fresh PNGs into this directory.

