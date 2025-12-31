# BatchMailer – Controlled Batch Email for Dolibarr

Controlled batch email module for Dolibarr ERP/CRM.

Copyright © 2025–2026 Colin Whyles  
Licensed under the GNU General Public License v3 or later

BatchMailer provides a **safe, auditable, batch-based email sending system**
for Dolibarr. It is designed for administrative and operational use where
control, transparency, and recoverability matter more than speed or volume.

It was originally developed to support annual subscription renewals for a
small organisation, but has since evolved into a general-purpose batch
email tool suitable for:

- Membership renewals
- Event reminders
- Administrative notices
- Small, targeted announcements

BatchMailer is **not** a marketing platform:
- No open or click tracking
- No background or automated sending
- No bulk “fire-and-forget” campaigns

Instead, it focuses on **human oversight and accountability**.

## Key Features

- CSV-driven recipient lists
- Personalised email templates
- Mandatory preview and approval
- Dry-run validation before sending
- Throttled, batch-based delivery
- Abortable and resumable campaigns
- Permanent, readable send logs
- Uses existing Dolibarr SMTP configuration

## Intended Use

BatchMailer is intended for **administrative and operational emails** sent
to known contacts.

It is deliberately conservative by design and prioritises safety over
automation.

## Documentation

- Full user documentation is provided in `docs/user_guide.md`
- An HTML and PDF version can be generated from the Markdown source

## Installation

See **INSTALL.md** for installation instructions.
