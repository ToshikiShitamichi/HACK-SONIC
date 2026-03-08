<?php

declare(strict_types=1);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatYen($value): string
{
    if ($value === '' || $value === null || !is_numeric((string)$value)) {
        return '未指定';
    }
    return number_format((int)$value) . '円';
}

function difficultyLabel(string $difficulty): string
{
    switch ($difficulty) {
        case 'easy':
            return 'やさしい';
        case 'normal':
            return 'ふつう';
        case 'hard':
            return 'むずかしい';
        default:
            return $difficulty;
    }
}

function parseIsoDateTime(?string $value): ?DateTime
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    try {
        return new DateTime($value);
    } catch (Throwable $e) {
        return null;
    }
}

function formatTimeFromIso(?string $value): string
{
    $dt = parseIsoDateTime($value);
    if (!$dt) {
        return '';
    }
    return $dt->format('H:i');
}

function formatDateFromIso(?string $value): string
{
    $dt = parseIsoDateTime($value);
    if (!$dt) {
        return '';
    }
    return $dt->format('Y-m-d');
}

function getTripDays(string $duration): int
{
    $duration = trim($duration);

    if ($duration === '日帰り') {
        return 1;
    }

    if (preg_match('/(\d+)泊(\d+)日/u', $duration, $matches)) {
        return max(1, (int)$matches[2]);
    }

    if ($duration === '4泊5日以上') {
        return 5;
    }

    return 1;
}

function buildDayLabels(string $departureDate, string $duration): array
{
    $days = getTripDays($duration);
    $base = DateTime::createFromFormat('Y-m-d', $departureDate);

    if (!$base) {
        $base = new DateTime();
    }

    $labels = [];
    for ($i = 0; $i < $days; $i++) {
        $d = clone $base;
        if ($i > 0) {
            $d->modify('+' . $i . ' day');
        }
        $labels[$d->format('Y-m-d')] = 'Day ' . ($i + 1) . '（' . $d->format('Y-m-d') . '）';
    }

    return $labels;
}

function getDestinationAreaLabel(array $input): string
{
    $pref = trim((string)($input['destinationPrefecture'] ?? ''));
    $city = trim((string)($input['destinationCity'] ?? ''));

    if ($city !== '') {
        return $city;
    }

    if ($pref !== '') {
        return $pref;
    }

    return '現地';
}

function makeLodgingLabel(array $input): string
{
    return getDestinationAreaLabel($input) . 'に宿泊';
}

function buildQuestLookup(array $quests): array
{
    $lookup = [];

    foreach ($quests as $quest) {
        $place = trim((string)($quest['place'] ?? ''));
        if ($place !== '') {
            $lookup[$place] = $quest;
        }
    }

    return $lookup;
}

function normalizePlaceKey(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = preg_replace('/\s+/u', '', $value);
    $value = preg_replace('/[（）\(\)・,、\-ー]/u', '', $value);
    return $value ?? '';
}

function findQuestByLabel(array $quests, string $label): ?array
{
    $labelKey = normalizePlaceKey($label);
    if ($labelKey === '') {
        return null;
    }

    foreach ($quests as $quest) {
        $place = (string)($quest['place'] ?? '');
        $placeKey = normalizePlaceKey($place);

        if ($placeKey === '') {
            continue;
        }

        if ($labelKey === $placeKey) {
            return $quest;
        }

        if (mb_strpos($labelKey, $placeKey) !== false || mb_strpos($placeKey, $labelKey) !== false) {
            return $quest;
        }
    }

    return null;
}

function isGoalLabel(array $goal, string $label): bool
{
    $goalName = normalizePlaceKey((string)($goal['name'] ?? ''));
    $labelKey = normalizePlaceKey($label);

    if ($goalName === '' || $labelKey === '') {
        return false;
    }

    return $goalName === $labelKey
        || mb_strpos($labelKey, $goalName) !== false
        || mb_strpos($goalName, $labelKey) !== false;
}

