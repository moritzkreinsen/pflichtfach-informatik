# Betriebsnotizen für dieses Repository

Diese Hinweise dokumentieren den vorbereiteten Betriebsablauf dieses Projekts. Für eigene Installationen sind weder GitHub Actions noch dieser Zeitplan erforderlich. Die Datei ist technische Dokumentation im Repository, kein geschützter Speicher für Zugangsdaten.

## Hosting: Webseite und Sammellauf sind getrennt

Die **Webseite läuft auf einem PHP-Webserver** und speichert Änderungen unmittelbar in einer gemeinsamen SQLite-Datenbank. GitHub Pages allein kann das nicht: Dort lassen sich Dateien ausliefern, aber keine serverseitigen Schreibzugriffe ausführen. Eine heruntergeladene SQLite-Datei wäre nur eine lokale Kopie im Browser.

**GitHub verwaltet den Quellcode und kann den wöchentlichen Sammellauf ausführen.** Der vorbereitete Workflow startet montags um 04:17 UTC (05:17 MEZ / 06:17 MESZ). GitHub-Schedules sind nicht minutengenau garantiert und laufen nur vom Standardbranch; bei längerer Inaktivität öffentlicher Repositories können sie deaktiviert werden.

Ablauf in `.github/workflows/weekly-news.yml`:

1. Artikelmetadaten per SSH vom Webserver lesen; keine Besucherhistorie, Sitzungsschlüssel oder IP-Hashes exportieren.
2. Google News mit `"Pflichtfach Informatik"` in Anführungszeichen abfragen, ergänzt um dieselbe exakte Suche je Bundesland und auf ausgewählten Domains. Direkte RSS-/Atom-Feeds und Artikelschnittstellen müssen die Wortfolge im Titel, Anreißer oder gelieferten Volltext enthalten.
3. Öffentlich zugängliche Artikeltexte laden. Paywalls, Zugriffssperren und Robots-Regeln werden nicht umgangen.
4. Auf dem **temporären GitHub-Runner** ein offenes Modell starten (`qwen3:4b` über Ollama), ohne Modell-API-Token. Das Modell läuft weder im Browser noch auf dem Rechner eines Besuchers. Der Modelldownload umfasst etwa 2,5 GB; CPU-Auswertung und Runner-Minuten sind einzuplanen. Es ist kein kostenfreier gehosteter KI-Dienst garantiert.
5. Neue Volltexte in zwei Durchgängen prüfen: Länder im Fokus, bloße Vergleichsländer, bundesweite Diskussion, Vollständigkeit des Texts, belegter Kurzüberblick. Die KI entscheidet nicht darüber, ob ein passender Suchtreffer thematisch erwünscht ist. Ungültige oder widersprüchliche Ergebnisse bleiben vorläufig.
6. Ergebnisse per SSH in die Live-Datenbank **zusammenführen**, nicht die Datenbankdatei ersetzen. Zwischenzeitliche öffentliche Korrekturen haben Vorrang. RSS-Dateien aktualisieren.

Standardmäßig werden höchstens 40 neue Volltexte pro Lauf geprüft; nach zwei Stunden werden keine weiteren Modellprüfungen begonnen, um Rechenzeit zu begrenzen. Für eine größere Erstprüfung lässt sich der Workflow manuell mit höherem `review_limit` starten. Bereits bestätigte Bewertungen und öffentliche Korrekturen werden übersprungen; ein offener Restbestand benötigt weitere Läufe. Ein kleineres offenes Modell kann Fehlentscheidungen treffen; der Prüfstatus ist keine redaktionelle Garantie.

Die Laufzeit-Datenbank bleibt auf dem Webserver. Der Workflow erzeugt **keine Repository-Commits**, veröffentlicht keine Volltexte und benötigt keinen OpenAI- oder anderen Modell-API-Schlüssel. Er benötigt allerdings SSH-Zugangsdaten zu dem Webserver. Das Quellcode-Repository ist `moritzkreinsen/pflichtfach-informatik`. Ein Zielserver samt SSH-Secrets ist noch nicht eingerichtet; der Sammellauf ist daher noch nicht betriebsbereit.

## GitHub Actions einrichten

