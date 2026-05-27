<?php
/**
 * 审核操作接口
 * POST 参数: id(软件ID), action(approve/reject), token(验证token)
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(400, '请求方式错误');
}

$input = json_decode(file_get_contents('php://input'), true);
$id = trim($input['id'] ?? '');
$action = trim($input['action'] ?? '');
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

if (empty($id) || !in_array($action, ['approve', 'reject'])) {
    jsonResponse(400, '参数错误');
}

$data = loadData();
$found = false;
foreach ($data as &$item) {
    if ($item['id'] === $id) {
        $item['status'] = ($action === 'approve') ? 'approved' : 'rejected';
        $item['reviewTime'] = date('Y-m-d H:i:s');
        $found = true;
        break;
    }
}
unset($item);

if (!$found) {
    jsonResponse(404, '未找到该软件');
}

saveData($data);
jsonResponse(200, $action === 'approve' ? '已通过审核' : '已拒绝');
