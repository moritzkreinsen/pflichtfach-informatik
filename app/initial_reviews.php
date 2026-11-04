<?php
require_once __DIR__.'/bootstrap.php';

/** Restore the bundled, documented full-text assessments without overwriting newer work. */
function apply_initial_reviews(?array $reviews=null): int {
    $reviews ??= json_decode(file_get_contents(dirname(__DIR__).'/data/initial-review.json'), true, 512, JSON_THROW_ON_ERROR);
    $statement=db()->prepare("UPDATE articles SET regions=?,reason=?,summary=?,url=?,review=?,excluded=? WHERE id=? AND manual=0 AND COALESCE(json_extract(review,'$.checked_at'),0) < CAST(? AS INTEGER)");
    $count=0;
    foreach ($reviews as $review) {
        if ($review['status']!=='verified' || !$review['complete_article']) continue;
        $statement->execute([json_encode($review['regions']),$review['reason'],$review['summary'],$review['source_url'],json_encode($review,JSON_UNESCAPED_UNICODE),0,$review['id'],$review['checked_at']]);
        $count+=$statement->rowCount();
    }
    return $count;
}

/** Regional assessments may use excerpts; they do not replace article summaries. */
function apply_regional_reviews(): int {
    $reviews=json_decode(file_get_contents(dirname(__DIR__).'/data/regional-review.json'),true,512,JSON_THROW_ON_ERROR);
    $statement=db()->prepare("UPDATE articles SET regions=?,reason=?,review=? WHERE id=? AND manual=0 AND COALESCE(json_extract(review,'$.checked_at'),0) < CAST(? AS INTEGER)");
    $count=0;
    foreach ($reviews as $review) {
        $statement->execute([json_encode($review['regions']),$review['reason'],json_encode($review,JSON_UNESCAPED_UNICODE),$review['id'],$review['checked_at']]);
        $count+=$statement->rowCount();
    }
    return $count;
}
