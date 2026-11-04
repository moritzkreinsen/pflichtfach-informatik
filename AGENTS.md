# Projektarbeit

- Änderungen in einem eigenen Feature- oder Fix-Branch entwickeln, niemals direkt auf `main`.
- Änderungen prüfen, committen, pushen und einen Pull Request gegen `main` erstellen.
- Vor Konfliktauflösungen und vor jedem Merge den Vorschlag bzw. Pull Request im Chat zur Durchsicht zeigen und die ausdrückliche Freigabe des Nutzers abwarten. Kein Auto-Merge aktivieren.
- Nach Freigabe selbstständig mergen und den lokalen Stand mit `main` synchronisieren.
- Quellcode und Dokumentation bei beauftragten Änderungen gemeinsam aktuell halten. Es ist keine unbeaufsichtigte Wartungsautomation eingerichtet.
- Repository: https://github.com/moritzkreinsen/pflichtfach-informatik
- Der Projektzugang liegt im macOS-Schlüsselbund unter `github.com codex/pflichtfach-informatik`. Tokens niemals ausgeben, in Dateien schreiben oder in Git-URLs speichern.
- Keine Laufzeitdatenbank, Volltexte, IP-/Sitzungsdaten, Konfiguration mit Zugangsdaten oder lokale Modell-Dateien committen. `.gitignore` beachten.
- PHP-/SQLite-Hosting bleibt erforderlich; GitHub Pages allein unterstützt die öffentlichen Datenbankänderungen nicht.
- Keine lokalen Sprachmodelle installieren oder starten. Der vorbereitete Wochenjob führt das Modell ausschließlich auf dem GitHub-Runner aus.
- Prüfungen: `php tests/run.php` und `python3 -m unittest discover -s tests -p 'test_*.py'`.
