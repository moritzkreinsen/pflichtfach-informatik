<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/collector.php';
try { $result=collect(); require_once dirname(__DIR__).'/app/initial_reviews.php'; $result['restored_reviews']=apply_initial_reviews()+apply_regional_reviews(); require_once dirname(__DIR__).'/app/rss.php'; export_rss(); echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL; exit($result['errors'] ? 1 : 0); }
catch (Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); }
