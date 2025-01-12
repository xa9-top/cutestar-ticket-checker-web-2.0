<?php
include '../conf.php';
// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// 设置允许跨域请求
header("Access-Control-Allow-Origin: *");

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
} 

$ticket_data =$_POST['ticket_data']; 

// 修改SQL查询，直接获取所需字段
$sql = "SELECT id, ticket_state, ticket_number, ticket_type, ticket_name FROM tickets WHERE ticket_data = '$ticket_data'";  
$result =$conn->query($sql);  

if ($result->num_rows > 0) {
    $row =$result->fetch_assoc();
    // 检查ticket_state是否为0
    if ($row['ticket_state'] === "0") {
        // 更新ticket_state为1
        $timenow = date('Y-m-d H:i:s');
        $sql_update = "UPDATE tickets SET ticket_state = '1', entry_time = '$timenow' WHERE id = '$row[id]'";
        if ($conn->query($sql_update) === TRUE) {
            $row += array('success' => "true");
        } else {
            $row += array('success' => "error");
        }
    } else {
        $row += array('success' => "false");
    }
    // 直接返回查询结果
    echo json_encode(array('error' => "false", 'data' => $row));
} else {
    echo json_encode(array('error' => "true"));
}
$conn->close();
?>
