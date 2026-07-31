<?php
$pass = 0;
function check($cond, $msg) { global $pass; if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); } $pass++; }

function isAutoTaskCategory($category) {
    $category = trim((string)$category);
    if ($category === '') return false;
    return in_array($category, ["\xe7\xb3\xbb\xe7\xbb\x9fBUG", "\xe6\x96\xb0\xe5\xa2\x9e\xe9\x9c\x80\xe6\xb1\x82"], true);
}

// Positive
check(isAutoTaskCategory("\xe7\xb3\xbb\xe7\xbb\x9fBUG") === true, 'positive: xitongBUG');
check(isAutoTaskCategory("\xe6\x96\xb0\xe5\xa2\x9e\xe9\x9c\x80\xe6\xb1\x82") === true, 'positive: xinzengxuqiu');

// Negative - new variants that must NOT trigger
$xinxuqiu = "\xe6\x96\xb0\xe9\x9c\x80\xe6\xb1\x82"; // xin xu qiu (different from xin zeng xu qiu)
check(isAutoTaskCategory($xinxuqiu) === false, 'negative: xinxuqiu');
$gongnengwanshan = "\xe5\x8a\x9f\xe8\x83\xbd\xe5\xae\x8c\xe5\x96\x84"; // gong neng wan shan
check(isAutoTaskCategory($gongnengwanshan) === false, 'negative: gongnengwanshan');
check(isAutoTaskCategory('') === false, 'negative: empty');
check(isAutoTaskCategory('bug') === false, 'negative: english bug');
check(isAutoTaskCategory('Bug') === false, 'negative: Bug capital');
check(isAutoTaskCategory('some bug here') === false, 'negative: fuzzy bug');

fwrite(STDOUT, "Ledger trigger test passed ($pass assertions)\n");