function buildStepDetailText(array $step): string
{
    $parts = [];

    if (!empty($step['instruction'])) {
        $parts[] = (string)$step['instruction'];
    }

    if (!empty($step['line_name'])) {
        $line = (string)$step['line_name'];

        if (!empty($step['headsign'])) {
            $line .= '・' . (string)$step['headsign'] . '行';
        }

        $parts[] = '乗車: ' . $line;
    } elseif (!empty($step['vehicle_name'])) {
        $parts[] = '乗車: ' . (string)$step['vehicle_name'];
    }

    if (!empty($step['stop_count'])) {
        $parts[] = (string)$step['stop_count'] . '駅';
    }

    if (!empty($step['duration_text'])) {
        $parts[] = (string)$step['duration_text'];
    }

    if (!empty($step['distance_text'])) {
        $parts[] = (string)$step['distance_text'];
    }

    return implode(' / ', $parts);
}

function buildSegmentStepEntries(array $segment): array
{
    $entries = [];
    $steps = (!empty($segment['step_details']) && is_array($segment['step_details']))
        ? $segment['step_details']
        : [];

    if (empty($steps)) {
        $fallbackMinutes = max(
            1,
            (int)ceil(((int)($segment['duration_seconds'] ?? 0)) / 60)
        );

        $entries[] = [
            'type' => 'move',
            'time' => '',
            'title' => '交通機関',
            'place' => trim((string)($segment['from_label'] ?? '') . ' → ' . (string)($segment['to_label'] ?? '')),
            'detail' => trim(
                (string)($segment['duration_text'] ?? '') .
                    (!empty($segment['cost_text']) ? ' / ' . (string)$segment['cost_text'] : '')
            ),
            'transport_summary' => [
                'from' => (string)($segment['from_label'] ?? ''),
                'to' => (string)($segment['to_label'] ?? ''),
                'duration' => (string)($segment['duration_text'] ?? ''),
                'cost' => (string)($segment['cost_text'] ?? ''),
                'distance' => (string)($segment['distance_text'] ?? ''),
            ],
            'image' => '',
            'official_url' => '',
            'google_maps_url' => '',
            'minutes' => $fallbackMinutes,
        ];

        return $entries;
    }

    foreach ($steps as $step) {
        $mode = (string)($step['travel_mode'] ?? '');
        $departureTime = trim((string)($step['departure_time'] ?? ''));
        $arrivalTime = trim((string)($step['arrival_time'] ?? ''));
        $departureStop = trim((string)($step['departure_stop'] ?? ''));
        $arrivalStop = trim((string)($step['arrival_stop'] ?? ''));
        $detailText = buildStepDetailText($step);
        $minutes = max(1, (int)ceil(((int)($step['duration_seconds'] ?? 0)) / 60));

        if ($mode === 'TRANSIT') {
            if ($departureStop !== '') {
                $entries[] = [
                    'type' => 'step_departure',
                    'time' => $departureTime,
                    'title' => '出発',
                    'place' => $departureStop,
                    'detail' => $detailText,
                    'image' => '',
                    'official_url' => '',
                    'google_maps_url' => '',
                    'minutes' => 0,
                ];
            }

            if ($arrivalStop !== '') {
                $entries[] = [
                    'type' => 'step_arrival',
                    'time' => $arrivalTime,
                    'title' => '到着',
                    'place' => $arrivalStop,
                    'detail' => '',
                    'image' => '',
                    'official_url' => '',
                    'google_maps_url' => '',
                    'minutes' => $minutes,
                ];
            }
        } else {
            $entries[] = [
                'type' => 'step_move',
                'time' => '',
                'title' => '交通機関',
                'place' => trim($departureStop . ($arrivalStop !== '' ? ' → ' . $arrivalStop : '')),
                'detail' => $detailText !== '' ? $detailText : ((string)($step['instruction'] ?? '徒歩または移動')),
                'image' => '',
                'official_url' => '',
                'google_maps_url' => '',
                'minutes' => $minutes,
            ];
        }
    }

    if (!empty($segment['cost_text']) || !empty($segment['distance_text']) || !empty($segment['duration_text'])) {
        $entries[] = [
            'type' => 'segment_summary',
            'time' => '',
            'title' => '交通機関情報（概算）',
            'place' => trim((string)($segment['from_label'] ?? '') . ' → ' . (string)($segment['to_label'] ?? '')),
            'detail' => '',
            'transport_summary' => [
                'from' => (string)($segment['from_label'] ?? ''),
                'to' => (string)($segment['to_label'] ?? ''),
                'duration' => (string)($segment['duration_text'] ?? ''),
                'cost' => (string)($segment['cost_text'] ?? ''),
                'distance' => (string)($segment['distance_text'] ?? ''),
            ],
            'image' => '',
            'official_url' => '',
            'google_maps_url' => '',
            'minutes' => 0,
        ];
    }

    return $entries;
}

