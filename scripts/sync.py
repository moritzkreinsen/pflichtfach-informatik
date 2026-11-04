"""Merge job results over SSH; no repository commits and no replacement of live SQLite."""
import argparse, os, re, shlex, subprocess, tempfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def sync(mode):
    host=os.environ.get('NEWS_SSH_HOST','');remote=os.environ.get('NEWS_REMOTE_PATH','')
    if not re.fullmatch(r'[A-Za-z0-9_.@:-]+',host) or not remote.startswith('/'):
        raise SystemExit('NEWS_SSH_HOST und absoluter NEWS_REMOTE_PATH müssen konfiguriert sein.')
    key=os.environ.get('NEWS_SSH_KEY','');known=os.environ.get('NEWS_KNOWN_HOSTS','')
    if not key or not known:raise SystemExit('SSH-Schlüssel und geprüfter Hostschlüssel fehlen.')
    with tempfile.TemporaryDirectory(prefix='news-ssh-') as folder:
        folder=Path(folder);(folder/'key').write_text(key+'\n');(folder/'key').chmod(0o600);(folder/'known_hosts').write_text(known+'\n')
        ssh=['ssh','-i',str(folder/'key'),'-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','IdentitiesOnly=yes','-o',f'UserKnownHostsFile={folder / "known_hosts"}',host]
        if mode=='fetch':
            data=subprocess.check_output(ssh+[f'cd {shlex.quote(remote)} && php bin/snapshot.php'],timeout=90)
            subprocess.run(['php','bin/import.php'],input=data,cwd=ROOT,check=True,timeout=90)
        else:
            data=subprocess.check_output(['php','bin/snapshot.php'],cwd=ROOT,timeout=90)
            subprocess.run(ssh+[f'cd {shlex.quote(remote)} && php bin/import.php'],input=data,check=True,timeout=180)
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('mode',choices=['fetch','push']);sync(p.parse_args().mode)
