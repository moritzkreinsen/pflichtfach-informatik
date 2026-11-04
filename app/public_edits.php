<?php
// Anonymous session is only used for short-lived form tokens, not accounts.
require_once __DIR__.'/bootstrap.php';
function edit_session(): void {
    if (session_status()===PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode','1');
    session_name('news_form');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(($_SERVER['HTTPS']??'')==='on'),'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
function edit_token(string $id): string {
    edit_session();
    $_SESSION['forms']=array_filter($_SESSION['forms']??[],fn($v)=>$v['at']>time()-1800);
    if (count($_SESSION['forms'])>=20) array_shift($_SESSION['forms']);
    $token=bin2hex(random_bytes(24));
    $_SESSION['forms'][$token]=['id'=>$id,'at'=>time()];
    return $token;
}
function validate_edit_token(array $data): void {
    edit_session();
    $token=$data['token']??'';
    $form=is_string($token)?($_SESSION['forms'][$token]??null):null;
    if (!$form || $form['id']!==($data['id']??null) || time()-$form['at']>1800) throw new InvalidArgumentException('Formular abgelaufen. Bitte schließen und erneut öffnen.');
    if (($data['website']??'')!=='') throw new InvalidArgumentException('Änderung wurde nicht akzeptiert.');
    if (time()-$form['at']<2) throw new InvalidArgumentException('Bitte einen Moment warten und erneut speichern.');
    unset($_SESSION['forms'][$token]);
}
function public_edit(array $data,string $address): void {
    $db=db();
    $db->exec('CREATE TABLE IF NOT EXISTS edit_history (id INTEGER PRIMARY KEY, article_id TEXT NOT NULL, before_regions TEXT NOT NULL, after_regions TEXT NOT NULL, changed INTEGER NOT NULL, actor TEXT NOT NULL); CREATE INDEX IF NOT EXISTS edit_actor ON edit_history(actor,changed)');
    $db->exec('BEGIN IMMEDIATE');
    try {
        $secret=meta('rate_secret');
        if (!$secret) { $secret=bin2hex(random_bytes(32)); set_meta('rate_secret',$secret); }
        // Raw addresses are not stored; never trust client-provided forwarding headers.
        $actor=hash_hmac('sha256',gmdate('Y-m-d').'|'.$address,$secret);
        $db->exec('DELETE FROM edit_history WHERE changed < '.(time()-30*86400));
        $s=$db->prepare('SELECT COUNT(*) FROM edit_history WHERE actor=? AND changed>=?');
        $s->execute([$actor,time()-900]);
        if ((int)$s->fetchColumn()>=10) throw new OverflowException('Zu viele Änderungen. Bitte in 15 Minuten erneut versuchen.');
        $s->execute([$actor,time()-86400]);
        if ((int)$s->fetchColumn()>=60) throw new OverflowException('Tageslimit erreicht. Bitte morgen erneut versuchen.');
        $s=$db->prepare('SELECT regions FROM articles WHERE id=?');$s->execute([$data['id']]);$before=$s->fetchColumn();
        if ($before===false) throw new OutOfBoundsException('Beitrag nicht gefunden');
        $after=json_encode(array_values(array_unique($data['regions'])));
        $s=$db->prepare('INSERT INTO edit_history(article_id,before_regions,after_regions,changed,actor) VALUES (?,?,?,?,?)');
        $s->execute([$data['id'],$before,$after,time(),$actor]);
        $s=$db->prepare('UPDATE articles SET regions=?,manual=1,reason=? WHERE id=?');
        $s->execute([$after,'Öffentlich manuell zugeordnet',$data['id']]);
        $db->exec('COMMIT');
    } catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
}
