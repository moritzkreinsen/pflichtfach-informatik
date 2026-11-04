# Pflichtfach Informatik – Nachrichten

Eine Nachrichtensammlung nach der exakten Wortfolge **„Pflichtfach Informatik“**, ähnlich einem Google Alert. Groß-/Kleinschreibung und typografische Leerzeichen werden vereinheitlicht; verwandte Begriffe wie „Informatikunterricht“, „Informatik als Pflichtfach“ oder „Digitale Grundbildung“ reichen allein nicht aus.

Eine schlanke PHP-/SQLite-Webseite mit Quellenlinks, kurzen Überblicken, Bundeslandkarte, Suche und öffentlich korrigierbaren Kategorien. Kein Frontend-Framework, kein Build-Schritt und kein Modell auf dem Webserver erforderlich.

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

## Installation auf dem Webserver

Voraussetzungen: PHP 8.1+ mit PDO_SQLite, cURL, SimpleXML, XMLWriter und mbstring; HTTPS; ausreichend Schreibrechte für `var/` und `public/feeds/`.

1. Projekt auf den Server kopieren. **Nur `public/` ist DocumentRoot.** `app/`, `bin/`, `var/`, `data/` und `config.php` müssen außerhalb des Webroots liegen.
2. `config.example.php` nach `config.php` kopieren; `base_url` auf die echte HTTPS-URL setzen (Unterverzeichnis möglich). Für Besucher gibt es keine Konten und keine Schlüssel.
3. `var/`, `data/cache/` und `public/feeds/` für den Sammler schreibbar machen, `var/` und `public/feeds/` auch für PHP. Keine pauschalen 0777-Rechte. SQLite und WAL gehören nicht ins öffentliche Webverzeichnis.
4. Apache: `mod_rewrite` und die mitgelieferte `public/.htaccess` aktivieren. Nginx: vor der normalen PHP-Konfiguration diese Regel ergänzen:

```nginx
location ~ ^/feeds/(all|[A-Z-]+)\.rss$ {
    rewrite ^/feeds/(.*)\.rss$ /feed.php?regions=$1 last;
}
```

5. Erster Abruf ohne Modell: `php bin/collect.php`. Daraus entstehen zunächst vorläufige Zuordnungen. RSS separat exportieren: `php bin/export-rss.php`.
6. Lokal zur Vorschau: `php -S 127.0.0.1:8080 -t public public/router.php`.

Es wird **kein Ollama lokal installiert oder gestartet**. Die Python-Abhängigkeiten in `requirements.txt` dienen dem Sammellauf; auf gewöhnlichem PHP-Hosting sind sie nicht nötig, wenn GitHub Actions diese Aufgabe übernimmt.

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

## Quellen und Kategorien

`app/sources.json` listet die Suchabfragen, direkten RSS-/Atom-Feeds und öffentlichen Artikelschnittstellen. Hinzu kommen 16 Bundeslandsuchen. Enthalten sind unter anderem bildungsklick, News4teachers, heise sowie gezielte Suchen auf Seiten der Kultusministerien, Bildungsserver, KMK, Stifterverband und Bitkom. Google News ist der zentrale Suchindex, keine redaktionelle Quelle. Exakte Suchtreffer werden auch übernommen, wenn die Wortfolge nur im indexierten Volltext und nicht im RSS-Anreißer steht. Dieser Suchnachweis wird als `google_exact_query` gespeichert und ist keine unabhängig bestätigte Volltextprüfung. Bei direkten Quellen wird `source_text` gespeichert. Originalquellen stehen am Beitrag.

`data/source-links.json` enthält verifizierte direkte Quellenlinks als Ergänzung, wenn Google-Weiterleitungslinks nicht automatisch auflösbar sind. Diese Zuordnung betrifft URLs, nicht die Kategorien. Zeitweise nicht erreichbare Quellen werden gemeldet; erfolgreiche Quellen werden trotzdem verarbeitet.

Zuordnung nach Inhalt:

