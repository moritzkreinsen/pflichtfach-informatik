"""Resolve Google News wrappers before full-text review; keep originals on failure."""
import argparse, asyncio, ipaddress, json, socket, sqlite3
from pathlib import Path
from urllib.parse import urlparse
ROOT=Path(__file__).resolve().parents[1]

def is_google(url):
    return urlparse(url).hostname=='news.google.com'

def valid_target(url):
    try:
        u=urlparse(url)
        if u.scheme not in ('https','http') or not u.hostname or u.username or u.password:return False
        if u.hostname=='google.com' or u.hostname.endswith('.google.com'):return False
        addresses=socket.getaddrinfo(u.hostname,u.port or (443 if u.scheme=='https' else 80))
        return bool(addresses) and all(ipaddress.ip_address(a[4][0]).is_global for a in addresses)
    except (ValueError,OSError):return False

def save_target(db,article,target,validator=valid_target):
    if not isinstance(target,str) or not validator(target):return False
    # Do not overwrite a newer URL, and never touch visitor category corrections.
    changed=db.execute('UPDATE articles SET url=? WHERE id=? AND url=?',(target,article['id'],article['url'])).rowcount
    db.commit()
    return bool(changed)

async def decode_batch(urls):
    from googlenewsdecoder.decoder_async import GoogleDecoderAsync
    async with GoogleDecoderAsync(timeout=15) as decoder:
        # Reject optional cookies in this ephemeral server session. European
        # requests otherwise lead to Google's consent page instead of the article.
        decoder.client.cookies.set('SOCS','CAE',domain='.google.com',path='/')
        return await decoder.decode_google_news_urls(urls,interval=1,concurrency=3)

async def bounded_decode(urls):
    return await asyncio.wait_for(decode_batch(urls),timeout=45)

def run(db_path,limit=0):
    db=sqlite3.connect(db_path,timeout=30);db.row_factory=sqlite3.Row
    rows=[dict(r) for r in db.execute('SELECT id,url FROM articles ORDER BY published DESC') if is_google(r['url'])]
    if limit:rows=rows[:limit]
    hints=json.loads((ROOT/'data/source-links.json').read_text())
    resolved=0;pending=[]
    for a in rows:
        cache_path=ROOT/'data/cache'/(a['id']+'.json')
        cache=json.loads(cache_path.read_text()) if cache_path.exists() else {}
        target=hints.get(a['id']) or cache.get('url','')
        if target and not is_google(target) and save_target(db,a,target):resolved+=1
        else:pending.append(a)
    for offset in range(0,len(pending),10):
        batch=pending[offset:offset+10]
        try:results=asyncio.run(bounded_decode([a['url'] for a in batch]))
        except Exception as exc:
            print(f'Linkauflösung fehlgeschlagen: {type(exc).__name__}',flush=True);results=[]
        for a,result in zip(batch,results):
            if result.get('success') and save_target(db,a,result.get('decoded_url')):resolved+=1
        print(f'Links geprüft: {min(offset+10,len(pending))}/{len(pending)}, aufgelöst: {resolved}',flush=True)
    db.close()
    return {'checked':len(rows),'resolved':resolved,'remaining':len(rows)-resolved}

if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--db',default=str(ROOT/'var/news.sqlite'));p.add_argument('--limit',type=int,default=0);args=p.parse_args()
    print(json.dumps(run(args.db,args.limit)))
