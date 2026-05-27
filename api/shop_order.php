<?php
/**
 * 订单接口
 * GET: 获取订单列表 参数: phone(用户), token(管理员), seller(商家)
 * POST: 创建订单
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $phone = $_GET['phone'] ?? '';
    $token = $_GET['token'] ?? '';
    $seller = $_GET['seller'] ?? '';

    $orders = loadOrders();

    // 管理员查看所有订单
    if ($token && verifyAdminToken($token)) {
        usort($orders, function($a, $b) { return strtotime($b['createTime']) - strtotime($a['createTime']); });
        jsonResponse(200, '成功', $orders);
    }

    // 商家查看自己的订单
    if ($seller) {
        $myOrders = array_filter($orders, function($o) use ($seller) { return $o['seller'] === $seller; });
        usort($myOrders, function($a, $b) { return strtotime($b['createTime']) - strtotime($a['createTime']); });
        jsonResponse(200, '成功', array_values($myOrders));
    }

    // 用户查看自己的订单
    if ($phone) {
        $myOrders = array_filter($orders, function($o) use ($phone) { return $o['buyerPhone'] === $phone; });
        usort($myOrders, function($a, $b) { return strtotime($b['createTime']) - strtotime($a['createTime']); });
        jsonResponse(200, '成功', array_values($myOrders));
    }

    jsonResponse(400, '缺少参数');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'create';

    if ($action === 'create') {
        $phone = trim($input['phone'] ?? '');
        $productId = trim($input['productId'] ?? '');
        $receiverName = trim($input['receiverName'] ?? '');
        $receiverPhone = trim($input['receiverPhone'] ?? '');
        $receiverAddress = trim($input['receiverAddress'] ?? '');

        if (empty($phone) || empty($productId)) jsonResponse(400, '缺少参数');

        $user = getUserByPhone($phone);
        if (!$user) jsonResponse(404, '用户不存在');

        $products = loadShop();
        $product = null;
        foreach ($products as $p) {
            if ($p['id'] === $productId) { $product = $p; break; }
        }
        if (!$product) jsonResponse(404, '商品不存在');

        // 实物商品需要收货信息
        if ($product['type'] === 'physical') {
            if (empty($receiverName) || empty($receiverPhone) || empty($receiverAddress)) {
                jsonResponse(400, '请填写完整的收货信息');
            }
        }

        // 检查余额
        $payType = $product['payType'] ?? 'points';
        $price = $product['price'];

        if ($payType === 'points') {
            if (($user['points'] ?? 0) < $price) {
                jsonResponse(400, '积分不足，需要' . $price . '积分');
            }
        } else {
            if (($user['goldBeans'] ?? 0) < $price) {
                jsonResponse(400, '金豆不足，需要' . $price . '金豆');
            }
        }

        // 扣除余额
        $users = loadUsers();
        foreach ($users as &$u) {
            if ($u['phone'] === $phone) {
                if ($payType === 'points') {
                    $u['points'] = ($u['points'] ?? 0) - $price;
                } else {
                    $u['goldBeans'] = ($u['goldBeans'] ?? 0) - $price;
                }
                break;
            }
        }

        // 给卖家加积分/金豆
        $seller = $product['seller'];
        if ($seller !== 'admin') {
            foreach ($users as &$u) {
                if ($u['phone'] === $seller) {
                    if ($payType === 'points') {
                        $u['points'] = ($u['points'] ?? 0) + $price;
                    } else {
                        $u['goldBeans'] = ($u['goldBeans'] ?? 0) + $price;
                    }
                    break;
                }
            }
        }
        saveUsers($users);

        // 创建订单
        $orders = loadOrders();
        $order = [
            'id' => uniqid('order_'),
            'productId' => $productId,
            'productName' => $product['name'],
            'productType' => $product['type'],
            'payType' => $payType,
            'price' => $price,
            'virtualFile' => $product['virtualFile'] ?? '',
            'virtualFileName' => $product['virtualFileName'] ?? '',
            'buyerPhone' => $phone,
            'buyerName' => $user['name'],
            'receiverName' => $receiverName,
            'receiverPhone' => $receiverPhone,
            'receiverAddress' => $receiverAddress,
            'seller' => $seller,
            'shipStatus' => $product['type'] === 'virtual' ? 'delivered' : 'pending', // 虚拟商品自动发货
            'shipInfo' => $product['type'] === 'virtual' ? '虚拟商品，请在订单中下载' : '',
            'noShipReason' => '',
            'createTime' => date('Y-m-d H:i:s'),
        ];
        $orders[] = $order;
        saveOrders($orders);

        // 发送通知给卖家
        if ($seller !== 'admin') {
            $notifies = loadNotify();
            $notifies[] = [
                'id' => uniqid('noti_'),
                'to' => $seller,
                'title' => '商品售出通知',
                'content' => "您的商品「{$product['name']}」已被购买，买家：{$user['name']}",
                'read' => false,
                'time' => date('Y-m-d H:i:s'),
            ];
            saveNotify($notifies);
        }

        jsonResponse(200, '购买成功', $order);
    }

    // 发货操作
    if ($action === 'ship') {
        $orderId = trim($input['orderId'] ?? '');
        $shipInfo = trim($input['shipInfo'] ?? '');
        $token = $input['token'] ?? '';
        $sellerPhone = $input['sellerPhone'] ?? '';

        $isAdmin = verifyAdminToken($token);
        $orders = loadOrders();
        foreach ($orders as &$order) {
            if ($order['id'] === $orderId) {
                if (!$isAdmin && $order['seller'] !== $sellerPhone) {
                    jsonResponse(403, '无权限');
                }
                $order['shipStatus'] = 'delivered';
                $order['shipInfo'] = $shipInfo;
                $order['shipTime'] = date('Y-m-d H:i:s');
                saveOrders($orders);

                // 通知买家
                $notifies = loadNotify();
                $notifies[] = [
                    'id' => uniqid('noti_'),
                    'to' => $order['buyerPhone'],
                    'title' => '发货通知',
                    'content' => "您购买的「{$order['productName']}」已发货，物流信息：{$shipInfo}",
                    'read' => false,
                    'time' => date('Y-m-d H:i:s'),
                ];
                saveNotify($notifies);

                jsonResponse(200, '发货成功');
            }
        }
        jsonResponse(404, '订单不存在');
    }

    // 未发货原因
    if ($action === 'noship') {
        $orderId = trim($input['orderId'] ?? '');
        $reason = trim($input['reason'] ?? '');
        $token = $input['token'] ?? '';
        $sellerPhone = $input['sellerPhone'] ?? '';

        $isAdmin = verifyAdminToken($token);
        $orders = loadOrders();
        foreach ($orders as &$order) {
            if ($order['id'] === $orderId) {
                if (!$isAdmin && $order['seller'] !== $sellerPhone) {
                    jsonResponse(403, '无权限');
                }
                $order['shipStatus'] = 'pending';
                $order['noShipReason'] = $reason;
                saveOrders($orders);

                // 通知买家
                $notifies = loadNotify();
                $notifies[] = [
                    'id' => uniqid('noti_'),
                    'to' => $order['buyerPhone'],
                    'title' => '订单通知',
                    'content' => "您购买的「{$order['productName']}」暂未发货，原因：{$reason}",
                    'read' => false,
                    'time' => date('Y-m-d H:i:s'),
                ];
                saveNotify($notifies);

                jsonResponse(200, '已更新');
            }
        }
        jsonResponse(404, '订单不存在');
    }
}
