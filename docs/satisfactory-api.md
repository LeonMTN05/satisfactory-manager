# Satisfactory-API: Notizen und Eigenheiten

Endpunkt: `POST https://<allocation-ip>:<spielport>/api/v1`, Body `{"function": "...", "data": {...}}`, selbstsigniertes Zertifikat.

## Beobachtet am Testserver (Build Okt. 2026)

- **Fehler kommen teils mit HTTP 200**, z. B. `PasswordLogin` mit falschem Passwort → `{"errorCode":"wrong_password", ...}`. Deshalb immer auf `errorCode` prüfen, nicht nur auf den Statuscode.
- **`SaveGame` antwortet nie, wenn kein Spiel geladen ist** (Timeout). Vorher `QueryServerState.isGameRunning` prüfen.
- Großes Speichern kann dauern: Timeout für `SaveGame` 50 s (nginx bricht nach 60 s ab).
- **`CreateNewGame` verlangt seit Build 25782485 das Feld `NewGameData.GameModeSettings`.** Fehlt es, lehnt der Server ab (im Log: `Missing JSON value named GameModeSettings`). Ein leeres Objekt `{}` funktioniert. Andere Felder dürfen fehlen.
- `CreateNewGame` antwortet erst, wenn die Karte geladen ist (am Testserver etwa 14 s). Eine Zeitüberschreitung bedeutet also meist „lädt noch“, nicht „Fehler“.
- ⚠️ Unvollständige Testanfragen an `CreateNewGame` können trotzdem ein Spiel anlegen. Nur mit Absicht testen!
- `PasswordlessLogin` mit `InitialAdmin` klappt nur, solange der Server nicht eingerichtet ist. So erkennen wir „unclaimed“.
- `PasswordlessLogin` mit `Client` klappt, wenn kein Spieler-Passwort gesetzt ist. Dann ist der Status auch ohne Admin-Login lesbar.
- Die API lauscht auf der IP der Allocation, **nicht** auf 127.0.0.1 (Docker-Port-Binding).
- Token-Payload = Base64-JSON vor dem Punkt, z. B. `{"pl":"InitialAdmin"}`.
- Parameter-Namen in PascalCase (`MinimumPrivilegeLevel`, `SaveName`). Kleinschreibung wird aber auch akzeptiert.

- Saves liegen unter `/home/container/.config/Epic/FactoryGame/Saved/SaveGames/server/<SaveName>.sav`. Dort abgelegte Dateien zeigt `EnumerateSessions` sofort an. Deshalb laufen Upload und Download über Wings statt über `UploadSaveGame`/`DownloadSaveGame` (umgeht die PHP-Uploadgrenze von 2 MB).
- `GetServerOptions` liefert auch undokumentierte Optionen (z. B. `FG.WeatherPreset`). Die Oberfläche zeigt unbekannte Optionen generisch an, das Backend akzeptiert nur Schlüssel, die der Server selbst meldet.
- `saveDateTime` ist UTC im Format `2026.10.25-12.34.45`.
- `RunCommand` mit unbekanntem Befehl → `{"commandResult":"Unknown Console Command","returnValue":false}`.

## Blueprint-Eigenheit

- Der Platzhalter `{appcontext}` verliert beim Ersetzen die Backslashes. Den Namespace deshalb direkt schreiben: `Pterodactyl\BlueprintFramework\Extensions\sfmanager`.
- In TSX-Dateien keine Ausdrücke wie `{name}`, `{version}`, `{mode}` oder `{root}` verwenden: Blueprint ersetzt sie als Platzhalter.
