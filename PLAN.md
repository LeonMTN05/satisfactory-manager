# Satisfactory Manager – Plan

Blueprint-Erweiterung für Pterodactyl, die Satisfactory Dedicated Server direkt im Panel verwaltbar macht.

## Ausgangslage (Stand 25.10.2026)

| Was | Stand |
|---|---|
| Panel | Pterodactyl 1.15.2, Blueprint beta-2026-08, Developer-Modus **an** |
| Node | `paprika.kindergulasch.de`, Wings + Docker laufen, 4 CPU / 15 GB RAM |
| Egg | „Satisfactory“ (ID 15) importiert, Ports 7777 (Spiel + HTTPS-API) und 8888 (Reliable) |
| Testserver | „TestSatis“ läuft (fabrik.kindergulasch.de:7777) |
| GitHub | https://github.com/LeonMTN05/satisfactory-manager (öffentlich) |

## Technische Grundlage

Satisfactory bringt seit 1.0 eine **HTTPS-API** mit, die auf dem Spielport läuft (`https://<ip>:7777/api/v1`, selbstsigniertes Zertifikat, Bearer-Token).
Fast alle Funktionen bauen darauf auf. Wichtige Endpunkte:

- Status: `HealthCheck`, `QueryServerState` (Spieler, Tech-Tier, Phase, Tickrate, Session, Spielzeit)
- Login/Setup: `PasswordLogin`, `PasswordlessLogin`, `ClaimServer`, `SetAdminPassword`, `SetClientPassword`, `RenameServer`
- Saves: `EnumerateSessions`, `SaveGame`, `LoadGame`, `DeleteSaveFile`, `DownloadSaveGame`, `UploadSaveGame`, `CreateNewGame`, `SetAutoLoadSessionName`
- Einstellungen: `GetServerOptions` / `ApplyServerOptions`, `GetAdvancedGameSettings` / `ApplyAdvancedGameSettings`
- Sonstiges: `RunCommand`, `Shutdown`

Einschränkung: Die API liefert nur die **Anzahl** der Spieler, keine Namen.

### Architektur

```
React-Tab „Satisfactory“ (Server-Ansicht)          Admin-Seite (globale Einstellungen)
        │  /api/client/extensions/sfmanager/servers/{server}/…
        ▼
Laravel-Controller (Pterodactyl-Rechte prüfen) ──► SatisfactoryApiClient (Guzzle, verify=false)
        │                                                   │
        ▼                                                   ▼
DB: sfmanager_servers (Token verschlüsselt),        https://<allocation>:7777/api/v1
    sfmanager_metrics, sfmanager_webhooks
        ▲
Blueprint-Konsole (Cronjob jede Minute): Status abfragen, Verlauf speichern (später auch Discord-Meldungen)
```

- **Nur für Satisfactory-Server sichtbar:** Blueprint kann den Tab im Admin-Bereich auf bestimmte Eggs begrenzen (eingebaut).
- **Rechte für Unternutzer:** Aktionen sind an vorhandene Pterodactyl-Rechte gekoppelt:
  - Status und Verbindung: `websocket.connect` (wer die Konsole sehen darf)
  - Jetzt speichern: `control.console`
  - Neues Spiel: `control.restart`
  - Einrichten, Admin-Login, Abmelden: `startup.update`
  - Saves auflisten: `file.read` · Laden: `control.restart` · Löschen: `file.delete`
  - Upload und Download laufen über das Dateisystem von Pterodactyl/Wings (eigene Rechte `file.create` und `file.read`, keine PHP-Uploadgrenze)
  - Einstellungen lesen: `startup.read` · ändern, Auto-Load, Umbenennen, Passwörter: `startup.update`
  - Konsole: `control.console` · Speichern & neu starten: `control.restart` · Speichern & stoppen: `control.stop`
- **Token:** Das Admin-Passwort wird nur einmal für `PasswordLogin` gebraucht. Gespeichert wird nur das Token, verschlüsselt mit Laravels `encrypt()`.
- **Adresse:** Die IP kommt aus der primären Allocation, bei `0.0.0.0` die Node-FQDN. 127.0.0.1 funktioniert nicht, weil Docker an die Allocation-IP bindet.
- **Hintergrund-Takt:** `sfmanager:tick` läuft jede Minute über den Laravel-Scheduler: Messwerte, Absturz-Wächter, geplante Neustarts und Backups. `sfmanager:prune` löscht täglich alte Messwerte.
- **Backups:** tar.gz im Server selbst (`/.sfmanager-backups/`, über Wings, zählt zum Speicherplatz des Servers). Inhalt: alles in `SaveGames` außer `ServerSettings.*.sav`, damit keine alten Passwörter zurückkommen.
- **Serverlisten-Badge:** liest nur gespeicherte Messwerte (eine Anfrage für alle Zeilen, keine Live-Abfragen).
- Weitere Rechte: Automatisierung lesen `startup.read`, ändern `startup.update`, Backups `backup.read/create/restore/delete`, Update auslösen `control.restart`.
- **Schutz:** Login und Einrichtung sind auf 5 Versuche pro Minute begrenzt, alle Aktionen landen im Aktivitätslog des Servers.
- API-Eigenheiten: siehe [docs/satisfactory-api.md](docs/satisfactory-api.md).

