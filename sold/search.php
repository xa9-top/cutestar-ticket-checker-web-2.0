<?php
header("Access-Control-Allow-Origin: *");
require '../user/auth.php';
require '../conf.php';

$conn = new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);

if ($conn->connect_error) {
    die(json_encode(['error' => 'true', 'message' => '数据库连接失败']));
}

$ticket_data = $_POST['ticket_data'];

// 使用预处理语句进行查询
$stmt = $conn->prepare("SELECT id, ticket_state, ticket_number, ticket_type, ticket_name
                       FROM tickets 
                       WHERE ticket_data = ?");
if (!$stmt) {
    die(json_encode(['error' => 'true', 'message' => '查询预处理失败']));
}

$stmt->bind_param("s", $ticket_data);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    if ($row['ticket_state'] == "0") {
        // 使用预处理语句进行更新
        $stmt_update = $conn->prepare("UPDATE tickets 
                                      SET ticket_state = '3'
                                      WHERE id = ?");
        if (!$stmt_update) {
            die(json_encode(['error' => 'true', 'message' => '更新预处理失败']));
        }
        
        $stmt_update->bind_param("i", $row['id']);
        if ($stmt_update->execute()) {
            $row['success'] = "true";
        } else {
            $row['success'] = "error";
        }
        $stmt_update->close();
    } else if( $row['ticket_state'] == "3") {
        // 使用预处理语句进行更新
        $stmt_update = $conn->prepare("UPDATE tickets 
                                      SET ticket_state = '0'
                                      WHERE id = ?");
        if (!$stmt_update) {
            die(json_encode(['error' => 'true', 'message' => '更新预处理失败']));
        }
        
        $stmt_update->bind_param("i", $row['id']);
        if ($stmt_update->execute()) {
            $row['success'] = "true";
        } else {
            $row['success'] = "error";
        }
        $stmt_update->close();
    } else {
        $row['success'] = "false";
    }
    
    echo json_encode(['error' => 'false', 'data' => $row]);
} else {
    echo json_encode(['error' => 'true']);
}

$stmt->close();
$conn->close();
?>