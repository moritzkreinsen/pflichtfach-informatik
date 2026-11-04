<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
function fetch_feed(string $url): string {
    if (!str_starts_with($url,'https://')) throw new RuntimeException('Nur HTTPS-Feeds sind erlaubt.');
    $ch=curl_init($url); $body='';
    curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_USERAGENT=>'PflichtfachInformatikNews/1.0 RSS reader',CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>function($ch,$chunk) use (&$body) { if (strlen($body)+strlen($chunk)>3000000) return 0; $body.=$chunk; return strlen($chunk); }]);
    $ok=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    if ($ok===false || $status!==200) throw new RuntimeException('Feed-Abruf fehlgeschlagen (HTTP '.$status.'): '.curl_error($ch));
    return $body;
}
function import_feed(string $xml, bool $exactSearch=false): int {
    if (stripos($xml,'<!DOCTYPE')!==false) throw new RuntimeException('DOCTYPE ist nicht erlaubt.');
    libxml_use_internal_errors(true);
    $feed=simplexml_load_string($xml,SimpleXMLElement::class,LIBXML_NONET|LIBXML_NOCDATA);
    if (!$feed) throw new RuntimeException('Ungültiger XML-Feed');
    $rss=isset($feed->channel);
    if (!$rss && $feed->getName()!=='feed') throw new RuntimeException('Kein RSS- oder Atom-Feed');
    $entries=$rss ? $feed->channel->item : $feed->children('http://www.w3.org/2005/Atom')->entry;
    $count=0;
    foreach ($entries as $item) {
        $title=plain((string)$item->title); $url=(string)$item->link;
        if (!$rss) foreach ($item->link as $link) if (!isset($link->attributes()['rel']) || (string)$link->attributes()['rel']==='alternate') { $url=(string)$link->attributes()['href']; break; }
        if (!$title || !valid_url($url)) continue;
        $description=plain((string)($rss ? $item->description : ($item->summary ?: $item->content)));
        $content=plain((string)($rss ? $item->children('http://purl.org/rss/1.0/modules/content/')->encoded : $item->content));
        $evidence=(matches_phrase($title) || matches_phrase($description) || matches_phrase($content)) ? 'source_text' : ($exactSearch ? 'google_exact_query' : '');
        if (!$evidence) continue;
        $source=plain((string)($rss ? $item->source : $item->author->name));
        if (!$source) $source=plain((string)($rss ? $feed->channel->title : $feed->title));
        if (!$source) $source=(string)parse_url($url,PHP_URL_HOST);
        if (str_ends_with($title,' - '.$source)) $title=substr($title,0,-strlen(' - '.$source));
        // Google News descriptions usually repeat the title: never present that as an independent summary.
        $clean=trim(str_replace([$title,$source], '', $description), ' -–|');
        $summary=mb_strlen($clean)>45 ? mb_substr($clean,0,320).(mb_strlen($clean)>320?' …':'') : 'Kurzüberblick: '.$title.'. Details und Einordnung im verlinkten Originalbeitrag.';
        $date=strtotime((string)($rss ? $item->pubDate : ($item->published ?: $item->updated)));
        if (!$date || $date>time()+86400) continue;
        [$assigned,$reason]=classify($title,$description,$source);
        $id=hash('sha256',mb_strtolower($title).'|'.mb_strtolower($source));
        $s=db()->prepare('INSERT INTO articles (id,title,url,source,summary,published,collected,regions,reason,match_evidence) VALUES (?,?,?,?,?,?,?,?,?,?) ON CONFLICT(id) DO UPDATE SET match_evidence=excluded.match_evidence, excluded=0, url=CASE WHEN articles.url NOT LIKE \'https://news.google.com/%\' AND excluded.url LIKE \'https://news.google.com/%\' THEN articles.url WHEN articles.review=\'{}\' THEN excluded.url ELSE articles.url END, summary=CASE WHEN articles.review=\'{}\' THEN excluded.summary ELSE articles.summary END, regions=CASE WHEN articles.manual=1 OR articles.review!=\'{}\' THEN articles.regions ELSE excluded.regions END, reason=CASE WHEN articles.manual=1 OR articles.review!=\'{}\' THEN articles.reason ELSE excluded.reason END');
        $s->execute([$id,$title,$url,$source,$summary,$date,time(),json_encode($assigned),$reason,$evidence]); $count++;
    }
    return $count;
}
function import_wordpress(string $json): int {
    $posts=json_decode($json,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($posts) || !array_is_list($posts)) throw new RuntimeException('Ungültige Artikelschnittstelle');
    $count=0;
    foreach ($posts as $post) {
        $title=plain((string)($post['title']['rendered']??''));
        $body=plain((string)($post['content']['rendered']??''));
        if (!(matches_phrase($title) || matches_phrase($body) || matches_phrase($post['excerpt']['rendered']??'')) || !empty($post['content']['protected'])) continue;
        $url=(string)($post['link']??'');
        $date=strtotime(($post['date_gmt']??'').' UTC');
        if (!$date || $date>time() || !valid_url($url)) continue;
        $description=plain((string)($post['excerpt']['rendered']??''));
        $xml='<rss xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><title>News4teachers</title><item><title>'.h($title).'</title><link>'.h($url).'</link><description>'.h($description).'</description><content:encoded>'.h($body).'</content:encoded><pubDate>'.gmdate(DATE_RSS,$date).'</pubDate></item></channel></rss>';
        $count+=import_feed($xml);
        // The public WordPress API explicitly supplies the article body. Store it only
        // in the ignored job cache, never in the website or a public repository.
        $body=plain((string)($post['content']['rendered']??''));
        if (mb_strlen($body)>=600 && mb_strlen($body)<=45000) {
            $id=hash('sha256',mb_strtolower($title).'|news4teachers');
            $dir=dirname(__DIR__).'/data/cache';if (!is_dir($dir)) mkdir($dir,0755,true);
            file_put_contents($dir.'/'.$id.'.json',json_encode(['id'=>$id,'title'=>$title,'source'=>'News4teachers','url'=>$url,'text'=>$body,'status'=>'text_available','sha256'=>hash('sha256',$body),'fetched_at'=>time(),'origin'=>'public_wordpress_api'],JSON_UNESCAPED_UNICODE));
        }
    }
    return $count;
}
function collect(): array {
    global $config;
    $lock=fopen(dirname($config['database']).'/collect.lock','c');
    if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Ein Abruf läuft bereits.');
    try {
        $sources=json_decode(file_get_contents(__DIR__.'/sources.json'),true);
        $queries=$sources['queries'];
        foreach (array_slice(regions(),0,16) as $name) $queries[]='"Pflichtfach Informatik" "'.$name.'"';
        $feeds=array_map(fn($q)=>'https://news.google.com/rss/search?'.http_build_query(['q'=>$q,'hl'=>'de','gl'=>'DE','ceid'=>'DE:de']),$queries);
        $feeds=array_merge($feeds,array_column($sources['feeds'],'url'),$config['extra_feeds']); $count=0; $errors=[]; $success=0;
        set_meta('last_attempt',gmdate('c'));
        foreach ($feeds as $url) { try { $count+=import_feed(fetch_feed($url),str_starts_with($url,'https://news.google.com/rss/search?') && str_contains(urldecode($url),'"Pflichtfach Informatik"')); $success++; } catch (Throwable $e) { $errors[]=$url.': '.$e->getMessage(); } }
        foreach ($sources['wordpress']??[] as $url) {
            try {
                for ($page=1;$page<=50;$page++) {
                    $json=fetch_feed($url.'&page='.$page);
                    $posts=json_decode($json,true,32,JSON_THROW_ON_ERROR);
                    $count+=import_wordpress($json);
                    if (count($posts)<100) break;
                }
                $success++;
            } catch (Throwable $e) { $errors[]=$url.': '.$e->getMessage(); }
        }
        if ($success) set_meta('last_success',gmdate('c'));
        set_meta('last_errors',json_encode($errors,JSON_UNESCAPED_UNICODE));
        return ['processed'=>$count,'successful_feeds'=>$success,'errors'=>$errors];
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
