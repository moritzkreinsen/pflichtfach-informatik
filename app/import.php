<?php
require_once __DIR__.'/bootstrap.php';
function import_snapshot(array $payload): int {
    if (($payload['version']??null)!==1 || !isset($payload['articles']) || !is_array($payload['articles']) || count($payload['articles'])>50000) throw new InvalidArgumentException('Ungültiger Import');
    $db=db();$db->beginTransaction();$count=0;
    try {
        foreach ($payload['articles'] as $a) {
            foreach (['id','title','url','source','summary','regions','reason','review'] as $key) if (!isset($a[$key]) || !is_string($a[$key])) throw new InvalidArgumentException('Ungültiges Feld: '.$key);
            if (!preg_match('/^[a-f0-9]{64}$/',$a['id']) || !valid_url($a['url'])) throw new InvalidArgumentException('Ungültiger Beitrag');
            $regions=json_decode($a['regions'],true,8,JSON_THROW_ON_ERROR);
            if (!is_array($regions) || !$regions || array_filter($regions,fn($v)=>!is_string($v)) || array_diff($regions,array_keys(regions()))) throw new InvalidArgumentException('Ungültige Regionen');
            $review=json_decode($a['review'],true,20,JSON_THROW_ON_ERROR);
            if (!is_array($review) || strlen($a['summary'])>5000 || strlen($a['review'])>20000) throw new InvalidArgumentException('Ungültige Bewertung');
            foreach (['published','collected','manual','excluded'] as $key) if (!isset($a[$key]) || !is_numeric($a[$key])) throw new InvalidArgumentException('Ungültige Zahl');
            $evidence=$a['match_evidence']??'';
            if (!in_array($evidence,['','source_text','google_exact_query','legacy_text'],true)) throw new InvalidArgumentException('Ungültiger Suchbeleg');
            // Resolved source links are independent of manual regional corrections.
            if (parse_url($a['url'],PHP_URL_HOST)!=='news.google.com') {
                $db->prepare("UPDATE articles SET url=? WHERE id=? AND url LIKE 'https://news.google.com/%'")->execute([$a['url'],$a['id']]);
            }
            // Visitor corrections made during the job always win. Never replace the whole DB.
            $stmt=$db->prepare("INSERT INTO articles(id,title,url,source,summary,published,collected,regions,reason,manual,review,excluded,match_evidence) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(id) DO UPDATE SET match_evidence=excluded.match_evidence,url=CASE WHEN articles.url NOT LIKE 'https://news.google.com/%' AND excluded.url LIKE 'https://news.google.com/%' THEN articles.url ELSE excluded.url END,summary=excluded.summary,regions=excluded.regions,reason=excluded.reason,review=excluded.review,excluded=excluded.excluded WHERE articles.manual=0 AND (articles.review='{}' OR COALESCE(json_extract(articles.review,'$.checked_at'),0)<=COALESCE(json_extract(excluded.review,'$.checked_at'),0))");
            $stmt->execute([$a['id'],$a['title'],$a['url'],$a['source'],$a['summary'],(int)$a['published'],(int)$a['collected'],$a['regions'],$a['reason'],(int)(bool)$a['manual'],$a['review'],(int)(bool)$a['excluded'],$evidence]);$count++;
        }
        if (!empty($payload['last_success']) && is_string($payload['last_success'])) set_meta('last_success',$payload['last_success']);
        $db->commit();return $count;
    } catch (Throwable $e) { $db->rollBack();throw $e; }
}
