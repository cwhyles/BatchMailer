# Installing BatchMailer for Dolibarr

This document describes how to install and enable the BatchMailer module in a Dolibarr installation.

---

## 1. Prerequisites

Before installing BatchMailer, ensure that:

- Dolibarr version **20.0 or later** is installed
- PHP **7.4 or later** is in use
- Email sending is already working in Dolibarr
  (SMTP or other configured mail transport)

BatchMailer relies entirely on Dolibarr’s existing email configuration.

---

## 2. Installation Directory

BatchMailer is an **external module** and must be installed in Dolibarr’s `custom` directory.

Typical location:
```
/htdocs/custom/batchmailer/
```

Your final directory structure should look like:

```
custom/
└── batchmailer/
	├── batchmailer.php
	├── core/
	│ └── modules/
	│ └── modBatchMailer.class.php
	├── admin/
	├── class/
	├── lib/
	├── docs/
	├── README.md
	├── INSTALL.md
	├── template_editor.php
```

---

## 3. Enable the Module

1. Log in to Dolibarr as an administrator
2. Go to **Setup → Modules / Applications**
3. Locate **BatchMailer** in the list
4. Click **Enable**

Once enabled, Dolibarr will automatically create the required data directories.

---

## 4. Data Directories

On activation, BatchMailer creates the following directories under Dolibarr’s data directory:

```
/batchmailer/
	├── csv/
	├── logs/
	└── templates/
```

These are used for:
- Uploaded recipient lists (CSV)
- Campaign log files
- Stored email templates

No manual directory creation is required.

---

## 5. Permissions

BatchMailer defines two permissions:

- **Run BatchMailer**
  - Required to prepare data and send emails
- **Administer BatchMailer**
  - Required to manage templates and safety controls

Assign permissions via:

```
Setup → Users & Groups → Permissions
```

---

## 6. Menu Location

Once enabled, BatchMailer appears under:

```
Tools → BatchMailer
```

Template management is available to administrators via a submenu.

---

## 7. Verification

After installation, verify that:

- The BatchMailer menu appears
- You can access the main interface
- The Admin tab shows no errors
- The User Guide opens from the interface

No emails will be sent until a campaign is explicitly approved.

---

## 8. Upgrading

To upgrade BatchMailer:

1. Disable the module in Dolibarr
2. Replace the `batchmailer` directory with the new version
3. Re-enable the module

Existing logs, templates, and CSV files are preserved.

---

## 9. Uninstallation

To uninstall:

1. Disable the module in Dolibarr
2. Remove the `custom/batchmailer` directory if desired

Data directories are **not deleted automatically** to prevent accidental loss of logs.

---

## 10. Notes

- BatchMailer is deliberately conservative
- Sending always requires explicit approval
- Logs are permanent unless manually removed

If something looks wrong during sending, stop and consult the logs.

That is the intended workflow.

---
