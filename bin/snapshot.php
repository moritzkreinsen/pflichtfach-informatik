<?php
// Only article metadata; never exports session secrets, IP hashes or visitor history.
if (PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
$rows=db()->query('SELECT * FROM articles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['version'=>1,'articles'=>$rows,'last_success'=>meta('last_success')],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
