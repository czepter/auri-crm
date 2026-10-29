<?php

use App\Documents\Data\DocumentViewData;
use App\Documents\Models\DocumentView;

it('names the device from a user agent', function (string $agent, string $label) {
    $view = new DocumentView(['user_agent' => $agent, 'via' => 'link', 'counts' => true, 'viewed_at' => now()]);
    $view->public_id = 'x';

    expect(DocumentViewData::fromModel($view)->device)->toBe($label);
})->with([
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'iPhone · Safari'],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'Mac · Chrome'],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0', 'Windows · Edge'],
    ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', 'Android · Chrome'],
    ['curl/8.4', ''],
]);
