# Pflichtfach Informatik – Nachrichten

Die App sammelt Nachrichten, in denen die Wortfolge **„Pflichtfach Informatik“** vorkommt, und verlinkt auf die Originalbeiträge. Eine Deutschlandkarte, Länderfilter und eine Suche erschließen den Bestand. Kurze Zusammenfassungen ergänzen die Quellenlinks; RSS-Feeds lassen sich für einzelne oder mehrere Regionen abonnieren.

Die Webseite benötigt PHP und SQLite, kein Frontend-Framework und keinen Build-Schritt. Kategorien können ohne Anmeldung direkt am Beitrag korrigiert werden.

## Selbst hosten

Die App kann auf einem eigenen PHP-Webserver betrieben werden. Die Artikel und öffentlichen Korrekturen werden in einer SQLite-Datenbank gespeichert. Ein rein statischer Webhost reicht dafür nicht aus.

Webseite und Nachrichtensammlung sind voneinander unabhängig: Der Sammler kann auf demselben Server oder auf einem anderen Rechner laufen. Zeitpunkt, Häufigkeit und Ausführungsumgebung legt die betreibende Person fest, etwa über einen Cronjob.

## Installation

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


## Nachrichten abrufen

Ein Sammellauf wird über die Kommandozeile gestartet:

```sh
php bin/collect.php
php bin/export-rss.php
```

Diese Befehle können mit einem beliebigen Zeitplaner regelmäßig ausgeführt werden. Die verfügbaren Quellen und Suchabfragen stehen in `app/sources.json`.

Optional lassen sich mit den Python-Skripten in `scripts/` Google-News-Links auflösen, zugängliche Volltexte abrufen und regionale Zuordnungen mit einem selbst konfigurierten Modell prüfen. Dafür werden die Abhängigkeiten aus `requirements.txt` benötigt. Die Webseite selbst benötigt weder Python noch ein Sprachmodell. Öffentliche Kategorieänderungen bleiben bei späteren Sammelläufen erhalten.

## Quellen und Zuordnung

Gesucht wird nach der exakten Wortfolge „Pflichtfach Informatik“. Groß-/Kleinschreibung und unterschiedliche Leerzeichen werden vereinheitlicht; verwandte Begriffe allein reichen nicht aus. Google News dient als Suchindex, ergänzt durch direkte RSS-/Atom-Feeds und öffentliche Artikelschnittstellen. Ein Suchtreffer kann die Wortfolge im Volltext enthalten, auch wenn sie im Anreißer nicht erscheint. Die Sammlung erhebt keinen Anspruch auf Vollständigkeit.

Für die regionale Zuordnung zählt der Schwerpunkt des Beitrags:

- Steht ein Bundesland im Mittelpunkt, wird dieses zugeordnet.
- Werden mehrere Länder gleichwertig behandelt, werden alle zugeordnet.
- Allgemeine Debatten über Deutschland erhalten die Kategorie „Bundesweit“.
- Beiläufige Vergleiche und der Sitz eines Verlags begründen keine Länderzuordnung.

Eigenständige Berichte verschiedener Medien bleiben getrennt; doppelte Einträge derselben Quelle werden zusammengeführt. Volltexte dienen nur der Verarbeitung und werden nicht auf der Webseite veröffentlicht. Paywalls und Zugriffssperren werden nicht umgangen.

## RSS-Dateien und JSON-Schnittstelle

RSS 2.0 ist ein offenes XML-Format, unabhängig von PHP oder einer bestimmten Plattform. Die App liefert:

- `feeds/all.rss`: alle Regionen.
- `feeds/NW.rss`: Nordrhein-Westfalen.
- `feeds/DE-HE-NW.rss`: Bundesweit, Hessen und Nordrhein-Westfalen (ODER).
- Je Feed höchstens die neuesten 100 Beiträge, stabile GUIDs, Datumsangaben, Kategorien, Kurzüberblicke und Quellenlinks.

Die Dateien für alle Einzelregionen und den Gesamtbestand werden zusätzlich physisch unter `public/feeds/` exportiert. Kombinierte `.rss`-Adressen werden vom Webserver aus der aktuellen Datenbank als RSS-Datei ausgeliefert. RSS-Programme können diese URLs direkt abonnieren. Aufruf und Auswahl im RSS-Dialog erzeugen dieselben `.rss`-Adressen.

Ohne Rewrite-Unterstützung bleibt `feed.php?regions=DE,HE,NW` als kompatibler RSS-2.0-Endpunkt verfügbar; die HTTP-Antwort trägt ebenfalls den Dateinamen `.rss`.

`api.php?regions=NW,HE&q=Pflichtfach&days=30&page=1` liefert JSON mit 20 Beiträgen pro Seite und Gesamtzahl. Regionszähler beziehen sich auf den gesamten sichtbaren Bestand, nicht auf den aktuellen Such-/Zeitfilter. Externe Webseiten können RSS oder JSON serverseitig abrufen; kein JSONP und kein pauschales CORS.

## Öffentliche Korrekturen

Über „Zuordnung“ kann jede Person die Kategorien eines Beitrags ändern. Die Änderungen werden sofort in der gemeinsamen Datenbank gespeichert und bei weiteren Abrufen beibehalten.

Einmal-Token, ein unsichtbares Prüffeld, eine Mindestwartezeit und eine Ratenbegrenzung erschweren automatisierten Missbrauch ohne Benutzerkonten. Ein Änderungsverlauf bewahrt alte und neue Kategorien 30 Tage lang auf. Statt Klartext-IP-Adressen werden täglich wechselnde HMACs gespeichert. Hinter einem Reverse-Proxy muss die tatsächliche Client-IP vertrauenswürdig bereitgestellt werden.

Für den Betrieb sollten konsistente SQLite-Backups angelegt und Arbeitscache sowie Logs regelmäßig bereinigt werden.

## Tests

```sh
php tests/run.php
python3 -m unittest discover -s tests -p 'test_*.py'
```

Tests nutzen eine isolierte In-Memory-Datenbank und Modell-Doubles. Geprüft werden RSS/Atom, Dubletten, Filter, öffentliche Korrekturen, Bot-Schutz, Importkonflikte, RSS-XML sowie die Validierung und Gegenprüfung von Modellantworten.

## Kartendaten

SVG abgeleitet aus [deutschlandGeoJSON](https://github.com/isellsoap/deutschlandGeoJSON), `2_bundeslaender/4_niedrig.geo.json`, Repository-Lizenz [Unlicense](https://github.com/isellsoap/deutschlandGeoJSON/blob/main/LICENSE.md). Dort genannte Datenquelle: GIS-DATA / DIVA-GIS. Die Karte dient der Nachrichtenauswahl und zeigt kein Bildungsranking.
