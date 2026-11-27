# Betriebsnotizen für dieses Repository

Diese Datei beschreibt die vorbereitete GitHub-Konfiguration. Die Webseite selbst kann auf jedem statischen Webserver betrieben werden. Hier dürfen keine Zugangsdaten eingetragen werden.

## Stand und Aktivierung

Stand 27. November 2026: Das Repository ist öffentlich. Die Seite ist unter https://moritzkreinsen.github.io/pflichtfach-informatik/ veröffentlicht; der erste Pages-Lauf war erfolgreich. HTML, JSON und RSS wurden über HTTPS geprüft. `ENABLE_PAGES`, `ENABLE_NEWS` und `ENABLE_CORRECTIONS` stehen auf `true`. Der erste Sammellauf wurde zum Funktionstest mit drei Modellprüfungen gestartet; der reguläre Wochenlauf bleibt bei maximal 40.

Pages verwendet GitHub Actions als Buildquelle. Änderungen an `main` lösen eine Veröffentlichung aus. Die Standardrechte des Workflow-Tokens bleiben lesend. Die GitHub-Einstellung zur Erstellung und Genehmigung von Pull Requests wurde mit ausdrücklicher Freigabe aktiviert; unsere Workflows erstellen ausschließlich Vorschläge und genehmigen oder mergen nichts.

Die Actions-Freigabe ist auf eigene Actions und diese ausdrücklich ausgewählten Bausteine beschränkt:

- `actions/checkout@v4`, `actions/setup-python@v5`, `actions/setup-node@v4`
- `actions/cache@v4`
- `actions/upload-pages-artifact@v3`, `actions/deploy-pages@v4`
- `actions/upload-artifact@v4`, `actions/download-artifact@v4`

Die Datenverarbeitung und Tests laufen mit Leserechten, ohne gespeicherte Git-Zugangsdaten im Checkout. Nur separate, kurze Jobs zur PR-Erstellung erhalten `contents: write` und `pull-requests: write`. Sie führen weder Paketinstallationen von Drittanbietern noch Quellenabrufe oder Modelle aus. Zwischen den Jobs werden ausschließlich geprüfte Artikelmetadaten übergeben; Volltexte, SQLite und Besucherinformationen sind keine Artefakte. Der Pages-Deployment-Job erhält nur `pages: write` und `id-token: write`.

Zur Deaktivierung eines Ablaufs dessen ENABLE-Variable auf `false` setzen. Wiederkehrende Änderungen am Nachrichtenbestand werden weiterhin als PR geprüft; erst der manuelle Merge veröffentlicht sie. Es sind keine Datenbank-, SSH- oder persönlichen GitHub-Tokens als Secrets nötig.

## Tokens

GitHub stellt jedem Actions-Job automatisch einen kurzlebigen `GITHUB_TOKEN` bereit. Er wird hier für Daten-PRs und Pages verwendet und erscheint nicht in der Website. Der persönliche Token im lokalen Schlüsselbund dient nur der Entwicklungsarbeit und wird nicht in Actions übernommen.

