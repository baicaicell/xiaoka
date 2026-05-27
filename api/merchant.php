<?php
/**
 * 商家入驻接口
 * GET: 获取商家列表（管理员）/ 检查商家状态
 * POST: 申请入驻 / 审核
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? '';
    $phone = $_GET['phone'] ?? '';

    // 管理员获取所有商家申请
    if ($token && verifyAdminToken($token)) {
        $merchants = loadMerchants();
        jsonResponse(200, '成功', $merchants);
    }

    // 用户查看自己的入驻状态
    if ($phone) {
        $merchants = loadMerchants();
        foreach ($merchants as $m) {
            if ($m['phone'] === $phone) {
                jsonResponse(200, '成功', $m);
            }
        }
        jsonResponse(404, '未申请入驻');
    }

    jsonResponse(400, '缺少参数');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'apply';

    // 申请入驻
    if ($action === 'apply') {
        $phone = trim($input['phone'] ?? '');
        $merchantName = trim($input['merchantName'] ?? '');
        $contact = trim($input['contact'] ?? '');
        $mainProducts = trim($input['mainProducts'] ?? '');

        if (empty($phone) || empty($merchantName) || empty($contact) || empty($mainProducts)) {
            jsonResponse(400, '请填写完整信息');
        }

        $merchants = loadMerchants();
        // 检查是否已申请
        foreach ($merchants as $m) {
            if ($m['phone'] === $phone) {
                if ($m['status'] === 'approved') jsonResponse(400, '您已是商家，无需重复申请');
                if ($m['status'] === 'pending') jsonResponse(400, '您的申请正在审核中');
            }
        }

        $merchants[] = [
            'id' => uniqid('merch_'),
            'phone' => $phone,
            'merchantName' => $merchantName,
            'contact' => $contact,
            'mainProducts' => $mainProducts,
            'status' => 'pending',
            'applyTime' => date('Y-m-d H:i:s'),
            'reviewTime' => null,
        ];
        saveMerchants($merchants);
        jsonResponse(200, '申请已提交，请等待审核');
    }

    // 管理员审核
    if ($action === 'review') {
        $token = $input['token'] ?? '';
        if (!verifyAdminToken($token)) jsonResponse(403, '无权限');

        $merchantId = trim($input['merchantId'] ?? '');
        $result = trim($input['result'] ?? ''); // approved / rejected

        $merchants = loadMerchants();
        foreach ($merchants as &$m) {
            if ($m['id'] === $merchantId) {
                $m['status'] = $result;
                $m['reviewTime'] = date('Y-m-d H:i:s');

                // 如果通过，更新用户的商家标识
                if ($result === 'approved') {
                    $users = loadUsers();
                    foreach ($users as &$u) {
                        if ($u['phone'] === $m['phone']) {
                            $u['isMerchant'] = true;
                            break;
                        }
                    }
                    saveUsers($users);
                }

                saveMerchants($merchants);

                // 发送通知
                $notifies = loadNotify();
                $statusText = $result === 'approved' ? '已通过' : '未通过';
                $notifies[] = [
                    'id' => uniqid('noti_'),
                    'to' => $m['phone'],
                    'title' => '商家入驻审核结果',
                    'content' => "您的商家入驻申请{$statusText}",
                    'read' => false,
                    'time' => date('Y-m-d H:i:s'),
                ];
                saveNotify($notifies);

                jsonResponse(200, '审核完成');
            }
        }
        jsonResponse(404, '商家不存在');
    }
}
