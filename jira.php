<?php

function loadEnv($path) {
    if (!file_exists($path)) {
        die(".env file not found at $path");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;

        if (!str_contains($line, '='))
            continue;

        list($name, $value) = explode('=', $line, 2);

        $name = trim($name);
        $value = trim($value);

        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

loadEnv(__DIR__ . '/.env');

function getEnvVar($key) {
    return $_ENV[$key] ?? getenv($key);
}

function jiraPostRequest($url, $payload) {
    $email = getenv('JIRA_EMAIL');
    $token = getenv('JIRA_API_TOKEN');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic " . base64_encode("$email:$token"),
            "Accept: application/json",
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function jiraGetRequest($url) {
    $email = getenv('JIRA_EMAIL');
    $token = getenv('JIRA_API_TOKEN');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic " . base64_encode("$email:$token"),
            "Accept: application/json"
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function getWorklogs($from, $to) {

    $cacheKey = "worklogs_$from.'_'.$to";

    if ($cached = cache($cacheKey)) {
        return $cached;
    }
    $hasError = false;
    $results = [];
    $totalSeconds = 0;
    $fromDate = strtotime($from);
    $toDate = strtotime($to . ' 23:59:59');

    $jql = "worklogAuthor = currentUser() AND worklogDate >= \"$from\" AND worklogDate <= \"$to\"";
    $base = getEnvVar('JIRA_BASE_URL');

    $searchUrl = "$base/rest/api/3/search/jql";

    $data = jiraPostRequest($searchUrl, [
        "jql" => $jql,
        "maxResults" => 10,
        "fields" => ["summary", "worklog"]
    ]);

    if (!isset($data['issues']) || !is_array($data['issues'])) {
        $hasError = true;
        return [
            'logs' => [],
            'total' => 0,
            'error' => 'Invalid Jira response'
        ];
    }

    foreach ($data['issues'] as $issue) {

        $key = $issue['key'] ?? null;
        if (!$key)
            continue;

        $summary = $issue['fields']['summary'] ?? 'No summary';

        $worklogsUrl = "$base/rest/api/3/issue/$key/worklog";

        $worklogsData = jiraGetRequest($worklogsUrl);

        if (!isset($worklogsData['worklogs']) || !is_array($worklogsData['worklogs'])) {
            continue;
        }

        foreach ($worklogsData['worklogs'] as $log) {

            if (!isset($log['started'], $log['timeSpentSeconds']))
                continue;

            $started = strtotime($log['started']);
            if (!$started || $started < $fromDate || $started > $toDate)
                continue;

            $seconds = (int) $log['timeSpentSeconds'];
            $totalSeconds += $seconds;

            $comment = '';
            if (!empty($log['comment']['content'])) {
                foreach ($log['comment']['content'] as $block) {
                    if (!empty($block['content'])) {
                        foreach ($block['content'] as $c) {
                            if (isset($c['text'])) {
                                $comment .= $c['text'] . ' ';
                            }
                        }
                    }
                }
            }

            $results[] = [
                'key' => $key,
                'summary' => $summary,
                'date' => date('Y-m-d', $started),
                'user' => $log['author']['displayName'] ?? '',
                'hours' => round($seconds / 3600, 2),
                'comment' => trim($comment) ?: '-'
            ];
        }
    }
    $final = [
        'logs' => $results,
        'total' => round($totalSeconds / 3600, 2)
    ];

    if (!$hasError) {
        cache($cacheKey, $final, 300);
    }

    return $final;
}

function cache($key, $data = null, $ttl = 300) {
    $file = __DIR__ . '/cache_' . md5($key) . '.json';

    if ($data === null) {
        if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
            return json_decode(file_get_contents($file), true);
        }
        return null;
    }

    file_put_contents($file, json_encode($data));
}

function clearCache() {
    foreach (glob(__DIR__ . '/cache_*.json') as $file) {
        unlink($file);
    }
}
