"""Build a self-contained site: only allowlisted assets and public metadata."""
import argparse, itertools, json, re, shutil
from pathlib import Path
from email.utils import formatdate
from xml.etree import ElementTree as ET
from news_data import ROOT, REGIONS, load_articles, load_overrides, public_articles, read_json, write_json

def feed_name(codes):
    return '-'.join(sorted(codes)) if codes else 'all'

def rss(items, codes, base):
    name = feed_name(codes)
    root = ET.Element('rss', {'version': '2.0', 'xmlns:atom': 'http://www.w3.org/2005/Atom'})
    channel = ET.SubElement(root, 'channel')
    for k, v in [('title', 'Pflichtfach Informatik – Nachrichten' + (' · ' + ', '.join(REGIONS[c] for c in codes) if codes else '')),
                 ('link', base), ('description', 'Nachrichten zum Pflichtfach Informatik mit Quellenlinks.'), ('language', 'de')]:
        ET.SubElement(channel, k).text = v
    ET.SubElement(channel, 'atom:link', {'href': base + '/feeds/' + name + '.rss', 'rel': 'self', 'type': 'application/rss+xml'})
    selected = [a for a in items if not codes or set(codes).intersection(a['regions'])][:100]
    for a in selected:
        item = ET.SubElement(channel, 'item')
        for k, v in [('title', a['title']), ('link', a['url']), ('description', a['summary'] + ' Quelle: ' + a['source']), ('pubDate', formatdate(a['published'], usegmt=True))]:
            ET.SubElement(item, k).text = v
        ET.SubElement(item, 'guid', {'isPermaLink': 'false'}).text = a['id']
        for c in a['regions']:
            ET.SubElement(item, 'category').text = REGIONS[c]
    return ET.tostring(root, encoding='utf-8', xml_declaration=True)

def build(out, payload=None, overrides=None, config=None):
    payload = payload if payload is not None else load_articles()
    overrides = overrides if overrides is not None else load_overrides(payload)
    config = config if config is not None else read_json(ROOT / 'site.json')
    base = config['base_url'].rstrip('/')
    if not base.startswith('https://') or not re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', config['repository']):
        raise ValueError('Ungültige Seitenkonfiguration')
    out = Path(out)
    out.mkdir(parents=True, exist_ok=True)
    # Never copy the checkout, runtime database, .env or full-text cache.
    (out / 'assets').mkdir(exist_ok=True)
    for name in ('style.css', 'app.js', 'static-data.js'):
        shutil.copyfile(ROOT / 'public/assets' / name, out / 'assets' / name)
    html = (ROOT / 'public/index.php').read_text()
    html = html.replace("<?php readfile(__DIR__.'/assets/map.svg'); ?>", (ROOT / 'public/assets/map.svg').read_text())
    html = html.replace('<script src="assets/app.js" defer>', '<script src="assets/static-data.js" defer></script><script src="assets/app.js" defer>')
    html = html.replace('Die regionale Zuordnung kann direkt am Beitrag korrigiert werden.', 'Korrekturen der regionalen Zuordnung können am Beitrag vorgeschlagen werden.')
    html = html.replace('Zuordnung korrigieren</h2>', 'Zuordnung vorschlagen</h2>')
    html = html.replace('Öffentliche Änderung ohne Anmeldung. Zum Schutz vor automatisierten Änderungen ist die Anzahl der Änderungen begrenzt.', 'Auf GitHub sind der Vorschlag und die Artikel-ID bereits ausgefüllt. Melde dich dort an und klicke auf „Issue erstellen“. Nach Prüfung wird die Änderung veröffentlicht.')
    html = html.replace('Zuordnung speichern</button>', 'Vorschlag auf GitHub öffnen ↗</button>')
    html = html.replace('Wähle beliebige Regionen. Ohne Auswahl enthält der Feed alle Nachrichten.', 'Verfügbar sind der Gesamtfeed, einzelne Regionen und Kombinationen aus zwei Regionen sowie weitere vorbereitete Kombinationen.')
    html = html.replace('Für Webseiten stehen dieselben Filter unter api.php bereit.', 'Der Nachrichtenbestand ist auch als data/news.json verfügbar.')
    html = html.replace('<input id="feed-url" readonly>', '<input id="feed-url" readonly><span id="feed-note" class="muted small" role="status"></span>')
    if '<?php' in html:
        raise ValueError('Nicht aufgelöstes PHP im statischen Export')
    (out / 'index.html').write_text(html)
    (out / '.nojekyll').write_text('')
    items = public_articles(payload, overrides)
    groups = [()] + [(c,) for c in REGIONS] + list(itertools.combinations(sorted(REGIONS), 2))
    for codes in config.get('extra_feeds', []):
        if not codes or any(c not in REGIONS for c in codes) or len(codes) != len(set(codes)):
            raise ValueError('Ungültige Feedkombination')
        groups.append(tuple(sorted(codes)))
    groups = sorted(set(groups))
    write_json(out / 'data/news.json', {'articles': items, 'regions': REGIONS, 'last_success': payload.get('last_success'),
                                       'repository': config['repository'], 'feeds': [feed_name(c) for c in groups]})
    (out / 'feeds').mkdir(exist_ok=True)
    for codes in groups:
        (out / 'feeds' / (feed_name(codes) + '.rss')).write_bytes(rss(items, codes, base))
    allowed = {'index.html', '.nojekyll', 'data/news.json', 'assets/app.js', 'assets/static-data.js', 'assets/style.css'}
    allowed.update('feeds/' + feed_name(codes) + '.rss' for codes in groups)
    for path in out.rglob('*'):
        if path.is_symlink() or (path.is_file() and path.relative_to(out).as_posix() not in allowed):
            raise ValueError('Unerwartete Datei im Ausgabeordner; bitte in ein leeres Verzeichnis bauen.')
    return len(items), len(groups)

if __name__ == '__main__':
    p = argparse.ArgumentParser()
    p.add_argument('--output', default=str(ROOT / 'dist'))
    args = p.parse_args()
    print('Artikel / RSS-Dateien:', *build(args.output))
