<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

/**
 * .env を簡易読み込み
 */
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

        // 先頭と末尾のクォートを除去
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

function getUserInterestCategory(): string
{
    // 将来はユーザープロフィールやDBから取得
    // 未実装のため .env を参照し、なければデフォルトで「グルメ」
    return getEnvValue('USER_DEFAULT_INTEREST', 'グルメ');
}

/**
 * OpenAI API を呼び出して旅行プランを生成
 *
 * @throws RuntimeException
 */
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
ユーザー入力をもとに、旅行プラン候補を3つ、日本語で提案してください。

必須条件:
- 必ず JSON のみを返すこと
- JSON以外の文章は一切含めないこと
- 候補は3件
- 旅行先の詳細が未定の場合は、そのエリア内で観光スポットになりそうな場所をゴールに設定する
- ユーザーの気になるカテゴリを反映したプランにする
- 各候補には quests を3〜5件含める
- 各 quest は以下の項目を持つこと:
  - title
  - description
  - exp
  - difficulty
  - place
  - image
- exp は 10〜300 の10刻み
- difficulty は easy / normal / hard のいずれか
  - easy: 10〜100
  - normal: 110〜200
  - hard: 210〜300
- image は取得できない場合は null
- 現実的な移動・観光を意識する
- 予算感はユーザーの予算範囲にできるだけ合わせる
- 出力JSONは次の形に厳密に合わせること

{
  "plans": [
    {
      "plan_title": "string",
      "concept": "string",
      "summary": "string",
      "budget_estimate": {
        "min": 0,
        "max": 0
      },
      "goal": {
        "name": "string",
        "description": "string",
        "area": "string",
        "image": null
      },
      "quests": [
        {
          "title": "string",
          "description": "string",
          "exp": 30,
          "difficulty": "easy",
          "place": "string",
          "image": null
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

【プラン方針】
- 気になるカテゴリは強めに反映する
- 気になるカテゴリが「グルメ」の場合は、食べ歩き、名物料理、市場、人気店エリア、カフェなどを適度に含める
- キーワードがあれば、無理のない範囲で雰囲気や立ち寄り先に反映する
- 各プランは少しずつ特色を変える
  - 王道
  - グルメ重視
  - 散策・穴場寄り
PROMPT;

    $payload = [
        'model' => $model,
        'temperature' => 0.9,
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
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
        CURLOPT_TIMEOUT => 60,
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
    $plans = callOpenAI($input, $userInterestCategory);

    respondJson([
        'success' => true,
        'input' => $input,
        'user_context' => [
            'interest_category' => $userInterestCategory,
        ],
        'data' => $plans,
    ]);
} catch (Throwable $e) {
    respondJson([
        'success' => false,
        'error' => $e->getMessage(),
        'input' => $input,
    ], 500);
}