## Funktionen

### Kern (MVP) ✅ Phase 1 umgesetzt
1. **Status-Karte**: online/offline, Spieler x/y, Tech-Tier und Phase, Tickrate, Session-Name, Spielzeit, automatisch aktualisiert.
2. **Einrichtungs-Assistent**: Einen neuen Server direkt im Panel „claimen“ (Servername und Admin-Passwort setzen). Dafür braucht man das Spiel nicht. Optional lassen sich gleich ein Spieler-Passwort setzen und ein neues Spiel starten (Startgebiet, Tutorial überspringen).
3. **Verbindungs-Infos**: IP:Port zum Kopieren, Hinweis auf UDP/TCP 7777 und TCP 8888, dazu ein Erreichbarkeits-Check der Ports.
4. **Jetzt speichern**: `SaveGame` mit Namen.

### Savegame-Manager ✅ Phase 2 umgesetzt
- „Neues Spiel“-Karte ✅ (aus Phase 2 vorgezogen)
5. Liste aller Sessions und Saves (Datum, Spielzeit, Build-Version, modded ja/nein).
6. Herunterladen, Hochladen (optional gleich laden), Laden, Löschen, Auto-Load-Session festlegen.
7. **Neues Spiel** mit Startgebiet und „Onboarding überspringen“.

### Server-Einstellungen ✅ Phase 2 umgesetzt
8. Oberfläche für Server-Optionen: Auto-Pause, Autosave beim Verlassen, Autosave-Intervall, Netzwerkqualität, Neustart-Zeitfenster, saisonale Events.
9. **Erweiterte Spieleinstellungen**: kein Baukosten, Flugmodus, God-Mode usw., mit deutlicher Warnung, dass Erfolge dann deaktiviert werden.
10. Server umbenennen, Spieler- und Admin-Passwort ändern.
11. Konsolenbefehle über `RunCommand`.
12. **Sicher stoppen und neu starten**: erst speichern, dann `Shutdown` bzw. Pterodactyl-Neustart.

### Extras

Ausgewählt nach Nutzen für Serverbetreiber, Alleinstellung auf dem Blueprint-Marktplatz und Aufwand.

**Stufe A: kommt in Version 1.0** (macht die Erweiterung attraktiv) ✅ Phase 3 umgesetzt

| # | Extra | Warum | Aufwand |
|---|---|---|---|
| 13 | **Badge in der Serverliste**: „● 2/4 · Tier 5“ auf der Server-Übersicht | Auf jedem Screenshot sofort zu sehen, starkes Verkaufsargument | klein |
| 14 | **Save-Backups mit Wiederherstellung per Klick**: automatisch vor Update, Neustart und Laden sowie zeitgesteuert, mit Rotation (Anzahl/Alter einstellbar) | Häufigstes Problem: kaputte oder verlorene Saves | mittel |
| 15 | **Verlauf und Diagramme**: Spieler, Tickrate und Tech-Tier über 24 h / 7 Tage / 30 Tage | Tickrate zeigt Lag durch große Fabriken früh | mittel |
| 16 | **Update-Center**: installierte Build mit der neuesten Steam-Build vergleichen (App 1690800), Branch-Schalter public/experimental (`SRCDS_BETAID`), „Backup → Update → Neustart“ in einem Schritt | Updates sind bei Satisfactory der häufigste Bruchpunkt | mittel |
| 17 | **Geplante Neustarts mit Sicherheitsnetz**: zur gewünschten Zeit erst speichern, dann neu starten. Optional nur, wenn niemand online ist | Pterodactyl-Zeitpläne können nicht über die API speichern | klein |
| 18 | **Absturz-Wächter**: Server antwortet nicht mehr auf `HealthCheck` → Neustart, letzte Logzeilen anzeigen | Spart nachts manuelles Eingreifen | klein |

**Stufe B: Version 1.x** (nette Extras)

| # | Extra | Warum |
|---|---|---|
| 19 | **Discord-Webhooks**: online/offline, Absturz erkannt, Spielerzahl ändert sich, Save/Backup erstellt, Update verfügbar, Meilensteine. Ereignisse einzeln wählbar, Test-Button | Wird bei Gameserver-Erweiterungen sehr oft gewünscht |
| 20 | **Netzwerk-Tuning**: bekannte Bandbreiten-Einstellungen in Engine.ini/Game.ini per Schalter, mit Backup der Originaldatei | Beliebter Fix gegen Gummiband-Effekte |
| 21 | **Öffentliches Status-Widget**: abschaltbarer Link bzw. Bild-Badge (online, Spieler, Tier) für Website oder Discord | Betreiber zeigen ihren Server gern her |
| 22 | **Fabrik-Steckbrief**: Spielzeit, Tier, Phase, Session-Historie und Meilensteine („Tier 7 erreicht am …“) | Stärkt die Spielerbindung |
| 23 | **Save-Transfer-Assistent**: Einzelspieler-Save hochladen, prüfen (Build-Version, Mods) und als Auto-Load-Session setzen | Häufigste Anfrage neuer Serverbetreiber |
| 24 | **Ressourcen-Empfehlung**: RAM-Verbrauch mit dem Limit vergleichen und Hinweis bei Engpass | Satisfactory braucht viel RAM, Abstürze durch OOM sind häufig |

