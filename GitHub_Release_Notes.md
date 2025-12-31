## BatchMailer v0.1.0

Initial public release of **BatchMailer**, a controlled batch email module for Dolibarr ERP/CRM.

BatchMailer is designed for organisations that value **safety, transparency, and human oversight** over automation. It is intentionally conservative and audit-friendly.

---

### ✨ Key Features

- CSV-driven recipient lists with header detection
- Reusable, tokenised email templates
- Mandatory campaign approval before sending
- Dry-run mode (no emails sent)
- Controlled batch sending with manual continuation
- Abortable campaigns with preserved state
- Permanent per-campaign log files
- Admin tools for campaign state, safety lock, and recovery
- Context-sensitive Quick Start help
- Full User Guide (HTML) integrated into the UI
- Downloadable PDF User Guide
- Automatic creation of required data directories on module activation
- Uses Dolibarr’s existing SMTP configuration

---

### ⚠️ Known Issues

- **Safari (macOS & iPadOS)**  
  Opening the User Guide with a section anchor may require a second click for the embedded panel to fully render. Safari users are therefore routed to the guide’s Contents, which renders reliably.

- **Firefox on iPadOS**  
  The embedded User Guide does not currently render in Firefox on iPadOS. The guide opens correctly in Safari on the same device.

These issues do **not** affect email sending, logging, or campaign integrity.

---

### 📝 Notes

- This is a production-ready **0.1.0** release
- No database schema changes
- No background or automated sending by design
- No tracking, analytics, or marketing features included

BatchMailer is intended for **administrative and operational email**, not high-volume marketing campaigns.
