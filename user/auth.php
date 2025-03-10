<?php
// 确保在输出前没有其他内容
ob_start();
session_start();
require __DIR__ . '/../conf.php';

// 登录状态检查
if (!isset($_SESSION['logged_in']) || $_SESSION['expire_time'] < time()) {
    // 清理可能存在的输出缓存
    ob_end_clean();
    
    // 返回200状态码和纯文本内容
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo "authfail";
    exit;
}

// 正常流程：更新会话有效期
$_SESSION['expire_time'] = time() + ($cookie_expire * 60);

// 更新session cookie（保持与登录一致）
setcookie(
    session_name(),
    session_id(),
    $_SESSION['expire_time'],
    '/',
    '',
    false,  // 允许HTTP
    true    // HttpOnly
);

// 清理输出缓存并继续后续流程
ob_end_flush();