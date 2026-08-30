<?php
declare(strict_types=1);

/*
 * Single-file Media Downloader API — PHP 8.1+
 * Instagram: SaveFromIns parser
 * GitHub: files, repositories and releases (no API key)
 * YouTube: set YOUTUBE_API_URL to a compatible no-key provider endpoint
 */

const RATE_LIMIT_PER_MINUTE = 30;
const YOUTUBE_VIDEOLY_API_URL = 'https://api.ytbvideoly.com/api/thirdvideo/parse';
const INSTAGRAM_API_URL = 'https://api.savefromins.com/api/contentsite_api/media/parse';
// Public request parameter currently used by the upstream website; not a private project key.
const INSTAGRAM_PUBLIC_AUTH = '20250901majwlqo';
const INSTAGRAM_DOMAIN = 'api-ak.savefromins.com';
const API_VERSION = '1.0.1-hafez-fix';
const DATA_DIR = __DIR__ . '/data';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function fail(string $message, string $code, int $status = 400, array $details = []): never
{
    $error = ['code' => $code, 'message' => $message];
    if ($details !== []) $error['details'] = $details;
    respond(['ok' => false, 'error' => $error], $status);
}

function rateLimit(): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir = sys_get_temp_dir() . '/iva-api';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $file = $dir . '/' . hash('sha256', $ip . '|' . floor(time() / 60));
    $handle = @fopen($file, 'c+');
    if (!$handle) return;
    try {
        flock($handle, LOCK_EX);
        $count = (int) stream_get_contents($handle);
        if ($count >= RATE_LIMIT_PER_MINUTE) fail('تعداد درخواست بیش از حد مجاز است.', 'RATE_LIMITED', 429);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) ($count + 1));
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function inputUrl(): string
{
    return trim((string) ($_GET['url'] ?? ''));
}

function request(string $url, string $method = 'GET', array $headers = [], ?string $body = null, bool $softFail = false): array
{
    if (!function_exists('curl_init')) fail('افزونه cURL روی هاست فعال نیست.', 'CURL_MISSING', 500);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 Chrome/137.0 Mobile Safari/537.36',
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false || $error !== '') {
        if ($softFail) throw new RuntimeException('upstream_connection_error');
        fail('ارتباط با سرویس مقصد برقرار نشد.', 'UPSTREAM_CONNECTION_ERROR', 502);
    }
    return ['status' => $status, 'body' => (string) $response];
}

function validPlatformUrl(string $url): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
        fail('یک لینک معتبر با https ارسال کنید.', 'INVALID_URL', 422);
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host) ?: $host;
    $allowed = ['instagram.com', 'youtube.com', 'm.youtube.com', 'youtu.be', 'github.com', 'raw.githubusercontent.com'];
    if (!in_array($host, $allowed, true)) fail('دامنه این لینک پشتیبانی نمی‌شود.', 'UNSUPPORTED_HOST', 422);
    return [$url, $host];
}

