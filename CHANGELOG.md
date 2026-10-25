# Changelog

All notable changes to this project. Format based on [Keep a Changelog](https://keepachangelog.com/), versions follow [SemVer](https://semver.org/).

## [1.0.0] – 2026-10-25

First public release.

### Added
- **Overview:** live status (players, tech tier, Space Elevator phase, tick rate, play time, session), connection card with port check, save now, save & restart, save & stop, history charts (24 h / 7 d / 30 d).
- **Setup wizard:** claim a fresh server, set admin/player password and start a new game without the game client.
- **Admin login:** stores only an encrypted access token, never the password.
- **Savegames:** list, download, upload (via Wings, up to 100 MB), load (optionally save first), delete saves and sessions, choose the auto-load session.
- **Settings:** server options (unknown options shown generically), advanced game settings, rename server, player and admin password.
- **Console** with command history.
- **Automation:** save backups (manual, scheduled, before restart/update/load, rotation, restore, download), scheduled restarts (optionally only when empty), crash watchdog, update centre with public/experimental branch.
- **Server list badge** with player count and tier.
- **Admin page:** overview of all Satisfactory servers, egg selection, feature switches, history retention.
- English and German translations.
- Permission checks for every action (subusers supported), activity log entries, rate limits.
