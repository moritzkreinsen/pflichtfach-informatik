import copy, json, sqlite3, sys, tempfile, unittest
from pathlib import Path
from xml.etree import ElementTree as ET
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts'))
from news_data import ROOT, load_articles, load_overrides, public_articles, export_db, FIELDS
from build_static import build, rss
from propose_correction import parse_proposal

ID = 'a' * 64

def fixture():
    return {'version': 1, 'last_success': '2026-11-04T10:00:00Z', 'articles': [
        {'id': ID, 'title': 'Pflichtfach Informatik <Test>', 'url': 'https://example.org/article',
         'source': 'Quelle & Verlag', 'summary': 'Ein kurzer Überblick.', 'published': 1700000000,
         'collected': 1700000001, 'regions': '["NW"]', 'reason': 'Schwerpunkt', 'manual': 0,
         'review': '{}', 'excluded': 0, 'match_evidence': 'source_text'}]}

def proposal(**change):
    p = {'version': 1, 'article_id': ID, 'previous_regions': ['NW'], 'regions': ['HE']}
    p.update(change)
    return '```json\n' + json.dumps(p) + '\n```'

class StaticTests(unittest.TestCase):
    def test_real_collection_and_overrides(self):
        data = load_articles()
        items = public_articles(data, load_overrides(data))
        self.assertGreater(len(items), 0)
        self.assertTrue(all('review' not in a and 'collected' not in a for a in items))

    def test_proposal_and_stale_baseline(self):
        self.assertEqual(parse_proposal(proposal(), fixture(), {}), (ID, ['HE']))
        with self.assertRaises(ValueError):
            parse_proposal(proposal(), fixture(), {ID: ['BY']})
        self.assertEqual(parse_proposal(proposal(previous_regions=['BY']), fixture(), {ID: ['BY']}), (ID, ['HE']))

    def test_bad_proposals_do_not_mutate(self):
        data = fixture()
        before = copy.deepcopy(data)
        cases = [proposal(article_id='../../config.php'), proposal(regions=['$(touch /tmp/pwn)']),
                 proposal(regions=['DE', 'NW']), proposal(regions=['UNK', 'HE']), proposal(regions=[]),
                 proposal(regions=['HE', 'HE']), proposal(previous_regions=['BY']), proposal(regions=['NW']),
                 proposal(article_id='b'*64), proposal(extra='code'), proposal(version=True),
                 proposal() + '\nrun: malicious', '```json\n{"version":1,"version":2}\n```', 'x'*4000]
        for body in cases:
            with self.subTest(body=body), self.assertRaises(ValueError):
                parse_proposal(body, data, {})
        self.assertEqual(data, before)

    def test_excluded_article_cannot_be_proposed(self):
        data = fixture(); data['articles'][0]['excluded'] = 1
        with self.assertRaises(ValueError): parse_proposal(proposal(), data, {})

    def test_static_build_public_files_and_overridden_rss(self):
        with tempfile.TemporaryDirectory() as tmp:
            out = Path(tmp)
            config = {'base_url': 'https://example.org/project', 'repository': 'owner/repo', 'extra_feeds': [['DE','HE','NW']]}
            count, feeds = build(out, fixture(), {ID: ['HE']}, config)
            self.assertEqual(count, 1)
            html = (out/'index.html').read_text()
            self.assertNotIn('<?php', html)
            self.assertIn('Vorschlag auf GitHub öffnen', html)
            self.assertNotIn('Öffentliche Änderung ohne Anmeldung', html)
            self.assertFalse(list(out.rglob('*.php')))
            self.assertFalse(list(out.rglob('*.sqlite')))
            data = json.loads((out/'data/news.json').read_text())
            self.assertEqual(data['articles'][0]['regions'], ['HE'])
            self.assertEqual(set(data['articles'][0]), {'id','title','url','source','summary','published','regions'})
            self.assertTrue((out/'feeds/DE-HE-NW.rss').exists())
            self.assertEqual(len(list((out/'feeds').glob('*.rss'))), feeds)
            rss_he = ET.parse(out/'feeds/HE.rss')
            self.assertEqual(rss_he.findtext('.//item/title'), fixture()['articles'][0]['title'])
            self.assertEqual(rss_he.findtext('.//item/category'), 'Hessen')
            self.assertIsNone(ET.parse(out/'feeds/NW.rss').find('.//item'))
            atom = rss_he.find('.//{http://www.w3.org/2005/Atom}link')
            self.assertEqual(atom.attrib['href'], 'https://example.org/project/feeds/HE.rss')

    def test_build_rejects_leftover_private_file(self):
        with tempfile.TemporaryDirectory() as tmp:
            out=Path(tmp);(out/'.env').write_text('PRIVATE')
            with self.assertRaises(ValueError):
                build(out, fixture(), {}, {'base_url':'https://example.org','repository':'owner/repo'})

    def test_rss_limit_and_or_filter(self):
        rows = []
        for n in range(130):
            item = public_articles(fixture(), {})[0].copy()
            item['id'] = str(n);item['regions'] = ['NW'] if n % 2 else ['HE']
            rows.append(item)
        tree = ET.fromstring(rss(rows, ['HE','NW'], 'https://example.org'))
        self.assertEqual(len(tree.findall('.//item')), 100)

    def test_model_skips_accepted_override(self):
        from unittest.mock import patch
        import review
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp); (root/'data/cache').mkdir(parents=True)
            (root/'data/category-overrides.json').write_text(json.dumps({ID:['HE']}))
            (root/'data/cache'/f'{ID}.json').write_text(json.dumps({'status':'text_available','text':'Artikel','url':'https://example.org'}))
            path = root/'work.sqlite'; db = sqlite3.connect(path)
            db.execute('CREATE TABLE articles (id TEXT, published INTEGER, manual INTEGER, review TEXT)')
            db.execute('INSERT INTO articles VALUES (?,1,0,?)',(ID,'{}')); db.commit(); db.close()
            with patch.object(review,'ROOT',root), patch.object(review,'review_article') as model:
                result=review.run(str(path),40)
                model.assert_not_called()
                self.assertEqual(result['attempted'],0)

    def test_export_never_copies_session_or_full_text(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp)/'source.sqlite';db = sqlite3.connect(path)
            db.execute('CREATE TABLE articles (' + ','.join(k + (' INTEGER' if k in ('published','collected','manual','excluded') else ' TEXT') for k in FIELDS) + ')')
            a = fixture()['articles'][0];a['review'] = json.dumps({'status':'verified','text':'PRIVATE FULL TEXT','evidence':'quote'})
            db.execute('INSERT INTO articles VALUES (' + ','.join('?' for _ in FIELDS) + ')', [a[k] for k in FIELDS])
            db.execute('CREATE TABLE meta (key TEXT, value TEXT)')
            db.execute("INSERT INTO meta VALUES ('session_secret','PRIVATE SESSION')")
            db.commit();db.close()
            out = Path(tmp)/'news.json';export_db(path, out)
            self.assertNotIn('PRIVATE', out.read_text())
            self.assertEqual(json.loads(load_articles(out)['articles'][0]['review']), {'status':'verified'})

if __name__ == '__main__': unittest.main()
