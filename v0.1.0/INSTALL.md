# BatchMailer – Installation Guide

This document explains how to install and enable the BatchMailer module
in Dolibarr ERP/CRM.

## Requirements

- Dolibarr **v20.0 or later**
- PHP **7.4 or later**
- Outbound email (SMTP) correctly configured in Dolibarr

BatchMailer uses Dolibarr’s existing email configuration and does not
introduce its own mail transport.

---

## Installation Steps

### 1. Download or clone the module

Place the `batchmailer` directory into your Dolibarr custom modules path,
typically:

```
/htdocs/custom/
```

Resulting structure:
```
htdocs/
└── custom/
	└── batchmailer/
		├── admin/
		├── class/
		├── css/
		├── docs/
		├── lib/
		├── batchmailer.php
		└── modBatchMailer.class.php
```

---

### 2. Enable the module

1. Log in to Dolibarr as an administrator
2. Go to **Home → Setup → Modules / Applications**
3. Find **BatchMailer** under the *Tools* section
4. Click **Enable**

When enabled, BatchMailer will automatically create the following
directories under Dolibarr’s documents area:
```
documents/
└── batchmailer/
	├── csv/
	├── logs/
	└── templates/
```

No manual directory creation is required.

---

### 3. Assign user permissions

BatchMailer defines two permissions:

- **Run batch mailer**
  - Required to prepare data and send emails

- **Administer batch mailer**
  - Required to manage templates and admin safety controls

Assign these permissions to appropriate users via:
```
Home → Users & Groups → Permissions
```

---

### 4. Verify email configuration

BatchMailer relies on Dolibarr’s global email settings.

Before sending emails, ensure that:

- SMTP settings are configured
- Test emails work from Dolibarr itself

You can verify this under:
```
Home → Setup → Email
```

---

## Upgrading

To upgrade BatchMailer:

1. Replace the existing `batchmailer` directory with the new version
2. Ensure file ownership and permissions are preserved
3. Refresh the Dolibarr module list if required

Data files (CSV, templates, logs) are stored in the documents directory
and are **not** affected by upgrades.

---

## Uninstallation

Disabling the module will:

- Remove menus and permissions
- Leave CSV files, templates, and logs intact

To remove data files completely, delete:
```
documents/batchmailer/
```

manually.

---

## Support and Troubleshooting

BatchMailer includes:

- Clear status messages during sending
- Detailed campaign logs accessible from the Admin tab
- A full user guide in `docs/user_guide.md`

If something feels wrong, stop sending and review the logs before retrying.


