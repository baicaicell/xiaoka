<?php
/**
 * 下载计数接口
 * GET: 获取所有下载量
 * POST: 增加某个软件的下载量, 参数: key(软件标识)
 */
require_once __DIR__ . '/config.php';

$COUNT_FILE = __DIR__ . '/download_count.json';

// 初始化计数文件
if (!file_exists($COUNT_FILE)) {
    file_put_contents($COUNT_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}

function loadCounts() {
    global $COUNT_FILE;
    $content = file_get_contents($COUNT_FILE);
    return json_decode($content, true) ?: [];
}

function saveCounts($data) {
    global $COUNT_FILE;
    file_put_contents($COUNT_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 返回所有下载量
    $counts = loadCounts();
    jsonResponse(200, '成功', $counts);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 增加下载量
    $input = json_decode(file_get_contents('php://input'), true);
    $key = trim($input['key'] ?? '');

    if (empty($key)) {
        jsonResponse(400, '缺少参数');
    }

    $counts = loadCounts();
    if (!isset($counts[$key])) {
        $counts[$key] = 0;
    }
    $counts[$key]++;
    saveCounts($counts);

    jsonResponse(200, '成功', ['key' => $key, 'count' => $counts[$key]]);
} else {
    jsonResponse(400, '请求方式错误');
}