function decodeJavascriptHtml(string $value): string
{
    // DownloadGram escapes HTML characters as \x20, \x22, etc.
    $value = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', static function (array $m): string {
        return chr(hexdec($m[1]));
    }, $value) ?? $value;
    $value = str_replace(['\\/', '\\"', "\\'"], ['/', '"', "'"], $value);
    $decoded = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', static function (array $m): string {
        $code = hexdec($m[1]);
        if ($code < 0x80) return chr($code);
        if ($code < 0x800) return chr(0xC0 | ($code >> 6)) . chr(0x80 | ($code & 0x3F));
        return chr(0xE0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 0x3F)) . chr(0x80 | ($code & 0x3F));
    }, $value);
    return html_entity_decode($decoded ?? $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function normalizeMediaUrl(string $url): string
{
    $url = decodeJavascriptHtml($url);
    // The upstream occasionally inserts spaces after token= in generated HTML.
    $url = preg_replace('/(?<=token=)\s+/', '', $url) ?? $url;
    return trim($url);
}

function isMediaUrl(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $path = strtolower((string) parse_url($url, PHP_URL_PATH));
    $mediaHost = str_contains($host, 'cdninstagram.com')
        || str_contains($host, 'fbcdn.net')
        || str_contains($host, 'downloadgram.org');
    $mediaExtension = (bool) preg_match('/\.(mp4|mov|webm|jpe?g|png|webp)(?:$|\?)/i', $url);
    return $mediaHost || $mediaExtension || str_contains($path, '/video/');
}

function mediaType(string $url): string
{
    $path = strtolower((string) parse_url($url, PHP_URL_PATH));
    if (preg_match('/\.(mp4|mov|webm)$/i', $path)) return 'video';
    if (preg_match('/\.(jpe?g|png|webp)$/i', $path)) return 'image';
    return 'video_or_image';
}

function instagram(string $url): array
{
    $primaryError = null;
    try {
        return instagramSaveFromIns($url);
    } catch (RuntimeException $e) {
        $primaryError = $e->getMessage();
    }

    $fallback = instagramDownloadGram($url);
    $fallback['fallback_reason'] = $primaryError;
    return $fallback;
}

function instagramSaveFromIns(string $url): array
{
    $body = http_build_query([
        'auth' => (getenv('IVA_INSTAGRAM_AUTH') ?: INSTAGRAM_PUBLIC_AUTH),
        'domain' => INSTAGRAM_DOMAIN,
        'origin' => 'source',
        'link' => rtrim($url, '/'),
    ]);
    $response = request(INSTAGRAM_API_URL, 'POST', [
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://savefromins.com',
        'Referer: https://savefromins.com/',
    ], $body, true);
    $json = json_decode($response['body'], true);
    if ($response['status'] !== 200 || !is_array($json)) throw new RuntimeException('savefromins_invalid_json');
    if (($json['status'] ?? 0) != 1 || !isset($json['data']) || !is_array($json['data'])) {
        throw new RuntimeException('savefromins_' . (string) ($json['status_code'] ?? 'parse_error'));
    }

    $data = $json['data'];
    $items = [];
    foreach ($data['resources'] ?? [] as $resource) {
        if (!is_array($resource)) continue;
        $downloadUrl = (string) ($resource['download_url'] ?? '');
        if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) continue;
        $type = ($resource['type'] ?? '') === 'picture' ? 'image' : (string) ($resource['type'] ?? 'file');
        $items[] = [
            'id' => $resource['resource_id'] ?? null,
            'type' => $type,
            'format' => $resource['format'] ?? $resource['original_format'] ?? null,
            'quality' => $resource['quality'] ?? null,
            'size' => $resource['size'] ?? null,
            'download_mode' => $resource['download_mode'] ?? null,
            'url' => $downloadUrl,
        ];
    }
    if ($items === []) throw new RuntimeException('savefromins_no_media');

    return [
        'platform' => 'instagram',
        'provider' => 'savefromins',
        'source_url' => $url,
        'id' => $data['id'] ?? null,
        'title' => $data['title'] ?? null,
        'thumbnail' => $data['thumbnail'] ?? null,
        'duration' => $data['duration'] ?? null,
        'published_at' => isset($data['publish_ts']) ? gmdate('c', (int) $data['publish_ts']) : null,
        'author' => $data['user_item'] ?? null,
        'items_count' => count($items),
        'items' => $items,
    ];
}

function instagramDownloadGram(string $url): array
{
    $body = http_build_query(['url' => $url, 'v' => '3', 'lang' => 'en']);
    $response = request('https://api.downloadgram.org/media', 'POST', [
        'Accept: */*',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://downloadgram.org',
        'Referer: https://downloadgram.org/',
    ], $body);
    if ($response['status'] !== 200 || $response['body'] === '') {
        fail('هر دو سرویس Instagram پاسخ نامعتبر دادند.', 'INSTAGRAM_PROVIDERS_FAILED', 502);
    }

    $html = decodeJavascriptHtml($response['body']);
    $poster = null;
    if (preg_match('/poster\s*=\s*["\']([^"\']+)["\']/i', $html, $match)) {
        $poster = normalizeMediaUrl($match[1]);
    }

    $items = [];
    if (preg_match_all('/<source[^>]+src\s*=\s*["\']([^"\']+)["\']/is', $html, $matches)) {
        foreach ($matches[1] as $mediaUrl) {
            $items[] = ['type' => 'video', 'format' => 'mp4', 'quality' => null, 'url' => normalizeMediaUrl($mediaUrl)];
        }
    }
    if (preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/is', $html, $matches)) {
        foreach ($matches[1] as $mediaUrl) {
            $items[] = ['type' => 'image', 'format' => 'jpg', 'quality' => null, 'url' => normalizeMediaUrl($mediaUrl)];
        }
    }

    $unique = [];
    foreach ($items as $item) {
        if (filter_var($item['url'], FILTER_VALIDATE_URL)) $unique[$item['url']] = $item;
    }
    if ($unique === []) fail('هر دو سرویس نتوانستند رسانه را استخراج کنند.', 'INSTAGRAM_PROVIDERS_FAILED', 502);

    return [
        'platform' => 'instagram',
        'provider' => 'downloadgram_fallback',
        'source_url' => $url,
        'thumbnail' => $poster,
        'items_count' => count($unique),
        'items' => array_values($unique),
    ];
}