- Ein Bundesland ist alleiniger Schwerpunkt: nur dieses Land.
- Mehrere Länder werden gleichwertig behandelt: diese Länder gemeinsam.
- Allgemeine deutsche Debatte oder bundesweiter Vergleich: `DE`.
- Ein beiläufiger Vergleich, eine Verlagsadresse oder der Sitz eines Bundesverbands genügt nicht für eine Länderzuordnung.
- Österreich `AT`, Schweiz `CH`, ungeklärt `UNK`.

Die Modellprüfung speichert Begründung, einen kurzen Textbeleg, Quellenlink, Zeitpunkt und Texthash. Im Zweifel bleibt die vorläufige Zuordnung bestehen. Volltexte liegen nur im ignorierten Arbeitscache und werden nicht an Besucher ausgeliefert. `data/initial-review.json` dokumentiert die tatsächlich durchgeführte einmalige Prüfung zugänglicher Texte; sie ist **keine abgeschlossene Volltextprüfung des gesamten Bestands**. Die dokumentierten Bewertungen werden beim Sammeln automatisch wiederhergestellt, sofern keine neuere Prüfung oder öffentliche Korrektur vorliegt. Nicht auflösbare Quellen, Paywalls und Sperren sind ausdrücklich unbestätigt.

Dubletten werden anhand Titel und Quelle zusammengeführt; eigenständige Berichte verschiedener Medien bleiben getrennt. Google-Suchen erfassen kein vollständiges historisches Archiv. Der Nachrichtenbestand wird nicht künstlich auf die neuesten 5.000 Datensätze begrenzt.

## RSS-Dateien und JSON-Schnittstelle

RSS 2.0 ist ein offenes XML-Format, unabhängig von PHP oder einer bestimmten Plattform. Die App liefert:

- `feeds/all.rss`: alle Regionen.
- `feeds/NW.rss`: Nordrhein-Westfalen.
- `feeds/DE-HE-NW.rss`: Bundesweit, Hessen und Nordrhein-Westfalen (ODER).
- Je Feed höchstens die neuesten 100 Beiträge, stabile GUIDs, Datumsangaben, Kategorien, Kurzüberblicke und Quellenlinks.

Die Dateien für alle Einzelregionen und den Gesamtbestand werden zusätzlich physisch unter `public/feeds/` exportiert. Kombinierte `.rss`-Adressen werden vom Webserver aus der aktuellen Datenbank als RSS-Datei ausgeliefert. RSS-Programme können diese URLs direkt abonnieren. Das ist kein proprietäres Feedformat. Aufruf und Auswahl im RSS-Dialog erzeugen dieselben `.rss`-Adressen.

Ohne Rewrite-Unterstützung bleibt `feed.php?regions=DE,HE,NW` als kompatibler RSS-2.0-Endpunkt verfügbar; die HTTP-Antwort trägt ebenfalls den Dateinamen `.rss`.

`api.php?regions=NW,HE&q=Pflichtfach&days=30&page=1` liefert JSON mit 20 Beiträgen pro Seite und Gesamtzahl. Regionszähler beziehen sich auf den gesamten sichtbaren Bestand, nicht auf den aktuellen Such-/Zeitfilter. Externe Webseiten können RSS oder JSON serverseitig abrufen; kein JSONP und kein pauschales CORS.

## Öffentliche Korrekturen

Jede Person kann über „Zuordnung“ Kategorien ändern. Änderungen gelten sofort in der gemeinsamen Datenbank und in den RSS-Antworten. Ein Sammellauf überschreibt sie nicht.

Bot-Schutz ohne Anmeldung: anonymer Sitzungscookie nur beim Öffnen des Formulars, beitragsgebundener Einmal-Token mit 30 Minuten Gültigkeit, zwei Sekunden Mindestwartezeit, unsichtbares Prüffeld und atomare Ratenbegrenzung in SQLite. Erlaubt sind 10 Änderungen in 15 Minuten und 60 pro UTC-Kalendertag je IP. Gemeinsam genutzte Schul-/Büronetze teilen dieses Limit. Es werden täglich wechselnde HMACs statt Klartext-IP-Adressen gespeichert.

