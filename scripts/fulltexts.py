"""Fetch publicly accessible article bodies; never bypass paywalls or robots rules."""
import concurrent.futures, hashlib, ipaddress, json, re, socket, sqlite3, sys, time
from pathlib import Path
from urllib.parse import urlparse, urljoin
from urllib.request import Request, build_opener, HTTPRedirectHandler, HTTPSHandler
from urllib.robotparser import RobotFileParser
import ssl, certifi, trafilatura
from resolve_links import run as resolve_links
ROOT=Path(__file__).resolve().parents[1]
CACHE=ROOT/'data/cache'; CACHE.mkdir(parents=True,exist_ok=True)
USER_AGENT='PflichtfachInformatikNews/1.0'

def public_url(url):
    u=urlparse(url)
    if u.scheme not in ('https','http') or not u.hostname or u.username or u.password: raise ValueError('Unsafe URL')
    ips=socket.getaddrinfo(u.hostname,u.port or (443 if u.scheme=='https' else 80))
    if not ips or any(not ipaddress.ip_address(i[4][0]).is_global for i in ips): raise ValueError('Non-public address')
    return url
class SafeRedirect(HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):
        public_url(newurl)
        return super().redirect_request(req,fp,code,msg,headers,newurl)
def get(url,limit=3000000):
    public_url(url)
    opener=build_opener(SafeRedirect(),HTTPSHandler(context=ssl.create_default_context(cafile=certifi.where())))
    with opener.open(Request(url,headers={'User-Agent':USER_AGENT}),timeout=20) as r:
        raw=r.read(limit+1)
        if len(raw)>limit: raise ValueError('Document too large')
        return raw,r.url

def extract(url):
    u=urlparse(url)
    try:
        robots,_=get(f'{u.scheme}://{u.netloc}/robots.txt',300000)
        rp=RobotFileParser();rp.parse(robots.decode('utf-8','replace').splitlines())
        if not rp.can_fetch(USER_AGENT,url): return {'status':'robots_blocked','url':url,'text':''}
    except Exception as exc:
        # Explicitly forbidden or unreachable robots policy: do not crawl.
        if getattr(exc,'code',None) not in (404,410): return {'status':'robots_unavailable','url':url,'text':''}
    raw,final=get(url)
    html=raw.decode('utf-8','replace')
    if re.search(r'"isAccessibleForFree"\s*:\s*(?:false|"false")',html,re.I): return {'status':'paywall','url':final,'text':''}
    text=trafilatura.extract(raw,include_comments=False,include_tables=True,favor_precision=True) or ''
    if len(text)<600 or not re.search(r'informatik|digitale grundbildung',text,re.I): return {'status':'insufficient_text','url':final,'text':''}
    if len(text)>45000: return {'status':'text_too_long','url':final,'text':''}
    return {'status':'text_available','url':final,'text':text,'sha256':hashlib.sha256(text.encode()).hexdigest(),'fetched_at':int(time.time())}

def run(db_path,limit=0):
    resolve_links(db_path,limit)
    db=sqlite3.connect(db_path);db.row_factory=sqlite3.Row
    rows=[dict(r) for r in db.execute('SELECT * FROM articles ORDER BY published DESC')]
    links=json.loads((ROOT/'data/source-links.json').read_text()) if (ROOT/'data/source-links.json').exists() else {}
    for r in rows:
        if r['id'] in links:r['url']=links[r['id']]
    pending=[]
    for r in rows:
        path=CACHE/(r['id']+'.json')
        if not path.exists():
            pending.append(r);continue
        cached=json.loads(path.read_text())
        changed=cached.get('url')!=r['url']
        retry=('--retry' in sys.argv or time.time()-path.stat().st_mtime>6*86400) and not cached.get('text')
        if changed or retry:pending.append(r)
    if limit:pending=pending[:limit]
    for start in range(0,len(pending),10):
        batch=pending[start:start+10]
        for r in batch:
            if urlparse(r['url']).hostname=='news.google.com':r['resolve_error']='Source unresolved'
        def fetch(r):
            try:
                result=({'status':'source_unresolved','text':'','url':r['url']} if r.get('resolve_error') else extract(r.get('resolved_url',r['url'])))
            except Exception as e:result={'status':'fetch_failed','url':r.get('resolved_url',r['url']),'text':'','error':str(e)[:200]}
            result.update(id=r['id'],title=r['title'],source=r['source'])
            (CACHE/(r['id']+'.json')).write_text(json.dumps(result,ensure_ascii=False))
            return result['status']
        with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool: statuses=list(pool.map(fetch,batch))
        print(f'Fetched {start+len(batch)}/{len(pending)}: '+', '.join(statuses),flush=True)
if __name__=='__main__':run(sys.argv[1] if len(sys.argv)>1 else ROOT/'var/news.sqlite',int(sys.argv[2]) if len(sys.argv)>2 else 0)
