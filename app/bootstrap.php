<?php
declare(strict_types=1);
$config = ['base_url'=>'http://127.0.0.1:8080','database'=>getenv('NEWS_DB') ?: dirname(__DIR__).'/var/news.sqlite','extra_feeds'=>[]];
if (getenv('NEWS_DB') !== ':memory:' && is_file(dirname(__DIR__).'/config.php')) $config = array_replace($config, require dirname(__DIR__).'/config.php');
function regions(): array { return json_decode(file_get_contents(__DIR__.'/regions.json'), true); }
function db(): PDO {
    global $config;
    static $db;
    if ($db) return $db;
    $db = new PDO('sqlite:'.$config['database'], null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000; CREATE TABLE IF NOT EXISTS articles (id TEXT PRIMARY KEY, title TEXT NOT NULL, url TEXT NOT NULL, source TEXT NOT NULL, summary TEXT NOT NULL, published INTEGER NOT NULL, collected INTEGER NOT NULL, regions TEXT NOT NULL, reason TEXT NOT NULL, manual INTEGER NOT NULL DEFAULT 0); CREATE INDEX IF NOT EXISTS published_idx ON articles(published); CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT NOT NULL);');
    $columns=array_column($db->query('PRAGMA table_info(articles)')->fetchAll(PDO::FETCH_ASSOC),'name');
    foreach (['summary_source'=>"TEXT NOT NULL DEFAULT ''", 'match_evidence'=>"TEXT NOT NULL DEFAULT ''", 'review'=>"TEXT NOT NULL DEFAULT '{}'", 'excluded'=>'INTEGER NOT NULL DEFAULT 0'] as $name=>$type) {
        if (!in_array($name,$columns,true)) $db->exec("ALTER TABLE articles ADD COLUMN $name $type");
    }
    return $db;
}
function plain(string $s): string { return trim(preg_replace('/\s+/u',' ', html_entity_decode(strip_tags($s), ENT_QUOTES|ENT_HTML5,'UTF-8')) ?? ''); }
function classify(string $title, string $summary, string $source=''): array {
    $aliases = ['BW'=>'baden.württemberg|baden.württembergisch|stuttgart','BY'=>'bayern|bayerisch|münchen','BE'=>'berlin(?:er)?','BB'=>'brandenburg(?:isch)?|potsdam','HB'=>'bremen|bremer|bremerhaven','HH'=>'hamburg(?:er)?','HE'=>'hessen|hessisch|wiesbaden','MV'=>'mecklenburg.vorpommern|schwerin','NI'=>'niedersachsen|niedersächsisch|hannover','NW'=>'nordrhein.westfalen|nrw|düsseldorf','RP'=>'rheinland.pfalz|mainz','SL'=>'saarland|saarländisch|saarbrücken','SN'=>'sachsen|sächsisch|dresden|görlitz|görlitzer|leipzig','ST'=>'sachsen.anhalt|magdeburg','SH'=>'schleswig.holstein|kiel|lübeck|lübecker','TH'=>'thüringen|thüringisch|erfurt','AT'=>'österreich|österreichisch|wien|innsbruck|AHS|WKÖ|digitale grundbildung','CH'=>'schweiz|schweizer|zürich|kanton'];
    $scores=[];
    foreach ($aliases as $code=>$pattern) {
        $t=$title; $s=$summary;
        if ($code==='SN') { $t=preg_replace('/sachsen[\s-]anhalt/iu','',$t); $s=preg_replace('/sachsen[\s-]anhalt/iu','',$s); }
        $pattern='/(?<!\p{L})(?:'.$pattern.')(?:e|en|er|es)?(?!\p{L})/iu';
        $score= (preg_match($pattern,$t)?4:0)+(preg_match($pattern,$s)?2:0)+(preg_match($pattern,$source)?1:0);
        if ($score>=2) $scores[$code]=$score;
    }
    if ($scores) return [array_keys($scores), 'Regionale Begriffe in Titel oder Feedtext: '.implode(', ',array_keys($scores))];
    if (preg_match('/bundesweit|deutschland|bundesländer|föderalismus|kultusministerkonferenz|\bKMK\b|\bSWK\b|bundesregierung/iu',$title.' '.$summary)) return [['DE'],'Überregionaler Deutschland-Bezug'];
    return [['UNK'],'Keine eindeutigen regionalen Merkmale; Prüfung empfohlen'];
}
function valid_url(string $url): bool { return filter_var($url,FILTER_VALIDATE_URL) && in_array(parse_url($url,PHP_URL_SCHEME),['http','https'],true); }
function articles(array $selected=[], string $q='', int $days=0): array {
    $stmt=db()->prepare('SELECT * FROM articles WHERE excluded=0 AND published >= ? ORDER BY published DESC');
    $stmt->execute([$days ? time()-$days*86400 : 0]);
    $out=[];
    foreach ($stmt as $row) {
        if (!$row['summary_source']) $row['summary']='';
        $row['regions']=json_decode($row['regions'],true);
        if ($selected && !array_intersect($selected,$row['regions'])) continue;
        if ($q!=='' && mb_stripos($row['title'].' '.$row['summary'].' '.$row['source'],$q)===false) continue;
        $out[]=$row;
    }
    return $out;
}
function selected_regions(): array {
    $raw=$_GET['regions']??'';
    if (!is_string($raw)) throw new InvalidArgumentException('Ungültige Regionen');
    $result=array_values(array_filter(explode(',',strtoupper($raw))));
    if (array_diff($result,array_keys(regions()))) throw new InvalidArgumentException('Unbekannte Region');
    return $result;
}
function meta(string $key): ?string { $s=db()->prepare('SELECT value FROM meta WHERE key=?'); $s->execute([$key]); return $s->fetchColumn() ?: null; }
function set_meta(string $key,string $value): void { $s=db()->prepare('INSERT INTO meta VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value'); $s->execute([$key,$value]); }
function h(string $s): string { return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

function matches_phrase(string $text): bool { return (bool)preg_match('/\bPflichtfach\s+Informatik\b/iu',plain($text)); }

/** Conservative phrase variants: no match across sentences, fields or unrelated subjects. */
function matches_topic(string $text): bool {
    $text=plain($text);
    if (matches_phrase($text)) return true;
    foreach (preg_split('/[.!?;\n]+/u',$text) as $sentence) {
        // Higher education uses Pflichtfach for compulsory degree modules as well.
        if (preg_match('/\b(?:Studium|Studiengang|Studierende|Hochschule|Universität|Bachelor|Master)\b/iu',$sentence)) continue;
        if (preg_match('/\bInformatik[\s‑–-]+Pflichtfach\b/iu',$sentence)) return true;
        if (preg_match('/\b(?:verpflichtend(?:er|en|em|es|e)?|obligatorisch(?:er|en|em|es|e)?|verbindlich(?:er|en|em|es|e)?)\s+Informatikunterricht(?:s)?\b/iu',$sentence)) return true;
        if (preg_match('/\bInformatik\s+(?:(?:soll|sollte|muss|müsse|wird|werde|werden|ist|bleibt|als|zum|ein|das|künftig|zukünftig|endlich|auch|überall|kein|nicht|verpflichtendes|verbindliches)\s+){1,8}Pflichtfach\b/iu',$sentence)) return true;
    }
    return false;
}
