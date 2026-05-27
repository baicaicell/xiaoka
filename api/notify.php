<?php
/**
 * 通知接口
 * GET: 获取通知 参数: phone
 * POST: 发送通知（管理员）/ 标记已读
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $phone = $_GET['phone'] ?? '';
    if (empty($phone)) jsonResponse(400, '缺少参数');

    $notifies = loadNotify();
    $my = array_filter($notifies, function($n) use ($phone) { return $n['to'] === $phone; });
    usort($my, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
    jsonResponse(200, '成功', array_values($my));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // 管理员发送通知
    if ($action === 'send') {
        $token = $input['token'] ?? '';
        if (!verifyAdminToken($token)) jsonResponse(403, '无权限');

        $to = trim($input['to'] ?? '');
        $title = trim($input['title'] ?? '');
        $content = trim($input['content'] ?? '');

        if (empty($to) || empty($content)) jsonResponse(400, '缺少参数');

        $notifies = loadNotify();

        // to可以是 'all' 表示发给所有人
        if ($to === 'all') {
            $users = loadUsers();
            foreach ($users as $user) {
                $notifies[] = [
                    'id' => uniqid('noti_'),
                    'to' => $user['phone'],
                    'title' => $title ?: '系统通知',
                    'content' => $content,
                    'read' => false,
                    'time' => date('Y-m-d H:i:s'),
                ];
            }
        } else {
            $notifies[] = [
                'id' => uniqid('noti_'),
                'to' => $to,
                'title' => $title ?: '系统通知',
                'content' => $content,
                'read' => false,
                'time' => date('Y-m-d H:i:s'),
            ];
        }
        saveNotify($notifies);
        jsonResponse(200, '发送成功');
    }

    // 标记已读
    if ($action === 'read') {
        $phone = trim($input['phone'] ?? '');
        $notifyId = trim($input['notifyId'] ?? '');

        $notifies = loadNotify();
        foreach ($notifies as &$n) {
            if ($notifyId === 'all') {
                if ($n['to'] === $phone) $n['read'] = true;
            } else {
                if ($n['id'] === $notifyId) { $n['read'] = true; break; }
            }
        }
        saveNotify($notifies);
        jsonResponse(200, '已读');
    }

    jsonResponse(400, '未知操作');
}
