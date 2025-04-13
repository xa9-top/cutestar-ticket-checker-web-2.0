<?php
require '../user/adminauth.php';
require '../conf.php';
session_start(); // 添加session启动

// 创建连接
$conn = new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// 修改主逻辑部分
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

            case 'addtype':
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $conn->prepare("INSERT INTO ticket_types (name, prefix) VALUES (?, ?)");
                $stmt->bind_param("ss", $data['name'], $data['prefix']);
                $stmt->execute();
                echo json_encode(['status' => 'success']);
                exit;

            case 'progress': // 新增进度查询
                echo json_encode(['percentage' => $_SESSION['generation_progress'] ?? 0]);
                exit;

            case 'generate':
                $conn->autocommit(false); // 开启事务
                $_SESSION['generation_progress'] = 0;
                
                // 修改输入验证
                $typeId = intval($_POST['ticket_type']);
                $ticketCount = intval($_POST['ticket_count']);
                $aesKey = $_POST['aes_key'];
                
                // 获取票类型信息并锁定行
                $typeStmt = $conn->prepare("SELECT name, prefix, max_number FROM ticket_types WHERE id = ? FOR UPDATE");
                $typeStmt->bind_param("i", $typeId);
                $typeStmt->execute();
                $typeResult = $typeStmt->get_result();
                
                if ($typeResult->num_rows === 0) {
                    throw new Exception("无效的票类型");
                }
                $type = $typeResult->fetch_assoc();
                $prefix = $type['prefix'];
                $ticketName = $type['name'];
                $maxNumber = $type['max_number']; // 获取当前最大序号
                
                // 修改后的插入语句（移除id字段）
                $insertStmt = $conn->prepare("INSERT INTO tickets (ticket_name, ticket_type, ticket_number, ticket_data, ticket_state) VALUES (?, ?, ?, ?, 3)");
                
                for ($i = 1; $i <= $ticketCount; $i++) {
                    $currentNumber = $maxNumber + $i;
                    $random_hex = bin2hex(random_bytes(4));
                    $ticket_number = $prefix . str_pad($currentNumber, 4, '0', STR_PAD_LEFT);
                    $ticket_predata = $ticket_number . "_" . $random_hex;
                    // 获取hex参数，并转换为布尔值
                    $hex = filter_input(INPUT_POST, 'hex', FILTER_VALIDATE_BOOLEAN);
                    
                    // AES加密配置
                    $cipher = 'aes-128-ecb';
                    
                    // 根据hex参数选择加密选项
                    if ($hex) {
                        // 使用OPENSSL_RAW_DATA获取原始二进制数据，并转换为hex
                        $ticket_data = bin2hex(openssl_encrypt($ticket_predata, $cipher, $aesKey, OPENSSL_RAW_DATA, ''));
                    } else {
                        // 默认返回Base64字符串
                        $ticket_data = openssl_encrypt($ticket_predata, $cipher, $aesKey, 0, '');
                    }
                    
                    // 修改参数绑定（移除了id参数）
                    $insertStmt->bind_param("ssss", $ticketName, $prefix, $ticket_number, $ticket_data);
                    $insertStmt->execute();
                    
                    // 更新进度
                    $_SESSION['generation_progress'] = ($i / $ticketCount) * 100;
                    session_write_close(); // 释放会话锁
                    session_start(); // 重新获取会话
                }
                
                // 更新票类型的最大序号
                $updateStmt = $conn->prepare("UPDATE ticket_types SET max_number = max_number + ? WHERE id = ?");
                $updateStmt->bind_param("ii", $ticketCount, $typeId);
                if (!$updateStmt->execute()) {
                    throw new Exception("更新票类型最大编号失败");
                }
                
                $conn->commit();
                $_SESSION['generation_progress'] = 100;
                echo json_encode(["status" => "success"]);
                exit;

            case 'deletetype':
                try {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $typeId = intval($data['typeId']);
                    
                    // 开启事务并加锁
                    $conn->begin_transaction();
            
                    // 1. 获取当前类型总数（加行锁）
                    $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM ticket_types FOR UPDATE");
                    $countStmt->execute();
                    $typeCount = $countStmt->get_result()->fetch_assoc()['cnt'];
            
                    // 2. 获取要删除的类型前缀
                    $prefixStmt = $conn->prepare("SELECT prefix FROM ticket_types WHERE id = ?");
                    $prefixStmt->bind_param("i", $typeId);
                    $prefixStmt->execute();
                    $prefix = $prefixStmt->get_result()->fetch_assoc()['prefix'];
            
                    if ($typeCount == 1) {
                        // 情况1：最后一个类型
                        // 先删除类型记录
                        $deleteStmt = $conn->prepare("DELETE FROM ticket_types WHERE id = ?");
                        $deleteStmt->bind_param("i", $typeId);
                        $deleteStmt->execute();
                        
                        // 提交事务后才能执行TRUNCATE（DDL操作会隐式提交）
                        $conn->commit();
                        
                        // 清空票表并重置自增ID
                        $conn->query("TRUNCATE TABLE tickets");
                    } else {
                        // 情况2：非最后一个类型
                        // 删除关联票号
                        $deleteTickets = $conn->prepare("DELETE FROM tickets WHERE ticket_type = ?");
                        $deleteTickets->bind_param("s", $prefix);
                        $deleteTickets->execute();
                    
                        // 删除票务类型
                        $deleteType = $conn->prepare("DELETE FROM ticket_types WHERE id = ?");
                        $deleteType->bind_param("i", $typeId);
                        $deleteType->execute();
                    
                        // 查询当前最大 ID（兼容空表情况）
                        $getMaxId = $conn->query("SELECT IFNULL(MAX(id), 0) + 1 AS max_id FROM tickets");
                        $row = $getMaxId->fetch_assoc();
                        $newAutoIncrement = $row['max_id'];
                    
                        // 直接拼接数值到SQL语句中，避免预处理导致引号问题
                        $resetSql = "ALTER TABLE tickets AUTO_INCREMENT = $newAutoIncrement";
                        $conn->query($resetSql);
                    
                        // 提交事务
                        $conn->commit();
                    }
            
                    echo json_encode(["status" => "success"]);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
                }
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
    function getTableData($conn) {
        $statusFilter = $_GET["statusFilter"] ?? null;
        $typeFilter = $_GET["typeFilter"] ?? null;
        $search = $_GET["search"] ?? null;
    
        // 使用 SQL_CALC_FOUND_ROWS 和 FOUND_ROWS() 优化分页查询
        $baseSql = "SELECT SQL_CALC_FOUND_ROWS 
                        t.id, 
                        t.ticket_number, 
                        t.ticket_state AS ticket_status, 
                        t.ticket_data, 
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
                "ticketData" => $row["ticket_data"],
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

    if (isset($_GET["data"]) && $_GET["data"] == "table") {
        echo json_encode(getTableData($conn));
    } else {
        echo json_encode(["error" => "Invalid data parameter"]);
    }
}

$conn->close();
?>