<?php
/**
 * 白名单验证接口
 * POST 参数: phone(手机号)
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(400, '请求方式错误');
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? '');

if (empty($phone)) {
    jsonResponse(400, '请输入手机号');
}

if (!in_array($phone, $WHITELIST)) {
    jsonResponse(403, '无权限访问，该手机号不在白名单中');
}

// 生成简单token（有效期24小时）
$token = md5($phone . date('Y-m-d') . 'kxb_secret_key');

jsonResponse(200, '验证成功', ['token' => $token]);