function buildRouteTimelineForPlan(array $plan, array $input): array
{
    $routeSegments = (!empty($plan['route_segments']) && is_array($plan['route_segments'])) ? $plan['route_segments'] : [];
    $quests = (!empty($plan['quests']) && is_array($plan['quests'])) ? $plan['quests'] : [];
    $goal = (!empty($plan['goal']) && is_array($plan['goal'])) ? $plan['goal'] : [];

    $questLookup = buildQuestLookup($quests);

    $tripDays = getTripDays((string)($input['duration'] ?? '日帰り'));
    $baseDate = (string)($input['departureDate'] ?? date('Y-m-d'));
    $base = DateTime::createFromFormat('Y-m-d', $baseDate);
    if (!$base) {
        $base = new DateTime();
    }

    $days = [];
    for ($i = 0; $i < $tripDays; $i++) {
        $d = clone $base;
        if ($i > 0) {
            $d->modify('+' . $i . ' day');
        }
        $days[$i] = [
            'date' => $d->format('Y-m-d'),
            'day_label' => 'Day ' . ($i + 1) . '（' . $d->format('Y-m-d') . '）',
            'entries' => [],
        ];
    }

    $currentDayIndex = 0;
    $currentMinutes = 9 * 60;
    $dayEndMinutes = 18 * 60;
    $lastDayIndex = $tripDays - 1;

    $pushEntry = function (array $entry) use (&$days, &$currentDayIndex, &$currentMinutes, $tripDays, $dayEndMinutes, $input): void {
        $minutes = (int)($entry['minutes'] ?? 0);

        if ($currentDayIndex < $tripDays - 1 && $minutes > 0 && ($currentMinutes + $minutes) > $dayEndMinutes) {
            $days[$currentDayIndex]['entries'][] = [
                'type' => 'lodging',
                'time' => '18:00',
                'title' => '宿泊',
                'place' => '',
                'detail' => makeLodgingLabel($input),
                'image' => '',
                'official_url' => '',
                'google_maps_url' => '',
                'minutes' => 0,
            ];

            $currentDayIndex++;
            $currentMinutes = 9 * 60;

            $days[$currentDayIndex]['entries'][] = [
                'type' => 'restart',
                'time' => '09:00',
                'title' => '行動開始',
                'place' => '',
                'detail' => 'この日の行動開始',
                'image' => '',
                'official_url' => '',
                'google_maps_url' => '',
                'minutes' => 0,
            ];
        }

        if (empty($entry['time'])) {
            $entry['time'] = sprintf('%02d:%02d', intdiv($currentMinutes, 60), $currentMinutes % 60);
        }

        $days[$currentDayIndex]['entries'][] = $entry;

        if ($minutes > 0) {
            $currentMinutes += $minutes;
            if ($currentMinutes > $dayEndMinutes) {
                $currentMinutes = $dayEndMinutes;
            }
        }
    };

    $pushToFinalDay = function (array $entry) use (&$days, $lastDayIndex): void {
        $days[$lastDayIndex]['entries'][] = $entry;
    };

    $days[0]['entries'][] = [
        'type' => 'start',
        'time' => '09:00',
        'title' => '出発地',
        'place' => trim((string)($input['departureCity'] ?? '')) !== '' ? (string)$input['departureCity'] : (string)($input['departurePrefecture'] ?? ''),
        'detail' => '出発日の9:00出発目安',
        'image' => '',
        'official_url' => '',
        'google_maps_url' => '',
        'minutes' => 0,
    ];

    foreach ($routeSegments as $segment) {
        $segmentType = (string)($segment['type'] ?? '');
        $toLabel = trim((string)($segment['to_label'] ?? ''));
        $toAddress = trim((string)($segment['to_address'] ?? ''));

        $isReturnSegment = in_array($segmentType, ['station_to_home', 'goal_to_home'], true);

        if ($isReturnSegment) {
            foreach (buildSegmentStepEntries($segment) as $stepEntry) {
                $stepEntry['time'] = $stepEntry['time'] ?? '';
                $pushToFinalDay($stepEntry);
            }

            $pushToFinalDay([
                'type' => 'end',
                'time' => '18:00',
                'title' => '出発地',
                'place' => (string)($segment['to_label'] ?? ''),
                'detail' => '終了日の18:00到着目安',
                'image' => '',
                'official_url' => '',
                'google_maps_url' => '',
                'minutes' => 0,
            ]);
            continue;
        }

        foreach (buildSegmentStepEntries($segment) as $stepEntry) {
            $pushEntry($stepEntry);
        }

        if (in_array($segmentType, ['start_to_station', 'goal_to_station'], true)) {
            $pushEntry([
                'type' => 'station',
                'time' => '',
                'title' => '最寄り駅',
                'place' => $toLabel,
                'detail' => '',
                'image' => '',
                'official_url' => '',
                'google_maps_url' => '',
                'minutes' => 0,
            ]);
            continue;
        }

        $matchedQuest = findQuestByLabel($quests, $toLabel);

        if ($matchedQuest !== null) {
            $pushEntry([
                'type' => 'quest',
                'time' => '',
                'title' => (string)($matchedQuest['title'] ?? 'クエスト'),
                'place' => (string)($matchedQuest['place'] ?? ''),
                'detail' => (string)($matchedQuest['description'] ?? ''),
                'stay_minutes' => (string)($matchedQuest['stay_minutes'] ?? ''),
                'estimated_cost' => (string)($matchedQuest['estimated_cost'] ?? ''),
                'difficulty' => (string)($matchedQuest['difficulty'] ?? ''),
                'exp' => (string)($matchedQuest['exp'] ?? ''),
                'image' => (string)($matchedQuest['image'] ?? ''),
                'official_url' => (string)($matchedQuest['official_url'] ?? ''),
                'google_maps_url' => (string)($matchedQuest['google_maps_url'] ?? ''),
                'area' => $toAddress !== '' ? $toAddress : (string)($matchedQuest['area'] ?? ''),
                'minutes' => max(10, (int)($matchedQuest['stay_minutes'] ?? 60)),
            ]);
        } elseif (!empty($goal) && isGoalLabel($goal, $toLabel)) {
            $pushEntry([
                'type' => 'goal',
                'time' => '',
                'title' => 'ゴール',
                'place' => (string)($goal['name'] ?? ''),
                'detail' => (string)($goal['description'] ?? ''),
                'image' => (string)($goal['image'] ?? ''),
                'official_url' => (string)($goal['official_url'] ?? ''),
                'google_maps_url' => (string)($goal['google_maps_url'] ?? ''),
                'area' => $toAddress !== '' ? $toAddress : (string)($goal['area'] ?? ''),
                'minutes' => 60,
            ]);
        }
    }

    if ($tripDays > 1) {
        for ($i = 0; $i < $tripDays - 1; $i++) {
            $hasLodging = false;
            foreach ($days[$i]['entries'] as $entry) {
                if (($entry['type'] ?? '') === 'lodging') {
                    $hasLodging = true;
                    break;
                }
            }
            if (!$hasLodging) {
                $days[$i]['entries'][] = [
                    'type' => 'lodging',
                    'time' => '18:00',
                    'title' => '宿泊',
                    'place' => '',
                    'detail' => makeLodgingLabel($input),
                    'image' => '',
                    'official_url' => '',
                    'google_maps_url' => '',
                    'minutes' => 0,
                ];
            }
        }
    }

    foreach ($days as $i => &$day) {
        usort($day['entries'], function ($a, $b) {
            $ta = (string)($a['time'] ?? '');
            $tb = (string)($b['time'] ?? '');
            return strcmp($ta, $tb);
        });

        if ($i === $lastDayIndex) {
            $filtered = [];
            $hasEnd = false;

            foreach ($day['entries'] as $entry) {
                if (($entry['type'] ?? '') === 'end') {
                    $hasEnd = true;
                }
            }

            foreach ($day['entries'] as $entry) {
                if ($hasEnd && ($entry['type'] ?? '') === 'lodging') {
                    continue;
                }
                $filtered[] = $entry;
            }

            $day['entries'] = $filtered;
        }
    }
    unset($day);

    return $days;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
?>
    <!DOCTYPE html>
    <html lang="ja">

    <head>
        <meta charset="UTF-8">
        <title>エラー</title>
    </head>

    <body>
        <h1>エラー</h1>
        <p>このページにはPOSTでアクセスしてください。</p>
        <p><a href="./input-plan.html">入力フォームに戻る</a></p>
    </body>

    </html>
<?php
    exit;
}