Ein Änderungsverlauf hält alte und neue Kategorien 30 Tage vor, damit serverseitig nachvollzogen und zurückgesetzt werden kann. Das bremst einfache Bots; gezielter verteilter Missbrauch lässt sich damit nicht vollständig verhindern. Hinter einem Reverse-Proxy muss der Webserver die tatsächliche Client-IP vertrauenswürdig bereitstellen; die App übernimmt keine beliebigen Forwarding-Header.

`var/` mit konsistentem SQLite-Backup sichern (z. B. SQLite `.backup`, nicht einfach die laufende Datei ohne WAL kopieren). Volltextcache regelmäßig löschen; Sammellauf-Logs rotieren.

## Tests

```sh
php tests/run.php
python3 -m unittest discover -s tests -p 'test_*.py'
```

Tests nutzen eine isolierte In-Memory-Datenbank und Modell-Doubles. Geprüft werden RSS/Atom, Dubletten, Filter, öffentliche Korrekturen, Bot-Schutz, Importkonflikte, RSS-XML sowie die Validierung und Gegenprüfung von Modellantworten. Ein echter Modelllauf auf GitHub ist ohne verbundenes Repository und Webserver-Zugang noch nicht getestet.

## Kartendaten

SVG abgeleitet aus [deutschlandGeoJSON](https://github.com/isellsoap/deutschlandGeoJSON), `2_bundeslaender/4_niedrig.geo.json`, Repository-Lizenz [Unlicense](https://github.com/isellsoap/deutschlandGeoJSON/blob/main/LICENSE.md). Dort genannte Datenquelle: GIS-DATA / DIVA-GIS. Die Karte dient der Nachrichtenauswahl und zeigt kein Bildungsranking.

## Bereinigung des früheren Suchumfangs

Nach einem Abruf mit der exakten Suchlogik kann `php bin/prune-search.php` Altbestände entfernen, für die weder ein exakter Suchtreffer noch die Wortfolge im verfügbaren Quelltext vorliegt. Modellgenerierte Zusammenfassungen zählen dabei nicht als Beleg. Die entfernten Datensätze werden unter `var/backups/` protokolliert; vorher ein konsistentes SQLite-Backup anlegen. Ein nicht zugänglicher Volltext bedeutet nicht, dass die Wortfolge sicher fehlt. Solche Altbestände werden ohne Nachweis entfernt und können bei einem späteren exakten Suchtreffer wieder aufgenommen werden.

## Direkte Beitragslinks

`python scripts/resolve_links.py --db var/news.sqlite` löst gespeicherte Google-News-Verweise auf und schreibt die Originaladresse direkt in SQLite. Der Volltextlauf führt diesen Schritt automatisch vor der Textextraktion aus, unabhängig von der Modellprüfung. Optionale Google-Cookies werden für die kurzlebige Serversitzung abgelehnt, damit die europäische Einwilligungsseite die Auflösung nicht unterbricht. Gespeicherte direkte Links bleiben bei späteren Abrufen erhalten; öffentliche Kategorieänderungen werden nicht berührt. Nicht auflösbare Links bleiben funktionsfähig als Google-News-Verweise bestehen und werden beim nächsten Lauf erneut versucht. Nach einem eigenständigen Auflösungslauf `php bin/export-rss.php` ausführen.

Die regionale Nachprüfung vom 4. November 2026 ist in `data/regional-review.json` dokumentiert. Sie unterscheidet verfügbaren Quelltext, Verlagsauszüge und Titel-/Quellenkontext; sie behauptet keine vollständige Volltextprüfung gesperrter Beiträge. Die Zuordnungen werden beim Sammeln wiederhergestellt und von der automatischen Modellprüfung bewahrt. Öffentliche Korrekturen und neuere Prüfungen haben Vorrang. Ein Beitrag zu Japan bleibt mangels passender Region im bestehenden Schema ungeklärt.

## Entwicklung

Änderungen werden über eigene Branches und Pull Requests eingebracht. Vor Konfliktauflösungen und einem Merge in `main` erfolgt die Durchsicht und Freigabe im Chat. Der Workflow „Prüfungen“ kontrolliert Pull Requests ohne Zugangsdaten oder Modellinstallation. Siehe `AGENTS.md` für den vereinbarten Ablauf.
