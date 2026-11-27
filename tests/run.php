<?php
putenv('NEWS_DB=:memory:');
require dirname(__DIR__).'/app/collector.php';
require dirname(__DIR__).'/app/public_edits.php';
edit_session();
function check(bool $ok,string $label): void { if (!$ok) throw new RuntimeException($label); echo "OK $label\n"; }
check(classify('Pflichtfach Informatik in Sachsen-Anhalt','')[0]===['ST'],'Sachsen-Anhalt nicht Sachsen');
check(classify('Bayern und NRW führen Informatik ein','')[0]===['BY','NW'],'Mehrfachregionen');
check(classify('An bayerischen Schulen','Informatik')[0]===['BY'],'Adjektivflexion');
check(classify('Viele Bundesländer diskutieren Informatik','')[0]===['DE'],'Bundesweiter Vergleich');
check(classify('Pflichtfach Informatik','')[0]===['UNK'],'Unsicherheit sichtbar');
$xml='<rss version="2.0"><channel><title>Testquelle</title><item><title>Informatik in Bayern</title><link>https://example.org/news</link><description>Das Pflichtfach Informatik wird an weiteren Schulen ausgebaut. Die Lehrkräfte erhalten Fortbildungen.</description><pubDate>Mon, 02 Nov 2026 10:00:00 GMT</pubDate></item></channel></rss>';
check(import_feed($xml)===1,'RSS importiert');import_feed($xml);check(count(articles())===1,'Deduplizierung');
check(matches_phrase('Das PFLICHTFACH&nbsp;Informatik kommt'),'Großschreibung und HTML-Leerzeichen');
check(!matches_phrase('Informatik wird Pflichtfach') && !matches_phrase('Pflichtfach Informatikunterricht'),'Keine ähnliche oder verlängerte Wortfolge');
foreach (['Informatik als Pflichtfach','Informatik soll künftig zum Pflichtfach werden','Informatik wird kein Pflichtfach','verpflichtender Informatikunterricht','verpflichtenden Informatikunterrichts','Informatik-Pflichtfach'] as $phrase) check(matches_topic($phrase),'Suchvariante: '.$phrase);
foreach (['Informatikunterricht wird ausgebaut','Wahlpflichtfach Informatik','Informatik ist beliebt. Mathematik wird Pflichtfach.','Informatik wird ausgebaut, während Wirtschaft Pflichtfach wird','Informatik ist im Studium ein Pflichtfach','Informatik als Pflichtfach im Bachelor'] as $phrase) check(!matches_topic($phrase),'Kein Fehlalarm: '.$phrase);
check(articles()[0]['summary']==='Das Pflichtfach Informatik wird an weiteren Schulen ausgebaut. Die Lehrkräfte erhalten Fortbildungen.','RSS-Teaser unverändert');
$unrelated=str_replace('Das Pflichtfach Informatik wird an weiteren Schulen ausgebaut. Die Lehrkräfte erhalten Fortbildungen.','Informatikunterricht wird ausgebaut.',$xml);
check(import_feed($unrelated)===0,'Allgemeiner Informatikunterricht wird verworfen');
$bodyOnly=str_replace('<rss version="2.0">','<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">',str_replace('</item>','<content:encoded>Der Artikel erwähnt das Pflichtfach Informatik im Volltext.</content:encoded></item>',$unrelated));
check(import_feed($bodyOnly)===1,'Wortfolge nur im gelieferten Volltext genügt');
$split=str_replace(['Informatik in Bayern','Informatikunterricht wird ausgebaut.'],['Ein Pflichtfach','Informatik ist das Thema.'],$unrelated);
check(import_feed($split)===0,'Wortfolge darf nicht über Feldgrenzen zusammengesetzt werden');
check(import_feed($unrelated,true)===1,'Exakter Google-Suchtreffer ohne Wortfolge im Anreißer bleibt erhalten');
check(import_feed(str_replace('Informatikunterricht wird ausgebaut.','Informatik soll Pflichtfach werden.',$unrelated))===1,'Variante wird als Suchtreffer aufgenommen');
$id=articles()[0]['id'];$s=db()->prepare('UPDATE articles SET manual=1,regions=?,reason=? WHERE id=?');$s->execute(['["NW"]','Manuell',$id]);import_feed($xml);check(articles()[0]['regions']===['NW'],'Korrektur bleibt nach Abruf erhalten');
check(count(articles(['BY']))===0 && count(articles(['NW','BY']))===1,'Regionen mit ODER');
check(count(articles([],'unauffindbar'))===0,'Suche filtert');
$atom='<feed xmlns="http://www.w3.org/2005/Atom"><title>Atomquelle</title><entry><title>Informatik in Hessen</title><link href="https://example.org/atom"/><summary>Das Pflichtfach Informatik wird diskutiert.</summary><updated>2026-11-01T10:00:00Z</updated></entry></feed>';
check(import_feed($atom)===1,'Atom importiert');
check(!valid_url('javascript:alert(1)'),'Unsichere Links verworfen');
try { import_feed('<!DOCTYPE rss><rss/>'); throw new LogicException('DOCTYPE akzeptiert'); } catch (RuntimeException $e) { echo "OK DOCTYPE blockiert\n"; }


