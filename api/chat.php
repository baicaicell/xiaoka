<?php
/**
 * 站内聊天接口
 * GET: 获取聊天列表 / 获取对话消息
 * POST: 发送消息
 */
require_once __DIR__ . '/config.php';

// 聊天数据文件
$CHAT_DATA_FILE = __DIR__ . '/chat_data.json';
if (!file_exists($CHAT_DATA_FILE)) {
    file_put_contents($CHAT_DATA_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}

function loadChats() {
    global $CHAT_DATA_FILE;
    return json_decode(file_get_contents($CHAT_DATA_FILE), true) ?: [];
}
function saveChats($data) {
    global $CHAT_DATA_FILE;
    file_put_contents($CHAT_DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $phone = $_GET['phone'] ?? '';
    $target = $_GET['target'] ?? '';

    if (empty($phone)) jsonResponse(400, '缺少参数');

    $chats = loadChats();

    // 获取与某人的对话记录
    if ($target) {
        $conversation = array_filter($chats, function($msg) use ($phone, $target) {
            return ($msg['from'] === $phone && $msg['to'] === $target) ||
                   ($msg['from'] === $target && $msg['to'] === $phone);
        });
        usort($conversation, function($a, $b) { return strtotime($a['time']) - strtotime($b['time']); });

        // 标记为已读
        $changed = false;
        foreach ($chats as &$msg) {
            if ($msg['from'] === $target && $msg['to'] === $phone && !$msg['read']) {
                $msg['read'] = true;
                $changed = true;
            }
        }
        if ($changed) saveChats($chats);

        jsonResponse(200, '成功', array_values($conversation));
    }

    // 获取聊天列表（所有跟我有对话的人）
    $contacts = [];
    foreach ($chats as $msg) {
        if ($msg['from'] === $phone) {
            $other = $msg['to'];
        } elseif ($msg['to'] === $phone) {
            $other = $msg['from'];
        } else {
            continue;
        }

        if (!isset($contacts[$other])) {
            $otherUser = getUserByPhone($other);
            $contacts[$other] = [
                'phone' => $other,
                'name' => $otherUser ? $otherUser['name'] : $other,
                'lastMsg' => $msg['content'],
                'lastTime' => $msg['time'],
                'unread' => 0,
            ];
        }
        // 更新最后一条消息
        if (strtotime($msg['time']) >= strtotime($contacts[$other]['lastTime'])) {
            $contacts[$other]['lastMsg'] = $msg['content'];
            $contacts[$other]['lastTime'] = $msg['time'];
        }
        // 统计未读
        if ($msg['to'] === $phone && !$msg['read']) {
            $contacts[$other]['unread']++;
        }
    }

    // 按最后消息时间排序
    usort($contacts, function($a, $b) { return strtotime($b['lastTime']) - strtotime($a['lastTime']); });
    jsonResponse(200, '成功', array_values($contacts));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $from = trim($input['from'] ?? '');
    $to = trim($input['to'] ?? '');
    $content = trim($input['content'] ?? '');

    if (empty($from) || empty($to) || empty($content)) {
        jsonResponse(400, '缺少参数');
    }

    $fromUser = getUserByPhone($from);
    if (!$fromUser) jsonResponse(404, '用户不存在');

    $chats = loadChats();
    $chats[] = [
        'id' => uniqid('msg_'),
        'from' => $from,
        'fromName' => $fromUser['name'],
        'to' => $to,
        'content' => $content,
        'read' => false,
        'time' => date('Y-m-d H:i:s'),
    ];
    saveChats($chats);

    jsonResponse(200, '发送成功');
}
