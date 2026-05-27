<?php
/**
 * 用户上传软件接口
 * POST 参数: title(软件名称), desc(描述), file(文件)
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(400, '请求方式错误');
}

// 获取参数
$title = trim($_POST['title'] ?? '');
$desc = trim($_POST['desc'] ?? '');

if (empty($title)) {
    jsonResponse(400, '请填写软件名称');
}

// 检查文件
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(400, '请选择要上传的文件');
}

$file = $_FILES['file'];

// 检查文件大小
if ($file['size'] > $MAX_FILE_SIZE) {
    jsonResponse(400, '文件大小不能超过100MB');
}

// 检查文件类型
$originalName = $file['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($ext, $ALLOWED_TYPES)) {
    jsonResponse(400, '不支持的文件类型，允许: ' . implode(', ', $ALLOWED_TYPES));
}

// 生成唯一文件名
$newFileName = uniqid('soft_') . '.' . $ext;
$targetPath = $UPLOAD_DIR . $newFileName;

// 移动文件
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    jsonResponse(500, '文件保存失败，请重试');
}

// 写入数据
$data = loadData();
$data[] = [
    'id' => uniqid(),
    'title' => $title,
    'desc' => $desc,
    'fileName' => $newFileName,
    'originalName' => $originalName,
    'size' => $file['size'],
    'status' => 'pending',  // pending=待审核, approved=已通过, rejected=已拒绝
    'uploadTime' => date('Y-m-d H:i:s'),
    'reviewTime' => null,
];
saveData($data);

jsonResponse(200, '上传成功，等待管理员审核');
