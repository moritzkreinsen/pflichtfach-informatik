# Betriebsnotizen für dieses Repository

Diese Datei beschreibt die vorbereitete GitHub-Konfiguration. Die Webseite selbst kann auf jedem statischen Webserver betrieben werden. Hier dürfen keine Zugangsdaten eingetragen werden.

## Stand und Aktivierung

Das Repository bleibt bis zur ausdrücklichen Freigabe privat. Die neuen Abläufe sind mit Repository-Variablen abgeschaltet, solange deren Wert nicht genau `true` lautet. Es werden keine Pages-Einstellungen, Sichtbarkeit oder Berechtigungen durch den Quellcode geändert.

Nach Prüfung und Merge des Feature-PRs:

1. Repository erst nach Freigabe öffentlich machen, damit Besucher Issues einreichen können. Die geplante Pages-Adresse lautet `https://moritzkreinsen.github.io/pflichtfach-informatik/`.
2. Unter Settings → Actions → General die vorgesehenen Actions erlauben und „Allow GitHub Actions to create and approve pull requests“ einschalten. Die Workflows erstellen PRs, genehmigen oder mergen sie aber nicht. Default-Token-Rechte können read-only bleiben; Schreibrechte sind auf die jeweiligen Jobs begrenzt.
3. Unter Settings → Pages als Buildquelle „GitHub Actions“ wählen; die Umgebung `github-pages` nur für `main` freigeben.
4. Unter Settings → Secrets and variables → Actions → Variables `ENABLE_PAGES`, `ENABLE_CORRECTIONS` und `ENABLE_NEWS` auf `true` setzen. Die Flags können unabhängig aktiviert werden. Es sind keine Datenbank-, SSH- oder persönlichen GitHub-Tokens als Secrets nötig.
5. Den Pages-Workflow manuell starten. Danach veröffentlicht jeder Push/Merge auf `main` die frisch erzeugte Ausgabe. Die üblichen PR-Prüfungen benötigen diese Flags nicht.
6. Den Korrekturweg einmal mit einem echten Artikel testen: Vorschlag absenden, PR prüfen, mergen, aktualisierte Webseite und RSS prüfen. Anschließend den Wochenlauf manuell erproben. Diese End-to-End-Läufe sind vor der Aktivierung noch nicht durchgeführt.
7. „Automatically delete head branches“ im Repository einschalten. Falls die Einstellung mit dem vorhandenen Projektzugang nicht bearbeitbar ist, gemergte Branches manuell entfernen. Lokal `git fetch --prune origin`, `main` per Fast-forward aktualisieren und ausschließlich gemergte Arbeitsbranches löschen.

## Tokens

GitHub stellt jedem Actions-Job automatisch einen kurzlebigen `GITHUB_TOKEN` bereit. Er wird hier für Daten-PRs und Pages verwendet und erscheint nicht in der Website. Der persönliche Token im lokalen Schlüsselbund dient nur der Entwicklungsarbeit und wird nicht in Actions übernommen.

Mit `GITHUB_TOKEN` erzeugte PRs können nach GitHubs Regeln eine zusätzliche Freigabe für ihre PR-Workflows benötigen. Deshalb laufen Datenvalidierung, Python-Tests und statischer Build bereits vor der PR-Erstellung im auslösenden Workflow. Die Durchsicht und der Merge bleiben manuell. Referenz: [GitHub-Token](https://docs.github.com/en/actions/concepts/security/github_token).

## Korrekturen

Die Website erzeugt eine Issue-URL mit JSON: Version, Artikel-ID, bisherigen und gewünschten Regionen. Die Person meldet sich auf GitHub an und bestätigt „Issue erstellen“. GitHub-Anmeldung wird nicht eingebettet.

Der Workflow `category-proposal.yml` reagiert auf neu eröffnete Issues und führt ausschließlich Code aus `main` aus. Issue-Inhalte werden als begrenzte JSON-Daten gelesen, niemals als Shellcode, Dateiname oder Workflow-Anweisung verwendet. Unbekannte Artikel, ungültige Länder, doppelte Felder, unveränderte Vorschläge und veraltete Ausgangsstände erzeugen keinen PR. Änderungen an einem bestehenden Issue lösen keine nachträgliche Änderung eines bereits geprüften PRs aus; nötigenfalls einen neuen Vorschlag eröffnen.

Ein gültiger Vorschlag ändert ausschließlich `data/category-overrides.json` auf `data/correction-<Issue-Nummer>`. Der PR verweist mit `Closes #...` auf das Issue; der Merge schließt es. Sammellauf und Modell dürfen diese manuell bestätigten Zuordnungen nicht überschreiben.

## Wöchentlicher Nachrichtenlauf

`weekly-news.yml` ist für Montag 04:17 UTC vorbereitet (05:17 MEZ / 06:17 MESZ). Der Runner importiert `data/articles.json` in eine temporäre SQLite-Datenbank, sammelt exakte Suchtreffer, löst Quellenlinks auf und lädt zugängliche Volltexte. Ein offenes Modell läuft ausschließlich auf dem temporären Runner. Standardmäßig werden maximal 40 Volltexte geprüft; nach zwei Stunden beginnt keine weitere Modellprüfung. Kein Modell wird auf dem lokalen Rechner installiert.

Der Export verwendet eine feste Feldliste. Besucherhistorie, Sitzungsschlüssel, IP-Daten und Volltexte gelangen weder in den Daten-PR noch ins Pages-Artefakt. Der neue Nachrichtenbestand wird als PR auf `data/news-<Lauf-ID>` vorgeschlagen. **Er erscheint erst nach manueller Prüfung und Merge**, entsprechend der Projektregel für `main`. Der Modelllauf kann Kosten für Runner-Minuten verursachen und ist keine redaktionelle Garantie. GitHub-Zeitpläne sind nicht minutengenau garantiert.

Ein fehlgeschlagener Modell- oder Exportlauf ersetzt den veröffentlichten Datenbestand nicht. Einzelne nicht erreichbare Nachrichtenquellen können übersprungen werden. Noch offene Daten-PRs vor dem nächsten Lauf prüfen; es werden keine Konflikte automatisch aufgelöst und keine Branches mit Force-Push überschrieben.

## Daten und Ausgabe

`data/articles.json` wurde einmalig aus dem vorhandenen lokalen Bestand mit `python3 scripts/news_data.py export` erzeugt. Das ist ein Metadatenexport, keine versionierte SQLite-Datei. Die lokale PHP-Datenbank wird durch den statischen Build nicht verändert.

`data/initial-review.json` und `data/regional-review.json` dokumentieren frühere Zuordnungsprüfungen. Nicht alle Quellen waren vollständig zugänglich; die Daten behaupten keine abgeschlossene Volltextprüfung des gesamten Bestands. `data/source-links.json` ergänzt verifizierte Originaladressen.

Der Build übernimmt nur HTML, drei bekannte Assetdateien, den sichtbaren JSON-Bestand und die erzeugten RSS-Dateien. Nur `dist/` veröffentlichen. Zusätzliche RSS-Kombinationen in `site.json` eintragen und neu bauen; beliebige Laufzeitkombinationen benötigen einen dynamischen Server. In der statischen Variante sind alle Einzelregionen und Paare ohne zusätzliche Konfiguration verfügbar.

Die optionale PHP-Installation ist separat in [php-hosting.md](php-hosting.md) beschrieben. SSH-Synchronisation und direkte öffentliche SQLite-Änderungen gehören zu dieser alten Betriebsart und sind für Pages nicht erforderlich.