Quellcode in ein GitHub-Repository übernehmen. In dessen Actions-Secrets hinterlegen:

| Secret | Inhalt |
| --- | --- |
| `NEWS_SSH_HOST` | SSH-Ziel, z. B. `newsjob@example.org` |
| `NEWS_REMOTE_PATH` | Absoluter Projektpfad auf dem Webserver |
| `NEWS_SSH_KEY` | Separater privater SSH-Schlüssel des Sammeljobs |
| `NEWS_KNOWN_HOSTS` | Vorher unabhängig geprüfter SSH-Hostschlüssel des Servers im known_hosts-Format |

Der Serverbenutzer braucht PHP-CLI und Zugriffsrechte auf die Projekt-Datenbank sowie die RSS-Ausgabe. Der SSH-Schlüssel sollte nur den nötigen Projektzugriff erhalten. Der Hostschlüssel wird strikt geprüft; der Workflow übernimmt keine ungeprüften Schlüssel aus dem Netzwerk. GitHub kann Runner-Nutzung je nach Repository und Tarif berechnen.

Danach den Workflow „Wöchentliche Nachrichtensammlung“ einmal manuell starten. Er ist auch über Cron ohne GitHub ausführbar: erst `php bin/collect.php`, dann Python-Volltextabruf und Modellprüfung mit `OLLAMA_URL` zu einem eigenen Modellserver, anschließend RSS-Export. Die Wahl des Modells wird über `NEWS_MODEL` gesteuert. Ein Modell wird nur auf explizit konfiguriertem Endpoint angesprochen.

## Bereinigung des früheren Suchumfangs

Nach einem Abruf mit der exakten Suchlogik kann `php bin/prune-search.php` Altbestände entfernen, für die weder ein exakter Suchtreffer noch die Wortfolge im verfügbaren Quelltext vorliegt. Modellgenerierte Zusammenfassungen zählen dabei nicht als Beleg. Die entfernten Datensätze werden unter `var/backups/` protokolliert; vorher ein konsistentes SQLite-Backup anlegen. Ein nicht zugänglicher Volltext bedeutet nicht, dass die Wortfolge sicher fehlt. Solche Altbestände werden ohne Nachweis entfernt und können bei einem späteren exakten Suchtreffer wieder aufgenommen werden.

## Direkte Beitragslinks

`python scripts/resolve_links.py --db var/news.sqlite` löst gespeicherte Google-News-Verweise auf und schreibt die Originaladresse direkt in SQLite. Der Volltextlauf führt diesen Schritt automatisch vor der Textextraktion aus, unabhängig von der Modellprüfung. Optionale Google-Cookies werden für die kurzlebige Serversitzung abgelehnt, damit die europäische Einwilligungsseite die Auflösung nicht unterbricht. Gespeicherte direkte Links bleiben bei späteren Abrufen erhalten; öffentliche Kategorieänderungen werden nicht berührt. Nicht auflösbare Links bleiben funktionsfähig als Google-News-Verweise bestehen und werden beim nächsten Lauf erneut versucht. Nach einem eigenständigen Auflösungslauf `php bin/export-rss.php` ausführen.

Die regionale Nachprüfung vom 4. November 2026 ist in `data/regional-review.json` dokumentiert. Sie unterscheidet verfügbaren Quelltext, Verlagsauszüge und Titel-/Quellenkontext; sie behauptet keine vollständige Volltextprüfung gesperrter Beiträge. Die Zuordnungen werden beim Sammeln wiederhergestellt und von der automatischen Modellprüfung bewahrt. Öffentliche Korrekturen und neuere Prüfungen haben Vorrang. Ein Beitrag zu Japan bleibt mangels passender Region im bestehenden Schema ungeklärt.

## Entwicklung

Änderungen werden über eigene Branches und Pull Requests eingebracht. Vor Konfliktauflösungen und einem Merge in `main` erfolgt die Durchsicht und Freigabe im Chat. Der Workflow „Prüfungen“ kontrolliert Pull Requests ohne Zugangsdaten oder Modellinstallation. Siehe [`AGENTS.md`](../AGENTS.md) für den vereinbarten Ablauf.