function youtube(string $url): array
{
    $videoId = youtubeVideoId($url);
    if ($videoId === null) fail('شناسه ویدئوی YouTube معتبر نیست.', 'INVALID_YOUTUBE_URL', 422);

    $normalizedUrl = 'https://youtu.be/' . $videoId;
    $response = request(YOUTUBE_VIDEOLY_API_URL, 'POST', [
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://videodownloaded.com',
        'Referer: https://videodownloaded.com/',
    ], http_build_query([
        'link' => $normalizedUrl,
        'from' => 'videodownloaded',
    ]));
    $json = json_decode($response['body'], true);
    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($json)) {
        fail('پاسخ سرویس YouTube معتبر نیست.', 'YOUTUBE_UPSTREAM_ERROR', 502, ['upstream_status' => $response['status']]);
    }

    $mp4 = $json['data']['videos']['mp4'] ?? [];
    if (!is_array($mp4)) $mp4 = [];
    $items = [];
    foreach ($mp4 as $video) {
        if (!is_array($video)) continue;
        $downloadUrl = (string) ($video['url'] ?? '');
        if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) continue;
        $items[] = [
            'type' => 'video',
            'format' => 'mp4',
            'resolution' => $video['resolution'] ?? null,
            'quality' => $video['quality'] ?? $video['resolution'] ?? null,
            'size' => $video['size'] ?? null,
            'url' => $downloadUrl,
        ];
    }

    $audioSources = $json['data']['videos']['mp3'] ?? $json['data']['audios'] ?? [];
    if (is_array($audioSources)) {
        foreach ($audioSources as $audio) {
            if (!is_array($audio)) continue;
            $downloadUrl = (string) ($audio['url'] ?? '');
            if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) continue;
            $items[] = [
                'type' => 'audio',
                'format' => $audio['format'] ?? 'mp3',
                'quality' => $audio['quality'] ?? $audio['bitrate'] ?? null,
                'size' => $audio['size'] ?? null,
                'url' => $downloadUrl,
            ];
        }
    }

    if ($items === []) {
        fail((string) ($json['message'] ?? 'هیچ لینک دانلودی برای ویدئو پیدا نشد.'), 'YOUTUBE_NO_MEDIA_FOUND', 422);
    }

    return [
        'platform' => 'youtube',
        'provider' => 'ytbvideoly',
        'source_url' => $url,
        'video_id' => $videoId,
        'title' => $json['data']['title'] ?? null,
        'thumbnail' => $json['data']['thumbnail'] ?? $json['data']['cover'] ?? null,
        'duration' => $json['data']['duration'] ?? null,
        'items_count' => count($items),
        'items' => $items,
    ];
}

function youtubeVideoId(string $url): ?string
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host) ?: $host;
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    $videoId = null;

    if ($host === 'youtu.be') {
        $videoId = explode('/', $path)[0] ?? null;
    } elseif (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (isset($query['v'])) $videoId = (string) $query['v'];
        elseif (preg_match('~^(?:shorts|embed)/([A-Za-z0-9_-]{11})~', $path, $match)) $videoId = $match[1];
    }

    return is_string($videoId) && preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) ? $videoId : null;
}

