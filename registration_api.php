<?php
// registration_api.php - Synology 用戶申請 API
// 放在 Web Station 的 PHP 根目錄 (通常是 /var/services/web/)

error_reporting(E_ALL);
ini_set('display_errors', 0);

// 檢查是否為 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

// 檢查 Content-Type
if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
    http_response_code(400);
    echo json_encode(['message' => 'Content-Type must be application/json']);
    exit;
}

// 取得並驗證輸入
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username'], $input['email'], $input['fullname'], $input['password'])) {
    http_response_code(400);
    echo json_encode(['message' => '缺少必要參數']);
    exit;
}

$username = trim($input['username']);
$email = trim($input['email']);
$fullname = trim($input['fullname']);
$password = $input['password'];

// 驗證帳號格式
if (!preg_match('/^[a-zA-Z0-9._-]{3,20}$/', $username)) {
    http_response_code(400);
    echo json_encode(['message' => '帳號格式不正確 (3-20 字符)']);
    exit;
}

// 驗證 Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['message' => '電子郵件格式不正確']);
    exit;
}

// 驗證密碼強度
if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    http_response_code(400);
    echo json_encode(['message' => '密碼不符合要求']);
    exit;
}

// 執行建立帳號 (需要 root 或 sudo 權限)
$output = [];
$returnCode = 0;

// 使用 synouser 命令建立帳號
// synouser --add [username] [password] [fullname] [0] [email] [0]
$cmd = sprintf(
    'sudo /usr/syno/sbin/synouser --add %s %s %s 0 %s 0',
    escapeshellarg($username),
    escapeshellarg($password),
    escapeshellarg($fullname),
    escapeshellarg($email)
);

exec($cmd, $output, $returnCode);

if ($returnCode !== 0) {
    // 帳號可能已存在或其他錯誤
    http_response_code(400);
    echo json_encode(['message' => '帳號建立失敗，可能帳號已存在或名稱不符合規則']);
    exit;
}

// 授予 Synology Drive 權限
exec(sprintf('sudo /usr/syno/sbin/synouser --setperm %s DriveAdmin 1', escapeshellarg($username)));

// 授予 Synology Chat 權限
exec(sprintf('sudo /usr/syno/sbin/synouser --setperm %s MailPlus 1', escapeshellarg($username)));

// 授予 Synology Photos 權限
exec(sprintf('sudo /usr/syno/sbin/synouser --setperm %s PhotosAdmin 1', escapeshellarg($username)));

// 建立 home 資料夾
exec(sprintf('sudo mkdir -p /volume1/homes/%s', escapeshellarg($username)));
exec(sprintf('sudo chown %s:%s /volume1/homes/%s', escapeshellarg($username), escapeshellarg($username), escapeshellarg($username)));
exec(sprintf('sudo chmod 700 /volume1/homes/%s', escapeshellarg($username)));

http_response_code(200);
echo json_encode([
    'message' => '帳號建立成功',
    'username' => $username,
    'email' => $email
]);
?>
