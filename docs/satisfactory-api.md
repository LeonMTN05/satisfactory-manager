# Satisfactory-API: Notizen und Eigenheiten

Endpunkt: `POST https://<allocation-ip>:<spielport>/api/v1`, Body `{"function": "...", "data": {...}}`, selbstsigniertes Zertifikat.

## Beobachtet am Testserver (Build Okt. 2026)

- **Fehler kommen teils mit HTTP 200**, z. B. `PasswordLogin` mit falschem Passwort → `{"errorCode":"wrong_password", ...}`. Deshalb immer auf `errorCode` prüfen, nicht nur auf den Statuscode.
- **`SaveGame` antwortet nie, wenn kein Spiel geladen ist** (Timeout). Vorher `QueryServerState.isGameRunning` prüfen.
- Großes Speichern kann dauern: Timeout für `SaveGame` 60 s.
- `PasswordlessLogin` mit `InitialAdmin` klappt nur, solange der Server nicht eingerichtet ist. So erkennen wir „unclaimed“.
- `PasswordlessLogin` mit `Client` klappt, wenn kein Spieler-Passwort gesetzt ist. Dann ist der Status auch ohne Admin-Login lesbar.
- Die API lauscht auf der IP der Allocation, **nicht** auf 127.0.0.1 (Docker-Port-Binding).
- Token-Payload = Base64-JSON vor dem Punkt, z. B. `{"pl":"InitialAdmin"}`.
- Parameter-Namen in PascalCase (`MinimumPrivilegeLevel`, `SaveName`). Kleinschreibung wird aber auch akzeptiert.

## Blueprint-Eigenheit

- Der Platzhalter `{appcontext}` verliert beim Ersetzen die Backslashes. Den Namespace deshalb direkt schreiben: `Pterodactyl\BlueprintFramework\Extensions\sfmanager`.
- In TSX-Dateien keine Ausdrücke wie `{name}`, `{version}`, `{mode}` oder `{root}` verwenden: Blueprint ersetzt sie als Platzhalter.
