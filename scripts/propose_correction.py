"""Turn a strict, untrusted issue payload into a single data-file change."""
import argparse, json, os, re
from news_data import ROOT, load_articles, load_overrides, public_articles, valid_regions, read_json, write_json

def parse_proposal(body, payload, overrides):
    if not isinstance(body, str) or len(body) > 3000:
        raise ValueError('Vorschlag fehlt oder ist zu lang')
    match = re.fullmatch(r'\s*```json\s*\n(.*?)\n```\s*', body, re.S)
    if not match:
        raise ValueError('Kein Kategorie-Vorschlag im erwarteten Format')
    def unique(pairs):
        result = {}
        for key, value in pairs:
            if key in result: raise ValueError('Doppelte JSON-Schlüssel')
            result[key] = value
        return result
    proposal = json.loads(match[1], object_pairs_hook=unique)
    if not isinstance(proposal, dict) or set(proposal) != {'version', 'article_id', 'previous_regions', 'regions'} or type(proposal['version']) is not int or proposal['version'] != 1:
        raise ValueError('Ungültige Vorschlagsfelder')
    article_id = proposal['article_id']
    if not isinstance(article_id, str) or not re.fullmatch('[a-f0-9]{64}', article_id):
        raise ValueError('Ungültige Artikel-ID')
    articles = {a['id']: a for a in public_articles(payload, overrides)}
    if article_id not in articles:
        raise ValueError('Artikel nicht vorhanden')
    previous = proposal['previous_regions']
    if not isinstance(previous, list) or any(not isinstance(c, str) for c in previous):
        raise ValueError('Ungültiger Ausgangsstand')
    if sorted(previous) != sorted(articles[article_id]['regions']):
        raise ValueError('Die Zuordnung wurde inzwischen geändert. Bitte den Vorschlag im Portal erneut öffnen.')
    codes = valid_regions(proposal['regions'])
    if codes == sorted(previous):
        raise ValueError('Die Zuordnung ist unverändert')
    return article_id, codes

if __name__ == '__main__':
    p = argparse.ArgumentParser()
    p.add_argument('--event', default=os.environ.get('GITHUB_EVENT_PATH'))
    args = p.parse_args()
    event = read_json(args.event)
    issue = event['issue']
    if issue.get('pull_request') or type(issue.get('number')) is not int or issue['number'] < 1:
        raise SystemExit('Kein gültiges Issue')
    payload = load_articles()
    overrides = load_overrides(payload)
    try:
        article_id, codes = parse_proposal(issue.get('body'), payload, overrides)
    except (ValueError, TypeError, KeyError) as exc:
        print('Kein PR erstellt:', str(exc))
        # Rejection is data, never code, a file path or a shell fragment.
        if os.environ.get('GITHUB_OUTPUT'):
            with open(os.environ['GITHUB_OUTPUT'], 'a') as f: f.write('valid=false\n')
        raise SystemExit(0)
    overrides[article_id] = codes
    write_json(ROOT / 'data/category-overrides.json', dict(sorted(overrides.items())))
    if os.environ.get('GITHUB_OUTPUT'):
        with open(os.environ['GITHUB_OUTPUT'], 'a') as f: f.write(f'valid=true\nissue={issue["number"]}\n')
    print('Kategorie-Vorschlag validiert.')
