"""Create a reviewable data PR using the ephemeral Actions token."""
import argparse, json, os, re, subprocess, urllib.request, urllib.error

def run(*args):
    return subprocess.run(args, check=True, capture_output=True, text=True).stdout.strip()

def main():
    p = argparse.ArgumentParser()
    p.add_argument('kind', choices=['correction', 'news'])
    args = p.parse_args()
    repository = os.environ['GITHUB_REPOSITORY']
    if not re.fullmatch(r'[\w.-]+/[\w.-]+', repository):
        raise ValueError('Ungültiges Repository')
    token = os.environ['GH_TOKEN']
    def api(path, data=None):
        req = urllib.request.Request('https://api.github.com/repos/' + repository + path,
              data=json.dumps(data).encode() if data is not None else None,
              headers={'Authorization': 'Bearer ' + token, 'Accept': 'application/vnd.github+json', 'X-GitHub-Api-Version': '2022-11-28'})
        try:
            with urllib.request.urlopen(req, timeout=30) as response: return json.load(response)
        except urllib.error.HTTPError as exc:
            raise RuntimeError(f'GitHub API: HTTP {exc.code}. Workflow-Berechtigungen prüfen.') from None
    if args.kind == 'correction':
        number = os.environ['CORRECTION_ISSUE']
        if not re.fullmatch('[1-9][0-9]*', number): raise ValueError('Ungültige Issue-Nummer')
        branch = 'data/correction-' + number
        files = ['data/category-overrides.json']
        title = 'Regionale Zuordnung korrigieren (#' + number + ')'
        body = 'Validierter Kategorie-Vorschlag. Artikel-ID, Ausgangsstand und Länderkennungen wurden geprüft. Nur die Zuordnungsdatei wird geändert.\n\nCloses #' + number + '\n\nValidierung: Python-Tests und statischer Build im auslösenden Workflow. Wartet auf manuelle Prüfung und Merge.'
    else:
        number = os.environ['GITHUB_RUN_ID']
        if not re.fullmatch('[1-9][0-9]*', number): raise ValueError('Ungültige Lauf-ID')
        branch = 'data/news-' + number
        files = ['data/articles.json']
        title = 'Nachrichtenbestand aktualisieren'
        body = 'Neue Suchtreffer und verfügbare Modellprüfungen aus dem Sammellauf. Manuell bestätigte Kategorien bleiben in der separaten Zuordnungsdatei erhalten. Nicht zugängliche Volltexte werden nicht als geprüft behandelt.\n\nValidierung: Python-Tests und statischer Build. Wartet auf manuelle Prüfung und Merge.'
    existing = api('/pulls?state=all&head=' + repository.split('/')[0] + ':' + branch)
    if existing:
        print('PR für diesen Vorschlag/Lauf bereits vorhanden:', existing[0]['html_url'])
        return
    run('git', 'add', '--', *files)
    changed = run('git', 'diff', '--cached', '--name-only').splitlines()
    if not changed:
        print('Keine Datenänderung.')
        return
    if set(changed) - set(files): raise ValueError('Unerwartete Dateiänderung')
    run('git', 'diff', '--cached', '--check')
    run('git', 'switch', '-c', branch)
    run('git', 'config', 'user.name', 'github-actions[bot]')
    run('git', 'config', 'user.email', '41898282+github-actions[bot]@users.noreply.github.com')
    run('git', 'commit', '-m', title)
    run('git', 'push', 'origin', branch)
    result = api('/pulls', {'title': title, 'body': body, 'head': branch, 'base': 'main'})
    print(result['html_url'])

if __name__ == '__main__': main()
