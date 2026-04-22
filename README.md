# WP Microsoft 365 Graph

A WordPress plugin that integrates with the **Microsoft 365 Graph API**, enabling you to display Outlook Calendar events, OneDrive files, and user profile data directly on your WordPress site.

---

## Features

| Feature | Details |
|---|---|
| **OAuth 2.0 Authentication** | Authorization Code flow – securely stores tokens as WordPress transients/options |
| **Calendar Events** | `[ms365_calendar]` shortcode – renders upcoming events from Outlook Calendar |
| **OneDrive Files** | `[ms365_files]` shortcode – renders a file/folder listing from OneDrive |
| **User Profile** | `[ms365_profile]` shortcode – displays the signed-in user's name, email and job title |
| **Specific User Targeting** | Optionally configure a Microsoft user (UPN or object ID); calendar and OneDrive use that user, otherwise they default to the signed-in user |
| **Admin Dashboard** | Live data preview of events and files inside WP Admin |
| **Token Refresh** | Automatically refreshes expired access tokens using the stored refresh token |

---

## Requirements

- WordPress 5.9+
- PHP 7.4+
- An **Azure Active Directory** app registration with the following:
  - **Redirect URI** (Web): `https://your-site.com/wp-admin/admin.php?page=wp-ms365-graph`
  - **API permissions**: `User.Read`, `Calendars.Read`, `Files.Read`, `offline_access`
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
3. Under **Redirect URI**, select **Web** and paste your site's redirect URI (shown in the plugin settings page).
4. After creation, copy the **Directory (tenant) ID** and **Application (client) ID**.
5. Go to **Certificates & secrets → New client secret** – copy the generated value immediately.
6. Go to **API permissions → Add a permission → Microsoft Graph** and add:
   - `User.Read`
   - `Calendars.Read`
   - `Calendars.Read.Shared` (required for accessing other users' calendars)
   - `Files.Read`
   - `Files.Read.Shared` (required for accessing other users' OneDrive)
   - `offline_access`
7. Click **Grant admin consent**.

### 2 – Enter Credentials in WordPress

1. Go to **Microsoft 365 → Settings**.
2. Fill in **Tenant ID**, **Client ID**, and **Client Secret**.
3. (Optional) Fill in **Specific User (UPN or ID)** to target one Microsoft account for calendar and OneDrive.
4. Click **Save Changes**.

### 3 – Authorize the Connection

1. After saving, click **Connect to Microsoft 365**.
2. Sign in with your Microsoft account and grant the requested permissions.
3. You are redirected back to WordPress with a *Successfully connected!* notice.

---

## Shortcodes

### `[ms365_calendar]`

Displays upcoming calendar events from the selected user. If no specific user is configured, the signed-in user is used.

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

Displays a OneDrive file/folder listing from the selected user. If no specific user is configured, the signed-in user is used.

| Attribute | Default | Description |
|---|---|---|
| `limit` | `10` | Maximum number of items |
| `folder` | *(root)* | OneDrive path (e.g. `Documents/Projects`) |
| `title` | `My Files` | Heading text |

```
[ms365_files limit="20" folder="Documents" title="Project Docs"]
```

---

### `[ms365_profile]`

Displays the signed-in Microsoft 365 user's display name, email, and job title.

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
│   ├── class-ms365-auth.php     OAuth 2.0 token management
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
