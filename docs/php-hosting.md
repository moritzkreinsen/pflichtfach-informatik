# Optionaler PHP-/SQLite-Betrieb

Die ursprüngliche PHP-Variante bleibt für eigenes Hosting verfügbar. Sie speichert Kategorien direkt in SQLite; das Vorschlagsverfahren der statischen Ausgabe wird dort nicht verwendet.

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


## Abruf

`php bin/collect.php` sammelt Nachrichten, `php bin/export-rss.php` exportiert RSS. Die JSON-Daten der statischen Variante werden dabei nicht automatisch mit der Datenbank synchronisiert. Beide Betriebsarten haben einen eigenen Datenbestand.
