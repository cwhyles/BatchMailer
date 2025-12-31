# BatchMailer for Dolibarr

**BatchMailer** is a controlled, auditable batch email module for **Dolibarr ERP/CRM**.

It is designed for organisations that need to send **small to medium-sized batches** of personalised emails safely — with full visibility, logging, and the ability to stop or recover at any point.

BatchMailer deliberately avoids “fire-and-forget” mass mailing.  
Instead, it prioritises **human control, transparency, and recoverability**.

---

## Key Features

- CSV-driven recipient lists
- Reusable, tokenised email templates
- Mandatory preview and approval stage
- Dry-run before sending
- Controlled batch sending (manual continuation)
- Abortable campaigns
- Permanent, readable send logs
- Admin safety lock and override tools
- Uses Dolibarr’s existing email configuration

---

## Intended Use

BatchMailer is intended for **administrative and operational email**, such as:

- Membership renewals
- Event reminders
- Official notices
- Small announcements to known contacts

It is **not** a marketing automation tool and does not track opens, clicks, or engagement.

---

## Design Philosophy

BatchMailer is built around a few clear principles:

- **Safety first** — nothing is sent accidentally
- **Transparency** — you always know what happened
- **Human control** — sending is deliberate and visible
- **Forgiveness** — campaigns can be paused, aborted, or recovered
- **Non-technical usability** — no programming knowledge required

This makes BatchMailer particularly suitable for charities, clubs, and small organisations.

---

## Requirements

- Dolibarr **20.0 or later**
- PHP **7.4 or later**
- Working email configuration in Dolibarr

---

## Installation

See **INSTALL.md** for full installation instructions.

---

## Documentation

A full **User Guide** is included with the module and is accessible from within the BatchMailer interface.

The guide covers:
- Preparing recipient data (CSV)
- Creating templates
- Approval and dry-runs
- Batch sending
- Logs and recovery
- Admin tools and safety controls

---

## Versioning

BatchMailer follows semantic versioning.

Current version: **0.1.0**

This is a **production-ready 0.x release**, indicating:
- Stable functionality
- Conservative behaviour
- Scope for refinement before a 1.0.0 stability guarantee

---

## License

BatchMailer is released under the **GNU General Public License v3.0 or later**.

See the LICENSE file for details.

---

## Author

**Colin Whyles**  
GitHub: https://github.com/cwhyles

BatchMailer is an independent project and is not affiliated with the Dolibarr project.
