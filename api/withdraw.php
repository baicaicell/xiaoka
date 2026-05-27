<?php
/**
 * 金豆提现接口
 * GET: 获取提现记录 参数: phone / token(管理员)
 * POST: 申请提现 / 审核提现
 */
require_once __DIR__ . '/config.php';

// 提现数据文件
$WITHDRAW_DATA_FILE = __DIR__ . '/withdraw_data.json';
if (!file_exists($WITHDRAW_DATA_FILE)) {
    file_put_contents($WITHDRAW_DATA_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}

function loadWithdraws() {
    global $WITHDRAW_DATA_FILE;
    return json_decode(file_get_contents($WITHDRAW_DATA_FILE), true) ?: [];
}
function saveWithdraws($data) {
    global $WITHDRAW_DATA_FILE;
    file_put_contents($WITHDRAW_DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $phone = $_GET['phone'] ?? '';
    $token = $_GET['token'] ?? '';

    // 管理员查看所有提现申请
    if ($token && verifyAdminToken($token)) {
        $withdraws = loadWithdraws();
        usort($withdraws, function($a, $b) { return strtotime($b['applyTime']) - strtotime($a['applyTime']); });
        jsonResponse(200, '成功', $withdraws);
    }

    // 用户查看自己的提现记录
    if ($phone) {
        $withdraws = loadWithdraws();
        $my = array_filter($withdraws, function($w) use ($phone) { return $w['phone'] === $phone; });
        usort($my, function($a, $b) { return strtotime($b['applyTime']) - strtotime($a['applyTime']); });
        jsonResponse(200, '成功', array_values($my));
    }

    jsonResponse(400, '缺少参数');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查是否是文件上传（申请提现）
    if (isset($_POST['action']) && $_POST['action'] === 'apply') {
        $phone = trim($_POST['phone'] ?? '');
        $amount = intval($_POST['amount'] ?? 0);

        if (empty($phone)) jsonResponse(400, '缺少参数');
        if ($amount < 10) jsonResponse(400, '最低提现10金豆');
        if ($amount > 100) jsonResponse(400, '每天最多提现100金豆');

        $user = getUserByPhone($phone);
        if (!$user) jsonResponse(404, '用户不存在');
        if (($user['goldBeans'] ?? 0) < $amount) jsonResponse(400, '金豆不足');

        // 检查今日是否已提现超过100
        $withdraws = loadWithdraws();
        $today = date('Y-m-d');
        $todayTotal = 0;
        foreach ($withdraws as $w) {
            if ($w['phone'] === $phone && substr($w['applyTime'], 0, 10) === $today && $w['status'] !== 'rejected') {
                $todayTotal += $w['amount'];
            }
        }
        if ($todayTotal + $amount > 100) {
            jsonResponse(400, '今日提现已达上限（最多100金豆/天），已申请' . $todayTotal . '金豆');
        }

        // 处理收款码图片上传
        $qrCodePath = '';
        if (isset($_FILES['qrCode']) && $_FILES['qrCode']['error'] === UPLOAD_ERR_OK) {
            global $SHOP_UPLOAD_DIR;
            $ext = strtolower(pathinfo($_FILES['qrCode']['name'], PATHINFO_EXTENSION));
            $newName = uniqid('qr_') . '.' . $ext;
            move_uploaded_file($_FILES['qrCode']['tmp_name'], $SHOP_UPLOAD_DIR . $newName);
            $qrCodePath = 'uploads/shop/' . $newName;
        } else {
            jsonResponse(400, '请上传收款码图片');
        }

        // 冻结金豆（先扣除）
        $users = loadUsers();
        foreach ($users as &$u) {
            if ($u['phone'] === $phone) {
                $u['goldBeans'] = ($u['goldBeans'] ?? 0) - $amount;
                break;
            }
        }
        saveUsers($users);

        // 记录提现申请
        $fee = floor($amount * 0.1);
        $actual = $amount - $fee;
        $withdraws[] = [
            'id' => uniqid('wd_'),
            'phone' => $phone,
            'userName' => $user['name'],
            'amount' => $amount,
            'fee' => $fee,
            'actual' => $actual,
            'qrCode' => $qrCodePath,
            'status' => 'pending', // pending/approved/rejected
            'applyTime' => date('Y-m-d H:i:s'),
            'reviewTime' => null,
        ];
        saveWithdraws($withdraws);

        jsonResponse(200, "提现申请已提交，扣除10%手续费({$fee}金豆)，实际到账{$actual}金豆，请等待审核");
    }

    // JSON请求（管理员审核）
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'review') {
        $token = $input['token'] ?? '';
        if (!verifyAdminToken($token)) jsonResponse(403, '无权限');

        $withdrawId = trim($input['withdrawId'] ?? '');
        $result = trim($input['result'] ?? ''); // approved / rejected

        $withdraws = loadWithdraws();
        foreach ($withdraws as &$w) {
            if ($w['id'] === $withdrawId) {
                $w['status'] = $result;
                $w['reviewTime'] = date('Y-m-d H:i:s');

                // 如果拒绝，退还金豆
                if ($result === 'rejected') {
                    $users = loadUsers();
                    foreach ($users as &$u) {
                        if ($u['phone'] === $w['phone']) {
                            $u['goldBeans'] = ($u['goldBeans'] ?? 0) + $w['amount'];
                            break;
                        }
                    }
                    saveUsers($users);
                }

                saveWithdraws($withdraws);

                // 发送通知
                $notifies = loadNotify();
                if ($result === 'approved') {
                    $actual = $w['actual'] ?? ($w['amount'] - floor($w['amount'] * 0.1));
                    $notifies[] = [
                        'id' => uniqid('noti_'),
                        'to' => $w['phone'],
                        'title' => '提现成功',
                        'content' => "您的{$w['amount']}金豆提现申请已通过，扣除10%手续费，实际到账{$actual}金豆，请注意查收",
                        'read' => false,
                        'time' => date('Y-m-d H:i:s'),
                    ];
                } else {
                    $notifies[] = [
                        'id' => uniqid('noti_'),
                        'to' => $w['phone'],
                        'title' => '提现失败',
                        'content' => "您的{$w['amount']}金豆提现申请未通过，金豆已退还。如有疑问请联系客服QQ：2206729714",
                        'read' => false,
                        'time' => date('Y-m-d H:i:s'),
                    ];
                }
                saveNotify($notifies);

                jsonResponse(200, '审核完成');
            }
        }
        jsonResponse(404, '提现记录不存在');
    }

    jsonResponse(400, '未知操作');
}
