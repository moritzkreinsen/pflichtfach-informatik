import sqlite3,sys,unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'))
from resolve_links import save_target,valid_target
class LinkTests(unittest.TestCase):
    def test_only_link_changes(self):
        db=sqlite3.connect(':memory:');self.addCleanup(db.close);db.execute('CREATE TABLE articles(id TEXT,url TEXT,manual INT,regions TEXT)')
        db.execute("INSERT INTO articles VALUES('1','https://news.google.com/rss/articles/example',1,'HE')")
        a={'id':'1','url':'https://news.google.com/rss/articles/example'}
        self.assertTrue(save_target(db,a,'https://publisher.example/article',lambda _:True))
        self.assertEqual(db.execute('SELECT manual,regions FROM articles').fetchone(),(1,'HE'))
        self.assertFalse(save_target(db,a,'https://publisher.example/old',lambda _:True))
    def test_reject_invalid_target(self):
        for url in ['javascript:alert(1)','https://consent.google.com/m','http://user:pass@example.org']:
            self.assertFalse(valid_target(url))
        with patch('resolve_links.socket.getaddrinfo',return_value=[(2,1,6,'',('127.0.0.1',80))]):
            self.assertFalse(valid_target('http://private.example/article'))
