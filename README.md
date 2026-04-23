# WP Microsoft 365 Graph

A WordPress plugin that integrates with the **Microsoft 365 Graph API**, enabling you to display Outlook Calendar events, OneDrive files, and user profile data directly on your WordPress site.

---

## Features

| Feature | Details |
|---|---|
| **App-Only Authentication** | OAuth 2.0 client credentials flow (no interactive sign-in) |
| **Calendar Events** | `[ms365_calendar]` shortcode – renders upcoming events from Outlook Calendar |
| **OneDrive Files** | `[ms365_files]` shortcode – renders a file/folder listing from OneDrive |
| **SharePoint Library Files** | `[ms365_sharepoint_library]` shortcode – renders a file/folder listing from a SharePoint document library |
| **User Profile** | `[ms365_profile]` shortcode – displays the selected user's name, email and job title |
| **Specific User Targeting** | Configure a Microsoft user (UPN or object ID); profile, calendar, and OneDrive are queried for that user |
| **Admin Dashboard** | Live data preview of events and files inside WP Admin |
| **Automatic Token Retrieval** | Fetches app-only Graph access tokens from tenant/client credentials |

---

## Requirements

- WordPress 5.9+
- PHP 7.4+
- An **Azure Active Directory** app registration with the following:
   - **Microsoft Graph application permissions**: `User.Read.All`, `Calendars.Read`, `Files.Read.All`, `Sites.Read.All`
   - A **Client Secret** generated in *Certificates & Secrets*

---

## Installation

1. Clone or download this repository into your WordPress `wp-content/plugins/` directory:
   ```bash
   git clone https://github.com/hannesdev63/wp-plug.git wp-content/plugins/wp-ms365-graph
   ```
2. Activate the plugin from **Plugins → Installed Plugins**.
3. Navigate to **Microsoft 365 → Settings** in the WordPress admin menu.

---

## Configuration

### 1 – Create an Azure App Registration

1. Go to [portal.azure.com](https://portal.azure.com/) → **Azure Active Directory → App registrations → New registration**.
2. Choose a name (e.g. *My WordPress Site*).
3. Redirect URI is not required for this plugin flow (app-only client credentials).
4. After creation, copy the **Directory (tenant) ID** and **Application (client) ID**.
5. Go to **Certificates & secrets → New client secret** – copy the generated value immediately.
6. Go to **API permissions → Add a permission → Microsoft Graph** and add:
   - `User.Read.All` (Application)
   - `Calendars.Read` (Application)
   - `Files.Read.All` (Application)
   - `Sites.Read.All` (Application, required for SharePoint library shortcode)
7. Click **Grant admin consent**.

### 2 – Enter Credentials in WordPress

1. Go to **Microsoft 365 → Settings**.
2. Fill in **Tenant ID**, **Client ID**, and **Client Secret**.
3. Fill in **Specific User (UPN or ID)** to target the Microsoft account for profile, calendar, and OneDrive.
4. Click **Save Changes**.

### 3 – Validate Connection

1. Open **Microsoft 365 → Diagnostics**.
2. Confirm the live checks for user, calendar, and OneDrive are green.

---

## Diagnostics & Troubleshooting

The plugin includes a **Diagnostics** page to help troubleshoot authentication and API issues:

1. Go to **WordPress Admin → Microsoft 365 → Diagnostics**
2. View system information, authentication configuration status, and debug logs
3. Enable **WP_DEBUG** in `wp-config.php` to record detailed debug logs:
   ```php
   define( 'WP_DEBUG', true );
   ```

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| **Connection Failed** | Invalid credentials or misconfigured app | Check Tenant ID, Client ID, Client Secret. See Diagnostics page for details. |
| **Access Denied (403)** | Missing Graph application permission | Add required application permissions (`User.Read.All`, `Calendars.Read`, `Files.Read.All`) and grant admin consent in Azure. |
| **No files/calendar displayed** | Not connected, wrong user targeted, or resource not provisioned | Verify connection in Settings. Check if configured user has mailbox/OneDrive provisioned. |
| **Specific User warning shown** | No target user configured | Set Specific User (UPN/object ID) in plugin settings. |
| **Logs are empty** | WP_DEBUG not enabled | Add `define( 'WP_DEBUG', true );` to `wp-config.php` |

---

## Shortcodes

### `[ms365_calendar]`

Displays upcoming calendar events from the configured specific user.

| Attribute | Default | Description |
|---|---|---|
| `limit` | `5` | Maximum number of events to display |
| `timezone` | `UTC` | IANA timezone string (e.g. `Europe/London`) |
| `title` | `Upcoming Events` | Heading text (empty string = no heading) |

```
[ms365_calendar limit="5" timezone="America/New_York" title="My Schedule"]
```

---

### `[ms365_files]`

Displays a OneDrive file/folder listing from the configured specific user.

| Attribute | Default | Description |
|---|---|---|
| `limit` | `10` | Maximum number of items |
| `folder` | *(root)* | OneDrive path (e.g. `Documents/Projects`) |
| `title` | `My Files` | Heading text |

```
[ms365_files limit="20" folder="Documents" title="Project Docs"]
```

---

### `[ms365_sharepoint_library]`

Displays a SharePoint document library file/folder listing.

| Attribute | Default | Description |
|---|---|---|
| `site_id` | *(required)* | SharePoint site ID |
| `drive_id` | *(required)* | Document library drive ID |
| `limit` | `10` | Maximum number of items |
| `folder` | *(root)* | Library folder path (e.g. `Shared Documents/Team`) |
| `title` | *(empty)* | Heading text |

```
[ms365_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" folder="Shared Documents" title="Team Library"]
```

---

### `[ms365_profile]`

Displays the configured Microsoft 365 user's display name, email, and job title.

```
[ms365_profile]
```

---

## Development

### Running tests

The included standalone test script does not require PHPUnit or a full WordPress install:

```bash
php tests/test-ms365-auth.php
```

### Plugin file structure

```
wp-ms365-graph/
├── wp-ms365-graph.php           Main plugin entry point
├── includes/
│   ├── class-ms365-auth.php     App-only token management (client credentials)
│   ├── class-ms365-graph.php    Graph API HTTP client
│   ├── class-ms365-admin.php    WordPress admin UI
│   └── class-ms365-shortcodes.php  Front-end shortcodes
├── admin/
│   ├── views/
│   │   ├── settings.php         Settings page template
│   │   └── dashboard.php        Data dashboard template
│   └── css/
│       └── admin.css            Admin stylesheet
├── assets/
│   └── css/
│       └── ms365.css            Front-end stylesheet
└── tests/
    └── test-ms365-auth.php      Unit tests
```

---

## License

GPL-2.0-or-later – see [LICENSE](LICENSE).
