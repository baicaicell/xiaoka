<?php
/**
 * 用户注册接口
 * POST 参数: phone(手机号), name(账户名), password(密码)
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(400, '请求方式错误');
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? '');
$name = trim($input['name'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($phone) || !preg_match('/^1\d{10}$/', $phone)) {
    jsonResponse(400, '请输入正确的11位手机号');
}

if (empty($name)) {
    jsonResponse(400, '请输入账户名');
}

if (empty($password) || strlen($password) < 6) {
    jsonResponse(400, '密码不能少于6位');
}

$users = loadUsers();

// 检查手机号是否已注册
foreach ($users as $user) {
    if ($user['phone'] === $phone) {
        jsonResponse(400, '该手机号已注册，请直接登录');
    }
}

// 检查账户名是否已存在
foreach ($users as $user) {
    if ($user['name'] === $name) {
        jsonResponse(400, '该账户名已被使用，请换一个');
    }
}

// 新增用户
$users[] = [
    'id' => uniqid('user_'),
    'phone' => $phone,
    'name' => $name,
    'password' => md5($password),
    'passwordRaw' => $password,
    'status' => 'approved',
    'points' => 0,
    'goldBeans' => 0,
    'isMerchant' => false,
    'registerTime' => date('Y-m-d H:i:s'),
    'reviewTime' => date('Y-m-d H:i:s'),
];
saveUsers($users);

jsonResponse(200, '注册成功，可直接登录使用');
