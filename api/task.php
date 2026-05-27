<?php
/**
 * 积分任务接口
 * GET: 获取任务列表 / 用户提交记录
 * POST: 管理员创建任务 / 用户提交任务 / 管理员审核
 */
require_once __DIR__ . '/config.php';

// 任务数据文件
$TASK_DATA_FILE = __DIR__ . '/task_data.json';
// 任务提交数据文件
$TASK_SUBMIT_FILE = __DIR__ . '/task_submit_data.json';

if (!file_exists($TASK_DATA_FILE)) {
    file_put_contents($TASK_DATA_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}
if (!file_exists($TASK_SUBMIT_FILE)) {
    file_put_contents($TASK_SUBMIT_FILE, json_encode([], JSON_UNESCAPED_UNICODE));
}

function loadTasks() {
    global $TASK_DATA_FILE;
    return json_decode(file_get_contents($TASK_DATA_FILE), true) ?: [];
}
function saveTasks($data) {
    global $TASK_DATA_FILE;
    file_put_contents($TASK_DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function loadTaskSubmits() {
    global $TASK_SUBMIT_FILE;
    return json_decode(file_get_contents($TASK_SUBMIT_FILE), true) ?: [];
}
function saveTaskSubmits($data) {
    global $TASK_SUBMIT_FILE;
    file_put_contents($TASK_SUBMIT_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $phone = $_GET['phone'] ?? '';
    $token = $_GET['token'] ?? '';
    $type = $_GET['type'] ?? 'tasks'; // tasks / submits

    // 管理员获取所有提交记录
    if ($token && verifyAdminToken($token) && $type === 'submits') {
        $submits = loadTaskSubmits();
        usort($submits, function($a, $b) { return strtotime($b['submitTime']) - strtotime($a['submitTime']); });
        jsonResponse(200, '成功', $submits);
    }

    // 获取任务列表（所有人可见）
    if ($type === 'tasks') {
        $tasks = loadTasks();
        $active = array_filter($tasks, function($t) { return ($t['status'] ?? 'active') === 'active'; });
        jsonResponse(200, '成功', array_values($active));
    }

    // 用户获取自己的提交记录
    if ($phone && $type === 'my_submits') {
        $submits = loadTaskSubmits();
        $my = array_filter($submits, function($s) use ($phone) { return $s['phone'] === $phone; });
        usort($my, function($a, $b) { return strtotime($b['submitTime']) - strtotime($a['submitTime']); });
        jsonResponse(200, '成功', array_values($my));
    }

    jsonResponse(200, '成功', []);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 文件上传（用户提交任务）
    if (isset($_POST['action']) && $_POST['action'] === 'submit') {
        $phone = trim($_POST['phone'] ?? '');
        $taskId = trim($_POST['taskId'] ?? '');

        if (empty($phone) || empty($taskId)) jsonResponse(400, '缺少参数');

        $user = getUserByPhone($phone);
        if (!$user) jsonResponse(404, '用户不存在');

        $tasks = loadTasks();
        $task = null;
        foreach ($tasks as $t) {
            if ($t['id'] === $taskId) { $task = $t; break; }
        }
        if (!$task) jsonResponse(404, '任务不存在');

        // 检查是否已提交过该任务（不允许重复提交）
        $submits = loadTaskSubmits();
        foreach ($submits as $s) {
            if ($s['phone'] === $phone && $s['taskId'] === $taskId) {
                jsonResponse(400, '您已完成过该任务，不能重复提交');
            }
        }

        // 处理图片上传
        $images = [];
        if (isset($_FILES['images'])) {
            global $SHOP_UPLOAD_DIR;
            $files = $_FILES['images'];
            $count = is_array($files['name']) ? count($files['name']) : 1;
            for ($i = 0; $i < $count; $i++) {
                $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $origName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

                if ($error === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $newName = uniqid('task_') . '.' . $ext;
                    move_uploaded_file($tmpName, $SHOP_UPLOAD_DIR . $newName);
                    $images[] = 'uploads/shop/' . $newName;
                }
            }
        }

        if (empty($images)) jsonResponse(400, '请上传截图');

        $submits = loadTaskSubmits();
        $submits[] = [
            'id' => uniqid('tsub_'),
            'taskId' => $taskId,
            'taskName' => $task['name'],
            'phone' => $phone,
            'userName' => $user['name'],
            'images' => $images,
            'reward' => $task['reward'],
            'status' => 'pending', // pending/approved/rejected
            'submitTime' => date('Y-m-d H:i:s'),
            'reviewTime' => null,
        ];
        saveTaskSubmits($submits);

        jsonResponse(200, '提交成功，等待审核');
    }

    // JSON请求
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // 管理员创建任务
    if ($action === 'create') {
        $token = $input['token'] ?? '';
        if (!verifyAdminToken($token)) jsonResponse(403, '无权限');

        $name = trim($input['name'] ?? '');
        $desc = trim($input['desc'] ?? '');
        $reward = intval($input['reward'] ?? 0);

        if (empty($name) || $reward <= 0) jsonResponse(400, '请填写任务名称和奖励积分');

        $tasks = loadTasks();
        $tasks[] = [
            'id' => uniqid('task_'),
            'name' => $name,
            'desc' => $desc,
            'reward' => $reward,
            'status' => 'active',
            'createTime' => date('Y-m-d H:i:s'),
        ];
        saveTasks($tasks);
        jsonResponse(200, '任务创建成功');
    }

    // 管理员删除任务
    if ($action === 'delete') {
        $token = $input['token'] ?? '';
        if (!verifyAdminToken($token)) jsonResponse(403, '无权限');

        $taskId = trim($input['taskId'] ?? '');
        $tasks = loadTasks();
        foreach ($tasks as &$t) {
            if ($t['id'] === $taskId) {
                $t['status'] = 'deleted';
                saveTasks($tasks);
                jsonResponse(200, '已删除');
            }
        }
        jsonResponse(404, '任务不存在');
    }

    // 管理员审核提交
    if ($action === 'review') {
        $token = $input['token'] ?? '';
        if (!verifyAdminToken($token)) jsonResponse(403, '无权限');

        $submitId = trim($input['submitId'] ?? '');
        $result = trim($input['result'] ?? ''); // approved / rejected

        $submits = loadTaskSubmits();
        foreach ($submits as &$s) {
            if ($s['id'] === $submitId) {
                $s['status'] = $result;
                $s['reviewTime'] = date('Y-m-d H:i:s');

                // 通过则加积分
                if ($result === 'approved') {
                    $users = loadUsers();
                    foreach ($users as &$u) {
                        if ($u['phone'] === $s['phone']) {
                            $u['points'] = ($u['points'] ?? 0) + $s['reward'];
                            break;
                        }
                    }
                    saveUsers($users);
                }

                saveTaskSubmits($submits);

                // 发送通知
                $notifies = loadNotify();
                if ($result === 'approved') {
                    $notifies[] = [
                        'id' => uniqid('noti_'),
                        'to' => $s['phone'],
                        'title' => '任务审核通过',
                        'content' => "您提交的任务「{$s['taskName']}」已通过审核，获得{$s['reward']}积分",
                        'read' => false,
                        'time' => date('Y-m-d H:i:s'),
                    ];
                } else {
                    $notifies[] = [
                        'id' => uniqid('noti_'),
                        'to' => $s['phone'],
                        'title' => '任务审核未通过',
                        'content' => "您提交的任务「{$s['taskName']}」未通过审核，请重新提交",
                        'read' => false,
                        'time' => date('Y-m-d H:i:s'),
                    ];
                }
                saveNotify($notifies);

                jsonResponse(200, '审核完成');
            }
        }
        jsonResponse(404, '提交记录不存在');
    }

    jsonResponse(400, '未知操作');
}
