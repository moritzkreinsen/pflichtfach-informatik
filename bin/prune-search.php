<?php
// One-time migration of the old broad search. Run after collecting exact-query results.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$db=db();
$removed=[];$retained=0;
$db->beginTransaction();
try {
    foreach ($db->query('SELECT * FROM articles')->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $path=dirname(__DIR__).'/data/cache/'.$a['id'].'.json';
        $cache=is_file($path)?json_decode(file_get_contents($path),true):[];
        // Model-generated summaries are not evidence of wording in the original.
        $hasText=matches_phrase($a['title']) || matches_phrase($cache['text']??'') || ($a['review']==='{}' && matches_phrase($a['summary']));
        if ($a['match_evidence'] || $hasText) {
            $db->prepare("UPDATE articles SET match_evidence=CASE WHEN match_evidence='' THEN 'legacy_text' ELSE match_evidence END,excluded=0 WHERE id=?")->execute([$a['id']]);
            $retained++;
        } else {
            $removed[]=$a;
            $db->prepare('DELETE FROM articles WHERE id=?')->execute([$a['id']]);
        }
    }
    $dir=dirname(__DIR__).'/var/backups';if (!is_dir($dir)) mkdir($dir,0755,true);
    $report=$dir.'/removed-broad-search-'.gmdate('Ymd-His').'.json';
    if (file_put_contents($report,json_encode($removed,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Bereinigungsprotokoll konnte nicht geschrieben werden');
    $db->commit();
    require dirname(__DIR__).'/app/rss.php';export_rss();
    echo json_encode(['removed'=>count($removed),'retained'=>$retained,'report'=>$report],JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1); }
