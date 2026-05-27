<?php
/**
 * 用户登录接口
 * POST 参数: name(账户名), password(密码)
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(400, '请求方式错误');
}

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($name)) {
    jsonResponse(400, '请输入账户名');
}

if (empty($password)) {
    jsonResponse(400, '请输入密码');
}

$users = loadUsers();

foreach ($users as $user) {
    if ($user['name'] === $name) {
        // 检查是否被封号
        if (($user['status'] ?? 'approved') === 'banned') {
            jsonResponse(403, '您的账号已被封禁，请联系客服QQ：2206729714');
        }
        // 兼容旧用户（没有password字段的）
        if (!isset($user['password']) || $user['password'] === md5($password)) {
            jsonResponse(200, '登录成功', [
                'name' => $user['name'],
                'phone' => $user['phone'],
                'points' => $user['points'] ?? 0,
                'goldBeans' => $user['goldBeans'] ?? 0,
                'isMerchant' => $user['isMerchant'] ?? false,
            ]);
        } else {
            jsonResponse(403, '密码错误');
        }
    }
}

jsonResponse(404, '该账户名未注册，请先注册');