function github(string $url, string $host): array
{
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    if ($host === 'raw.githubusercontent.com') {
        return ['platform' => 'github', 'type' => 'file', 'items' => [['name' => basename($path), 'url' => $url]]];
    }
    if (count($parts) < 2) fail('لینک GitHub ناقص است.', 'INVALID_GITHUB_URL', 422);
    [$owner, $repo] = [$parts[0], $parts[1]];
    if (($parts[2] ?? '') === 'blob' && isset($parts[3])) {
        $file = implode('/', array_slice($parts, 4));
        $raw = "https://raw.githubusercontent.com/{$owner}/{$repo}/{$parts[3]}/{$file}";
        return ['platform' => 'github', 'type' => 'file', 'items' => [['name' => basename($file), 'url' => $raw]]];
    }
    if (($parts[2] ?? '') === 'releases' && ($parts[3] ?? '') === 'tag' && isset($parts[4])) {
        $tag = rawurldecode($parts[4]);
        $api = request("https://api.github.com/repos/{$owner}/{$repo}/releases/tags/" . rawurlencode($tag), 'GET', ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28']);
        $data = json_decode($api['body'], true);
        if ($api['status'] !== 200 || !is_array($data)) fail('Release گیت‌هاب پیدا نشد.', 'GITHUB_RELEASE_ERROR', 502);
        $items = [];
        foreach ($data['assets'] ?? [] as $asset) {
            if (isset($asset['browser_download_url'])) $items[] = ['name' => $asset['name'] ?? 'asset', 'size' => $asset['size'] ?? null, 'url' => $asset['browser_download_url']];
        }
        $items[] = ['name' => "{$repo}-{$tag}.zip", 'url' => "https://github.com/{$owner}/{$repo}/archive/refs/tags/" . rawurlencode($tag) . '.zip'];
        return ['platform' => 'github', 'type' => 'release', 'title' => $data['name'] ?? $tag, 'items' => $items];
    }
    return ['platform' => 'github', 'type' => 'repository', 'title' => "{$owner}/{$repo}", 'items' => [
        ['name' => "{$repo}.zip", 'url' => "https://github.com/{$owner}/{$repo}/archive/HEAD.zip"],
        ['name' => "{$repo}.tar.gz", 'url' => "https://github.com/{$owner}/{$repo}/archive/HEAD.tar.gz"],
    ]];
}

function query(string $name, string $default = ''): string
{
    return trim((string) ($_GET[$name] ?? $default));
}

function requiredQuery(string $name): string
{
    $value = query($name);
    if ($value === '') fail("پارامتر {$name} الزامی است.", 'VALIDATION_ERROR', 422);
    return $value;
}

function fetchExternal(string $url, array $headers = [], int $timeout = 20): string
{
    if (!function_exists('curl_init')) fail('افزونه cURL روی هاست فعال نیست.', 'CURL_MISSING', 500);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 7,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/150.0 Safari/537.36',
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $error !== '' || $status < 200 || $status >= 400) {
        fail('دریافت اطلاعات از منبع خارجی ناموفق بود.', 'UPSTREAM_ERROR', 502, ['status' => $status]);
    }
    return (string) $body;
}

function randomLegacyStrings(string $filename, string $variable): string
{
    $path = DATA_DIR . '/' . $filename;
    $source = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($source)) fail('دیتاست پیدا نشد.', 'DATASET_MISSING', 500);
    $needle = '$' . $variable . ' = [';
    $start = strpos($source, $needle);
    if ($start === false) $start = strpos($source, '$' . $variable . '=[');
    if ($start === false) fail('ساختار دیتاست معتبر نیست.', 'DATASET_INVALID', 500);
    $end = strrpos($source, '];');
    if ($end === false || $end <= $start) fail('ساختار دیتاست کامل نیست.', 'DATASET_INVALID', 500);
    $chunk = substr($source, $start, $end - $start + 1);
    preg_match_all("/'((?:\\\\.|[^'\\\\])*)'/s", $chunk, $matches);
    $items = array_values(array_filter(array_map(static fn(string $v): string => stripcslashes($v), $matches[1] ?? []), static fn(string $v): bool => trim($v) !== ''));
    if ($items === []) fail('دیتاست خالی است.', 'DATASET_EMPTY', 500);
    return $items[array_rand($items)];
}

