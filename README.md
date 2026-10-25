<p align="center"><img src="docs/assets/icon-512.png" width="96" alt="Satisfactory Manager icon"></p>

<h1 align="center">Satisfactory Manager</h1>

<p align="center">
  A <a href="https://blueprint.zip">Blueprint</a> extension for the Pterodactyl Panel that makes Satisfactory dedicated servers easy to run – status, savegames, backups, settings and automation, right in the panel.
  <br><a href="README.de.md">🇩🇪 Deutsche Version</a>
</p>

---

## Features

**Overview**
- Live status: online state, players, tech tier, Space Elevator phase, tick rate (colour-coded), play time, session
- **Set up a new server without the game client** – claim it, set admin and player password, start a new game
- Connection card with copyable address and a check of game and reliable port
- Save now · Save & restart · Save & stop
- History charts for players and tick rate (24 h / 7 d / 30 d)
- Player count and tier right in the panel's server list

**Savegames**
- All sessions and saves with date, play time, build and flags (modded, edited, advanced settings)
- Download, upload (up to 100 MB, via Wings – no PHP upload limit), load, delete, choose the session loaded on start

**Automation**
- Save backups – manual or every 1–24 h, before restarts, updates and loading; rotation; one-click restore
- Scheduled restarts that save first, optionally only when nobody is online
- Crash watchdog: restarts a server whose process runs but no longer responds
- Update centre: installed vs. newest Steam build, public/experimental branch, "Back up & update" in one step

**Settings**
- Server options with friendly labels (auto-pause, autosave, network quality, …) – options unknown to the extension are still shown
- Advanced game settings (god mode, flight mode, no build cost, …) with a clear warning
- Rename server, change player and admin password
- Server console with command history

**For panel admins**
- Overview of all Satisfactory servers
- Choose which eggs count as Satisfactory, switch every feature on or off, history retention

**Built to be safe**
- Every action checks the user's Pterodactyl permissions (subusers included) and is written to the activity log
- The Satisfactory admin password is never stored – only an encrypted access token
- The panel only ever connects to the server's own allocation
- Rate limits on login and setup; no telemetry. The only external request (newest Steam build via api.steamcmd.net) can be switched off.
- English and German, picked by browser language

## Requirements

| | |
|---|---|
| Pterodactyl Panel | 1.x (tested with 1.15.2) |
| Blueprint | beta-2026-08 or newer |
| Satisfactory | 1.0 or newer (dedicated server HTTPS API) |
| Egg | [pterodactyl/game-eggs Satisfactory egg](https://eggs.pterodactyl.io/egg/games-satisfactory/) or any egg that starts `FactoryGame` / installs Steam app 1690800 |
| Ports | game port (TCP/UDP, also used by the API) **and** the reliable port (TCP, default 8888) as allocations |
| Scheduler | Laravel cron `php artisan schedule:run` (part of every Pterodactyl install) – needed for history, backups, scheduled restarts and the watchdog |

## Installation

```bash
cd /var/www/pterodactyl
# download sfmanager.blueprint from the latest GitHub release, then:
blueprint -install sfmanager
```

Afterwards open **Admin → Extensions → Satisfactory Manager** and check the egg selection. Tip: restrict the extension to the same eggs in Blueprint's extension settings so the tab only appears on Satisfactory servers.

Update: install the new `.blueprint` file the same way. Remove: `blueprint -remove sfmanager`.

## Permissions

| Action | Pterodactyl permission |
|---|---|
| View status, history, connection | `websocket.connect` |
| Save now, console | `control.console` |
| Load save, new game, save & restart, update | `control.restart` |
| Save & stop | `control.stop` |
| List saves | `file.read` · download: `file.read` · upload: `file.create` · delete: `file.delete` |
| Read settings / automation | `startup.read` |
| Change settings, automation, branch, passwords, name, claim, admin login | `startup.update` |
| Backups | `backup.read` / `backup.create` / `backup.restore` / `backup.delete` |

## FAQ

**The status says "offline" although the server runs.** The panel reaches the server via the IP of its primary allocation on the game port. Make sure that address is reachable from the panel machine (not just from outside).

**Where are backups stored?** Inside the server in `/.sfmanager-backups/` as `.tar.gz`. They count towards the server's disk space and contain all saves and blueprints – but not `ServerSettings.*.sav`, so restoring never brings back old passwords.

**Updates are not installed on restart.** The egg variable `AUTO_UPDATE` must be `1`.

**Can I see player names or kick players?** No – Satisfactory's API only reports the number of players.

## Development

```bash
./scripts/dev-sync.sh          # copy extension/ into the panel and build (Blueprint developer mode)
./scripts/dev-sync.sh --watch  # rebuild on every change
./scripts/package.sh           # create dist/sfmanager.blueprint
```

Notes on API quirks: [docs/satisfactory-api.md](docs/satisfactory-api.md) · Roadmap: [PLAN.md](PLAN.md) · Changes: [CHANGELOG.md](CHANGELOG.md)

## Legal

This is an unofficial community project. It is not affiliated with, endorsed or reviewed by any of the companies or projects below.

- **Satisfactory** is a trademark of **Coffee Stain Studios AB**. The game, the dedicated server, its HTTPS API, names, logos and game content belong to Coffee Stain Studios.
- **Pterodactyl®** belongs to the Pterodactyl project. The [Satisfactory egg](https://eggs.pterodactyl.io/egg/games-satisfactory/) comes from the community collection [pterodactyl/game-eggs](https://github.com/pterodactyl/game-eggs).
- **Blueprint** is a project of the [BlueprintFramework](https://github.com/BlueprintFramework).
- **Steam** is a trademark of Valve Corporation. Build information is fetched from the third-party service [api.steamcmd.net](https://www.steamcmd.net).
- **ficsit.app** and the Satisfactory Mod Loader (SML) belong to the Satisfactory modding community.

All trademarks belong to their respective owners. Only the code of this extension is my own; the icon is original artwork.

## License

[MIT](LICENSE) – covers only the code of this extension.
