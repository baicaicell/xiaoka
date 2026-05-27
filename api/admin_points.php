<?php
/**
 * 管理员积分/金豆管理接口
 * POST: 增加/减少用户积分或金豆
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(400, '请求方式错误');
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$phone = trim($input['phone'] ?? '');
$type = trim($input['type'] ?? 'points'); // points 或 goldBeans
$amount = intval($input['amount'] ?? 0);

if (!verifyAdminToken($token)) {
    jsonResponse(403, '无权限');
}

if (empty($phone)) jsonResponse(400, '请输入手机号');
if ($amount === 0) jsonResponse(400, '数量不能为0');

$users = loadUsers();
$found = false;
foreach ($users as &$u) {
    if ($u['phone'] === $phone) {
        $found = true;
        if ($type === 'goldBeans') {
            $u['goldBeans'] = ($u['goldBeans'] ?? 0) + $amount;
            if ($u['goldBeans'] < 0) $u['goldBeans'] = 0;
        } else {
            $u['points'] = ($u['points'] ?? 0) + $amount;
            if ($u['points'] < 0) $u['points'] = 0;
        }
        saveUsers($users);

        $typeText = $type === 'goldBeans' ? '金豆' : '积分';
        $actionText = $amount > 0 ? '增加' : '减少';

        // 发送通知
        $notifies = loadNotify();
        $notifies[] = [
            'id' => uniqid('noti_'),
            'to' => $phone,
            'title' => $typeText . '变动通知',
            'content' => "管理员为您{$actionText}了" . abs($amount) . "{$typeText}，当前{$typeText}：" . ($type === 'goldBeans' ? $u['goldBeans'] : $u['points']),
            'read' => false,
            'time' => date('Y-m-d H:i:s'),
        ];
        saveNotify($notifies);

        jsonResponse(200, '操作成功', [
            'points' => $u['points'] ?? 0,
            'goldBeans' => $u['goldBeans'] ?? 0,
        ]);
    }
}

if (!$found) jsonResponse(404, '用户不存在');
