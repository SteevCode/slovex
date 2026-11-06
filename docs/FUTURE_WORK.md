# Solvex Report — Product Roadmap & Future Architecture

This document outlines enhancements planned for upcoming releases of Solvex Report, categorized by functional domain.

---

## 1. Campus Geospatial Mapping & GIS Heatmaps
- **Interactive OpenStreetMap / Leaflet Integration:**
  - Visual layer plotting open public complaints as heatmaps across hostels, dining halls, and academic blocks.
  - Color-coded pins matching ticket category and priority.
  - GeoJSON campus boundary polygons for automatic building identification based on student GPS coordinates.

---

## 2. Multi-Channel Messaging & Webhooks
- **SMS & WhatsApp Gateway Integration:**
  - Webhook hooks into Twilio, MSG91, or Gupshup.
  - Automatically dispatch SMS alerts when complaints are marked *Resolved* so students in low-bandwidth hostel environments can review and accept tickets without opening email.
- **Institutional Webhooks:**
  - Real-time event webhooks for campus Slack / Discord channels or Telegram bot alerts for emergency facilities outages.

---

## 3. Machine Learning & Semantic Duplicate Detection
- **Local Embedding Vector Matching:**
  - Enhance keyword-based similar ticket detection using lightweight local embeddings (or PHP-based TF-IDF cosine similarity).
  - Matches complaints filed with differing vernacular (e.g. *"sink leaking tap"* vs *"washroom faucet broken"*).
- **Automated Department Classification:**
  - Naive Bayes or fine-tuned text classifier to recommend or auto-route categories when students report ambiguous issues.

---

## 4. Multi-Tiered Institutional Escalation Engine
- **Automated Dean / Registrar Alerts:**
  - Automated escalation engine alerting Wardens or the Dean of Campus Infrastructure when critical tickets remain unassigned past 24 hours or open past 150% of target SLA.
- **SLA Breach Warnings:**
  - Pre-breach notifications sent to department heads 4 hours before target SLA expiry.

---

## 5. Offline Progressive Web App (PWA)
- **Service Worker Image Caching:**
  - Cache report forms and draft tickets locally on mobile devices.
  - Enable offline photo capture and background sync once Wi-Fi or mobile connectivity is re-established in hostel basements or remote sports grounds.
- **Web Push Notifications:**
  - Direct browser push notifications when staff comment or mark a ticket resolved.

---

## 6. Enterprise Audit & Compliance Export
- **PDF Incident Docket Generator:**
  - Export full printable PDF dossiers of complaints including complete append-only audit event timelines, before/after photos, and student verification signatures.
- **Institutional NAAC / NIRF Facility Accreditation Reports:**
  - Aggregated annual reports on campus infrastructure maintenance velocity, response rates, and student satisfaction ratings.
