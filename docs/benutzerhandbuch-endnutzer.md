# ESC Connect – Benutzerhandbuch (Endnutzer)

Dieses Handbuch richtet sich an Redakteure, Autoren und interne Nutzer, die Inhalte aus Microsoft 365 in WordPress verwenden.

## 1. Was macht ESC Connect?

ESC Connect verbindet WordPress mit Microsoft 365 (EntraID/Microsoft Graph), damit Inhalte wie Kalendertermine und Dateien direkt auf Seiten angezeigt werden können.

Typische Anwendungsfälle:
1. Termine aus Outlook anzeigen
2. Dateien aus OneDrive anzeigen
3. Dateien aus SharePoint-Bibliotheken anzeigen
4. Anmeldung über Microsoft-Tenant (falls aktiviert)

## 2. Anmeldung

Je nach Konfiguration gibt es zwei Wege:
1. Normale WordPress-Anmeldung
2. "Mit Microsoft anmelden" (Tenant Sign-In)

Wenn der Microsoft-Login aktiviert ist:
1. Öffne die Login-Seite.
2. Klicke auf den Microsoft-Button.
3. Melde dich mit deinem Firmenkonto an.
4. Du wirst zurück zur gewünschten Seite oder zur Standard-Zielseite geleitet.

Hinweis:
- Falls dein Konto nicht zugelassen ist (z. B. Domain nicht erlaubt), kontaktiere den Administrator.

## 3. Kalender auf einer Seite einfügen

Für den Block-Editor:
1. Seite oder Beitrag öffnen.
2. ESC Connect Kalender-Block einfügen.
3. Optional Werte für Anzahl/Zeitraum anpassen.

Alternativ per Shortcode:

```text
[msgraph_calendar]
```

Beispiel mit Parametern:

```text
[msgraph_calendar limit="10" days="30" title="Nächste Termine"]
```

## 4. OneDrive-Dateien anzeigen

Per Shortcode:

```text
[msgraph_files]
```

Beispiel:

```text
[msgraph_files folder="/Team" limit="50" title="Dokumente"]
```

Mögliche Wirkung:
- Dateien werden als Liste/Tabelle angezeigt.
- Spalten und Darstellung können vom Admin global oder pro Einsatz angepasst sein.

## 5. SharePoint-Bibliothek anzeigen

Per Shortcode:

```text
[msgraph_sharepoint_library]
```

Beispiel:

```text
[msgraph_sharepoint_library site_id="<site-id>" drive_id="<drive-id>" folder="/Allgemein"]
```

Hinweis:
- Site/Drive-Werte werden in der Regel vom Admin bereitgestellt.

## 6. Login-Button auf Seiten einbinden

Falls gewünscht, kann ein Login-Button auf beliebigen Seiten eingebaut werden:

```text
[msgraph_login_button]
```

Optional:

```text
[msgraph_login_button label="Mit Firmenkonto anmelden" redirect_to="/dashboard"]
```

## 7. Häufige Probleme

1. Kalender oder Dateien werden nicht angezeigt
- Seite neu laden
- Prüfen, ob der Shortcode korrekt ist
- Prüfen, ob du die nötige Berechtigung in Microsoft 365 hast

2. Microsoft-Login funktioniert nicht
- Browser-Cache löschen
- Nochmals anmelden
- Admin informieren (siehe Abschnitt 8)

3. Keine oder unvollständige Inhalte
- Möglicherweise fehlen Berechtigungen im Quellsystem (Outlook/OneDrive/SharePoint)
- Admin informieren

## 8. Wenn etwas fehlt: An wen wenden?

Bitte sende eine kurze Meldung an den zuständigen WordPress-/IT-Support mit:
1. Seite/URL, auf der das Problem auftritt
2. Zeitpunkt
3. Genaue Fehlermeldung (falls sichtbar)
4. Welche Funktion betroffen ist (Kalender, OneDrive, SharePoint, Login)

Sinnvolle Zuständigkeiten:
1. WordPress-Admin: Plugin-Einstellungen, Shortcodes, Seitenkonfiguration
2. EntraID/M365-Admin: App-Berechtigungen, Consent, Benutzer-/Dateiberechtigungen

## 9. Gute Praxis für Redakteure

1. Shortcodes zunächst auf einer Testseite prüfen.
2. Bei Problemen zuerst einfache Standardparameter nutzen.
3. Für produktive Seiten feste Titel und konsistente Struktur verwenden.
4. Änderungen dokumentieren (welcher Shortcode auf welcher Seite genutzt wird).
