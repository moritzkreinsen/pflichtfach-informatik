<?php
require_once __DIR__.'/bootstrap.php';
function rss_xml(array $selected=[]): string {
    global $config;
    $rows=array_slice(articles($selected),0,100);
    $base=rtrim($config['base_url'],'/');sort($selected);
    $name='Pflichtfach Informatik – Nachrichten'.($selected?' · '.implode(', ',array_map(fn($r)=>regions()[$r],$selected)):'');
    $file=$selected?implode('-',$selected):'all';
    $x=new XMLWriter();$x->openMemory();$x->startDocument('1.0','UTF-8');
    $x->startElement('rss');$x->writeAttribute('version','2.0');$x->writeAttribute('xmlns:atom','http://www.w3.org/2005/Atom');
    $x->startElement('channel');$x->writeElement('title',$name);$x->writeElement('link',$base);$x->writeElement('description','Nachrichten mit der Wortfolge „Pflichtfach Informatik“, mit Quellenlinks und Kurzüberblicken.');$x->writeElement('language','de');
    $x->startElement('atom:link');$x->writeAttribute('href',$base.'/feeds/'.$file.'.rss');$x->writeAttribute('rel','self');$x->writeAttribute('type','application/rss+xml');$x->endElement();
    foreach ($rows as $a) {
        $x->startElement('item');$x->writeElement('title',$a['title']);$x->writeElement('link',$a['url']);
        $x->startElement('guid');$x->writeAttribute('isPermaLink','false');$x->text($a['id']);$x->endElement();
        $x->writeElement('description',$a['summary'].' Quelle: '.$a['source']);$x->writeElement('pubDate',gmdate(DATE_RSS,(int)$a['published']));
        foreach ($a['regions'] as $r) $x->writeElement('category',regions()[$r]);$x->endElement();
    }
    $x->endElement();$x->endElement();$x->endDocument();return $x->outputMemory();
}
function export_rss(): void {
    $dir=dirname(__DIR__).'/public/feeds';if (!is_dir($dir)) mkdir($dir,0755,true);
    $groups=[[]];foreach(array_keys(regions()) as $r) $groups[]=[$r];
    foreach (glob($dir.'/*.rss') as $file) { $codes=explode('-',basename($file,'.rss'));if (!array_diff($codes,array_keys(regions()))) $groups[]=$codes; }
    foreach($groups as $codes) {sort($codes);$name=$codes?implode('-',$codes):'all';$temp=tempnam($dir,'.rss-');file_put_contents($temp,rss_xml($codes));rename($temp,$dir.'/'.$name.'.rss');}
}
