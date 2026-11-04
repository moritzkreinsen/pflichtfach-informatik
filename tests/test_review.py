import sys, unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'))
from review import validate,review_article
class ReviewTests(unittest.TestCase):
    text='Berlin führt Informatik ein. Niedersachsen wird nur als Vergleich genannt.'
    def result(self,regions=None):return {'relevant':True,'complete_article':True,'regions':regions or ['BE'],'reason':'Berlin steht im Fokus; Niedersachsen ist nur ein Vergleich.','evidence':'Berlin führt Informatik ein.','summary':'Berlin plant die Einführung des Schulfachs Informatik.'}
    def test_evidence(self):
        result=self.result();result['evidence']='Das steht nicht im Text'
        with self.assertRaises(ValueError):validate(result,self.text)
    def test_mixed_federal(self):
        with self.assertRaises(ValueError):validate(self.result(['DE','BE']),self.text)
    def test_agreement(self):
        r=review_article({'title':'Informatik in Berlin','source':'Test'},{'text':self.text,'url':'https://example.org'},lambda _:self.result())
        self.assertEqual(r['status'],'verified')
    def test_disagreement(self):
        results=iter([self.result(),self.result(['NI'])])
        r=review_article({'title':'Test','source':'Test'},{'text':self.text,'url':'https://example.org'},lambda _:next(results))
        self.assertEqual(r['status'],'needs_review')
    def test_irrelevant_without_region(self):
        result=self.result(['UNK']);result['relevant']=False
        r=review_article({'title':'Test','source':'Test'},{'text':self.text,'url':'https://example.org'},lambda _:result)
        self.assertEqual(r['status'],'verified')
        self.assertFalse(r['relevant'])
    def test_partial_not_verified(self):
        result=self.result();result['complete_article']=False
        r=review_article({'title':'Test','source':'Test'},{'text':self.text,'url':'https://example.org'},lambda _:result)
        self.assertEqual(r['status'],'needs_review')
if __name__=='__main__':unittest.main()
