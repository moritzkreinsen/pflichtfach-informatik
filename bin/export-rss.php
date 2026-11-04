<?php
if (PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/rss.php';export_rss();echo "RSS-Dateien exportiert.\n";
