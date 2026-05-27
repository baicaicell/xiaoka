<?php
/**
 * 商品管理接口
 * GET: 获取商品列表
 * POST: 上传商品（管理员/商家）/ 上下架商品
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $all = $_GET['all'] ?? '';
    $token = $_GET['token'] ?? '';
    $products = loadShop();

    // 管理员查看全部商品（包括已下架的）
    if ($all && $token && verifyAdminToken($token)) {
        jsonResponse(200, '成功', $products);
    }

    // 普通用户只看上架的商品
    $active = array_filter($products, function($p) { return ($p['status'] ?? 'active') === 'active'; });
    
    // 附加商家联系方式
    $merchants = loadMerchants();
    $result = array_values($active);
    foreach ($result as &$p) {
        $p['sellerContact'] = '';
        if ($p['seller'] !== 'admin') {
            foreach ($merchants as $m) {
                if ($m['phone'] === $p['seller'] && $m['status'] === 'approved') {
                    $p['sellerContact'] = $m['contact'];
                    break;
                }
            }
        }
    }
    
    jsonResponse(200, '成功', $result);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查是否是JSON请求（上下架操作）
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'toggle') {
            $token = $input['token'] ?? '';
            if (!verifyAdminToken($token)) jsonResponse(403, '无权限');

            $productId = trim($input['productId'] ?? '');
            $newStatus = trim($input['status'] ?? 'active');

            $products = loadShop();
            foreach ($products as &$p) {
                if ($p['id'] === $productId) {
                    $p['status'] = $newStatus;
                    saveShop($products);
                    jsonResponse(200, '操作成功');
                }
            }
            jsonResponse(404, '商品不存在');
        }

        jsonResponse(400, '未知操作');
    }

    // FormData请求（上传商品）
    $token = $_POST['token'] ?? '';
    $sellerPhone = $_POST['sellerPhone'] ?? '';

    // 验证权限：管理员或已通过的商家
    $isAdmin = verifyAdminToken($token);
    $isMerchant = false;
    if (!$isAdmin && $sellerPhone) {
        $user = getUserByPhone($sellerPhone);
        if ($user && ($user['isMerchant'] ?? false)) {
            $isMerchant = true;
        }
    }

    if (!$isAdmin && !$isMerchant) {
        jsonResponse(403, '无权限');
    }

    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    $price = trim($_POST['price'] ?? '0');
    $type = trim($_POST['type'] ?? 'physical'); // physical=实物, virtual=虚拟软件
    $payType = trim($_POST['payType'] ?? 'points'); // points=积分, goldBeans=金豆

    if (empty($name)) jsonResponse(400, '请填写商品名称');
    if (empty($price)) jsonResponse(400, '请填写价格');

    global $SHOP_UPLOAD_DIR;

    // 处理图片上传（多图）
    $images = [];
    if (isset($_FILES['images'])) {
        $files = $_FILES['images'];
        $count = is_array($files['name']) ? count($files['name']) : 1;
        for ($i = 0; $i < $count; $i++) {
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $origName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

            if ($error === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $newName = uniqid('shop_') . '.' . $ext;
                move_uploaded_file($tmpName, $SHOP_UPLOAD_DIR . $newName);
                $images[] = 'uploads/shop/' . $newName;
            }
        }
    }

    // 处理虚拟软件文件上传
    $virtualFile = '';
    $virtualFileName = '';
    if ($type === 'virtual' && isset($_FILES['virtualFile']) && $_FILES['virtualFile']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['virtualFile'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newName = uniqid('vsoft_') . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $SHOP_UPLOAD_DIR . $newName);
        $virtualFile = 'uploads/shop/' . $newName;
        $virtualFileName = $file['name'];
    }

    $products = loadShop();
    $products[] = [
        'id' => uniqid('prod_'),
        'name' => $name,
        'desc' => $desc,
        'price' => intval($price),
        'type' => $type,
        'payType' => $payType,
        'images' => $images,
        'virtualFile' => $virtualFile,
        'virtualFileName' => $virtualFileName,
        'seller' => $isAdmin ? 'admin' : $sellerPhone,
        'status' => 'active',
        'createTime' => date('Y-m-d H:i:s'),
    ];
    saveShop($products);

    jsonResponse(200, '商品上传成功');
}