$input = [
    'departurePrefecture' => trim((string)($_POST['departurePrefecture'] ?? '')),
    'departureCity' => trim((string)($_POST['departureCity'] ?? '')),
    'destinationPrefecture' => trim((string)($_POST['destinationPrefecture'] ?? '')),
    'destinationCity' => trim((string)($_POST['destinationCity'] ?? '')),
    'destinationKeyword' => trim((string)($_POST['destinationKeyword'] ?? '')),
    'departureDate' => trim((string)($_POST['departureDate'] ?? '')),
    'duration' => trim((string)($_POST['duration'] ?? '')),
    'people' => trim((string)($_POST['people'] ?? '')),
    'budgetMin' => trim((string)($_POST['budgetMin'] ?? '')),
    'budgetMax' => trim((string)($_POST['budgetMax'] ?? '')),
];

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$apiUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $baseDir . '/api/generate-plan.php';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 180,
]);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$apiResponse = null;
$pageError = null;

if ($responseBody === false) {
    $pageError = 'APIの呼び出しに失敗しました: ' . $curlError;
} else {
    $apiResponse = json_decode($responseBody, true);

    if (!is_array($apiResponse)) {
        $pageError = 'APIレスポンスのJSON解析に失敗しました。';
    } elseif ($httpCode < 200 || $httpCode >= 300 || empty($apiResponse['success'])) {
        $pageError = $apiResponse['error'] ?? 'プラン生成に失敗しました。';
    }
}