**Stufe C: später** (großer Aufwand)

| # | Extra | Hinweis |
|---|---|---|
| 25 | **Mod-Manager** über die ficsit.app-API: SML und Mods suchen, installieren, aktualisieren, Abhängigkeiten auflösen | eigenes Teilprojekt, eventuell Version 2.0 |

**Bewusst nicht geplant:** Spielernamen, Kick/Ban und Chat. Die Satisfactory-API bietet das nicht, ein Workaround wäre unzuverlässig.

### Grundlage für alle Funktionen
- **Admin-Seite**: alle Satisfactory-Server auf einen Blick, globale Standardwerte (Polling-Intervall, Backup-Rotation), jede Funktion einzeln abschaltbar.
- **Sprachen**: Deutsch und Englisch, von Anfang an eingebaut (`components/lang.ts`, Auswahl nach Browsersprache). Der Marktplatz ist international.
- **Sicherheit**: Token verschlüsselt speichern. Verbindungen nur zu Allocations des jeweiligen Servers, damit niemand über das Panel beliebige Adressen ansteuern kann (SSRF-Schutz). Jede Aktion prüft die passenden Unternutzer-Rechte.
- **Datenschutz**: keine Telemetrie. Externe Abfragen (Steam-Build, später Discord) passieren nur, wenn sie eingeschaltet sind.

## Phasen

| Phase | Inhalt | Ergebnis |
|---|---|---|
| 0 | Repo, Ordner, Dev-Workflow, Testserver | erledigt (Testserver wird erstellt) |
| 1 | API-Client, Token-Speicher, Status-Karte, Claim-Assistent, Verbindungs-Infos, Jetzt speichern, DE/EN-Grundgerüst | ✅ MVP (Claim-Assistent noch im Browser testen) |
| 2 | Savegame-Manager, Server- und Spieleinstellungen, Konsole, sicherer Neustart | ✅ vollständige Verwaltung (Laden, Neustart und Passwörter noch im Browser testen) |
| 3 | Extras Stufe A (13–18), Admin-Seite | ✅ Funktionsumfang 1.0 (geplanter Neustart, Wächter und Update noch live testen) |
| 4 | Übersetzungen vervollständigen, Doku, Screenshots, Icon, Marktplatz-Eintrag, Release über GitHub Actions | **Release 1.0 auf blueprint.zip** |
| 5 | Extras Stufe B (19–24, u. a. Discord) | 1.x-Updates |
| 6 | Mod-Manager (25) | 2.0 |

## Veröffentlichung (Blueprint-Marktplatz)
- Eigenes Icon und Screenshots ohne Original-Logos von Satisfactory oder Coffee Stain (Markenrecht). Den Namen „Satisfactory“ verwenden wir nur beschreibend.
- Rechtlicher Hinweis in README, Marktplatz-Beschreibung und auf der Admin-Seite.
- Getestet gegen die aktuelle Pterodactyl- und Blueprint-Version, Zielversion in `conf.yml` gepflegt.
- Versionierung nach SemVer, Changelog, Release-Asset `sfmanager.blueprint` über GitHub Releases.

## Repo-Struktur

```
satisfactory-manager/
├── extension/          # Inhalt der Blueprint-Erweiterung (= .blueprint/dev)
│   ├── conf.yml
│   ├── admin/          # Admin-Seite (Blade + Controller)
│   ├── app/            # PHP: Services, Controller, Models, Konsole
│   ├── components/     # React: Tab, Serverlisten-Badge, API-Hooks
│   ├── routers/        # Client-API-Routen
│   ├── database/       # Migrationen
│   ├── console/        # geplante Aufgaben
│   ├── public/ private/
├── scripts/
│   ├── dev-sync.sh     # extension/ -> Panel kopieren + blueprint -build (--watch möglich)
│   └── package.sh      # baut dist/sfmanager.blueprint
├── docs/
└── PLAN.md
```

## Entscheidungen
- Name „Satisfactory Manager“, Identifier `sfmanager` ✅
- Öffentliches GitHub-Repo ✅
- Ziel: Veröffentlichung auf dem Blueprint-Marktplatz ✅

- Lizenz: MIT ✅
- Discord-Meldungen erst nach 1.0 (Stufe B) ✅
