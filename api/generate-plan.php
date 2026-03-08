<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);

        $name = trim($name);
        $value = trim($value);
        $value = trim($value, "\"'");

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}

loadEnv(dirname(__DIR__) . '/.env');

function respondJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function normalizeString(array $data, string $key): string
{
    return trim((string)($data[$key] ?? ''));
}

function normalizeBudget(array $data, string $key): string
{
    $value = trim((string)($data[$key] ?? ''));
    return is_numeric($value) ? (string)((int)$value) : $value;
}

function getEnvValue(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return (string)$value;
}

function getGoogleMapsApiKey(): string
{
    $apiKey = getEnvValue('GOOGLE_MAPS_API_KEY');

    if ($apiKey === '') {
        throw new RuntimeException('GOOGLE_MAPS_API_KEY が設定されていません。.env を確認してください。');
    }

    return $apiKey;
}

function getUserInterestCategory(): string
{
    return getEnvValue('USER_DEFAULT_INTEREST', 'すべて');
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

function buildDateTimeString(string $date, string $time = '09:00:00'): string
{
    $date = trim($date);
    if ($date === '') {
        $date = date('Y-m-d');
    }

    return $date . 'T' . $time . '+09:00';
}

function buildReturnDate(string $departureDate, string $duration): string
{
    $days = getTripDays($duration);
    $base = DateTime::createFromFormat('Y-m-d', $departureDate);
    if (!$base) {
        $base = new DateTime();
    }

    if ($days > 1) {
        $base->modify('+' . ($days - 1) . ' day');
    }

    return $base->format('Y-m-d');
}

function secondsFromDuration(?string $duration): int
{
    if (!is_string($duration) || $duration === '') {
        return 0;
    }

    if (preg_match('/^(\d+)s$/', $duration, $m)) {
        return (int)$m[1];
    }

    return 0;
}

function formatDurationJa(string $duration): string
{
    $seconds = secondsFromDuration($duration);

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    if ($hours > 0) {
        return $minutes > 0 ? "{$hours}時間{$minutes}分" : "{$hours}時間";
    }

    return max(1, $minutes) . '分';
}

function isoToUnix(?string $iso): ?int
{
    if (!is_string($iso) || trim($iso) === '') {
        return null;
    }

    try {
        return (new DateTime($iso))->getTimestamp();
    } catch (Throwable $e) {
        return null;
    }
}

function secondsToJa(int $seconds): string
{
    if ($seconds <= 0) {
        return '0分';
    }

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    if ($hours > 0) {
        return $minutes > 0 ? "{$hours}時間{$minutes}分" : "{$hours}時間";
    }

    return max(1, $minutes) . '分';
}

function getLocalizedTimeText(array $localizedTime): string
{
    return (string)($localizedTime['time']['text'] ?? '');
}

function getLatLngText(?array $location): string
{
    $lat = $location['latLng']['latitude'] ?? null;
    $lng = $location['latLng']['longitude'] ?? null;

    if ($lat === null || $lng === null) {
        return '';
    }

    return (string)$lat . ',' . (string)$lng;
}

function formatDistanceJa(int $distanceMeters): string
{
    if ($distanceMeters >= 1000) {
        return round($distanceMeters / 1000, 1) . 'km';
    }
    return $distanceMeters . 'm';
}

function containsAirportWord(string $text): bool
{
    $text = trim($text);

    if ($text === '') {
        return false;
    }

    return mb_strpos($text, '空港') !== false;
}

function estimateLongDistanceTravel(int $distanceMeters, string $originAddress, string $destinationAddress): array
{
    $distanceKm = $distanceMeters / 1000;

    $isAirportRoute = containsAirportWord($originAddress) || containsAirportWord($destinationAddress);

    if ($isAirportRoute) {
        return [
            'duration_seconds' => 2 * 3600,
            'duration_text' => '約2時間',
            'cost_text' => '概算',
            'distance_text' => formatDistanceJa($distanceMeters),
            'step_details' => [
                [
                    'travel_mode' => 'FLIGHT_ESTIMATE',
                    'instruction' => '飛行機移動想定',
                    'distance_meters' => $distanceMeters,
                    'distance_text' => formatDistanceJa($distanceMeters),
                    'duration_seconds' => 2 * 3600,
                    'duration_text' => '約2時間',
                    'start_location' => '',
                    'end_location' => '',
                    'departure_time' => '',
                    'arrival_time' => '',
                    'departure_stop' => $originAddress,
                    'arrival_stop' => $destinationAddress,
                    'headsign' => '',
                    'stop_count' => '',
                    'line_name' => '飛行機移動想定',
                    'line_short_name' => '',
                    'vehicle_type' => 'AIRPLANE',
                    'vehicle_name' => '飛行機',
                    'agency_name' => '',
                    'fare_text' => '',
                ]
            ],
        ];
    }

    if ($distanceKm < 50) {
        $seconds = 3600;
        $text = '約1時間';
    } elseif ($distanceKm < 150) {
        $seconds = (int)(1.5 * 3600);
        $text = '約1時間30分';
    } elseif ($distanceKm < 300) {
        $seconds = (int)(2.5 * 3600);
        $text = '約2時間30分';
    } elseif ($distanceKm < 700) {
        $seconds = 4 * 3600;
        $text = '約4時間';
    } else {
        $seconds = 6 * 3600;
        $text = '約6時間';
    }

    return [
        'duration_seconds' => $seconds,
        'duration_text' => $text,
        'cost_text' => '概算',
        'distance_text' => formatDistanceJa($distanceMeters),
        'step_details' => [
            [
                'travel_mode' => 'LONG_DISTANCE_ESTIMATE',
                'instruction' => '長距離移動想定',
                'distance_meters' => $distanceMeters,
                'distance_text' => formatDistanceJa($distanceMeters),
                'duration_seconds' => $seconds,
                'duration_text' => $text,
                'start_location' => '',
                'end_location' => '',
                'departure_time' => '',
                'arrival_time' => '',
                'departure_stop' => $originAddress,
                'arrival_stop' => $destinationAddress,
                'headsign' => '',
                'stop_count' => '',
                'line_name' => '長距離移動想定',
                'line_short_name' => '',
                'vehicle_type' => 'LONG_DISTANCE',
                'vehicle_name' => '長距離移動',
                'agency_name' => '',
                'fare_text' => '',
            ]
        ],
    ];
}

function postJson(string $url, array $payload, array $headers = [], int $timeout = 20): array
{
    $ch = curl_init($url);

    $defaultHeaders = [
        'Content-Type: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        throw new RuntimeException('外部API呼び出しに失敗しました: ' . $curlError);
    }

    $decoded = json_decode($responseBody, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('外部APIレスポンスのJSON解析に失敗しました。HTTP: ' . $httpCode . ' / body: ' . $responseBody);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $errorMessage = $decoded['error']['message'] ?? ('HTTPエラー: ' . $httpCode);
        throw new RuntimeException('外部APIエラー: ' . $errorMessage);
    }

    return $decoded;
}

function callOpenAI(array $input, string $userInterestCategory): array
{
    $apiKey = getEnvValue('OPENAI_API_KEY');
    $model = getEnvValue('OPENAI_MODEL', 'gpt-4o-mini');

    if ($apiKey === '') {
        throw new RuntimeException('OPENAI_API_KEY が設定されていません。.env を確認してください。');
    }

    $departurePrefecture = $input['departurePrefecture'];
    $departureCity = $input['departureCity'];
    $destinationPrefecture = $input['destinationPrefecture'];
    $destinationCity = $input['destinationCity'];
    $destinationKeyword = $input['destinationKeyword'];
    $departureDate = $input['departureDate'];
    $duration = $input['duration'];
    $people = $input['people'];
    $budgetMin = $input['budgetMin'];
    $budgetMax = $input['budgetMax'];

    $systemPrompt = <<<PROMPT
あなたは日本国内旅行プラン作成のプロです。
ユーザー入力、候補スポット情報、移動条件をもとに、旅行プラン候補を3つ、日本語で提案してください。

必須条件:
- 必ず JSON のみを返すこと
- JSON以外の文章は一切含めないこと
- 候補は3件
- 旅行期間は「出発日の9:00 〜 帰り日の18:00」を行動可能時間の目安として組むこと
- 現実的な移動・観光順にすること
- quests は必ず3件にすること
- goal と quests で同じ場所を使わないこと
- ゴールや quest に含めるスポット・店舗は、必ず実在する固有名詞を前提にすること
- 各スポットには、わかる範囲で area を入れること
- 予算感はユーザー予算にできるだけ合わせること
- questに1件スウィーツに関するものを入れること

各 plan は以下の項目を持つこと:
- plan_title
- concept
- summary
- trip_window
- budget_estimate
- total_travel_time_text
- total_cost_estimate_text
- goal
- quests
- notes

trip_window は以下:
- start_time
- end_time

budget_estimate は以下:
- min
- max

goal は以下:
- name
- description
- area
- image
- image_source
- official_url

quests は各要素ごとに以下:
- title
- description
- exp
- difficulty
- place
- area
- stay_minutes
- travel_time_from_previous
- estimated_cost
- image
- image_source
- official_url

制約:
- exp は 10〜300 の10刻み
- difficulty は easy / normal / hard のいずれか
- image は URL 文字列または null
- image_source は "official" / "generated" / null
- official_url は 公式サイトが分かる場合のみ文字列、なければ null
- travel_time_from_previous は「前の地点からの移動時間」を自然な日本語で記載
- estimated_cost は「1人あたり」「グループ合計」などが分かる自然な日本語で記載
- total_travel_time_text はプラン全体の移動時間目安
- total_cost_estimate_text は交通費・食費・入場料などを含む概算説明

出力JSONは次の形に厳密に合わせること:

{
  "plans": [
    {
      "plan_title": "string",
      "concept": "string",
      "summary": "string",
      "trip_window": {
        "start_time": "09:00",
        "end_time": "18:00"
      },
      "budget_estimate": {
        "min": 0,
        "max": 0
      },
      "total_travel_time_text": "string",
      "total_cost_estimate_text": "string",
      "goal": {
        "name": "string",
        "description": "string",
        "area": "string",
        "image": null,
        "image_source": null,
        "official_url": null
      },
      "quests": [
        {
          "title": "string",
          "description": "string",
          "exp": 30,
          "difficulty": "easy",
          "place": "string",
          "area": "string",
          "stay_minutes": 60,
          "travel_time_from_previous": "string",
          "estimated_cost": "string",
          "image": null,
          "image_source": null,
          "official_url": null
        }
      ],
      "notes": [
        "string"
      ]
    }
  ]
}
PROMPT;

    $userPrompt = <<<PROMPT
以下の入力条件から、旅行プラン候補を3つ作成してください。

【入力情報】
- 出発地（都道府県）: {$departurePrefecture}
- 出発地（市区町村）: {$departureCity}
- 旅行先（都道府県）: {$destinationPrefecture}
- 旅行先（市区町村）: {$destinationCity}
- 旅行先キーワード: {$destinationKeyword}
- 出発日: {$departureDate}
- 期間: {$duration}
- 人数: {$people}
- 予算下限: {$budgetMin}
- 予算上限: {$budgetMax}
- ユーザーが気になるカテゴリ: {$userInterestCategory}

【重要ルール】
- 行動時間は、出発日の09:00から帰り日の18:00までを目安にする
- 各スポットや店は、実在する固有名詞を優先して提案する
- 店名や施設名は、チェーン名だけでなく候補店舗が想像できる粒度で具体化する
- 無理のある詰め込みは避け、移動と滞在のバランスを取る
- キーワードがあれば、雰囲気や立ち寄り先に反映する
- ゴールとクエストは同じ施設・同じ店にしないこと
- クエストは必ず3件作成すること

【補足】
- この時点では画像URLや公式URLが不明なら null でよい
- ただし、あとで外部APIで補完しやすいよう、場所名は曖昧にせず具体的に出すこと
PROMPT;

    $payload = [
        'model' => $model,
        'temperature' => 0.9,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    if ($ch === false) {
        throw new RuntimeException('cURLの初期化に失敗しました。');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 120,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        throw new RuntimeException('OpenAI APIの呼び出しに失敗しました: ' . $curlError);
    }

    $response = json_decode($responseBody, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = 'OpenAI APIエラー';
        if (is_array($response) && isset($response['error']['message'])) {
            $message .= ': ' . $response['error']['message'];
        }
        throw new RuntimeException($message);
    }

    $content = $response['choices'][0]['message']['content'] ?? null;
    if (!is_string($content) || $content === '') {
        throw new RuntimeException('AIの応答本文を取得できませんでした。');
    }

    $json = json_decode($content, true);
    if (!is_array($json) || !isset($json['plans']) || !is_array($json['plans'])) {
        throw new RuntimeException('AI応答のJSON解析に失敗しました。AI応答: ' . $content);
    }

    return $json;
}

function callGooglePlacesTextSearch(string $textQuery, ?string $regionCode = 'JP'): ?array
{
    $apiKey = getGoogleMapsApiKey();
    $url = 'https://places.googleapis.com/v1/places:searchText';

    $payload = [
        'textQuery' => $textQuery,
        'languageCode' => 'ja',
    ];

    if (!empty($regionCode)) {
        $payload['regionCode'] = $regionCode;
    }

    $headers = [
        'X-Goog-Api-Key: ' . $apiKey,
        'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.location,places.googleMapsUri,places.websiteUri,places.photos',
    ];

    $response = postJson($url, $payload, $headers, 20);

    if (empty($response['places']) || !is_array($response['places'])) {
        return null;
    }

    return $response['places'][0];
}

function callGooglePlacesNearbyStations(float $lat, float $lng): array
{
    $apiKey = getGoogleMapsApiKey();
    $url = 'https://places.googleapis.com/v1/places:searchNearby';

    $payload = [
        'includedTypes' => ['train_station', 'subway_station', 'transit_station'],
        'maxResultCount' => 3,
        'languageCode' => 'ja',
        'rankPreference' => 'DISTANCE',
        'locationRestriction' => [
            'circle' => [
                'center' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                ],
                'radius' => 3000.0,
            ],
        ],
    ];

    $headers = [
        'X-Goog-Api-Key: ' . $apiKey,
        'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.location,places.googleMapsUri',
    ];

    $response = postJson($url, $payload, $headers, 20);

    if (empty($response['places']) || !is_array($response['places'])) {
        return [];
    }

    return $response['places'];
}

function buildGooglePlacePhotoUrl(string $photoName, int $maxWidth = 800): string
{
    $apiKey = getGoogleMapsApiKey();
    return "https://places.googleapis.com/v1/{$photoName}/media?maxWidthPx={$maxWidth}&key={$apiKey}";
}

function normalizePlaceInfo(?array $place, string $fallbackLabel = '', string $fallbackAddress = ''): array
{
    $label = $fallbackLabel;
    $address = $fallbackAddress;
    $lat = null;
    $lng = null;
    $mapsUrl = null;
    $websiteUrl = null;
    $image = null;

    if (is_array($place)) {
        if (!empty($place['displayName']['text'])) {
            $label = (string)$place['displayName']['text'];
        }

        if (!empty($place['formattedAddress'])) {
            $address = (string)$place['formattedAddress'];
        }

        if (isset($place['location']['latitude'])) {
            $lat = (float)$place['location']['latitude'];
        }

        if (isset($place['location']['longitude'])) {
            $lng = (float)$place['location']['longitude'];
        }

        if (!empty($place['googleMapsUri'])) {
            $mapsUrl = (string)$place['googleMapsUri'];
        }

        if (!empty($place['websiteUri'])) {
            $websiteUrl = (string)$place['websiteUri'];
        }

        if (!empty($place['photos'][0]['name'])) {
            $image = buildGooglePlacePhotoUrl((string)$place['photos'][0]['name']);
        }
    }

    return [
        'label' => $label,
        'address' => $address,
        'lat' => $lat,
        'lng' => $lng,
        'google_maps_url' => $mapsUrl,
        'official_url' => $websiteUrl,
        'image' => $image,
    ];
}

function extractTransitFareText(array $route): ?string
{
    $candidates = [
        $route['travelAdvisory']['transitFare']['localizedValues']['fare']['text'] ?? null,
        $route['travelAdvisory']['transitFare']['localizedValues']['money']['text'] ?? null,
        $route['travelAdvisory']['transitFare']['localizedValues']['text'] ?? null,
        $route['travelAdvisory']['transitFare']['currencyCode'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return null;
}

function callGoogleComputeRoutes(
    string $originAddress,
    string $destinationAddress,
    string $travelMode = 'TRANSIT',
    ?string $departureTime = null,
    ?string $arrivalTime = null
): ?array {
    $apiKey = getGoogleMapsApiKey();
    $url = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    $payload = [
        'origin' => ['address' => $originAddress],
        'destination' => ['address' => $destinationAddress],
        'travelMode' => $travelMode,
        'languageCode' => 'ja',
        'units' => 'METRIC',
    ];

    if ($travelMode === 'TRANSIT') {
        $payload['computeAlternativeRoutes'] = false;
        $payload['transitPreferences'] = [
            'routingPreference' => 'FEWER_TRANSFERS',
            'allowedTravelModes' => ['TRAIN', 'RAIL', 'SUBWAY', 'LIGHT_RAIL', 'BUS'],
        ];
    }

    if ($departureTime !== null && $departureTime !== '') {
        $payload['departureTime'] = $departureTime;
    }

    if ($arrivalTime !== null && $arrivalTime !== '') {
        $payload['arrivalTime'] = $arrivalTime;
    }

    $headers = [
        'X-Goog-Api-Key: ' . $apiKey,
        'X-Goog-FieldMask: '
            . 'routes.duration,'
            . 'routes.distanceMeters,'
            . 'routes.travelAdvisory.transitFare,'
            . 'routes.legs.steps.travelMode,'
            . 'routes.legs.steps.staticDuration,'
            . 'routes.legs.steps.distanceMeters,'
            . 'routes.legs.steps.startLocation,'
            . 'routes.legs.steps.endLocation,'
            . 'routes.legs.steps.navigationInstruction,'
            . 'routes.legs.steps.localizedValues,'
            . 'routes.legs.steps.transitDetails',
    ];

    $response = postJson($url, $payload, $headers, 30);

    if (empty($response['routes']) || !is_array($response['routes'])) {
        return null;
    }

    return $response['routes'][0];
}

function parseRouteStepDetails(array $route): array
{
    $stepDetails = [];

    $legs = $route['legs'] ?? [];
    if (!is_array($legs)) {
        return $stepDetails;
    }

    foreach ($legs as $legIndex => $leg) {
        $steps = $leg['steps'] ?? [];
        if (!is_array($steps)) {
            continue;
        }

        foreach ($steps as $stepIndex => $step) {
            $travelMode = (string)($step['travelMode'] ?? '');
            $distanceMeters = (int)($step['distanceMeters'] ?? 0);
            $staticDuration = (string)($step['staticDuration'] ?? '');
            $durationSeconds = secondsFromDuration($staticDuration);
            $durationText = $durationSeconds > 0 ? secondsToJa($durationSeconds) : '';

            $detail = [
                'leg_index' => $legIndex,
                'step_index' => $stepIndex,
                'travel_mode' => $travelMode,
                'instruction' => (string)($step['navigationInstruction']['instructions'] ?? ''),
                'distance_meters' => $distanceMeters,
                'distance_text' => $distanceMeters > 0 ? formatDistanceJa($distanceMeters) : '',
                'duration_seconds' => $durationSeconds,
                'duration_text' => $durationText,
                'start_location' => getLatLngText($step['startLocation'] ?? null),
                'end_location' => getLatLngText($step['endLocation'] ?? null),
                'departure_time' => '',
                'arrival_time' => '',
                'departure_stop' => '',
                'arrival_stop' => '',
                'headsign' => '',
                'stop_count' => '',
                'line_name' => '',
                'line_short_name' => '',
                'vehicle_type' => '',
                'vehicle_name' => '',
                'agency_name' => '',
                'fare_text' => '',
            ];

            if (!empty($step['transitDetails']) && is_array($step['transitDetails'])) {
                $transit = $step['transitDetails'];

                $detail['departure_time'] = getLocalizedTimeText($transit['localizedValues']['departureTime'] ?? []);
                $detail['arrival_time'] = getLocalizedTimeText($transit['localizedValues']['arrivalTime'] ?? []);
                $detail['departure_stop'] = (string)($transit['stopDetails']['departureStop']['name'] ?? '');
                $detail['arrival_stop'] = (string)($transit['stopDetails']['arrivalStop']['name'] ?? '');
                $detail['headsign'] = (string)($transit['headsign'] ?? '');
                $detail['stop_count'] = isset($transit['stopCount']) ? (string)$transit['stopCount'] : '';
                $detail['line_name'] = (string)($transit['transitLine']['name'] ?? '');
                $detail['line_short_name'] = (string)($transit['transitLine']['nameShort'] ?? '');
                $detail['vehicle_type'] = (string)($transit['transitLine']['vehicle']['type'] ?? '');
                $detail['vehicle_name'] = (string)($transit['transitLine']['vehicle']['name']['text'] ?? '');
                $detail['agency_name'] = (string)($transit['transitLine']['agencies'][0]['name'] ?? '');

                $departureUnix = isoToUnix($transit['stopDetails']['departureTime'] ?? null);
                $arrivalUnix = isoToUnix($transit['stopDetails']['arrivalTime'] ?? null);
                if ($departureUnix !== null && $arrivalUnix !== null && $arrivalUnix >= $departureUnix) {
                    $detail['duration_seconds'] = $arrivalUnix - $departureUnix;
                    $detail['duration_text'] = secondsToJa($detail['duration_seconds']);
                }
            } else {
                $detail['departure_time'] = getLocalizedTimeText($step['localizedValues']['departureTime'] ?? []);
                $detail['arrival_time'] = getLocalizedTimeText($step['localizedValues']['arrivalTime'] ?? []);
            }

            $stepDetails[] = $detail;
        }
    }

    return $stepDetails;
}

function buildRouteSummary(
    string $originAddress,
    string $destinationAddress,
    ?string $departureTime = null,
    ?string $arrivalTime = null
): ?array {
    $route = callGoogleComputeRoutes($originAddress, $destinationAddress, 'TRANSIT', $departureTime, $arrivalTime);

    if (!$route) {
        return null;
    }

    $durationSeconds = secondsFromDuration($route['duration'] ?? null);
    $durationText = !empty($route['duration']) ? formatDurationJa((string)$route['duration']) : null;
    $distanceMeters = isset($route['distanceMeters']) ? (int)$route['distanceMeters'] : 0;
    $distanceText = $distanceMeters > 0 ? formatDistanceJa($distanceMeters) : null;
    $fareText = extractTransitFareText($route);
    $stepDetails = parseRouteStepDetails($route);

    $isSuspiciousShortLongDistance =
        $distanceMeters >= 300000 && (
            $durationSeconds <= 1800 ||
            empty($stepDetails)
        );

    $isVeryLongDistance =
        $distanceMeters >= 700000;

    if ($isSuspiciousShortLongDistance || $isVeryLongDistance) {
        return estimateLongDistanceTravel($distanceMeters, $originAddress, $destinationAddress);
    }

    return [
        'duration_seconds' => $durationSeconds,
        'duration_text' => $durationText,
        'distance_text' => $distanceText,
        'cost_text' => $fareText,
        'step_details' => $stepDetails,
        'raw_route' => $route,
    ];
}

function enrichPlansWithGooglePlaces(array $plans, array $input): array
{
    $originQuery = trim(($input['departurePrefecture'] ?? '') . ' ' . ($input['departureCity'] ?? ''));
    $originPlace = $originQuery !== '' ? callGooglePlacesTextSearch($originQuery) : null;
    $originInfo = normalizePlaceInfo($originPlace, $originQuery, $originQuery);

    $originStation = null;
    if ($originInfo['lat'] !== null && $originInfo['lng'] !== null) {
        $stations = callGooglePlacesNearbyStations($originInfo['lat'], $originInfo['lng']);
        if (!empty($stations[0])) {
            $originStation = normalizePlaceInfo($stations[0]);
        }
    }

    foreach ($plans as &$plan) {
        $plan['origin'] = [
            'label' => $originInfo['label'] !== '' ? $originInfo['label'] : $originQuery,
            'address' => $originInfo['address'] !== '' ? $originInfo['address'] : $originQuery,
            'nearest_station' => $originStation,
        ];

        if (!empty($plan['goal']['name'])) {
            $query = $plan['goal']['name'] . ' ' . $input['destinationPrefecture'];
            $place = callGooglePlacesTextSearch($query);

            if ($place) {
                $goalInfo = normalizePlaceInfo($place, (string)$plan['goal']['name'], (string)($plan['goal']['area'] ?? ''));
                $plan['goal']['image'] = $goalInfo['image'];
                $plan['goal']['image_source'] = $goalInfo['image'] ? 'official' : ($plan['goal']['image_source'] ?? null);
                $plan['goal']['official_url'] = $goalInfo['official_url'] ?: ($plan['goal']['official_url'] ?? null);
                $plan['goal']['area'] = $goalInfo['address'] ?: ($plan['goal']['area'] ?? '');
                $plan['goal']['google_maps_url'] = $goalInfo['google_maps_url'];
                $plan['goal']['lat'] = $goalInfo['lat'];
                $plan['goal']['lng'] = $goalInfo['lng'];

                if ($goalInfo['lat'] !== null && $goalInfo['lng'] !== null) {
                    $stations = callGooglePlacesNearbyStations($goalInfo['lat'], $goalInfo['lng']);
                    if (!empty($stations[0])) {
                        $plan['goal']['nearest_station'] = normalizePlaceInfo($stations[0]);
                    }
                }
            }
        }

        if (!empty($plan['quests']) && is_array($plan['quests'])) {
            foreach ($plan['quests'] as &$quest) {
                if (empty($quest['place'])) {
                    continue;
                }

                $query = $quest['place'] . ' ' . $input['destinationPrefecture'];
                $place = callGooglePlacesTextSearch($query);

                if (!$place) {
                    continue;
                }

                $questInfo = normalizePlaceInfo($place, (string)$quest['place'], (string)($quest['area'] ?? ''));
                $quest['image'] = $questInfo['image'];
                $quest['image_source'] = $questInfo['image'] ? 'official' : ($quest['image_source'] ?? null);
                $quest['official_url'] = $questInfo['official_url'] ?: ($quest['official_url'] ?? null);
                $quest['area'] = $questInfo['address'] ?: ($quest['area'] ?? '');
                $quest['google_maps_url'] = $questInfo['google_maps_url'];
                $quest['lat'] = $questInfo['lat'];
                $quest['lng'] = $questInfo['lng'];

                if ($questInfo['lat'] !== null && $questInfo['lng'] !== null) {
                    $stations = callGooglePlacesNearbyStations($questInfo['lat'], $questInfo['lng']);
                    if (!empty($stations[0])) {
                        $quest['nearest_station'] = normalizePlaceInfo($stations[0]);
                    }
                }
            }
            unset($quest);
        }
    }
    unset($plan);

    return $plans;
}

function addSecondsToDateTimeString(string $dateTime, int $seconds): string
{
    $dt = new DateTime($dateTime);
    if ($seconds !== 0) {
        $dt->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds');
    }
    return $dt->format('c');
}

function chooseAddress(array $node): string
{
    return trim((string)($node['address'] ?? $node['label'] ?? ''));
}

function chooseLabel(array $node): string
{
    return trim((string)($node['label'] ?? $node['address'] ?? ''));
}

function appendSegment(
    array &$segments,
    string $type,
    array $fromNode,
    array $toNode,
    ?string $departureTime = null,
    ?string $arrivalTime = null
): array {
    $fromAddress = chooseAddress($fromNode);
    $toAddress = chooseAddress($toNode);

    if ($fromAddress === '' || $toAddress === '') {
        return [
            'duration_seconds' => 0,
            'duration_text' => '',
            'cost_text' => null,
            'step_details' => [],
        ];
    }

    $summary = buildRouteSummary($fromAddress, $toAddress, $departureTime, $arrivalTime);

    $segment = [
        'type' => $type,
        'from_label' => chooseLabel($fromNode),
        'to_label' => chooseLabel($toNode),
        'from_address' => $fromAddress,
        'to_address' => $toAddress,
        'departure_time' => $departureTime,
        'arrival_time' => null,
        'duration_seconds' => $summary['duration_seconds'] ?? 0,
        'duration_text' => $summary['duration_text'] ?? '',
        'cost_text' => $summary['cost_text'] ?? null,
        'distance_text' => $summary['distance_text'] ?? null,
        'step_details' => $summary['step_details'] ?? [],
    ];

    if (!empty($segment['step_details'])) {
        $firstStep = $segment['step_details'][0];
        $lastStep = $segment['step_details'][count($segment['step_details']) - 1];

        $segment['departure_time_text'] = !empty($firstStep['departure_time']) ? $firstStep['departure_time'] : '';
        $segment['arrival_time_text'] = !empty($lastStep['arrival_time']) ? $lastStep['arrival_time'] : '';
    } else {
        $segment['departure_time_text'] = '';
        $segment['arrival_time_text'] = '';
    }

    if ($departureTime !== null && !empty($segment['duration_seconds'])) {
        $segment['arrival_time'] = addSecondsToDateTimeString($departureTime, (int)$segment['duration_seconds']);
    } elseif ($arrivalTime !== null) {
        $segment['arrival_time'] = $arrivalTime;
    }

    $segments[] = $segment;

    return [
        'duration_seconds' => (int)($segment['duration_seconds'] ?? 0),
        'duration_text' => (string)($segment['duration_text'] ?? ''),
        'cost_text' => $segment['cost_text'] ?? null,
        'step_details' => $segment['step_details'] ?? [],
    ];
}

function enrichPlansWithTransitPlan(array $plans, array $input): array
{
    $departureDate = $input['departureDate'] ?: date('Y-m-d');
    $returnDate = buildReturnDate($departureDate, $input['duration'] ?? '日帰り');

    foreach ($plans as &$plan) {
        $segments = [];
        $totalSeconds = 0;
        $costTexts = [];

        $origin = [
            'label' => $plan['origin']['label'] ?? trim(($input['departurePrefecture'] ?? '') . ' ' . ($input['departureCity'] ?? '')),
            'address' => $plan['origin']['address'] ?? trim(($input['departurePrefecture'] ?? '') . ' ' . ($input['departureCity'] ?? '')),
        ];

        $originStation = null;
        if (!empty($plan['origin']['nearest_station']) && is_array($plan['origin']['nearest_station'])) {
            $originStation = [
                'label' => $plan['origin']['nearest_station']['label'] ?? '',
                'address' => $plan['origin']['nearest_station']['address'] ?? '',
            ];
        }

        $quests = (!empty($plan['quests']) && is_array($plan['quests'])) ? $plan['quests'] : [];
        $goal = (!empty($plan['goal']) && is_array($plan['goal'])) ? $plan['goal'] : [];

        $departureTime = buildDateTimeString($departureDate, '09:00:00');

        $firstTarget = null;
        if (!empty($quests[0])) {
            $firstTarget = [
                'label' => $quests[0]['place'] ?? 'クエスト1',
                'address' => $quests[0]['area'] ?? ($quests[0]['place'] ?? ''),
            ];
        } elseif (!empty($goal)) {
            $firstTarget = [
                'label' => $goal['name'] ?? 'ゴール',
                'address' => $goal['area'] ?? ($goal['name'] ?? ''),
            ];
        }

        if ($originStation !== null) {
            $sum = appendSegment($segments, 'start_to_station', $origin, $originStation, $departureTime, null);
            $totalSeconds += $sum['duration_seconds'];
            if (!empty($sum['cost_text'])) {
                $costTexts[] = $sum['cost_text'];
            }
            $departureTime = end($segments)['arrival_time'] ?: $departureTime;

            if ($firstTarget !== null) {
                $sum = appendSegment($segments, 'station_to_first', $originStation, $firstTarget, $departureTime, null);
                $totalSeconds += $sum['duration_seconds'];
                if (!empty($sum['cost_text'])) {
                    $costTexts[] = $sum['cost_text'];
                }
                $departureTime = end($segments)['arrival_time'] ?: $departureTime;
            }
        } elseif ($firstTarget !== null) {
            $sum = appendSegment($segments, 'start_direct', $origin, $firstTarget, $departureTime, null);
            $totalSeconds += $sum['duration_seconds'];
            if (!empty($sum['cost_text'])) {
                $costTexts[] = $sum['cost_text'];
            }
            $departureTime = end($segments)['arrival_time'] ?: $departureTime;
        }

        for ($i = 0; $i < count($quests); $i++) {
            if ($i > 0) {
                $fromQuest = [
                    'label' => $quests[$i - 1]['place'] ?? ('クエスト' . $i),
                    'address' => $quests[$i - 1]['area'] ?? ($quests[$i - 1]['place'] ?? ''),
                ];
                $toQuest = [
                    'label' => $quests[$i]['place'] ?? ('クエスト' . ($i + 1)),
                    'address' => $quests[$i]['area'] ?? ($quests[$i]['place'] ?? ''),
                ];

                $sum = appendSegment($segments, 'quest_move', $fromQuest, $toQuest, null, null);
                $totalSeconds += $sum['duration_seconds'];
                if (!empty($sum['cost_text'])) {
                    $costTexts[] = $sum['cost_text'];
                }

                if (!empty($sum['duration_text'])) {
                    $quests[$i]['travel_time_from_previous'] = $sum['duration_text'] . (!empty($sum['cost_text']) ? ' / ' . $sum['cost_text'] : '');
                }
            } else {
                if (!empty($segments)) {
                    $firstTravel = $segments[count($segments) - 1];
                    if (!empty($firstTravel['duration_text'])) {
                        $quests[$i]['travel_time_from_previous'] = $firstTravel['duration_text'] . (!empty($firstTravel['cost_text']) ? ' / ' . $firstTravel['cost_text'] : '');
                    }
                }
            }
        }

        $plan['quests'] = $quests;

        if (!empty($goal)) {
            $lastQuestOrStart = !empty($quests)
                ? [
                    'label' => end($quests)['place'] ?? 'クエスト3',
                    'address' => end($quests)['area'] ?? (end($quests)['place'] ?? ''),
                ]
                : ($originStation ?? $origin);

            $goalNode = [
                'label' => $goal['name'] ?? 'ゴール',
                'address' => $goal['area'] ?? ($goal['name'] ?? ''),
            ];

            $sum = appendSegment($segments, 'to_goal', $lastQuestOrStart, $goalNode, null, null);
            $totalSeconds += $sum['duration_seconds'];
            if (!empty($sum['cost_text'])) {
                $costTexts[] = $sum['cost_text'];
            }

            $goalStation = null;
            if (!empty($goal['nearest_station']) && is_array($goal['nearest_station'])) {
                $goalStation = [
                    'label' => $goal['nearest_station']['label'] ?? '',
                    'address' => $goal['nearest_station']['address'] ?? '',
                ];
            }

            $returnArrival = buildDateTimeString($returnDate, '18:00:00');

            if ($goalStation !== null) {
                $sum = appendSegment($segments, 'goal_to_station', $goalNode, $goalStation, null, null);
                $totalSeconds += $sum['duration_seconds'];
                if (!empty($sum['cost_text'])) {
                    $costTexts[] = $sum['cost_text'];
                }

                $sum = appendSegment($segments, 'station_to_home', $goalStation, $origin, null, $returnArrival);
                $totalSeconds += $sum['duration_seconds'];
                if (!empty($sum['cost_text'])) {
                    $costTexts[] = $sum['cost_text'];
                }
            } else {
                $sum = appendSegment($segments, 'goal_to_home', $goalNode, $origin, null, $returnArrival);
                $totalSeconds += $sum['duration_seconds'];
                if (!empty($sum['cost_text'])) {
                    $costTexts[] = $sum['cost_text'];
                }
            }
        }

        $plan['route_segments'] = $segments;
        $plan['has_step_details'] = true;
        $plan['route_step_count'] = array_sum(array_map(
            fn($seg) => is_array($seg['step_details'] ?? null) ? count($seg['step_details']) : 0,
            $segments
        ));
        $plan['total_travel_time_text'] = formatDurationJa($totalSeconds . 's');

        $costTexts = array_values(array_unique(array_filter($costTexts)));
        if (!empty($costTexts)) {
            $plan['total_cost_estimate_text'] = '移動費目安: ' . implode(' / ', $costTexts);
        }
    }
    unset($plan);

    return $plans;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson([
        'success' => false,
        'error' => 'POSTでアクセスしてください。'
    ], 405);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    respondJson([
        'success' => false,
        'error' => 'JSON形式のリクエストボディが必要です。'
    ], 400);
}

$input = [
    'departurePrefecture' => normalizeString($data, 'departurePrefecture'),
    'departureCity' => normalizeString($data, 'departureCity'),
    'destinationPrefecture' => normalizeString($data, 'destinationPrefecture'),
    'destinationCity' => normalizeString($data, 'destinationCity'),
    'destinationKeyword' => normalizeString($data, 'destinationKeyword'),
    'departureDate' => normalizeString($data, 'departureDate'),
    'duration' => normalizeString($data, 'duration'),
    'people' => normalizeString($data, 'people'),
    'budgetMin' => normalizeBudget($data, 'budgetMin'),
    'budgetMax' => normalizeBudget($data, 'budgetMax'),
];

$errors = [];

if ($input['departurePrefecture'] === '') {
    $errors[] = '出発地（都道府県）を入力してください。';
}
if ($input['destinationPrefecture'] === '') {
    $errors[] = '旅行先（都道府県）を入力してください。';
}
if ($input['departureDate'] === '') {
    $errors[] = '出発日を入力してください。';
}
if ($input['duration'] === '') {
    $errors[] = '期間を入力してください。';
}
if ($input['people'] === '') {
    $errors[] = '人数を入力してください。';
}
if ($input['budgetMin'] !== '' && !is_numeric($input['budgetMin'])) {
    $errors[] = '予算（下限）は数値で入力してください。';
}
if ($input['budgetMax'] !== '' && !is_numeric($input['budgetMax'])) {
    $errors[] = '予算（上限）は数値で入力してください。';
}
if (
    $input['budgetMin'] !== '' &&
    $input['budgetMax'] !== '' &&
    is_numeric($input['budgetMin']) &&
    is_numeric($input['budgetMax']) &&
    (int)$input['budgetMin'] > (int)$input['budgetMax']
) {
    $errors[] = '予算の下限は上限以下にしてください。';
}

if (!empty($errors)) {
    respondJson([
        'success' => false,
        'error' => '入力エラー',
        'errors' => $errors,
        'input' => $input,
    ], 422);
}

$userInterestCategory = getUserInterestCategory();

try {
    $result = callOpenAI($input, $userInterestCategory);
    $result['plans'] = enrichPlansWithGooglePlaces($result['plans'], $input);
    $result['plans'] = enrichPlansWithTransitPlan($result['plans'], $input);

    respondJson([
        'success' => true,
        'input' => $input,
        'user_context' => [
            'interest_category' => $userInterestCategory,
        ],
        'data' => $result,
    ]);
} catch (Throwable $e) {
    respondJson([
        'success' => false,
        'error' => $e->getMessage(),
        'input' => $input,
    ], 500);
}