$plans = [];
if (is_array($apiResponse) && !empty($apiResponse['data']['plans']) && is_array($apiResponse['data']['plans'])) {
    $plans = $apiResponse['data']['plans'];
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>旅行プラン提案結果</title>
    <style>
        body {
            margin: 0;
            padding: 16px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .error {
            border: 1px solid #cc0000;
            padding: 12px;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            align-items: start;
        }

        .plan-column {
            border: 1px solid #cccccc;
            padding: 12px;
            box-sizing: border-box;
        }

        .timeline-day {
            margin-top: 16px;
            margin-bottom: 16px;
        }

        .timeline-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .timeline-item {
            display: grid;
            grid-template-columns: 72px 1fr;
            gap: 12px;
            padding: 8px 0;
            border-top: 1px solid #dddddd;
        }

        .timeline-item:last-child {
            border-bottom: 1px solid #dddddd;
        }

        .timeline-time {
            font-weight: bold;
        }

        pre {
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        @media (max-width: 1024px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>旅行プラン提案結果</h1>

        <section>
            <h2>入力内容</h2>
            <dl>
                <dt>出発地（都道府県）</dt>
                <dd><?= h($input['departurePrefecture']) ?></dd>

                <dt>出発地（市区町村）</dt>
                <dd><?= h($input['departureCity']) ?: '未入力' ?></dd>

                <dt>旅行先（都道府県）</dt>
                <dd><?= h($input['destinationPrefecture']) ?></dd>

                <dt>旅行先（市区町村）</dt>
                <dd><?= h($input['destinationCity']) ?: '未入力' ?></dd>

                <dt>旅行先キーワード</dt>
                <dd><?= h($input['destinationKeyword']) ?: '未入力' ?></dd>

                <dt>出発日</dt>
                <dd><?= h($input['departureDate']) ?></dd>

                <dt>期間</dt>
                <dd><?= h($input['duration']) ?></dd>

                <dt>人数</dt>
                <dd><?= h($input['people']) ?></dd>

                <dt>予算（下限）</dt>
                <dd><?= h(formatYen($input['budgetMin'])) ?></dd>

                <dt>予算（上限）</dt>
                <dd><?= h(formatYen($input['budgetMax'])) ?></dd>

                <dt>気になるカテゴリ</dt>
                <dd><?= h($apiResponse['user_context']['interest_category'] ?? 'すべて') ?></dd>
            </dl>
        </section>

        <?php if ($pageError !== null): ?>
            <section class="error">
                <h2>エラー</h2>
                <p><?= h($pageError) ?></p>

                <?php if (is_array($apiResponse) && !empty($apiResponse['errors']) && is_array($apiResponse['errors'])): ?>
                    <ul>
                        <?php foreach ($apiResponse['errors'] as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <p><a href="./input-plan.html">入力フォームに戻る</a></p>
            </section>
        <?php else: ?>
            <section>
                <h2>AIが生成した旅行プラン候補</h2>

                <?php if (!empty($plans)): ?>
                    <div class="plans-grid">
                        <?php foreach ($plans as $index => $plan): ?>
                            <?php $timeline = buildRouteTimelineForPlan($plan, $input); ?>
                            <section class="plan-column">
                                <h2>プラン<?= $index + 1 ?></h2>

                                <h3><?= h($plan['plan_title'] ?? 'タイトル未設定') ?></h3>

                                <?php if (!empty($plan['concept'])): ?>
                                    <p><strong>コンセプト:</strong> <?= nl2br(h($plan['concept'])) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($plan['summary'])): ?>
                                    <p><strong>概要:</strong> <?= nl2br(h($plan['summary'])) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($plan['budget_estimate'])): ?>
                                    <p>
                                        <strong>予算目安:</strong>
                                        <?= h(formatYen($plan['budget_estimate']['min'] ?? '')) ?>
                                        〜
                                        <?= h(formatYen($plan['budget_estimate']['max'] ?? '')) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($plan['total_travel_time_text'])): ?>
                                    <p><strong>総移動時間目安:</strong> <?= h($plan['total_travel_time_text']) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($plan['total_cost_estimate_text'])): ?>
                                    <p><strong>総費用目安:</strong> <?= h($plan['total_cost_estimate_text']) ?></p>
                                <?php endif; ?>

                                <h4>タイムライン</h4>

                                <?php foreach ($timeline as $dayBlock): ?>
                                    <div class="timeline-day">
                                        <h5><?= h($dayBlock['day_label']) ?></h5>
                                        <ol class="timeline-list">
                                            <?php foreach ($dayBlock['entries'] as $entry): ?>
                                                <li class="timeline-item">
                                                    <div class="timeline-time"><?= h($entry['time'] ?? '') ?></div>
                                                    <div>
                                                        <div>
                                                            <strong><?= h($entry['title'] ?? '') ?></strong>
                                                            <?php if (!empty($entry['place'])): ?>
                                                                ：<?= h($entry['place']) ?>
                                                            <?php endif; ?>
                                                        </div>

                                                        <?php if (!empty($entry['area']) && in_array(($entry['type'] ?? ''), ['quest', 'goal'], true)): ?>
                                                            <div>住所・エリア: <?= h($entry['area']) ?></div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($entry['detail'])): ?>
                                                            <div><?= nl2br(h($entry['detail'])) ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($entry['transport_summary'])): ?>
                                                            <div><strong>交通機関情報（概算）</strong></div>

                                                            <?php if (!empty($entry['transport_summary']['from']) || !empty($entry['transport_summary']['to'])): ?>
                                                                <div>
                                                                    区間:
                                                                    <?= h((string)($entry['transport_summary']['from'] ?? '')) ?>
                                                                    →
                                                                    <?= h((string)($entry['transport_summary']['to'] ?? '')) ?>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($entry['transport_summary']['duration'])): ?>
                                                                <div>所要時間: <?= h((string)$entry['transport_summary']['duration']) ?></div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($entry['transport_summary']['cost'])): ?>
                                                                <div>交通費: <?= h((string)$entry['transport_summary']['cost']) ?></div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($entry['transport_summary']['distance'])): ?>
                                                                <div>距離: <?= h((string)$entry['transport_summary']['distance']) ?></div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>

                                                        <?php if (($entry['type'] ?? '') === 'quest'): ?>
                                                            <?php if (!empty($entry['stay_minutes'])): ?>
                                                                <div>滞在目安: <?= h((string)$entry['stay_minutes']) ?>分</div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($entry['estimated_cost'])): ?>
                                                                <div>費用目安: <?= h($entry['estimated_cost']) ?></div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($entry['difficulty'])): ?>
                                                                <div>難易度: <?= h(difficultyLabel((string)$entry['difficulty'])) ?></div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($entry['exp'])): ?>
                                                                <div>EXP: <?= h((string)$entry['exp']) ?></div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>

                                                        <?php if (!empty($entry['google_maps_url'])): ?>
                                                            <div>
                                                                <a href="<?= h($entry['google_maps_url']) ?>" target="_blank" rel="noopener noreferrer">Googleマップで見る</a>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($entry['official_url'])): ?>
                                                            <div>
                                                                <a href="<?= h($entry['official_url']) ?>" target="_blank" rel="noopener noreferrer">公式サイトを見る</a>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($entry['image']) && in_array(($entry['type'] ?? ''), ['quest', 'goal'], true)): ?>
                                                            <div>
                                                                <img src="<?= h($entry['image']) ?>" alt="<?= h($entry['place'] ?? $entry['title'] ?? '') ?>">
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (!empty($plan['notes']) && is_array($plan['notes'])): ?>
                                    <div>
                                        <h4>メモ</h4>
                                        <ul>
                                            <?php foreach ($plan['notes'] as $note): ?>
                                                <li><?= h($note) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>プラン候補を取得できませんでした。</p>
                <?php endif; ?>
            </section>

            <section>
                <h2>デバッグ用: APIレスポンス</h2>
                <pre><?= h(json_encode($apiResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
            </section>
        <?php endif; ?>

        <p><a href="./input-plan.html">入力フォームに戻る</a></p>
    </div>
</body>

</html>