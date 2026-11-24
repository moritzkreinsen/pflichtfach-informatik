"""Versioned article metadata. No runtime secrets, visitor data or full texts."""
import json, re, sqlite3
from pathlib import Path
from urllib.parse import urlsplit
ROOT = Path(__file__).resolve().parents[1]
REGIONS = json.loads((ROOT / 'app/regions.json').read_text())
FIELDS = ('id', 'title', 'url', 'source', 'summary', 'published', 'collected',
          'regions', 'reason', 'manual', 'review', 'excluded', 'match_evidence')
REVIEW_FIELDS = ('status', 'checked_at', 'method', 'prompt_version', 'text_sha256', 'complete_article')

def read_json(path):
    return json.loads(Path(path).read_text(encoding='utf-8'))

def write_json(path, value):
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    Path(path).write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

def valid_regions(codes):
    if (not isinstance(codes, list) or not codes or len(codes) > len(REGIONS)
        or any(not isinstance(c, str) or c not in REGIONS for c in codes)
        or len(codes) != len(set(codes))
        or (len(codes) > 1 and any(c in codes for c in ('DE', 'UNK')))):
        raise ValueError('Ungültige oder widersprüchliche Regionen')
    return sorted(codes)

def load_articles(path=ROOT / 'data/articles.json'):
    payload = read_json(path)
    if payload.get('version') != 1 or not isinstance(payload.get('articles'), list):
        raise ValueError('Ungültiges Nachrichtenformat')
    ids = set()
    for a in payload['articles']:
        if set(a) != set(FIELDS) or not re.fullmatch(r'[a-f0-9]{64}', a['id']) or a['id'] in ids:
            raise ValueError('Ungültiger oder doppelter Artikel')
        ids.add(a['id'])
        u = urlsplit(a['url'])
        if u.scheme not in ('http', 'https') or not u.hostname or u.username or u.password:
            raise ValueError('Ungültiger Quellenlink')
        for key in ('title', 'source', 'summary', 'reason', 'match_evidence'):
            if not isinstance(a[key], str) or len(a[key]) > 5000:
                raise ValueError('Ungültiger Artikeltext')
        # Existing machine assessments may contain DE alongside a regional code;
        # proposals have stricter focus validation via valid_regions().
        codes = json.loads(a['regions'])
        if not isinstance(codes, list) or not codes or any(c not in REGIONS for c in codes):
            raise ValueError('Ungültige gespeicherte Regionen')
        review = json.loads(a['review'])
        if not isinstance(review, dict) or set(review) - set(REVIEW_FIELDS):
            raise ValueError('Unzulässige Bewertungsfelder')
        for key in ('published', 'collected', 'manual', 'excluded'):
            if type(a[key]) is not int or a[key] < 0:
                raise ValueError('Ungültige Zahl')
    return payload

def load_overrides(payload, path=ROOT / 'data/category-overrides.json'):
    values = read_json(path)
    ids = {a['id'] for a in payload['articles']}
    if not isinstance(values, dict) or set(values) - ids:
        raise ValueError('Korrektur für unbekannten Artikel')
    return {key: valid_regions(value) for key, value in values.items()}

def public_articles(payload, overrides):
    result = []
    for a in payload['articles']:
        if a['excluded']:
            continue
        item = {key: a[key] for key in ('id', 'title', 'url', 'source', 'summary', 'published')}
        item['regions'] = overrides.get(a['id'], json.loads(a['regions']))
        result.append(item)
    return sorted(result, key=lambda a: (-a['published'], a['id']))

def export_db(db_path, output):
    db = sqlite3.connect(f'file:{Path(db_path).resolve()}?mode=ro', uri=True)
    db.row_factory = sqlite3.Row
    rows = []
    for record in db.execute('SELECT ' + ','.join(FIELDS) + ' FROM articles ORDER BY id'):
        a = dict(record)
        review = json.loads(a['review'])
        if isinstance(review.get('method'), str):
            review['method'] = review['method'].removeprefix('assistant_')
        a['review'] = json.dumps({k: review[k] for k in REVIEW_FIELDS if k in review}, ensure_ascii=False)
        rows.append(a)
    last = db.execute("SELECT value FROM meta WHERE key='last_success'").fetchone()
    write_json(output, {'version': 1, 'articles': rows, 'last_success': last[0] if last else None})
    db.close()
    load_articles(output)

if __name__ == '__main__':
    import argparse
    p = argparse.ArgumentParser()
    p.add_argument('operation', choices=['export', 'validate'])
    p.add_argument('--db', default=str(ROOT / 'var/news.sqlite'))
    p.add_argument('--output', default=str(ROOT / 'data/articles.json'))
    args = p.parse_args()
    if args.operation == 'export':
        export_db(args.db, args.output)
    else:
        load_overrides(load_articles(args.output))
