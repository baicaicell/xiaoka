<?php
/**
 * 配置文件
 */

// 白名单手机号（可以看到审核页面的手机号）
$WHITELIST = ['19212601468'];

// 上传目录
$UPLOAD_DIR = __DIR__ . '/../uploads/';

// 商品图片上传目录
$SHOP_UPLOAD_DIR = __DIR__ . '/../uploads/shop/';

// 数据文件（存储软件信息）
$DATA_FILE = __DIR__ . '/software_data.json';

// 用户数据文件
$USER_DATA_FILE = __DIR__ . '/user_data.json';

// 商品数据文件
$SHOP_DATA_FILE = __DIR__ . '/shop_data.json';

// 订单数据文件
$ORDER_DATA_FILE = __DIR__ . '/order_data.json';

// 签到数据文件
$CHECKIN_DATA_FILE = __DIR__ . '/checkin_data.json';

// 通知数据文件
$NOTIFY_DATA_FILE = __DIR__ . '/notify_data.json';

// 商家入驻数据文件
$MERCHANT_DATA_FILE = __DIR__ . '/merchant_data.json';

// 允许上传的文件类型
$ALLOWED_TYPES = ['exe', 'apk', 'zip', 'rar', '7z', 'msi', 'dmg', 'deb', 'apk.1'];

// 最大文件大小 1000MB
$MAX_FILE_SIZE = 1000 * 1024 * 1024;

// 初始化数据文件
$dataFiles = [$DATA_FILE, $USER_DATA_FILE, $SHOP_DATA_FILE, $ORDER_DATA_FILE, $CHECKIN_DATA_FILE, $NOTIFY_DATA_FILE, $MERCHANT_DATA_FILE];
foreach ($dataFiles as $f) {
    if (!file_exists($f)) {
        file_put_contents($f, json_encode([], JSON_UNESCAPED_UNICODE));
    }
}

// 初始化上传目录
if (!is_dir($UPLOAD_DIR)) { mkdir($UPLOAD_DIR, 0755, true); }
if (!is_dir($SHOP_UPLOAD_DIR)) { mkdir($SHOP_UPLOAD_DIR, 0755, true); }

// 通用响应函数
function jsonResponse($code, $msg, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    $res = ['code' => $code, 'msg' => $msg];
    if ($data !== null) $res['data'] = $data;
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// 读取数据
function loadData() {
    global $DATA_FILE;
    $content = file_get_contents($DATA_FILE);
    return json_decode($content, true) ?: [];
}

// 保存数据
function saveData($data) {
    global $DATA_FILE;
    file_put_contents($DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 读取用户数据
function loadUsers() {
    global $USER_DATA_FILE;
    $content = file_get_contents($USER_DATA_FILE);
    return json_decode($content, true) ?: [];
}

// 保存用户数据
function saveUsers($data) {
    global $USER_DATA_FILE;
    file_put_contents($USER_DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 商品数据
function loadShop() {
    global $SHOP_DATA_FILE;
    return json_decode(file_get_contents($SHOP_DATA_FILE), true) ?: [];
}
function saveShop($data) {
    global $SHOP_DATA_FILE;
    file_put_contents($SHOP_DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 订单数据
function loadOrders() {
    global $ORDER_DATA_FILE;
    return json_decode(file_get_contents($ORDER_DATA_FILE), true) ?: [];
}
function saveOrders($data) {
    global $ORDER_DATA_FILE;
    file_put_contents($ORDER_DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 签到数据
function loadCheckins() {
    global $CHECKIN_DATA_FILE;
    return json_decode(file_get_contents($CHECKIN_DATA_FILE), true) ?: [];
}
function saveCheckins($data) {
    global $CHECKIN_DATA_FILE;
    file_put_contents($CHECKIN_DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 通知数据
function loadNotify() {
    global $NOTIFY_DATA_FILE;
    return json_decode(file_get_contents($NOTIFY_DATA_FILE), true) ?: [];
}
function saveNotify($data) {
    global $NOTIFY_DATA_FILE;
    file_put_contents($NOTIFY_DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 商家数据
function loadMerchants() {
    global $MERCHANT_DATA_FILE;
    return json_decode(file_get_contents($MERCHANT_DATA_FILE), true) ?: [];
}
function saveMerchants($data) {
    global $MERCHANT_DATA_FILE;
    file_put_contents($MERCHANT_DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// 验证管理员token
function verifyAdminToken($token) {
    global $WHITELIST;
    foreach ($WHITELIST as $phone) {
        if (md5($phone . date('Y-m-d') . 'kxb_secret_key') === $token) {
            return true;
        }
    }
    return false;
}

// 根据手机号获取用户
function getUserByPhone($phone) {
    $users = loadUsers();
    foreach ($users as $user) {
        if ($user['phone'] === $phone) return $user;
    }
    return null;
}
