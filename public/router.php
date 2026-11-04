<?php
// Router only for PHP's local development server.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (preg_match('~^/feeds/([A-Z-]+|all)\.rss$~',$path,$match)) { $_GET['regions']=$match[1]; require __DIR__.'/feed.php'; return true; }
return false;
