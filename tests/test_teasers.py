import sqlite3, sys, tempfile, unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'))
from teasers import extract_teaser
from review import run
from news_data import public_articles

class TeaserTests(unittest.TestCase):
    def test_publisher_text_and_priority(self):
        html='<title>Informatik wird Pflichtfach in Bremen</title><meta name="description" content="Anderer Originaltext der Redaktion."><meta property="og:description" content="Die Schulen erhalten ein neues Fach &amp; weitere Lehrkräfte.">'
        self.assertEqual(extract_teaser(html,'Informatik wird Pflichtfach in Bremen'),{'summary':'Die Schulen erhalten ein neues Fach & weitere Lehrkräfte.','summary_source':'og:description'})
    def test_no_generated_fallback(self):
        self.assertEqual(extract_teaser('<title>Informatik wird Pflichtfach</title><p>Text eines Artikels.</p>','Informatik wird Pflichtfach'),{})
    def test_wrong_page(self):
        self.assertEqual(extract_teaser('<title>Startseite</title><meta name="description" content="Alle aktuellen Nachrichten aus Deutschland.">','Informatik wird Pflichtfach'),{})
    def test_legacy_summary_hidden(self):
        a={'id':'a','title':'T','url':'https://example.org','source':'S','summary':'Erfundener Text','published':1,'regions':'["DE"]','excluded':0}
        self.assertEqual(public_articles({'articles':[a]}, {})[0]['summary'],'')
    def test_model_never_changes_teaser(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);(root/'data/cache').mkdir(parents=True)
            (root/'data/cache/a.json').write_text('{"status":"text_available","text":"Artikel","url":"https://example.org"}')
            db=sqlite3.connect(root/'test.sqlite')
            db.execute('CREATE TABLE articles(id,title,source,summary,summary_source,published,manual,review,regions,reason,url,excluded)')
            db.execute("INSERT INTO articles VALUES('a','Titel','Quelle','Originalteaser','feed',1,0,'{}','[\"UNK\"]','','',0)");db.commit();db.close()
            result={'status':'verified','regions':['DE'],'reason':'Bundesweit','summary':'Nicht übernehmen'}
            with patch('review.ROOT',root),patch('review.review_article',return_value=result): run(root/'test.sqlite',1)
            db=sqlite3.connect(root/'test.sqlite')
            self.assertEqual(db.execute('SELECT summary,summary_source FROM articles').fetchone(),('Originalteaser','feed'));db.close()