function randomRiddle(): array
{
    $source = file_get_contents(DATA_DIR . '/chistan.php');
    if (!is_string($source) || !preg_match("/json_decode\\('(\\{.*?\\})'\\s*\\)/s", $source, $match)) {
        fail('دیتاست چیستان معتبر نیست.', 'DATASET_INVALID', 500);
    }
    $decoded = json_decode($match[1], true);
    $items = $decoded['Result'] ?? [];
    if (!is_array($items) || $items === []) fail('دیتاست چیستان خالی است.', 'DATASET_EMPTY', 500);
    return $items[array_rand($items)];
}

function wikiSearch(): array
{
    $term = requiredQuery('q');
    $limit = min(20, max(1, (int) query('limit', '5')));
    $url = 'https://fa.wikipedia.org/w/api.php?action=query&format=json&utf8=1&list=search&srlimit=' . $limit . '&srsearch=' . rawurlencode($term);
    $json = json_decode(fetchExternal($url, ['Accept: application/json']), true);
    if (!is_array($json)) fail('پاسخ ویکی‌پدیا معتبر نیست.', 'UPSTREAM_INVALID_RESPONSE', 502);
    $items = [];
    foreach ($json['query']['search'] ?? [] as $row) {
        $title = (string) ($row['title'] ?? '');
        $items[] = [
            'title' => $title,
            'snippet' => html_entity_decode(strip_tags((string) ($row['snippet'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'page_id' => $row['pageid'] ?? null,
            'url' => 'https://fa.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title)),
        ];
    }
    return ['query' => $term, 'count' => count($items), 'items' => $items];
}

function yjcNews(): array
{
    $html = fetchExternal('https://www.yjc.ir/fa/allnews');
    preg_match_all('~<a[^>]+href="(/fa/news/([^"/]+)/[^"]+)"[^>]+title="([^"]+)"~iu', $html, $links, PREG_SET_ORDER);
    $limit = min(30, max(1, (int) query('limit', '10')));
    $items = [];
    foreach (array_slice($links, 0, $limit) as $row) {
        $items[] = ['id' => $row[2], 'title' => html_entity_decode($row[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'url' => 'https://www.yjc.ir' . $row[1]];
    }
    return ['count' => count($items), 'items' => $items];
}

function reverseText(): array
{
    $text = requiredQuery('text');
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars)) fail('متن UTF-8 معتبر نیست.', 'INVALID_TEXT', 422);
    return ['input' => $text, 'result' => implode('', array_reverse($chars))];
}

function finglish(): array
{
    $text = requiredQuery('text');
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $converted = [];
    foreach (array_slice($words, 0, 30) as $word) {
        $url = 'https://inputtools.google.com/request?text=' . rawurlencode($word) . '&itc=fa-t-i0-und&num=1&cp=0&cs=1&ie=utf-8&oe=utf-8&app=demopage';
        $json = json_decode(fetchExternal($url, ['Accept: application/json']), true);
        $converted[] = $json[1][0][1][0] ?? $word;
    }
    return ['input' => $text, 'result' => implode(' ', $converted)];
}

function validateNationalId(): array
{
    $code = preg_replace('/\D+/', '', requiredQuery('code')) ?? '';
    $valid = false;
    if (strlen($code) === 10 && count(array_unique(str_split($code))) > 1) {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) $sum += ((int) $code[$i]) * (10 - $i);
        $remainder = $sum % 11;
        $check = $remainder < 2 ? $remainder : 11 - $remainder;
        $valid = $check === (int) $code[9];
    }
    return ['code' => $code, 'valid' => $valid];
}

function dailyZekr(): array
{
    $items = [
        'Sat' => ['zekr' => 'یا رَبِّ الْعالَمِین', 'translation' => 'ای پروردگار جهانیان'],
        'Sun' => ['zekr' => 'یا ذَالجَلالِ وَ اْلاِکْرام', 'translation' => 'ای صاحب جلال و بزرگواری'],
        'Mon' => ['zekr' => 'یا قاضیَ الحاجات', 'translation' => 'ای برآورنده حاجت‌ها'],
        'Tue' => ['zekr' => 'یا أَرْحَمَ الرَّاحِمِین', 'translation' => 'ای مهربان‌ترین مهربانان'],
        'Wed' => ['zekr' => 'یا حَیُّ یا قَیّومُ', 'translation' => 'ای زنده، ای پاینده'],
        'Thu' => ['zekr' => 'لا إِلهَ إِلَّا اللَّهُ المَلِک الحقّ المُبین', 'translation' => 'نیست خدایی جز الله، فرمانروای حق و آشکار'],
        'Fri' => ['zekr' => 'الّلهُمَّ صَلِّ عَلَی مُحَمَّدٍ وَآلِ مُحَمَّدٍ و عجل فرجهم', 'translation' => 'خدایا بر محمد و آل محمد درود فرست'],
    ];
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
    return ['day' => $now->format('l'), 'date' => $now->format(DATE_ATOM), ...$items[$now->format('D')]];
}

function dateTimeInfo(): array
{
    $timezone = query('timezone', 'Asia/Tehran');
    try { $zone = new DateTimeZone($timezone); }
    catch (Throwable) { fail('منطقه زمانی معتبر نیست.', 'INVALID_TIMEZONE', 422); }
    $date = new DateTimeImmutable('now', $zone);
    return ['timezone' => $timezone, 'iso' => $date->format(DATE_ATOM), 'date' => $date->format('Y-m-d'), 'time' => $date->format('H:i:s'), 'unix' => $date->getTimestamp()];
}

function hadith(): array
{
    $html = fetchExternal('http://hadis.toolsir.com/hadis.php?bg=1&m=1,2,3,4,5,6,7,8,9,10,11,12,13,14,&time=none');
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $textNode = $xpath->query('//div[contains(@class,"txt")]')->item(0);
    $nameNode = $xpath->query('//div[contains(@class,"name")]')->item(0);
    if (!$textNode) fail('حدیث از منبع استخراج نشد.', 'UPSTREAM_PARSE_ERROR', 502);
    return ['source' => $nameNode?->textContent, 'text' => trim($textNode->textContent)];
}

function hafezFortune(): array
{
    $html = fetchExternal('https://www.hafez.it/tabir/');
    preg_match('~<source\s+src="([^"]+)"\s+type="audio/mp3"~i', $html, $audio);
    preg_match('~<h1[^>]*>\s*(غزل\s+شماره.*?)</h1>~is', $html, $number);

    $poem = [];
    if (preg_match('~<div[^>]*class="[^"]*faal_poem[^"]*"[^>]*>(.*?)</div>~is', $html, $poemBlock)) {
        preg_match_all('~<p[^>]*>(.*?)</p>~is', $poemBlock[1], $verses);
        $poem = array_values(array_filter(array_map(
            static fn(string $p): string => trim(html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $verses[1] ?? []
        )));
    }

    $interpretation = null;
    if (preg_match('~<div[^>]*class="[^"]*taabir_bottom[^"]*"[^>]*>.*?<h2[^>]*>\s*تعبیر\s+فال\s+شما\s*</h2>\s*<p[^>]*>(.*?)</p>~is', $html, $meaning)) {
        $interpretation = trim(html_entity_decode(strip_tags($meaning[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    if ($poem === [] || $interpretation === null) {
        fail('شعر یا تعبیر فال از صفحه استخراج نشد.', 'UPSTREAM_PARSE_ERROR', 502);
    }

    return [
        'number' => isset($number[1]) ? trim(html_entity_decode(strip_tags($number[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : null,
        'poem' => $poem,
        'interpretation' => $interpretation,
        'audio' => $audio[1] ?? null,
    ];
}

function tgjuMarket(): array
{
    $allowed = ['crypto-bitcoin','crypto-ethereum','crypto-tether','crypto-dogecoin','crypto-tron','price_dollar_rl','price_eur','price_gbp','price_aed','price_try','mesghal','geram18','geram24','ons','silver','platinum','sekeb','sekee','nim','rob','gerami'];
    $symbol = query('symbol', 'price_dollar_rl');
    if (!in_array($symbol, $allowed, true)) fail('نماد پشتیبانی نمی‌شود.', 'UNSUPPORTED_SYMBOL', 422, ['allowed' => $allowed]);
    $html = fetchExternal('https://www.tgju.org/profile/' . $symbol);
    preg_match_all('~<td[^>]*class="text-left"[^>]*>(.*?)</td>~is', $html, $matches);
    $values = array_values(array_filter(array_map(static fn(string $v): string => trim(strip_tags($v)), $matches[1] ?? [])));
    if ($values === []) fail('قیمت از TGJU استخراج نشد.', 'UPSTREAM_PARSE_ERROR', 502);
    return ['symbol' => $symbol, 'price' => $values[0], 'values' => array_slice($values, 0, 8), 'unit' => 'ریال'];
}

function bonbast(): array
{
    $allowed = ['USD','EUR','GBP','CHF','CAD','AUD','SEK','NOK','RUB','AZN','AED','JPY','TRY','CNY','SAR','INR','AFN','KWD','IQD','BHD','OMR','QAR'];
    $currency = strtoupper(query('currency', 'USD'));
    if (!in_array($currency, $allowed, true)) fail('ارز پشتیبانی نمی‌شود.', 'UNSUPPORTED_CURRENCY', 422, ['allowed' => $allowed]);
    $html = fetchExternal('https://bonbast.com/graph/' . strtolower($currency));
    preg_match_all('~<td\s+class="price">(.*?)</td>~is', $html, $prices);
    preg_match('~<option[^>]+selected[^>]*>(.*?)</option>~is', $html, $name);
    $p = array_map(static fn(string $v): string => trim(strip_tags($v)), $prices[1] ?? []);
    if (count($p) < 6) fail('قیمت از Bonbast استخراج نشد.', 'UPSTREAM_PARSE_ERROR', 502);
    return ['currency' => $currency, 'name' => isset($name[1]) ? trim(strip_tags($name[1])) : null, 'unit' => 'تومان', 'average' => ['sell' => $p[0], 'buy' => $p[1]], 'maximum' => ['sell' => $p[2], 'buy' => $p[3]], 'minimum' => ['sell' => $p[4], 'buy' => $p[5]]];
}

function arzDigital(): array
{
    $html = fetchExternal('https://statics.arz.digital/wp-content/themes/arz-theme/assets/js/arzCoins.js');
    if (!preg_match('/var\s+arzCoins\s*=\s*(\{.*?\});/s', $html, $match)) fail('اطلاعات ارز دیجیتال پیدا نشد.', 'UPSTREAM_PARSE_ERROR', 502);
    $data = json_decode($match[1], true);
    if (!is_array($data)) fail('پاسخ ارز دیجیتال معتبر نیست.', 'UPSTREAM_INVALID_RESPONSE', 502);
    $symbol = strtoupper(query('symbol'));
    if ($symbol !== '') {
        foreach ($data as $key => $value) if (strtoupper((string) $key) === $symbol) return ['symbol' => $key, 'data' => $value];
        fail('نماد پیدا نشد.', 'SYMBOL_NOT_FOUND', 404);
    }
    return ['count' => count($data), 'items' => $data];
}

function coronaWeekly(): array
{
    $html = fetchExternal('https://www.worldometers.info/coronavirus/weekly-trends/');
    preg_match_all('~<span[^>]*>(.*?)</span>~is', $html, $spans);
    $values = array_values(array_filter(array_map(static fn(string $v): string => trim(html_entity_decode(strip_tags($v), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $spans[1] ?? [])));
    preg_match_all('~<a[^>]+class="mt_a"[^>]*>(.*?)</a>~is', $html, $countries);
    $names = array_values(array_filter(array_map(static fn(string $v): string => trim(strip_tags($v)), $countries[1] ?? [])));
    if ($values === [] && $names === []) fail('آمار کرونا از منبع استخراج نشد.', 'UPSTREAM_PARSE_ERROR', 502);
    return [
        'source' => 'Worldometers weekly trends',
        'notice' => 'این داده برای استفاده پزشکی یا تصمیم‌گیری درمانی مناسب نیست.',
        'updated_at' => gmdate('c'),
        'summary_values' => array_slice($values, 0, 12),
        'countries_count' => count($names),
        'countries' => array_slice($names, 0, min(50, max(1, (int) query('limit', '20')))),
    ];
}

function endpointCatalog(): array
{
    return [
        ['id'=>'media','title'=>'دانلودر Instagram، YouTube و GitHub','params'=>'url'],
        ['id'=>'wikipedia','title'=>'جستجوی ویکی‌پدیای فارسی','params'=>'q, limit'],
        ['id'=>'news/yjc','title'=>'آخرین اخبار YJC','params'=>'limit'],
        ['id'=>'market/tgju','title'=>'قیمت طلا، ارز و رمزارز','params'=>'symbol'],
        ['id'=>'market/bonbast','title'=>'نرخ ارز Bonbast','params'=>'currency'],
        ['id'=>'market/crypto','title'=>'فهرست ارزهای دیجیتال','params'=>'symbol (optional)'],
        ['id'=>'finglish','title'=>'تبدیل فینگلیش به فارسی','params'=>'text'],
        ['id'=>'text/reverse','title'=>'برعکس‌کردن متن UTF-8','params'=>'text'],
        ['id'=>'national-id/validate','title'=>'اعتبارسنج کد ملی','params'=>'code'],
        ['id'=>'datetime','title'=>'تاریخ و ساعت','params'=>'timezone (optional)'],
        ['id'=>'zekr','title'=>'ذکر روز','params'=>'-'],
        ['id'=>'hadith','title'=>'حدیث تصادفی','params'=>'-'],
        ['id'=>'hafez','title'=>'فال حافظ','params'=>'-'],
        ['id'=>'corona','title'=>'روند هفتگی کرونا','params'=>'limit (optional)'],
        ['id'=>'random/bio-fa','title'=>'بیو فارسی','params'=>'-'],
        ['id'=>'random/bio-en','title'=>'بیو انگلیسی','params'=>'-'],
        ['id'=>'random/quote','title'=>'سخن بزرگان','params'=>'-'],
        ['id'=>'random/riddle','title'=>'چیستان','params'=>'-'],
        ['id'=>'random/fact','title'=>'دانستنی','params'=>'-'],
        ['id'=>'random/joke','title'=>'جوک','params'=>'-'],
    ];
}

function dispatch(string $endpoint): array
{
    return match ($endpoint) {
        'health' => ['status'=>'ok','version'=>API_VERSION,'php'=>PHP_VERSION,'time'=>gmdate('c')],
        'endpoints' => ['count'=>count(endpointCatalog()),'items'=>endpointCatalog()],
        'media' => (function (): array { [$url,$host]=validPlatformUrl(requiredQuery('url')); return $host==='instagram.com' ? instagram($url) : (in_array($host,['youtube.com','m.youtube.com','youtu.be'],true) ? youtube($url) : github($url,$host)); })(),
        'wikipedia' => wikiSearch(),
        'news/yjc' => yjcNews(),
        'market/tgju' => tgjuMarket(),
        'market/bonbast' => bonbast(),
        'market/crypto' => arzDigital(),
        'finglish' => finglish(),
        'text/reverse' => reverseText(),
        'national-id/validate' => validateNationalId(),
        'datetime' => dateTimeInfo(),
        'zekr' => dailyZekr(),
        'hadith' => hadith(),
        'hafez' => hafezFortune(),
        'corona' => coronaWeekly(),
        'random/bio-fa' => ['text'=>randomLegacyStrings('bioFA.php','ar')],
        'random/bio-en' => ['text'=>randomLegacyStrings('bioEN.php','ar')],
        'random/quote' => ['text'=>randomLegacyStrings('bozorg.php','faby')],
        'random/riddle' => randomRiddle(),
        'random/fact' => ['text'=>randomLegacyStrings('donstany.php','faby')],
        'random/joke' => ['text'=>randomLegacyStrings('joke.php','ar')],
        default => fail('Endpoint پیدا نشد.', 'ENDPOINT_NOT_FOUND', 404),
    };
}

try {
    if (!in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'OPTIONS'], true)) {
        fail('فقط متد GET پشتیبانی می‌شود.', 'METHOD_NOT_ALLOWED', 405);
    }
    rateLimit();
    $endpoint = query('endpoint', 'health');
    respond(['ok' => true, 'endpoint' => $endpoint, 'data' => dispatch($endpoint)]);
} catch (Throwable $e) {
    fail('خطای داخلی سرور.', 'SERVER_ERROR', 500);
}
