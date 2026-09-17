# ESC Connect

[![Standalone Tests](https://github.com/hannesdev63/wp-plug/actions/workflows/standalone-tests.yml/badge.svg)](https://github.com/hannesdev63/wp-plug/actions/workflows/standalone-tests.yml)

A WordPress plugin that integrates with the **Microsoft 365 Graph API**, enabling you to display Outlook Calendar events, OneDrive files, and user profile data directly on your WordPress site.

---

## Features

| Feature | Details |
|---|---|
| **App-Only Authentication** | OAuth 2.0 client credentials flow (no interactive sign-in) |
| **Calendar Events** | `[msgraph_calendar]` shortcode – renders upcoming events from Outlook Calendar |
| **OneDrive Files** | `[msgraph_files]` shortcode – renders a file/folder listing from OneDrive |
| **SharePoint Library Files** | `[msgraph_sharepoint_library]` shortcode – renders a file/folder listing from a SharePoint document library |
| **Specific User Targeting** | Configure a Microsoft user (UPN or object ID); profile, calendar, and OneDrive are queried for that user |
| **Admin Dashboard** | Live data preview of events and files inside WP Admin |
| **Automatic Token Retrieval** | Fetches app-only Graph access tokens from tenant/client credentials |
| **WordPress Mail via Microsoft Graph** | Routes `wp_mail()` through Microsoft Graph `sendMail` (optional, requires `Mail.Send` permission) |
| **Diagnostics** | Diagnostics page with live Graph checks, paged debug log viewer (PII-redacted), and test email button |

---

## Requirements

- WordPress 5.9+
- PHP 7.4+
- An **Azure Active Directory** app registration with the following:
   - **Microsoft Graph application permissions**: `User.Read.All`, `Calendars.Read`, `Files.Read.All`, `Sites.Read.All`
   - `Mail.Send` (Application permission, optional — only required if Graph Mail Transport is enabled)
   - A **Client Secret** generated in *Certificates & Secrets*
   - A **Teams Workflow Endpoint URL** (if using Teams message form)

---

## Required Microsoft Graph API Rights

This plugin uses app-only authentication (OAuth client credentials), so configure **Application** permissions in Microsoft Graph and grant admin consent.

| Feature | Required Graph Application Permission |
|---|---|
| Read configured user profile | `User.Read.All` |
| Calendar shortcode `[msgraph_calendar]` | `Calendars.Read` |
| OneDrive shortcode `[msgraph_files]` | `Files.Read.All` |
| SharePoint library shortcode `[msgraph_sharepoint_library]` | `Sites.Read.All` |
| SharePoint team sheet shortcode `[msgraph_sharepoint_team]` | `Sites.Read.All` |
| Teams message form shortcode `[msgraph_teams_message_form]` | No Graph permission required (uses Teams Workflow endpoint URL) |
| WordPress Mail via Microsoft Graph (optional) | `Mail.Send` |

After assigning these rights, click **Grant admin consent** in Azure and then request/save a fresh token in the plugin settings.

---

## Installation

1. Clone or download this repository into your WordPress `wp-content/plugins/` directory:
   ```bash
   git clone https://github.com/hannesdev63/wp-plug.git wp-content/plugins/esc-connect
   ```
2. Activate the plugin from **Plugins → Installed Plugins**.
3. Navigate to **ESC Connect -> Settings** in the WordPress admin menu.

## Packaging

Create a distributable ZIP for WordPress plugin upload:

```bash
./bin/package-plugin.sh
```

Optional: pass an explicit version (otherwise it uses the version from `esc-connect.php`):

```bash
./bin/package-plugin.sh 1.2.3
```

Output is written to `dist/esc-connect-<VERSION>.zip`.

---

## Configuration

### 1 – Create an Azure App Registration

1. Go to [portal.azure.com](https://portal.azure.com/) → **Azure Active Directory → App registrations → New registration**.
2. Choose a name (e.g. *My WordPress Site*).
3. Redirect URI is **not** required for the app-only (client credentials) plugin flow.  
   > If you want to enable **Tenant Sign-In** (see below), set the Redirect URI type to **Web** and register the WordPress callback URL shown in **ESC Connect -> Settings** (e.g. `https://example.com/ms365-sso-callback/`).
4. After creation, copy the **Directory (tenant) ID** and **Application (client) ID**.
5. Go to **Certificates & secrets → New client secret** – copy the generated value immediately.
6. Go to **API permissions → Add a permission → Microsoft Graph** and add:
   - `User.Read.All` (Application)
   - `Calendars.Read` (Application)
   - `Files.Read.All` (Application)
   - `Sites.Read.All` (Application, required for SharePoint library shortcode)
   - `Mail.Send` (Application — **optional**, only needed for Graph Mail Transport)
   - `openid`, `email`, `profile` (Delegated – required for Tenant Sign-In)
7. Click **Grant admin consent**.

---

### Tenant Sign-In (WordPress Login via Microsoft)

Allows WordPress users to authenticate with their Microsoft tenant account using OAuth 2.0 Authorization Code + PKCE. The same Tenant ID, Client ID, and Client Secret configured above are reused — no second app registration is needed.

#### Azure App Registration requirements

| Step | Detail |
|------|--------|
| Application type | Set at least one **Web** redirect URI |
| Redirect URI | The value shown in **ESC Connect -> Settings -> WordPress Sign-In** (e.g. `https://yoursite.com/ms365-sso-callback/`) |
| Delegated permissions | `openid`, `email`, `profile` — no admin consent required |

#### WordPress settings

Go to **ESC Connect -> Settings -> WordPress Sign-In (Microsoft Tenant)**:

| Setting | Description |
|---------|-------------|
| **Enable Tenant Sign-In** | Adds a "Sign in with Microsoft" button to wp-login.php and activates the shortcode. |
| **Auto-Create Users** | Automatically creates a new WordPress account for first-time Microsoft users. |
| **Default Role for New Users** | Role assigned to auto-created accounts (default: Subscriber). |
| **Allowed Email Domains** | Comma-separated list (e.g. `contoso.com, fabrikam.org`). Leave blank to allow any domain from your tenant. |
| **Post-Login Redirect URL** | Optional. Overrides the normal WordPress redirect after a successful sign-in. |

---

### WordPress Mail via Microsoft Graph

Routes WordPress emails sent via `wp_mail()` through Microsoft Graph `sendMail` instead of the default SMTP transport.

#### Requirements

- The Azure app registration must have the **`Mail.Send`** application permission with admin consent granted.
- The configured sender mailbox must exist in Exchange Online and allow app-only `sendMail` calls.

#### Configuration

1. Go to **ESC Connect -> Settings -> WordPress Mail via Microsoft Graph**.
2. Enable **Graph Mail Transport**.
3. Set **Mail Sender Mailbox (UPN or ID)** to the mailbox used as sender (e.g. `no-reply@contoso.com`).
4. Optionally enable **Save Messages in Sent Items** to store sent emails in the sender's *Sent Items* folder.
5. Click **Save Changes**.
6. **Deactivate and reactivate the plugin once** to register the transport hook.

#### Testing

1. Go to **ESC Connect -> Diagnostics -> Email Transport Test**.
2. Click **Send Test Email**. The test message is sent to the configured WordPress admin email.
3. Check the result notice and the debug log for details.

#### Notes

- When Graph Mail Transport is disabled, `wp_mail()` falls back to the default WordPress SMTP transport.
- The sender address in the email headers is always the mailbox configured above, regardless of the `from` header passed to `wp_mail()`.

---

### Wording customization

- **Calendar & Files Wording**
- **Teams Form Wording**
- **Sign-In Wording**

In **Sign-In Wording**, set **Entra Sign-In Button Text** to override the default label used on `wp-login.php` and by `[msgraph_login_button]` when no `label` attribute is provided.

#### Settings persistence check

Use this quick regression test after updates:

1. In **ESC Connect -> Settings -> WordPress Sign-In (Microsoft Tenant)**, enable **Enable Tenant Sign-In** and **Auto-Create Users**, then click **Save Changes**.
2. Open **ESC Connect -> Wording** and change any wording field (for example **Entra Sign-In Button Text**), then click **Save Changes**.
3. Return to **ESC Connect -> Settings** and confirm **Enable Tenant Sign-In** and **Auto-Create Users** are still enabled.

#### Shortcode

Place the sign-in button anywhere on a page or widget:

```
[msgraph_login_button]
```

Optional attributes:

| Attribute | Default | Description |
|-----------|---------|-------------|
| `label` | value from **Wording -> Sign-In Wording -> Entra Sign-In Button Text** (fallback: *Sign in with Microsoft*) | Button label text |
| `redirect_to` | current page | URL to redirect to after sign-in |
| `class` | *(empty)* | Extra CSS class(es) added to the button wrapper |

Example:

```
[msgraph_login_button label="Log in with Company Account" redirect_to="/dashboard"]
```

#### Security notes

- PKCE (SHA-256 code challenge) is always used — no implicit flow.
- State and nonce are one-time transients (10-minute TTL) to prevent CSRF and replay attacks.
- ID token is validated: `aud` = Client ID, `iss` = `https://login.microsoftonline.com/{tenant_id}/v2.0`, `exp` ± 60 s, nonce match.
- Redirects after login are sanitized via `wp_validate_redirect()`.

---

### Teams form workflow setup

1. Open **Microsoft Teams** and go to the team/channel where form messages should arrive.
2. Open **Workflows** for that team/channel.
3. Create a flow using trigger **When a Teams webhook request is received**.
4. Default delivery is plain text (webhook style).
5. If you want adaptive cards, add action **Post card in a chat or channel** and map trigger body field `adaptive_card` as card payload.
6. Save the flow and copy the generated HTTP POST URL.
7. In WordPress, open **ESC Connect -> Settings** and paste the URL into **Teams Workflow Endpoint URL**.
8. Save settings and submit a test message with `[msgraph_teams_message_form]`.

Notes:
- Keep the workflow URL private because anyone with the URL can trigger the flow.
- The shortcode also accepts `webhook_url` as a legacy alias, but `endpoint_url` is now the preferred parameter.
- Auto mode detects workflow-style URLs (for example `logic.azure.com`) and uses adaptive-card payload automatically.
- You can force mode in shortcode with `use_adaptive_card="true"` or `use_adaptive_card="false"`.
- In adaptive-card mode, `team_id` and `channel_id` are hidden in the admin UI and omitted from the payload.

### 2 – Enter Credentials in WordPress

1. Go to **ESC Connect -> Settings**.
2. Fill in **Tenant ID**, **Client ID**, and **Client Secret**.
3. Fill in **Specific User (UPN or ID)** to target the Microsoft account for profile, calendar, and OneDrive.
4. Click **Save Changes**.

### 3 – Validate Connection

1. Open **ESC Connect -> Diagnostics**.
2. Confirm the live checks for user, calendar, and OneDrive are green.

---

## Diagnostics & Troubleshooting

The plugin includes a **Diagnostics** page (**ESC Connect -> Diagnostics**) to help troubleshoot authentication, API, and mail issues.

### What the Diagnostics page shows

| Section | Description |
|---------|-------------|
| **System Information** | WordPress version, PHP version, plugin version, WP_DEBUG status |
| **Authentication Configuration** | Whether Tenant ID, Client ID, Client Secret, and Specific User are configured |
| **Live Graph Checks** | Real-time checks: user profile, calendar, OneDrive, token scope |
| **Email Transport Test** | Send a test email to the configured admin email address via `wp_mail()` (routes through Graph if Graph Mail Transport is enabled) |
| **Debug Logs** | Paged table of logged events (25 / 50 / 100 rows per page). All log entries are automatically redacted: email addresses, IP addresses, GUIDs, OAuth tokens, and bearer tokens are replaced with `[REDACTED]`. |

### Enabling debug logs

```php
// wp-config.php
define( 'WP_DEBUG', true );
```

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| **Connection Failed** | Invalid credentials or misconfigured app | Check Tenant ID, Client ID, Client Secret. See Diagnostics page for details. |
| **Access Denied (403)** | Missing Graph application permission | Add required application permissions (`User.Read.All`, `Calendars.Read`, `Files.Read.All`, `Sites.Read.All`) and grant admin consent in Azure. |
| **No files/calendar displayed** | Not connected, wrong user targeted, or resource not provisioned | Verify connection in Settings. Check if configured user has mailbox/OneDrive provisioned. |
| **Specific User warning shown** | No target user configured | Set Specific User (UPN/object ID) in plugin settings. |
| **Logs are empty** | WP_DEBUG not enabled | Add `define( 'WP_DEBUG', true );` to `wp-config.php` |
| **Test email failed** | Graph Mail Transport misconfigured or missing `Mail.Send` permission | Verify sender mailbox UPN, check that `Mail.Send` application permission has admin consent, and confirm Graph Mail Transport is enabled. Check debug log for the error detail. |

---

## Shortcodes

### `[msgraph_calendar]`

| Attribute | Default |
|---|---|
| `limit` | `5` |
| `timezone` | site timezone |
| `title` | *(empty)* |
| `calendar` | default calendar |
| `past_days` | `0` |
| `columns` | all (`date,event,duration,location`) |
| `duration_display` | `hours_minutes` |
| `group_by_date` | `false` |
| `categories` | *(empty = all)* |
| `show_headers` | `true` |
| `class` / `table_class` / `item_class` | merged with defaults |

```
[msgraph_calendar calendar="AAMkAGI2..."]
[msgraph_calendar limit="8" timezone="Europe/Berlin" past_days="2" group_by_date="true"]
```

### `[msgraph_files]`

| Attribute | Default |
|---|---|
| `limit` | `50` |
| `folder` | *(root)* |
| `title` | *(empty)* |
| `columns` | all (`file,size,modified`) |
| `column_order` | `file,size,modified` |
| `hide_columns` | none |
| `download_columns` | `file` |
| `show_headers` | `true` |
| `class` / `table_class` / `item_class` | merged with defaults |

```
[msgraph_files limit="20" folder="Documents/Policies"]
[msgraph_files columns="file,size" hide_columns="size" download_columns="file"]
```

### `[msgraph_sharepoint_library]`

| Attribute | Default |
|---|---|
| `site_id` / `drive_id` | required |
| `limit` | `50` |
| `folder` | *(root)* |
| `title` | *(empty)* |
| `columns` | all (`file,size,modified`) |
| `column_order` | `file,size,modified` |
| `hide_columns` | none |
| `download_columns` | `file` |
| `image_columns` | none |
| `image_basepath` | settings value, fallback uploads URL |
| `show_headers` | `true` |
| `class` / `table_class` / `item_class` | merged with defaults |

```
[msgraph_sharepoint_library site_id="..." drive_id="..." folder="Shared Documents/HR"]
[msgraph_sharepoint_library site_id="..." drive_id="..." columns="file" image_columns="file"]
```

### `[msgraph_sharepoint_team]`

| Attribute | Default |
|---|---|
| `site_id` / `drive_id` | required |
| `folder` | *(root)* |
| `name` | `team.xlsx` |
| `sheet` | first sheet (fallback if named sheet not found) |
| `title` | *(empty)* |
| `show_headers` | `true` |
| `sort_columns` | `Position, Order, Name` |
| `display_columns` | all columns |
| `hide_columns` | none |
| `formatter` | none |
| `image_columns` | none |
| `urls` | none |
| `image_basepath` | settings value, fallback uploads URL |
| `displaymode` | `list` |
| `card_title_column` | `name` (or two columns: `Name,Position`) |
| `class` / `table_class` / `item_class` | merged with defaults |

```
[msgraph_sharepoint_team site_id="..." drive_id="..." name="TeamTemplate2.xlsx" sheet="U13"]
[msgraph_sharepoint_team site_id="..." drive_id="..." urls="Profil" displaymode="card" card_title_column="Name"]
[msgraph_sharepoint_team site_id="..." drive_id="..." displaymode="card" card_title_column="Name,Position"]
```

### `[msgraph_teams_message_form]`

| Attribute | Default |
|---|---|
| `endpoint_url` | settings value |
| `webhook_url` | *(legacy alias)* |
| `use_adaptive_card` | `auto` |
| `team_id` / `channel_id` | settings value |
| `title` | *(empty)* |
| `placeholder` | wording/settings default |
| `button_text` | wording/settings default |
| `max_length` | `1000` |
| `class` / `form_class` / `input_class` / `textarea_class` / `submit_class` | merged with defaults |

```
[msgraph_teams_message_form]
[msgraph_teams_message_form endpoint_url="https://..." use_adaptive_card="true"]
```

### `[msgraph_login_button]`

| Attribute | Default |
|---|---|
| `label` | wording value, fallback "Sign in with Microsoft" |
| `redirect_to` | current page |
| `class` | *(empty)* |

```
[msgraph_login_button]
[msgraph_login_button label="Log in with Company Account" redirect_to="/dashboard"]
```

For full, in-admin parameter details and examples, see the Documentation tab in ESC Connect.

---

## Development

### Running tests

The standalone test suite does not require PHPUnit or a full WordPress install.

Run all tests:

```bash
./bin/test.sh
```

Run a single test script:

```bash
php tests/test-ms365-auth.php
php tests/test-ms365-admin.php
php tests/test-ms365-login.php
```

### Plugin file structure

```
esc-connect/
├── esc-connect.php              Main plugin entry point
├── bin/
│   ├── package-plugin.sh           Packaging utility
│   └── test.sh                     Runs standalone test scripts
├── includes/
│   ├── class-ms365-auth.php        App-only token management (client credentials)
│   ├── class-ms365-graph.php       Graph API HTTP client
│   ├── class-ms365-admin.php       WordPress admin UI and settings
│   ├── class-ms365-shortcodes.php  Front-end shortcodes
│   ├── class-ms365-login.php       Tenant Sign-In (OAuth 2.0 + PKCE)
│   ├── class-ms365-mail.php        WordPress Mail via Microsoft Graph (wp_mail hook)
│   ├── class-ms365-login-logs.php  Login attempt logging and retention cleanup
│   ├── class-ms365-wp-access-stats.php WordPress and shortcode access statistics
│   └── class-ms365-logger.php      Debug logger with PII redaction
├── admin/
│   ├── views/
│   │   ├── settings.php            Settings page template
│   │   ├── dashboard.php           Data dashboard template
│   │   ├── diagnostics.php         Diagnostics page template
│   │   ├── documentation.php       In-admin documentation
│   │   ├── access-stats.php        Access statistics (shortcode render counters)
│   │   ├── wording.php             Wording customisation template
│   │   ├── sharepoint-explorer.php SharePoint explorer template
│   │   └── calendar-explorer.php   Calendar explorer template
│   └── css/
│       └── admin.css               Admin stylesheet
├── assets/
│   ├── css/
│   │   ├── ms365.css               Front-end stylesheet
│   │   └── login.css               Login page stylesheet
│   ├── js/
│   │   └── editor-shortcodes.js    Gutenberg block/shortcode helper
│   └── images/
│       └── icon.svg                Plugin icon
├── languages/
│   ├── esc-connect-de_DE.po     German translations (source)
│   └── esc-connect-de_DE.mo     German translations (compiled)
└── tests/
   ├── test-ms365-auth.php         Unit tests for auth helpers
   ├── test-ms365-admin.php        Unit tests for admin settings sanitation
   └── test-ms365-login.php        Unit tests for login bypass and redirect logic
```

---

## License

GPL-2.0-or-later – see [LICENSE](LICENSE).
