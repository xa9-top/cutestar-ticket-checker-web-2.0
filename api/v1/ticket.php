<?
require_once '../../conf.php';
// 设置响应头为JSON格式
header("Content-Type: application/json");
// 设置允许跨域请求
header("Access-Control-Allow-Origin: *");

$response = ['error' => ''];

try {
    // 检查必要参数
    if (!isset($_GET['ticket_data'])) {
        throw new Exception('缺少ticket_data参数');
    }

    $ticket_data = trim($_GET['ticket_data']);

    // 参数有效性验证
    if (empty($ticket_data)) {
        throw new Exception('ticket_data不能为空');
    }

    // 对URL编码的参数进行解码（如果客户端已编码）
    $decoded_ticket_data = urldecode($ticket_data);

    // 创建数据库连接
    $conn = new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: {$conn->connect_error}");
    }

    // 准备查询语句
    $stmt = $conn->prepare("
        SELECT 
            ticket_name, 
            ticket_type, 
            ticket_number, 
            ticket_state, 
            entry_time 
        FROM tickets 
        WHERE ticket_data = ?
    ");
    
    if (!$stmt) {
        throw new Exception("预处理失败: " . $conn->error);
    }

    // 绑定参数并执行
    $stmt->bind_param("s", $decoded_ticket_data);
    $stmt->execute();
    $result = $stmt->get_result();

    // 处理查询结果
    if ($result->num_rows === 1) {
        $rowData = $result->fetch_assoc();
        $responseData = [
            'ticket_name' => $rowData['ticket_name'],
            'ticket_type' => $rowData['ticket_type'],
            'ticket_number' => $rowData['ticket_number'],
            'ticket_data' => htmlspecialchars($decoded_ticket_data), // 防止XSS
            'ticket_state' => $rowData['ticket_state'],
            'entry_time' => $rowData['entry_time']
        ];
        echo json_encode($responseData);
    } else {
        throw new Exception('未找到有效的电子票信息');
    }

    // 释放资源
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    echo json_encode($response);
}
?>