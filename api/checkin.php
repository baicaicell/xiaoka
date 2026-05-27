<?php
/**
 * 签到接口
 * POST: 执行签到 参数: phone
 * GET: 获取签到信息 参数: phone
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $phone = $_GET['phone'] ?? '';
    if (empty($phone)) jsonResponse(400, '缺少参数');

    $checkins = loadCheckins();
    $userCheckin = $checkins[$phone] ?? ['records' => [], 'streak' => 0];
    $user = getUserByPhone($phone);
    $points = $user['points'] ?? 0;
    $goldBeans = $user['goldBeans'] ?? 0;

    jsonResponse(200, '成功', [
        'records' => $userCheckin['records'],
        'streak' => $userCheckin['streak'] ?? 0,
        'points' => $points,
        'goldBeans' => $goldBeans,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $phone = trim($input['phone'] ?? '');

    if (empty($phone)) jsonResponse(400, '缺少参数');

    $user = getUserByPhone($phone);
    if (!$user) jsonResponse(404, '用户不存在');

    $checkins = loadCheckins();
    $userCheckin = $checkins[$phone] ?? ['records' => [], 'streak' => 0];
    $today = date('Y-m-d');

    // 检查今天是否已签到
    if (in_array($today, $userCheckin['records'])) {
        jsonResponse(400, '今天已经签到过了');
    }

    // 计算连续签到天数
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    if (in_array($yesterday, $userCheckin['records'])) {
        $userCheckin['streak'] = ($userCheckin['streak'] ?? 0) + 1;
    } else {
        $userCheckin['streak'] = 1;
    }

    // 计算积分：第1天1分，第2天2分...第7天7分，之后固定7分
    $streak = $userCheckin['streak'];
    $earnPoints = min($streak, 7);

    // 记录签到
    $userCheckin['records'][] = $today;
    $checkins[$phone] = $userCheckin;
    saveCheckins($checkins);

    // 增加用户积分
    $users = loadUsers();
    foreach ($users as &$u) {
        if ($u['phone'] === $phone) {
            $u['points'] = ($u['points'] ?? 0) + $earnPoints;
            break;
        }
    }
    saveUsers($users);

    $updatedUser = getUserByPhone($phone);

    jsonResponse(200, '签到成功', [
        'earnPoints' => $earnPoints,
        'streak' => $userCheckin['streak'],
        'totalPoints' => $updatedUser['points'] ?? 0,
    ]);
}
