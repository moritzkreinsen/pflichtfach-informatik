import io, json, os, subprocess, sys, tempfile, unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]/'scripts'))
from open_data_pr import main

class DataPRTests(unittest.TestCase):
    def test_creates_only_data_branch_and_does_not_merge(self):
        with tempfile.TemporaryDirectory() as tmp:
            root=Path(tmp);remote=root/'remote.git';repo=root/'work'
            def git(*args,cwd=repo):
                return subprocess.run(['git',*args],cwd=cwd,capture_output=True,text=True,check=True).stdout.strip()
            subprocess.run(['git','init','--bare',str(remote)],capture_output=True,check=True)
            repo.mkdir();git('init','-b','main');git('config','user.name','Test');git('config','user.email','test@example.org')
            (repo/'data').mkdir();file=repo/'data/category-overrides.json';file.write_text('{}\n')
            git('add','.');git('commit','-m','Initial');git('remote','add','origin',str(remote));git('push','origin','main')
            original=git('rev-parse','main');file.write_text(json.dumps({'a'*64:['HE']})+'\n')
            responses=iter([io.BytesIO(b'[]'),io.BytesIO(b'{"html_url":"https://github.com/owner/repo/pull/1"}')])
            requests=[]
            def request(req,**kwargs):requests.append(req);return next(responses)
            previous=Path.cwd()
            try:
                os.chdir(repo)
                with patch.dict(os.environ,{'GITHUB_REPOSITORY':'owner/repo','GH_TOKEN':'test-placeholder','CORRECTION_ISSUE':'9'}),patch.object(sys,'argv',['open_data_pr.py','correction']),patch('open_data_pr.urllib.request.urlopen',side_effect=request),patch('builtins.print'):
                    main()
            finally:os.chdir(previous)
            self.assertEqual(git('rev-parse','main'),original)
            self.assertEqual(git('diff','--name-only','main','data/correction-9'),'data/category-overrides.json')
            pr=json.loads(requests[1].data)
            self.assertEqual(pr['base'],'main');self.assertEqual(pr['head'],'data/correction-9');self.assertIn('Closes #9',pr['body'])
            self.assertTrue(git('ls-remote','origin','refs/heads/data/correction-9'))

if __name__=='__main__':unittest.main()
