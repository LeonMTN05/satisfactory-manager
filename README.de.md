<p align="center"><img src="docs/assets/icon-512.png" width="96" alt="Satisfactory Manager Icon"></p>

<h1 align="center">Satisfactory Manager</h1>

<p align="center">
  Eine <a href="https://blueprint.zip">Blueprint</a>-Erweiterung für das Pterodactyl Panel, mit der sich Satisfactory Dedicated Server bequem betreiben lassen: Status, Savegames, Backups, Einstellungen und Automatisierung direkt im Panel.
  <br><a href="README.md">🇬🇧 English version</a>
</p>

---

## Funktionen

**Übersicht**
- Live-Status: online/offline, Spieler, Tech-Tier, Weltraumaufzug-Phase, Tickrate (farbig), Spielzeit, Session
- **Neuen Server ohne Spiel-Client einrichten**: claimen, Admin- und Spieler-Passwort setzen, neues Spiel starten
- Verbindungs-Karte mit kopierbarer Adresse und Prüfung von Spiel- und Reliable-Port
- Jetzt speichern · Speichern & neu starten · Speichern & stoppen
- Verlaufsdiagramme für Spieler und Tickrate (24 Std. / 7 Tage / 30 Tage)
- Spieleranzahl und Tier direkt in der Serverliste des Panels

**Savegames**
- Alle Sessions und Saves mit Datum, Spielzeit, Build und Hinweisen (modded, bearbeitet, erweiterte Einstellungen)
- Herunterladen, Hochladen (bis 100 MB, über Wings, ohne PHP-Grenze), Laden, Löschen, Session für den Serverstart festlegen

**Automatisierung**
- Save-Backups: manuell oder alle 1–24 Std., vor Neustarts, Updates und dem Laden; Rotation; Wiederherstellen per Klick
- Geplante Neustarts, die vorher speichern, optional nur, wenn niemand online ist
- Absturz-Wächter: startet einen Server neu, der läuft, aber nicht mehr antwortet
- Update-Center: installierte und neueste Steam-Build, Branch public/experimental, „Backup & Update“ in einem Schritt

**Einstellungen**
- Server-Optionen mit verständlichen Namen (Auto-Pause, Autosave, Netzwerkqualität …); auch unbekannte Optionen werden angezeigt
- Erweiterte Spieleinstellungen (God-Mode, Flugmodus, keine Baukosten …) mit deutlicher Warnung
- Server umbenennen, Spieler- und Admin-Passwort ändern
- Server-Konsole mit Befehlsverlauf

**Für Panel-Admins**
- Übersicht aller Satisfactory-Server
- Auswahl der Satisfactory-Eggs, jede Funktion einzeln abschaltbar, Aufbewahrungsdauer des Verlaufs

**Sicher gebaut**
- Jede Aktion prüft die Pterodactyl-Rechte (auch für Unternutzer) und landet im Aktivitätslog
- Das Satisfactory-Admin-Passwort wird nie gespeichert, nur ein verschlüsselter Zugangs-Token
- Das Panel verbindet sich nur mit der Allocation des jeweiligen Servers
- Anfragebremse bei Login und Einrichtung; keine Telemetrie. Die einzige externe Abfrage (neueste Steam-Build über api.steamcmd.net) ist abschaltbar.
- Deutsch und Englisch, je nach Browsersprache

## Voraussetzungen

