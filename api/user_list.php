<?php
/**
 * 获取用户列表（管理员）
 * GET 参数: token(管理员token)
 */
require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';

// 验证管理员token
$validToken = false;
foreach ($WHITELIST as $phone) {
    if (md5($phone . date('Y-m-d') . 'kxb_secret_key') === $token) {
        $validToken = true;
        break;
    }
}
if (!$validToken) {
    jsonResponse(403, '无权限');
}

$users = loadUsers();
usort($users, function($a, $b) {
    return strtotime($b['registerTime']) - strtotime($a['registerTime']);
});

jsonResponse(200, '成功', $users);
