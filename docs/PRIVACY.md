# Solvex Report — Campus Privacy, Anonymity & Data Protection Specification

This document details the architectural boundaries, redaction mechanisms, and privacy guarantees implemented in Solvex Report.

---

## 1. Core Principles

1. **Student Whistleblower & Reporter Protection:** Peer students must never be able to discover the personal identity (name, register number, email, or GPS location) of a student who reported a campus complaint.
2. **Sensitive Incident Isolation:** Safety, harassment, and confidential hostel grievances must be completely invisible to general department staff and only visible to the owner and designated university administrators.
3. **Audit Immutability Without Surveillance:** An append-only audit trail (`complaint_events`) records all state transitions and timestamps, but redacts reporter identity for unauthorized consumers.
4. **Media Sanitization:** All user-uploaded media is re-encoded at ingestion to strip EXIF, geolocation, and device fingerprints.

---

## 2. Privacy Surfaces & Masking Matrix

| Surface | Owning Student | Department Staff | Peer Students | System Admin |
| :--- | :--- | :--- | :--- | :--- |
| **Public Feed** (`/feed.php`) | Full info | Masked ("Anonymous student") | Masked ("Anonymous student") | Full info |
| **Public Complaint View** (`/complaint.php`) | Full info | Full info (Assigned Dept only) | Masked ("Anonymous student", no GPS/reg) | Full info |
| **Public Comments API** (`/api/comments.php`) | Real Name | Real Name | "Anonymous student" (for reporter) | Real Name |
| **Department Queue** (`/staff/queue.php`) | N/A | Full info (Assigned Dept only) | Denied (HTTP 403) | Full info |
| **Sensitive Category Tickets** | Accessible | **HTTP 404 (Invisible)** | **HTTP 404 (Invisible)** | Accessible |
| **Uploaded Photos** (`/image.php`) | Viewable | Viewable (Assigned Dept only) | Viewable if Public | Viewable |

---

## 3. Data Sanitization Implementation

### 3.1 PHP Sanitizer: `sanitize_complaint_for_view()`
Located in `includes/permissions.php`. When `can_see_reporter($user, $complaint)` evaluates to `false`:
- `reporter_name`, `student_name`, `full_name` are set to `'Anonymous student'`.
- `register_no`, `student_register_no`, `email`, `student_email` are set to `null`.
- `lat`, `lng`, and `gps_accuracy_m` coordinates are set to `null`.
- Avatar initials are generated from the masked name (`"AS"`), never from the underlying user account.

### 3.2 Media Metadata Stripping
Located in `includes/upload.php`.
- Uploaded JPEG, PNG, and WebP files are ingested into memory using GD (`imagecreatefromjpeg`, `imagecreatefrompng`, `imagecreatefromwebp`).
- Re-encoded without camera metadata, timestamp, device model, or GPS EXIF tags.
- Stored outside document root (`storage/uploads/complaints/`) under randomized 32-character hexadecimal filenames.

---

## 4. Anonymization & GDPR / DPDPA Tooling

To purge or anonymize inactive student data or demo environments, an administrative anonymizer utility is provided at `scripts/anonymize_data.php`.

```bash
# Anonymize inactive students older than 365 days
php scripts/anonymize_data.php --older-than-days=365

# Dry-run inspection
php scripts/anonymize_data.php --dry-run
```

