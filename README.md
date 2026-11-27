# Pflichtfach Informatik – Nachrichten

Die App sammelt Nachrichten, in denen die Wortfolge **„Pflichtfach Informatik“** vorkommt, und verlinkt auf die Originalbeiträge. Eine Deutschlandkarte, Länderfilter und eine Suche erschließen den Bestand. Kurze Zusammenfassungen ergänzen die Quellenlinks; RSS-Feeds lassen sich für einzelne oder mehrere Regionen abonnieren.

Die Webseite besteht aus HTML, CSS, JavaScript und JSON-Dateien. Sie benötigt weder eine Datenbank noch serverseitige Software und kann auf einem beliebigen statischen Webserver veröffentlicht werden.

## Webseite erzeugen

Voraussetzung für den Build: Python 3.10 oder neuer, ohne zusätzliche Pakete.

```sh
python3 scripts/build_static.py
python3 -m http.server 8081 --directory dist
```

Die lokale Vorschau ist unter `http://localhost:8081` erreichbar. Für die Veröffentlichung wird ausschließlich der Inhalt von `dist/` auf den Webserver kopiert. Das Ausgabeverzeichnis enthält weder Zugangsdaten noch Arbeitsdatenbank oder Volltexte.

In `site.json` stehen die spätere Basisadresse, das Repository für Korrekturvorschläge und zusätzliche RSS-Kombinationen. Auch ein Unterverzeichnis wird unterstützt. Die optionale PHP-/SQLite-Variante ist in [docs/php-hosting.md](docs/php-hosting.md) beschrieben.

## Nachrichten und Kategorien

`data/articles.json` enthält den versionierten Nachrichtenbestand. Bestätigte Kategorieänderungen stehen separat in `data/category-overrides.json` und haben bei der Ausgabe Vorrang vor automatischen Zuordnungen.

Gesucht wird nach der exakten Wortfolge „Pflichtfach Informatik“. Groß-/Kleinschreibung und unterschiedliche Leerzeichen werden vereinheitlicht; verwandte Begriffe allein reichen nicht aus. Google News dient als Suchindex, ergänzt durch RSS-/Atom-Feeds und öffentliche Artikelschnittstellen. Die Quellen stehen in `app/sources.json`. Die Sammlung erhebt keinen Anspruch auf Vollständigkeit.

Für die regionale Zuordnung zählt der Schwerpunkt des Beitrags:

- Steht ein Bundesland im Mittelpunkt, wird dieses zugeordnet.
- Werden mehrere Länder gleichwertig behandelt, werden alle zugeordnet.
- Allgemeine Debatten über Deutschland erhalten die Kategorie „Bundesweit“.
- Beiläufige Vergleiche und der Sitz eines Verlags begründen keine Länderzuordnung.

Der Sammler kann unabhängig von der Webseite ausgeführt werden. Er benötigt PHP mit SQLite; der optionale Volltextabruf und die Modellprüfung zusätzlich Python-Pakete aus `requirements.txt` und einen ausdrücklich konfigurierten Modell-Endpunkt. Die Webseite lädt oder startet selbst kein Sprachmodell. Volltexte werden nicht veröffentlicht, Paywalls und Zugriffssperren nicht umgangen.

## Zuordnung vorschlagen

Am Beitrag lassen sich die gewünschten Regionen auswählen. Anschließend öffnet sich ein vorausgefülltes GitHub-Issue. Nach einer gegebenenfalls nötigen Anmeldung muss der Vorschlag dort nur noch abgesendet werden; ein eigener Text ist nicht erforderlich.

Bei eingerichteter Vorschlagsautomation entsteht daraus ein Pull Request mit der Datenänderung. Nach Prüfung und Übernahme wird die korrigierte Zuordnung veröffentlicht. Dafür brauchen Besucher Zugriff auf das angegebene Repository. Die Webseite speichert keine GitHub-Zugangsdaten.

## RSS und JSON

RSS 2.0 ist ein offenes XML-Format. Die Ausgabe enthält dauerhaft abonnierbare Dateien:

- `feeds/all.rss`: alle Regionen.
- `feeds/NW.rss`: Nordrhein-Westfalen.
- `feeds/HE-NW.rss`: Hessen oder Nordrhein-Westfalen.
- `feeds/DE-HE-NW.rss`: eine zusätzlich konfigurierte Kombination.

Alle Einzelregionen und Zweierkombinationen werden beim Build erzeugt. Weitere Kombinationen stehen unter `extra_feeds` in `site.json`. Die Reihenfolge der Kürzel im Dateinamen ist alphabetisch. Jeder Feed enthält höchstens die 100 neuesten passenden Beiträge. Beliebige URL-Parameter erzeugen auf einem statischen Webserver keine zusätzlichen Feeds.

`data/news.json` enthält den gesamten sichtbaren Nachrichtenbestand mit Kategorien, Quellenlinks und Zusammenfassungen für eigene Auswertungen. Suche und Filter der Webseite arbeiten auf dieser Datei im Browser.

## Tests

```sh
php tests/run.php
python3 -m unittest discover -s tests -p 'test_*.py'
node tests/test_static_frontend.cjs
python3 scripts/build_static.py
```

Die Tests prüfen unter anderem Quellenverarbeitung, Korrekturvorschläge, unveränderte und veraltete Zuordnungen, RSS-Ausgabe, Datenauswahl für die Veröffentlichung und die vorausgefüllten Issue-Links. Modellantworten werden mit Test-Doubles geprüft.

## Kartendaten

SVG abgeleitet aus [deutschlandGeoJSON](https://github.com/isellsoap/deutschlandGeoJSON), `2_bundeslaender/4_niedrig.geo.json`, Repository-Lizenz [Unlicense](https://github.com/isellsoap/deutschlandGeoJSON/blob/main/LICENSE.md). Dort genannte Datenquelle: GIS-DATA / DIVA-GIS. Die Karte dient als Länderfilter; die Zahlen je Region zeigen die Anzahl der zugeordneten Beiträge.
