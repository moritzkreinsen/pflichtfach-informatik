<?php
require dirname(__DIR__).'/app/rss.php';
if (isset($_GET['regions']) && is_string($_GET['regions'])) $_GET['regions']=$_GET['regions']==='all'?'':str_replace('-',',',$_GET['regions']);
try { $selected=selected_regions(); } catch (InvalidArgumentException $e) { http_response_code(400); exit('Ungültige Region'); }
sort($selected);$name=$selected?implode('-',$selected):'all';
header('Content-Type: application/rss+xml; charset=utf-8');
header('Content-Disposition: inline; filename="'.$name.'.rss"');
header('Cache-Control: public, max-age=300');
echo rss_xml($selected);
