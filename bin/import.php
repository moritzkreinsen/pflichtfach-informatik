<?php
if (PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/import.php';
try {
    $raw=stream_get_contents(STDIN,50000000);
    $count=import_snapshot(json_decode($raw,true,32,JSON_THROW_ON_ERROR));
    require_once dirname(__DIR__).'/app/rss.php';export_rss();
    echo "$count Beiträge verarbeitet.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1); }
