# ESC Connect – Administratorhandbuch

Dieses Handbuch beschreibt die Einrichtung, Konfiguration und den Betrieb von ESC Connect für Administratoren.

## 1. Architektur und Zweck

ESC Connect nutzt Microsoft Graph über App-Only-Authentifizierung (Client Credentials), um M365-Daten in WordPress bereitzustellen.

Bereitgestellte Funktionen:
1. Kalender-Shortcode
2. OneDrive-Datei-Shortcode
3. SharePoint-Bibliotheks-Shortcode
4. Tenant Sign-In (Microsoft Login für WordPress)
5. Optionaler Mailversand via Microsoft Graph
6. Diagnoseseite mit Live-Checks und Testfunktionen

## 2. Voraussetzungen

1. WordPress 5.9+
2. PHP 7.4+
3. EntraID App-Registrierung
4. Globaler Admin-Consent für benötigte Graph-Rechte

## 3. EntraID-Einrichtung (verpflichtend)

### 3.1 App-Registrierung

1. Entra Admin Center öffnen.
2. Neue App-Registrierung erstellen.
3. Werte notieren:
- Tenant ID
- Client ID

### 3.2 Client Secret

1. Unter Zertifikate & Geheimnisse neues Secret erzeugen.
2. Secret-Wert sofort sichern.
3. Secret in ESC Connect eintragen.

### 3.3 Graph-Berechtigungen (Application Permissions)

Für die Kernfunktionen mindestens:
1. User.Read.All
2. Calendars.Read
3. Files.Read.All
4. Sites.Read.All

Optional:
1. Mail.Send (nur wenn Graph Mail Transport genutzt wird)

Wichtig:
- Danach immer Admin Consent erteilen.

### 3.4 Delegated Permissions für Tenant Sign-In

Für Login per Microsoft zusätzlich:
1. openid
2. email
3. profile

## 4. Plugin-Konfiguration in WordPress

Öffne ESC Connect -> Settings.

### 4.1 Grundkonfiguration

Pflichtfelder:
1. Tenant ID
2. Client ID
3. Client Secret
4. Specific User (UPN oder Objekt-ID)

Bedeutung von Specific User:
- Gegen diesen Benutzerkontext werden Profil-, Kalender- und OneDrive-Abfragen ausgeführt.

### 4.2 Tenant Sign-In (WordPress Login via Microsoft)

Einstellungen:
1. Enable Tenant Sign-In
- Aktiviert Microsoft-Login auf der Login-Seite.

2. Auto-Create Users
- Erstellt WordPress-Konten automatisch beim ersten Login.

3. Default Role for New Users
- Rolle für automatisch erzeugte Nutzer.

4. Allowed Email Domains
- Kommagetrennte Allowlist für Domains.
- Leer bedeutet keine zusätzliche Domain-Einschränkung.

5. Post-Login Redirect URL
- Optionale Zielseite nach erfolgreichem Login.

Zusätzliche Entra-Anforderung:
- Gültige Web Redirect URI in der App-Registrierung (Callback-URL aus Plugin übernehmen).

### 4.3 WordPress Mail via Microsoft Graph

Einstellungen:
1. Graph Mail Transport aktivieren
2. Mail Sender Mailbox (UPN/ID) setzen
3. Optional Save Messages in Sent Items

Anforderung:
- Mail.Send mit Admin Consent.

Betriebshinweis:
- Nach Aktivierung ggf. Plugin einmal deaktivieren/aktivieren, damit alle Hooks sicher greifen.

### 4.4 Teams Workflow Endpoint (falls genutzt)

Einsatz:
- Für Teams-Nachrichtenformular-Workflows.

Vorgehen:
1. In Teams/Power Automate einen Workflow mit eingehendem Webhook anlegen.
2. Endpoint-URL im Plugin speichern.
3. Test über Frontend-Formular durchführen.

Sicherheitsregel:
- Endpoint-URL wie ein Secret behandeln.

## 5. Shortcodes und redaktioneller Betrieb

### 5.1 Kalender

```text
[msgraph_calendar]
```

Typische Parameter:
- limit
- days
- title

### 5.2 OneDrive

```text
[msgraph_files]
```

Typische Parameter:
- folder
- limit
- columns
- hide_columns

### 5.3 SharePoint-Library

```text
[msgraph_sharepoint_library]
```

Typische Parameter:
- site_id
- drive_id
- folder
- limit

### 5.4 Login-Button

```text
[msgraph_login_button]
```

Optionen:
- label
- redirect_to
- class

## 6. Diagnostics: Was prüfen?

Öffne ESC Connect -> Diagnostics.

Regelmäßig prüfen:
1. Systeminformationen (WP/PHP/Plugin-Version)
2. Auth-Konfiguration vollständig
3. Live-Graph-Checks (User, Kalender, OneDrive)
4. E-Mail-Transporttest (falls aktiv)
5. Debug-Logs (PII-redaktiert)

## 7. Häufige Fehlerbilder und Lösungen

1. Keine Daten in Kalender/Dateien
- Credentials prüfen
- Graph-Permissions prüfen
- Consent prüfen
- Specific User prüfen

2. Login mit Microsoft schlägt fehl
- Redirect URI prüfen
- Delegated Permissions prüfen
- Domain-Allowlist prüfen

3. Mailversand per Graph funktioniert nicht
- Mail.Send prüfen
- Sender-Mailbox existent und berechtigt
- Diagnostics-Test ausführen

4. SharePoint-Inhalte fehlen
- Sites.Read.All prüfen
- Site-/Drive-ID prüfen
- Zugriff auf Bibliothek prüfen

## 8. Sicherheits- und Compliance-Empfehlungen

1. Least-Privilege-Prinzip anwenden.
2. Secret-Rotation etablieren.
3. Nur notwendige Features aktivieren.
4. Debug-Logs regelmäßig prüfen und bereinigen.
5. Änderungen an Einstellungen versioniert dokumentieren.

## 9. Change- und Supportprozess

Bei Tickets sollten folgende Informationen vorliegen:
1. Betroffene Funktion (Kalender/Files/SharePoint/Login/Mail)
2. Betroffene Seite/URL
3. Zeitpunkt und Fehlermeldung
4. Betroffener Benutzer
5. Ergebnis der Diagnostics-Checks

Zuständigkeiten:
1. WordPress-Admin: Plugin-Konfiguration, Seitenintegration, Shortcodes
2. EntraID/M365-Admin: App-Registrierung, Berechtigungen, Consent, Tenant-Richtlinien
3. Redaktion: Fachliche Anforderungen und Seiteninhalt

## 10. Betriebscheckliste nach Änderungen

Nach jeder Konfigurationsänderung:
1. Einstellungen speichern
2. Diagnostics öffnen und Live-Checks prüfen
3. Eine Seite mit Kalender-Shortcode testen
4. Eine Seite mit Datei-Shortcode testen
5. Optional Login und Mailtest durchführen

Wenn alle Punkte grün sind, ist die Änderung in der Regel produktionsreif.
