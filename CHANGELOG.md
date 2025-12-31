# Changelog

All notable changes to BatchMailer will be documented in this file.

The format is based on *Keep a Changelog* principles and follows
semantic versioning where possible.

---

## [0.1.0] — 2026-01-xx

### Added
- Initial public release of BatchMailer for Dolibarr
- CSV-driven recipient lists with header detection
- Reusable, tokenised email templates
- Mandatory campaign approval before sending
- Dry-run mode to validate campaigns without sending
- Controlled batch sending with manual continuation
- Abortable campaigns with preserved state
- Permanent per-campaign log files
- Admin tools for campaign state, safety lock, and recovery
- Context-sensitive Quick Start help per tab
- Integrated full User Guide (HTML)
- Downloadable PDF version of the User Guide
- Automatic creation of required data directories on module activation
- Dolibarr permission model integration

---

### Known Issues

- **Safari (macOS & iPadOS)**  
  When opening the User Guide with a section anchor (e.g. `#3-preparing-your-recipient-data`), Safari may require a second click to fully render the content in the embedded help panel.  
  As a workaround, Safari users are routed to the top of the guide (Contents), which renders reliably.

- **Firefox on iPadOS**  
  The embedded User Guide panel does not currently render in Firefox on iPadOS.  
  The guide opens correctly in Safari on the same device.

These issues do not affect email sending or campaign integrity.

---

### Notes

- Version **0.1.0** is a production-ready 0.x release
- Functionality is stable and intentionally conservative
- Future releases may refine UI behaviour and browser compatibility
- No breaking changes are expected within the 0.1.x series

---
