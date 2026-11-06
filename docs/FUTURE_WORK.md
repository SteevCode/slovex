# Solvex Report — Future Roadmap

The following enhancements are architected for subsequent versions of Solvex Report:

1. **Interactive Campus GIS Map View:**
   - Visual Leaflet / OpenStreetMap integration displaying open public complaints as heatmaps across hostels, dining halls, and academic blocks.

2. **Automated SMS & WhatsApp Webhooks:**
   - Direct integration with Twilio / Gupshup to send SMS updates to students without requiring active email polling.

3. **Natural Language / Embedding Duplicate Detection:**
   - Utilize local vector embeddings or lightweight TF-IDF to identify semantic duplicates even when student descriptions use different vocabulary (e.g. *"sink dripping"* vs *"tap broken"*).

4. **Escalation Rules Engine & Automated Wardens Alert:**
   - Automated multi-tiered escalation alerting Dean of Infrastructure when critical issues remain unassigned past 48 hours.

5. **Progressive Web App (PWA) & Native Mobile App:**
   - Offline-capable service worker for instant photo capture and caching in hostel areas with low mobile connectivity.