$token=edit_token($id);
$payload=['id'=>$id,'regions'=>['HE'],'token'=>$token,'website'=>''];
try { validate_edit_token($payload); throw new LogicException('Zu schnelles Formular akzeptiert'); } catch (InvalidArgumentException $e) { echo "OK Mindestwartezeit\n"; }
$_SESSION['forms'][$token]['at']=time()-3;
validate_edit_token($payload);
try { validate_edit_token($payload); throw new LogicException('Token wiederverwendet'); } catch (InvalidArgumentException $e) { echo "OK Einmal-Token\n"; }
$token=edit_token($id);$_SESSION['forms'][$token]['at']=time()-3;
try { validate_edit_token(['id'=>$id,'token'=>$token,'website'=>'spam']); throw new LogicException('Bot-Feld akzeptiert'); } catch (InvalidArgumentException $e) { echo "OK Bot-Feld\n"; }
$token=edit_token($id);$_SESSION['forms'][$token]['at']=time()-1900;
try { validate_edit_token(['id'=>$id,'token'=>$token]); throw new LogicException('Alter Token akzeptiert'); } catch (InvalidArgumentException $e) { echo "OK Tokenablauf\n"; }
for ($i=0;$i<10;$i++) public_edit(['id'=>$id,'regions'=>['HE']],'192.0.2.1');
check(count(articles(['HE']))===2,'Öffentliche Korrektur gespeichert');
try { public_edit(['id'=>$id,'regions'=>['BW']],'192.0.2.1'); throw new LogicException('Limit überschritten'); } catch (OverflowException $e) { echo "OK Ratenlimit\n"; }
check((int)db()->query('SELECT COUNT(*) FROM edit_history')->fetchColumn()===10,'Änderungsverlauf und Rollback');
check(count(articles(['BW']))===0,'Blockierte Änderung nicht gespeichert');

require dirname(__DIR__).'/app/import.php';
$incoming=db()->query('SELECT * FROM articles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($incoming as &$record) { $record['regions']='["BW"]'; $record['manual']=0; }
unset($record);
import_snapshot(['version'=>1,'articles'=>$incoming]);
check(articles(['HE'])[0]['id']===$id,'Live-Korrektur gewinnt gegen veralteten Job');
require dirname(__DIR__).'/app/rss.php';
$feed=simplexml_load_string(rss_xml(['HE']));
check($feed!==false && $feed->getName()==='rss' && count($feed->channel->item)===1,'RSS 2.0 XML und Regionsfilter');
check((string)$feed->channel->item->category==='Hessen','RSS-Kategorie');
$beforeCount=(int)db()->query('SELECT COUNT(*) FROM articles')->fetchColumn();
$bad=$incoming;$bad[0]['regions']='["INVALID"]';
try { import_snapshot(['version'=>1,'articles'=>$bad]); throw new LogicException('Ungültiger Import akzeptiert'); } catch (InvalidArgumentException $e) { echo "OK Importvalidierung\n"; }
check((int)db()->query('SELECT COUNT(*) FROM articles')->fetchColumn()===$beforeCount,'Ungültiger Import ohne Datenverlust');
require dirname(__DIR__).'/app/initial_reviews.php';
$review=json_decode(file_get_contents(dirname(__DIR__).'/data/initial-review.json'),true)[0];
$review['id']=$id;
check(apply_initial_reviews([$review])===0,'Erstprüfung überschreibt öffentliche Korrektur nicht');
db()->prepare("UPDATE articles SET manual=0,review='{}' WHERE id=?")->execute([$id]);
check(apply_initial_reviews([$review])===1,'Erstprüfung wiederhergestellt');
check(apply_initial_reviews([$review])===0,'Erstprüfung idempotent');
import_feed($xml);
$restored=db()->query('SELECT * FROM articles WHERE id='.db()->quote($id))->fetch(PDO::FETCH_ASSOC);
check($restored['url']===$review['source_url'] && $restored['summary']==='Das Pflichtfach Informatik wird an weiteren Schulen ausgebaut. Die Lehrkräfte erhalten Fortbildungen.','Quellenlink bleibt erhalten und Originalteaser wird übernommen');
$review['checked_at']--;
check(apply_initial_reviews([$review])===0,'Ältere Erstprüfung überschreibt neuere Prüfung nicht');
$googleXml=str_replace('https://example.org/news','https://news.google.com/rss/articles/test',$xml);
import_feed($googleXml,true);
check(db()->query('SELECT url FROM articles WHERE id='.db()->quote($id))->fetchColumn()===$review['source_url'],'Sammeln ersetzt direkten Link nicht durch Google');
$dbRecord=db()->query('SELECT * FROM articles WHERE id='.db()->quote($id))->fetch(PDO::FETCH_ASSOC);
$dbRecord['url']='https://news.google.com/rss/articles/test';
import_snapshot(['version'=>1,'articles'=>[$dbRecord]]);
check(db()->query('SELECT url FROM articles WHERE id='.db()->quote($id))->fetchColumn()===$review['source_url'],'Alter Snapshot erhält direkten Link');
db()->prepare("UPDATE articles SET url='https://news.google.com/rss/articles/test',manual=1 WHERE id=?")->execute([$id]);
$dbRecord['url']='https://example.org/resolved';
import_snapshot(['version'=>1,'articles'=>[$dbRecord]]);
check(db()->query('SELECT url FROM articles WHERE id='.db()->quote($id))->fetchColumn()==='https://example.org/resolved','Direkter Link wird auch bei manueller Kategorie importiert');
echo "Alle Tests bestanden.\n";
