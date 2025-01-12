<?php
include '../conf.php';
// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 要导出的表名
$table = 'tickets';

// 创建SQL查询以获取表的所有字段和数据
$sql = "SELECT ticket_data, ticket_number, ticket_name FROM `$table`";

$result = $conn->query($sql);

if ($result->num_rows > 0) {

    // 获取表头信息（列名）
    $fields = array();
    while ($meta = $result->fetch_field()) {
        $fields[] = $meta->name;
    }

    // 打开一个输出流并将表头写入其中
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$table.'.csv"');

    $fp = fopen('php://output', 'w');

    fputcsv($fp, $fields);

    // 遍历结果集并将每一行写入CSV文件
    while ($row = $result->fetch_assoc()) {
        fputcsv($fp, $row);
    }

    fclose($fp);

} else {
    echo "0 results";
}

$conn->close();

?>