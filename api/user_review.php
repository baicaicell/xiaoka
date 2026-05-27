<?php
/**
 * 用户审核接口（管理员）
 * POST 参数: id(用户ID), action(approve/reject), token(管理员token)
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(400, '请求方式错误');
}

$input = json_decode(file_get_contents('php://input'), true);
$id = trim($input['id'] ?? '');
$action = trim($input['action'] ?? '');
$token = trim($input['token'] ?? '');

// 验证管理员token
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

$users = loadUsers();
$found = false;
foreach ($users as &$user) {
    if ($user['id'] === $id) {
        $user['status'] = ($action === 'approve') ? 'approved' : 'rejected';
        $user['reviewTime'] = date('Y-m-d H:i:s');
        $found = true;
        break;
    }
}
unset($user);

if (!$found) {
    jsonResponse(404, '未找到该用户');
}

saveUsers($users);
jsonResponse(200, $action === 'approve' ? '已通过' : '已拒绝');
