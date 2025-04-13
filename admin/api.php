<?php
require '../user/adminauth.php';
require '../conf.php';
// 创建连接
$conn = new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 设置响应头为JSON格式
header("Content-Type: application/json");
// 设置允许跨域请求
header("Access-Control-Allow-Origin: *");

if (isset($_GET['mode'])) {
    try {
        switch ($_GET['mode']) {
            // 新增类型管理功能
            case 'types':
                $stmt = $conn->prepare("SELECT id, name, prefix FROM ticket_types");
                $stmt->execute();
                $result = $stmt->get_result();
                echo json_encode($result->fetch_all(MYSQLI_ASSOC));
                exit;
            case 'change':
                try {
                    $conn->autocommit(false); // 开启事务
                    
                    // 验证参数
                    $ticketId = intval($_GET['ticket'] ?? 0);
                    $dataType = $_GET['data'] ?? '';
                    
                    if ($ticketId <= 0 || !in_array($dataType, ['checkChange', 'blacklistChange', 'soldChange'])) {
                        throw new Exception("非法请求参数");
                    }
            
                    // 获取当前状态
                    $stmt = $conn->prepare("SELECT ticket_state, entry_time FROM tickets WHERE id = ? FOR UPDATE");
                    $stmt->bind_param("i", $ticketId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        throw new Exception("票号不存在");
                    }
                    
                    $row = $result->fetch_assoc();
                    $currentState = $row['ticket_state'];
                    $newState = $currentState;
                    $time = null;
            
                    // 状态转换逻辑
                    switch ($dataType) {
                        case 'checkChange':
                            $newState = ($currentState == 1) ? 0 : 1;
                            $time = ($newState == 1) ? date("Y-m-d H:i:s") : null;
                            $updateStmt = $conn->prepare("UPDATE tickets SET ticket_state = ?, entry_time = ? WHERE id = ?");
                            $updateStmt->bind_param("isi", $newState, $time, $ticketId);
                            break;
                            
                        case 'blacklistChange':
                            if ($currentState == 2) {
                                $newState = 3; // 黑名单 -> 未售出
                            } else {
                                $newState = 2; // 其他状态 -> 黑名单
                            }
                            $updateStmt = $conn->prepare("UPDATE tickets SET ticket_state = ?, entry_time = NULL WHERE id = ?");
                            $updateStmt->bind_param("ii", $newState, $ticketId);
                            break;
                            
                        case 'soldChange':
                            if ($currentState == 3) {
                                $newState = 0; // 未售出 -> 未检
                            } else {
                                $newState = 3; // 其他状态 -> 未售出
                            }
                            $updateStmt = $conn->prepare("UPDATE tickets SET ticket_state = ?, entry_time = NULL WHERE id = ?");
                            $updateStmt->bind_param("ii", $newState, $ticketId);
                            break;
                    }
            
                    // 执行更新
                    if (!$updateStmt->execute()) {
                        throw new Exception("状态更新失败");
                    }
            
                    // 获取更新后的完整数据
                    $updatedStmt = $conn->prepare("SELECT * FROM tickets WHERE id = ?");
                    $updatedStmt->bind_param("i", $ticketId);
                    $updatedStmt->execute();
                    $updatedData = $updatedStmt->get_result()->fetch_assoc();
            
                    $conn->commit();
                    
                    // 返回更新后的数据和成功状态
                    echo json_encode([
                        "errorCode" => "false",
                        "updatedData" => [
                            "id" => $updatedData['id'],
                            "ticketNumber" => $updatedData['ticket_number'],
                            "ticketType" => $updatedData['ticket_type'],
                            "ticketStatus" => $updatedData['ticket_state'],
                            "ticketData" => $updatedData['ticket_data']
                        ]
                    ]);
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode([
                        "errorCode" => "true",
                        "message" => $e->getMessage()
                    ]);
                } finally {
                    $conn->autocommit(true);
                }
                exit;
                
            default:
                throw new Exception("无效的模式参数");
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["error" => $e->getMessage()]);
    }
} else {
    // 获取概览数据
    function getOverviewData($conn) {
        // 获取所有票种
        $types = [];
        $typeQuery = $conn->query("SELECT prefix FROM ticket_types");
        while ($row = $typeQuery->fetch_assoc()) {
            $types[] = $row['prefix'];
        }

        // 构建动态SQL
        $sql = "SELECT 
            SUM(CASE WHEN ticket_state = 1 THEN 1 ELSE 0 END) AS checked_all,
            SUM(CASE WHEN ticket_state = 0 THEN 1 ELSE 0 END) AS unchecked_all,
            SUM(CASE WHEN ticket_state = 2 THEN 1 ELSE 0 END) AS blacklist,
            SUM(CASE WHEN ticket_state = 3 THEN 1 ELSE 0 END) AS unsold, ";

        // 添加各票种统计
        foreach ($types as $prefix) {
            $sql .= sprintf(
                "SUM(CASE WHEN ticket_type = '%s' AND ticket_state = 1 THEN 1 ELSE 0 END) AS checked_%s, 
                SUM(CASE WHEN ticket_type = '%s' AND ticket_state = 0 THEN 1 ELSE 0 END) AS unchecked_%s, ",
                $conn->real_escape_string($prefix),
                $conn->real_escape_string($prefix),
                $conn->real_escape_string($prefix),
                $conn->real_escape_string($prefix)
            );
        }

        $sql = rtrim($sql, ', ') . " FROM tickets";
        
        $result = $conn->query($sql);
        return $result->fetch_assoc();
    }

    // 获取表格数据
    function getTableData($conn) {
        $statusFilter = $_GET["statusFilter"] ?? null;
        $typeFilter = $_GET["typeFilter"] ?? null;
        $search = $_GET["search"] ?? null;
    
        // 使用 SQL_CALC_FOUND_ROWS 和 FOUND_ROWS() 优化分页查询
        $baseSql = "SELECT SQL_CALC_FOUND_ROWS 
                        t.id, 
                        t.ticket_number, 
                        t.ticket_state AS ticket_status, 
                        t.entry_time, 
                        tt.name AS type_name, 
                        tt.prefix AS type_prefix 
                    FROM tickets t
                    LEFT JOIN ticket_types tt ON t.ticket_type = tt.prefix";
    
        $where = [];
        $params = [];
        $types = "";
    
        // 构建过滤条件（注意使用原始字段名 ticket_state）
        if ($statusFilter !== null && in_array($statusFilter, ['0','1','2','3'])) {
            $where[] = "t.ticket_state = ?";
            $params[] = $statusFilter;
            $types .= "i";
        }
    
        if ($typeFilter !== null) {
            $where[] = "t.ticket_type = ?";
            $params[] = $typeFilter;
            $types .= "s";
        }
    
        if ($search !== null && !empty($search)) {
            $where[] = "t.ticket_number LIKE ?";
            $params[] = "%$search%";
            $types .= "s";
        }
    
        // 组合完整SQL
        if (!empty($where)) {
            $baseSql .= " WHERE " . implode(" AND ", $where);
        }
    
        // 添加分页参数
        $recordsPerPage = 20;
        $currentPage = $_GET["page"] ?? 1;
        $offset = ($currentPage - 1) * $recordsPerPage;
        $baseSql .= " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $recordsPerPage;
        $types .= "ii";
    
        // 准备语句
        $stmt = $conn->prepare($baseSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    
        // 获取实际数据
        $tableData = [];
        while ($row = $result->fetch_assoc()) {
            $tableData[] = [
                "id" => $row["id"],
                "ticketNumber" => $row["ticket_number"],
                "typeName" => $row["type_name"],
                "typePrefix" => $row["type_prefix"],
                "ticketStatus" => $row["ticket_status"],
                "entryTime" => $row["entry_time"],
            ];
        }
    
        // 获取总记录数（通过 FOUND_ROWS() 优化）
        $totalResult = $conn->query("SELECT FOUND_ROWS() AS total");
        $totalRecords = $totalResult->fetch_assoc()['total'];
        $totalPages = ceil($totalRecords / $recordsPerPage);
    
        // 添加分页信息到返回数据
        array_unshift($tableData, ["totalPage" => $totalPages]);
    
        // 确保即使查询结果为空，也返回一个空数组
        return $tableData;
    }

    // 检查请求的data参数
    if (isset($_GET["data"])) {
        $data = $_GET["data"];
        // 根据请求的data参数返回相应的数据
        if ($data == "overview") {
            echo json_encode(getOverviewData($conn));
        } elseif ($data == "table") {
            echo json_encode(getTableData($conn));
        } else {
            // 如果data参数不匹配，返回错误信息
            echo json_encode(["error" => "Invalid data parameter"]);
        }
    } else {
        // 如果没有提供data参数，返回错误信息
        echo json_encode(["error" => "Data parameter is required"]);
    }
}

$conn->close();
?>