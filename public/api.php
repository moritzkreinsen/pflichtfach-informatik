<?php
require dirname(__DIR__).'/app/public_edits.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>8192) throw new InvalidArgumentException('Anfrage zu groß');
        $data=json_decode(file_get_contents('php://input'),true,16,JSON_THROW_ON_ERROR);
        $codes=$data['regions']??[];
        if (!is_array($codes) || !$codes || count($codes)>20 || array_filter($codes,fn($v)=>!is_string($v)) || array_diff($codes,array_keys(regions()))) throw new InvalidArgumentException('Mindestens eine gültige Region wählen.');
        validate_edit_token($data);
        public_edit($data,$_SERVER['REMOTE_ADDR']??'unknown');
        try { require_once dirname(__DIR__).'/app/rss.php'; export_rss(); } catch (Throwable $e) { error_log('RSS-Export: '.$e->getMessage()); }
        echo json_encode(['ok'=>true]); exit;
    }
    if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit; }
    if (($_GET['action']??'')==='edit-token') {
        $id=(string)($_GET['id']??'');
        if (!preg_match('/^[a-f0-9]{64}$/',$id)) throw new InvalidArgumentException('Ungültiger Beitrag');
        echo json_encode(['token'=>edit_token($id)]); exit;
    }
    $all=articles(); $counts=array_fill_keys(array_keys(regions()),0);
    foreach ($all as $a) foreach ($a['regions'] as $r) $counts[$r]++;
    $rows=articles(selected_regions(),mb_substr((string)($_GET['q']??''),0,200),max(0,min(3650,(int)($_GET['days']??0))));
    $page=max(1,(int)($_GET['page']??1));
    echo json_encode(['articles'=>array_slice($rows,($page-1)*20,20),'total'=>count($rows),'counts'=>$counts,'regions'=>regions(),'last_success'=>meta('last_success'),'feed_errors'=>count(json_decode(meta('last_errors')??'[]',true))],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (OverflowException $e) { http_response_code(429); header('Retry-After: 900'); echo json_encode(['error'=>$e->getMessage()]); }
catch (OutOfBoundsException $e) { http_response_code(404); echo json_encode(['error'=>$e->getMessage()]); }
catch (InvalidArgumentException|JsonException $e) { http_response_code(400); echo json_encode(['error'=>$e->getMessage()]); }
catch (Throwable $e) { error_log((string)$e); http_response_code(500); echo json_encode(['error'=>'Daten konnten nicht verarbeitet werden.']); }
