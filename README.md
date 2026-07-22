# WP Microsoft 365 Graph

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
| Teams message form shortcode `[msgraph_teams_message_form]` | No Graph permission required (uses Teams Workflow endpoint URL) |
| WordPress Mail via Microsoft Graph (optional) | `Mail.Send` |

After assigning these rights, click **Grant admin consent** in Azure and then request/save a fresh token in the plugin settings.

---

## Installation

1. Clone or download this repository into your WordPress `wp-content/plugins/` directory:
   ```bash
   git clone https://github.com/hannesdev63/wp-plug.git wp-content/plugins/wp-ms365-graph
   ```
2. Activate the plugin from **Plugins → Installed Plugins**.
3. Navigate to **Microsoft 365 → Settings** in the WordPress admin menu.

## Packaging

Create a distributable ZIP for WordPress plugin upload:

```bash
./scripts/package-plugin.sh
```

Optional: pass an explicit version (otherwise it uses the version from `wp-ms365-graph.php`):

```bash
./scripts/package-plugin.sh 1.2.3
```

Output is written to `dist/wp-ms365-graph-<VERSION>.zip`.

---

## Configuration

### 1 – Create an Azure App Registration

1. Go to [portal.azure.com](https://portal.azure.com/) → **Azure Active Directory → App registrations → New registration**.
2. Choose a name (e.g. *My WordPress Site*).
3. Redirect URI is **not** required for the app-only (client credentials) plugin flow.  
   > If you want to enable **Tenant Sign-In** (see below), set the Redirect URI type to **Web** and register the WordPress callback URL shown in **Microsoft 365 → Settings** (e.g. `https://example.com/ms365-sso-callback/`).
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
| Redirect URI | The value shown in **Microsoft 365 → Settings → WordPress Sign-In** (e.g. `https://yoursite.com/ms365-sso-callback/`) |
| Delegated permissions | `openid`, `email`, `profile` — no admin consent required |

#### WordPress settings

Go to **Microsoft 365 → Settings → WordPress Sign-In (Microsoft Tenant)**:

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

1. Go to **Microsoft 365 → Settings → WordPress Mail via Microsoft Graph**.
2. Enable **Graph Mail Transport**.
3. Set **Mail Sender Mailbox (UPN or ID)** to the mailbox used as sender (e.g. `no-reply@contoso.com`).
4. Optionally enable **Save Messages in Sent Items** to store sent emails in the sender's *Sent Items* folder.
5. Click **Save Changes**.
6. **Deactivate and reactivate the plugin once** to register the transport hook.

#### Testing

1. Go to **Microsoft 365 → Diagnostics → Email Transport Test**.
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

1. In **Microsoft 365 -> Settings -> WordPress Sign-In (Microsoft Tenant)**, enable **Enable Tenant Sign-In** and **Auto-Create Users**, then click **Save Changes**.
2. Open **Microsoft 365 -> Wording** and change any wording field (for example **Entra Sign-In Button Text**), then click **Save Changes**.
3. Return to **Microsoft 365 -> Settings** and confirm **Enable Tenant Sign-In** and **Auto-Create Users** are still enabled.

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
7. In WordPress, open **Microsoft 365 -> Settings** and paste the URL into **Teams Workflow Endpoint URL**.
8. Save settings and submit a test message with `[msgraph_teams_message_form]`.

Notes:
- Keep the workflow URL private because anyone with the URL can trigger the flow.
- The shortcode also accepts `webhook_url` as a legacy alias, but `endpoint_url` is now the preferred parameter.
- Auto mode detects workflow-style URLs (for example `logic.azure.com`) and uses adaptive-card payload automatically.
- You can force mode in shortcode with `use_adaptive_card="true"` or `use_adaptive_card="false"`.
- In adaptive-card mode, `team_id` and `channel_id` are hidden in the admin UI and omitted from the payload.

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

The plugin includes a **Diagnostics** page (**Microsoft 365 → Diagnostics**) to help troubleshoot authentication, API, and mail issues.

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

Displays upcoming calendar events from the configured specific user.

| Attribute | Default | Description |
|---|---|---|
| `limit` | `5` | Maximum number of events to display |
| `timezone` | `UTC` | IANA timezone string (e.g. `Europe/London`) |
| `title` | `Upcoming Events` | Heading text (empty string = no heading) |
| `class` | `msgraph_calendar` | Additional wrapper classes (merged with defaults) |
| `table_class` | `msgraph_table msgraph_calendar__table` | Additional table classes (merged with defaults) |
| `item_class` | `msgraph_calendar__item` | Additional row/item classes (merged with defaults) |

```
[msgraph_calendar limit="5" timezone="America/New_York" title="My Schedule"]
[msgraph_calendar class="my-calendar" table_class="my-calendar-table" item_class="my-calendar-row"]
```

---

### `[msgraph_files]`

Displays a OneDrive file/folder listing from the configured specific user.

| Attribute | Default | Description |
|---|---|---|
| `limit` | `10` | Maximum number of items |
| `folder` | *(root)* | OneDrive path (e.g. `Documents/Projects`) |
| `title` | `My Files` | Heading text |
| `class` | `msgraph_files` | Additional wrapper classes (merged with defaults) |
| `table_class` | `msgraph_table msgraph_files__table` | Additional table classes (merged with defaults) |
| `item_class` | `msgraph_files__item` | Additional row/item classes (merged with defaults) |

```
[msgraph_files limit="20" folder="Documents" title="Project Docs"]
[msgraph_files class="my-files" table_class="my-files-table" item_class="my-files-row"]
```

---

### `[msgraph_sharepoint_library]`

Displays a SharePoint document library file/folder listing.

| Attribute | Default | Description |
|---|---|---|
| `site_id` | *(required)* | SharePoint site ID |
| `drive_id` | *(required)* | Document library drive ID |
| `limit` | `10` | Maximum number of items |
| `folder` | *(root)* | Library folder path (e.g. `Shared Documents/Team`) |
| `title` | *(empty)* | Heading text |
| `class` | `msgraph_files msgraph_files--sharepoint` | Additional wrapper classes (merged with defaults) |
| `table_class` | `msgraph_table msgraph_files__table` | Additional table classes (merged with defaults) |
| `item_class` | `msgraph_files__item` | Additional row/item classes (merged with defaults) |

```
[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" folder="Shared Documents" title="Team Library"]
[msgraph_sharepoint_library site_id="contoso.sharepoint.com,abc123,def456" drive_id="b!XYZ123" class="my-sp-files" table_class="my-sp-table" item_class="my-sp-row"]
```

---

### `[msgraph_teams_message_form]`

Displays a public Teams message form (name and email required).

| Attribute | Default | Description |
|---|---|---|
| `endpoint_url` | settings value | Teams Workflow endpoint URL |
| `webhook_url` | *(empty)* | Legacy alias for `endpoint_url` |
| `use_adaptive_card` | `auto` | `auto`, `true`, or `false` |
| `title` | *(empty)* | Optional heading text |
| `placeholder` | `Type your message` | Message textarea placeholder |
| `button_text` | `Send Message` | Submit button label |
| `max_length` | `1000` | Message max length |
| `class` | `msgraph_teams_form-wrap` | Additional wrapper classes (merged with defaults) |
| `form_class` | `msgraph_teams_form` | Additional form classes (merged with defaults) |
| `input_class` | `msgraph_teams_form__input` | Additional name/email input classes (merged with defaults) |
| `textarea_class` | `msgraph_teams_form__textarea` | Additional textarea classes (merged with defaults) |
| `submit_class` | `msgraph_teams_form__submit` | Additional submit button classes (merged with defaults) |

```
[msgraph_teams_message_form]
[msgraph_teams_message_form endpoint_url="https://..." use_adaptive_card="true"]
[msgraph_teams_message_form class="my-teams-wrap" form_class="my-teams-form" input_class="my-teams-input" textarea_class="my-teams-textarea" submit_class="my-teams-submit"]
```

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
wp-ms365-graph/
├── wp-ms365-graph.php              Main plugin entry point
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
│   └── class-ms365-logger.php      Debug logger with PII redaction
├── admin/
│   ├── views/
│   │   ├── settings.php            Settings page template
│   │   ├── dashboard.php           Data dashboard template
│   │   ├── diagnostics.php         Diagnostics page template
│   │   ├── documentation.php       In-admin documentation
│   │   ├── access-stats.php        Access statistics (shortcode render counters)
│   │   └── wording.php             Wording customisation template
│   └── css/
│       └── admin.css               Admin stylesheet
├── assets/
│   └── css/
│       └── ms365.css               Front-end stylesheet
├── languages/
│   ├── wp-ms365-graph-de_DE.po     German translations (source)
│   └── wp-ms365-graph-de_DE.mo     German translations (compiled)
└── tests/
   ├── test-ms365-auth.php         Unit tests for auth helpers
   ├── test-ms365-admin.php        Unit tests for admin settings sanitation
   └── test-ms365-login.php        Unit tests for login bypass and redirect logic
```

---

## License

GPL-2.0-or-later – see [LICENSE](LICENSE).