| | |
|---|---|
| Pterodactyl Panel | 1.x (getestet mit 1.15.2) |
| Blueprint | beta-2026-08 oder neuer |
| Satisfactory | 1.0 oder neuer (HTTPS-API des Dedicated Servers) |
| Egg | [Satisfactory-Egg aus pterodactyl/game-eggs](https://eggs.pterodactyl.io/egg/games-satisfactory/) oder jedes Egg, das `FactoryGame` startet bzw. Steam-App 1690800 installiert |
| Ports | Spielport (TCP/UDP, auch für die API) **und** Reliable-Port (TCP, Standard 8888) als Allocations |
| Scheduler | Laravel-Cronjob `php artisan schedule:run` (Teil jeder Pterodactyl-Installation), nötig für Verlauf, Backups, geplante Neustarts und Wächter |

## Installation

```bash
cd /var/www/pterodactyl
# sfmanager.blueprint aus dem neuesten GitHub-Release herunterladen, dann:
blueprint -install sfmanager
```

Danach **Admin → Extensions → Satisfactory Manager** öffnen und die Egg-Auswahl prüfen. Tipp: In den Blueprint-Einstellungen der Erweiterung dieselben Eggs auswählen, dann erscheint der Tab nur bei Satisfactory-Servern.

Update: die neue `.blueprint`-Datei genauso installieren. Entfernen: `blueprint -remove sfmanager`.

## Rechte

| Aktion | Pterodactyl-Recht |
|---|---|
| Status, Verlauf, Verbindung ansehen | `websocket.connect` |
| Jetzt speichern, Konsole | `control.console` |
| Save laden, neues Spiel, Speichern & neu starten, Update | `control.restart` |
| Speichern & stoppen | `control.stop` |
| Saves auflisten | `file.read` · Herunterladen: `file.read` · Hochladen: `file.create` · Löschen: `file.delete` |
| Einstellungen / Automatisierung lesen | `startup.read` |
| Einstellungen, Automatisierung, Branch, Passwörter, Name, Einrichtung, Admin-Login ändern | `startup.update` |
| Backups | `backup.read` / `backup.create` / `backup.restore` / `backup.delete` |

## FAQ

**Der Status zeigt „offline“, obwohl der Server läuft.** Das Panel erreicht den Server über die IP der primären Allocation auf dem Spielport. Diese Adresse muss vom Panel-Rechner aus erreichbar sein, nicht nur von außen.

**Wo liegen die Backups?** Im Server unter `/.sfmanager-backups/` als `.tar.gz`. Sie zählen zum Speicherplatz des Servers und enthalten alle Saves und Blueprints, aber nicht `ServerSettings.*.sav`. Eine Wiederherstellung holt also nie alte Passwörter zurück.

**Beim Neustart werden keine Updates installiert.** Die Egg-Variable `AUTO_UPDATE` muss auf `1` stehen.

**Kann ich Spielernamen sehen oder Spieler kicken?** Nein, die Satisfactory-API liefert nur die Anzahl der Spieler.

## Entwicklung

```bash
./scripts/dev-sync.sh          # extension/ ins Panel kopieren und bauen (Blueprint-Developer-Modus)
./scripts/dev-sync.sh --watch  # bei jeder Änderung neu bauen
./scripts/package.sh           # dist/sfmanager.blueprint erzeugen
```

API-Eigenheiten: [docs/satisfactory-api.md](docs/satisfactory-api.md) · Fahrplan: [PLAN.md](PLAN.md) · Änderungen: [CHANGELOG.md](CHANGELOG.md)

## Rechtlicher Hinweis

Dies ist ein inoffizielles Community-Projekt. Es steht in keiner Verbindung zu den unten genannten Firmen und Projekten und wird von ihnen weder unterstützt noch geprüft.

- **Satisfactory** ist eine Marke von **Coffee Stain Studios AB**. Spiel, Dedicated Server, dessen HTTPS-API, Namen, Logos und Spielinhalte gehören Coffee Stain Studios.
- **Pterodactyl®** gehört dem Pterodactyl-Projekt. Das [Satisfactory-Egg](https://eggs.pterodactyl.io/egg/games-satisfactory/) stammt aus der Community-Sammlung [pterodactyl/game-eggs](https://github.com/pterodactyl/game-eggs).
- **Blueprint** ist ein Projekt des [BlueprintFramework](https://github.com/BlueprintFramework).
- **Steam** ist eine Marke der Valve Corporation. Build-Infos kommen vom Drittanbieter-Dienst [api.steamcmd.net](https://www.steamcmd.net).
- **ficsit.app** und der Satisfactory Mod Loader (SML) gehören der Satisfactory-Modding-Community.

Alle Marken gehören ihren jeweiligen Inhabern. Von mir stammt nur der Code dieser Erweiterung; das Icon ist eine eigene Gestaltung.

## Lizenz

[MIT](LICENSE). Die Lizenz gilt nur für den Code dieser Erweiterung.