Mit `GITHUB_TOKEN` erzeugte PRs können nach GitHubs Regeln eine zusätzliche Freigabe für ihre PR-Workflows benötigen. Deshalb laufen Datenvalidierung, Python-Tests und statischer Build bereits vor der PR-Erstellung im auslösenden Workflow. Die Durchsicht und der Merge bleiben manuell. Referenz: [GitHub-Token](https://docs.github.com/en/actions/concepts/security/github_token).

## Korrekturen

Die Website erzeugt eine Issue-URL mit JSON: Version, Artikel-ID, bisherigen und gewünschten Regionen. Die Person meldet sich auf GitHub an und bestätigt „Issue erstellen“. GitHub-Anmeldung wird nicht eingebettet.

Der Workflow `category-proposal.yml` reagiert auf neu eröffnete Issues und führt ausschließlich Code aus `main` aus. Issue-Inhalte werden als begrenzte JSON-Daten gelesen, niemals als Shellcode, Dateiname oder Workflow-Anweisung verwendet. Unbekannte Artikel, ungültige Länder, doppelte Felder, unveränderte Vorschläge und veraltete Ausgangsstände erzeugen keinen PR. Änderungen an einem bestehenden Issue lösen keine nachträgliche Änderung eines bereits geprüften PRs aus; nötigenfalls einen neuen Vorschlag eröffnen.

Ein gültiger Vorschlag ändert ausschließlich `data/category-overrides.json` auf `data/correction-<Issue-Nummer>`. Der PR verweist mit `Closes #...` auf das Issue; der Merge schließt es. Sammellauf und Modell dürfen diese manuell bestätigten Zuordnungen nicht überschreiben.

## Wöchentlicher Nachrichtenlauf

`weekly-news.yml` ist für Montag 04:17 UTC vorbereitet (05:17 MEZ / 06:17 MESZ). Der Runner importiert `data/articles.json` in eine temporäre SQLite-Datenbank, sammelt Suchtreffer nach der dokumentierten Suchstrategie, löst Quellenlinks auf und lädt zugängliche Volltexte und Originalteaser. `summary_source` hält die Herkunft des Teasers fest; die Modellprüfung verändert ihn nicht. Ein offenes Modell läuft ausschließlich auf dem temporären Runner. Standardmäßig werden maximal 40 Volltexte geprüft; nach zwei Stunden beginnt keine weitere Modellprüfung. Kein Modell wird auf dem lokalen Rechner installiert.

Der Export verwendet eine feste Feldliste. Besucherhistorie, Sitzungsschlüssel, IP-Daten und Volltexte gelangen weder in den Daten-PR noch ins Pages-Artefakt. Der neue Nachrichtenbestand wird als PR auf `data/news-<Lauf-ID>` vorgeschlagen. **Er erscheint erst nach manueller Prüfung und Merge**, entsprechend der Projektregel für `main`. Der Modelllauf kann Kosten für Runner-Minuten verursachen und ist keine redaktionelle Garantie. GitHub-Zeitpläne sind nicht minutengenau garantiert.

Ein fehlgeschlagener Modell- oder Exportlauf ersetzt den veröffentlichten Datenbestand nicht. Einzelne nicht erreichbare Nachrichtenquellen können übersprungen werden. Noch offene Daten-PRs vor dem nächsten Lauf prüfen; es werden keine Konflikte automatisch aufgelöst und keine Branches mit Force-Push überschrieben.

## Daten und Ausgabe

`data/articles.json` wurde einmalig aus dem vorhandenen lokalen Bestand mit `python3 scripts/news_data.py export` erzeugt. Das ist ein Metadatenexport, keine versionierte SQLite-Datei. Die lokale PHP-Datenbank wird durch den statischen Build nicht verändert.

`data/initial-review.json` und `data/regional-review.json` dokumentieren frühere Zuordnungsprüfungen. Nicht alle Quellen waren vollständig zugänglich; die Daten behaupten keine abgeschlossene Volltextprüfung des gesamten Bestands. `data/source-links.json` ergänzt verifizierte Originaladressen.

Der Build übernimmt nur HTML, drei bekannte Assetdateien, den sichtbaren JSON-Bestand und die erzeugten RSS-Dateien. Nur `dist/` veröffentlichen. Zusätzliche RSS-Kombinationen in `site.json` eintragen und neu bauen; beliebige Laufzeitkombinationen benötigen einen dynamischen Server. In der statischen Variante sind alle Einzelregionen und Paare ohne zusätzliche Konfiguration verfügbar.

Die optionale PHP-Installation ist separat in [php-hosting.md](php-hosting.md) beschrieben. SSH-Synchronisation und direkte öffentliche SQLite-Änderungen gehören zu dieser alten Betriebsart und sind für Pages nicht erforderlich.
