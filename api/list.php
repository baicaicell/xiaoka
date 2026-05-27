<?php
/**
 * 获取软件列表接口
 * GET 参数: 
 *   status=approved (前台只显示已通过的)
 *   status=all&token=xxx (后台显示全部，需要token)
 */
require_once __DIR__ . '/config.php';

$status = $_GET['status'] ?? 'approved';
$token = $_GET['token'] ?? '';

$data = loadData();

if ($status === 'all') {
    // 验证token
    $validToken = false;
    foreach ($WHITELIST as $phone) {
        if (md5($phone . date('Y-m-d') . 'kxb_secret_key') === $token) {
            $validToken = true;
            break;
        }
    }
    if (!$validToken) {
        jsonResponse(403, '无权限');
    }
    // 返回全部，按时间倒序
    usort($data, function($a, $b) {
        return strtotime($b['uploadTime']) - strtotime($a['uploadTime']);
    });
    jsonResponse(200, '成功', $data);
} else {
    // 前台只返回已通过的
    $approved = array_filter($data, function($item) {
        return $item['status'] === 'approved';
    });
    $approved = array_values($approved);
    usort($approved, function($a, $b) {
        return strtotime($b['uploadTime']) - strtotime($a['uploadTime']);
    });
    jsonResponse(200, '成功', $approved);
}
