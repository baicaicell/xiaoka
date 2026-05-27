<?php
/**
 * 获取用户信息接口
 * GET 参数: phone
 * POST: 封号/解封（管理员）
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $phone = $_GET['phone'] ?? '';
    if (empty($phone)) jsonResponse(400, '缺少参数');

    $user = getUserByPhone($phone);
    if (!$user) jsonResponse(404, '用户不存在');

    // 检查是否被封号
    if (($user['status'] ?? 'approved') === 'banned') {
        jsonResponse(403, '您的账号已被封禁，请联系客服QQ：2206729714');
    }

    jsonResponse(200, '成功', [
        'name' => $user['name'],
        'phone' => $user['phone'],
        'points' => $user['points'] ?? 0,
        'goldBeans' => $user['goldBeans'] ?? 0,
        'isMerchant' => $user['isMerchant'] ?? false,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    $phone = trim($input['phone'] ?? '');
    $action = trim($input['action'] ?? '');

    if (!verifyAdminToken($token)) jsonResponse(403, '无权限');
    if (empty($phone) || empty($action)) jsonResponse(400, '缺少参数');

    $users = loadUsers();
    foreach ($users as &$u) {
        if ($u['phone'] === $phone) {
            if ($action === 'ban') {
                $u['status'] = 'banned';
            } else {
                $u['status'] = 'approved';
            }
            saveUsers($users);

            // 发送通知
            $notifies = loadNotify();
            if ($action === 'ban') {
                $notifies[] = [
                    'id' => uniqid('noti_'),
                    'to' => $phone,
                    'title' => '账号封禁通知',
                    'content' => '您的账号已被管理员封禁，如有疑问请联系客服QQ：2206729714',
                    'read' => false,
                    'time' => date('Y-m-d H:i:s'),
                ];
            } else {
                $notifies[] = [
                    'id' => uniqid('noti_'),
                    'to' => $phone,
                    'title' => '账号解封通知',
                    'content' => '您的账号已解封，可以正常使用了',
                    'read' => false,
                    'time' => date('Y-m-d H:i:s'),
                ];
            }
            saveNotify($notifies);

            jsonResponse(200, '操作成功');
        }
    }
    jsonResponse(404, '用户不存在');
}
