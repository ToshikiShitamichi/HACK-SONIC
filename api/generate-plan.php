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
ユーザー入力と候補スポット情報をもとに、旅行プラン候補を3つ、日本語で提案してください。

必須条件:
- 必ず JSON のみを返すこと
- JSON以外の文章は一切含めないこと
- 候補は3件
- quests は必ず5件にすること
- goal と quests で同じ場所を使わないこと
- ゴールや quest に含めるスポット・店舗は、必ず実在する固有名詞を前提にすること
- 各スポットには、わかる範囲で area を入れること
- 予算感はユーザー予算にできるだけ合わせること
- questに1件スウィーツに関するものを入れること
- 観光順は意識しなくてよい
- 経路検索や時系列の行程は前提にしないこと
- travel_time_from_previous は固定文言でもよい

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
- travel_time_from_previous は「観光順未考慮」など自然な日本語で記載してよい
- estimated_cost は「1人あたり」「グループ合計」などが分かる自然な日本語で記載
- total_travel_time_text は「経路検索なし」などでもよい
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
- 各スポットや店は、実在する固有名詞を優先して提案する
- 店名や施設名は、チェーン名だけでなく候補店舗が想像できる粒度で具体化する
- キーワードがあれば、雰囲気や立ち寄り先に反映する
- ゴールとクエストは同じ施設・同じ店にしないこと
- クエストは必ず5件作成すること
- 観光順は意識しないこと
- 経路検索や移動順ベースの提案にはしないこと

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

function enrichPlansWithGooglePlaces(array $plans, array $input): array
{
    foreach ($plans as &$plan) {
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
            }
            unset($quest);
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
