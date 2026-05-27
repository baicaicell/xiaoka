<?php
/**
 * 删除软件接口
 * POST 参数: id(软件ID), token(验证token)
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(400, '请求方式错误');
}

$input = json_decode(file_get_contents('php://input'), true);
$id = trim($input['id'] ?? '');
$token = trim($input['token'] ?? '');

// 验证token
$validToken = false;
foreach ($WHITELIST as $phone) {
    if (md5($phone . date('Y-m-d') . 'kxb_secret_key') === $token) {
        $validToken = true;
        break;
    }
}
if (!$validToken) {
    jsonResponse(403, '身份验证失败，请重新登录');
}

if (empty($id)) {
    jsonResponse(400, '参数错误');
}

$data = loadData();
$newData = [];
$deleted = false;

foreach ($data as $item) {
    if ($item['id'] === $id) {
        // 删除对应文件
        $filePath = $UPLOAD_DIR . $item['fileName'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $deleted = true;
    } else {
        $newData[] = $item;
    }
}

if (!$deleted) {
    jsonResponse(404, '未找到该软件');
}

saveData($newData);
jsonResponse(200, '删除成功');
