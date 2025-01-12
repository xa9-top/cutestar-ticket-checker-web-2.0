<?php
include '../conf.php';
// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 设置响应头为JSON格式
header("Content-Type: application/json");
// 设置允许跨域请求
header("Access-Control-Allow-Origin: *");

if ($_GET["mode"] === "change") {
    if ($_GET["data"] === "checkChange") {
        $sql1 =
            "SELECT * FROM tickets WHERE id = '" .
            $_GET["ticket"] .
            "' AND ticket_state = '1'";
        if ($conn->query($sql1)->num_rows > 0) {
            $sql2 =
                "UPDATE tickets SET ticket_state = '0', entry_time = null WHERE id = '" .
                $_GET["ticket"] .
                "'";
            if ($conn->query($sql2) === true) {
                $error = false;
            } else {
                $error = true;
            }
        } else {
            $timenow = date("Y-m-d H:i:s");
            $sql2 =
                "UPDATE tickets SET ticket_state = '1', entry_time = '$timenow' WHERE id = '" .
                $_GET["ticket"] .
                "'";
            if ($conn->query($sql2) === true) {
                $error = false;
            } else {
                $error = true;
            }
        }
    } elseif ($_GET["data"] === "blacklistChange") {
        $sql1 =
            "SELECT * FROM tickets WHERE id = '" .
            $_GET["ticket"] .
            "' AND ticket_state = '2'";
        if ($conn->query($sql1)->num_rows > 0) {
            $sql2 =
                "UPDATE tickets SET ticket_state = '3', entry_time = null WHERE id = '" .
                $_GET["ticket"] .
                "'";
            if ($conn->query($sql2) === true) {
                $error = false;
            } else {
                $error = true;
            }
        } else {
            $timenow = date("Y-m-d H:i:s");
            $sql2 =
                "UPDATE tickets SET ticket_state = '2', entry_time = null WHERE id = '" .
                $_GET["ticket"] .
                "'";
            if ($conn->query($sql2) === true) {
                $error = false;
            } else {
                $error = true;
            }
        }
    } elseif ($_GET["data"] === "soldChange") {
        $sql1 =
            "SELECT * FROM tickets WHERE id = '" .
            $_GET["ticket"] .
            "' AND ticket_state = '3'";
        if ($conn->query($sql1)->num_rows > 0) {
            $sql2 =
                "UPDATE tickets SET ticket_state = '0', entry_time = null WHERE id = '" .
                $_GET["ticket"] .
                "'";
            if ($conn->query($sql2) === true) {
                $error = false;
            } else {
                $error = true;
            }
        } else {
            $timenow = date("Y-m-d H:i:s");
            $sql2 =
                "UPDATE tickets SET ticket_state = '3', entry_time = null WHERE id = '" .
                $_GET["ticket"] .
                "'";
            if ($conn->query($sql2) === true) {
                $error = false;
            } else {
                $error = true;
            }
        }
    } else {
        $error = true;
    }
    $data = $_GET["data"];
    if ($error === false) {
        echo json_encode(["errorCode" => "false"]);
    } else {
        echo json_encode(["errorCode" => "true"]);
    }
} else {
    // 获取概览数据
    function getOverviewData($conn)
    {
        $overviewData = [
            "checkedTicketsAll" => 0,
            "uncheckedTicketsAll" => 0,
            "checkedTicketsNormal" => 0,
            "uncheckedTicketsNormal" => 0,
            "checkedTicketsVip" => 0,
            "uncheckedTicketsVip" => 0,
            "checkedTicketsNight" => 0,
            "uncheckedTicketsNight" => 0,
            "blacklistTickets" => 0,
            "unsoldTickets" => 0,
        ];
        $sql = "SELECT ticket_state, ticket_type FROM tickets";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                switch ($row["ticket_state"]) {
                    case 0:
                        $overviewData["uncheckedTicketsAll"]++;
                        if ($row["ticket_type"] == "N") {
                            $overviewData["uncheckedTicketsNormal"]++;
                        } elseif ($row["ticket_type"] == "V") {
                            $overviewData["uncheckedTicketsVip"]++;
                        } elseif ($row["ticket_type"] == "Y") {
                            $overviewData["uncheckedTicketsNight"]++;
                        }
                        break;
                    case 1:
                        $overviewData["checkedTicketsAll"]++;
                        if ($row["ticket_type"] == "N") {
                            $overviewData["checkedTicketsNormal"]++;
                        } elseif ($row["ticket_type"] == "V") {
                            $overviewData["checkedTicketsVip"]++;
                        } elseif ($row["ticket_type"] == "Y") {
                            $overviewData["checkedTicketsNight"]++;
                        }
                        break;
                    case 2:
                        $overviewData["blacklistTickets"]++;
                        break;
                    case 3:
                        $overviewData["unsoldTickets"]++;
                        break;
                }
            }
        }
        $result->close();
        return $overviewData;
    }

    function getTableData($conn)
    {
        $statusFilter = $_GET["statusFilter"];
        $typeFilter = $_GET["typeFilter"];
        $search = $_GET["search"];
        $tableData = [];
        $sql =
            "SELECT id, ticket_name, ticket_type, ticket_number, ticket_state, entry_time FROM tickets";
        $whereClauses = [];
        $params = [];

        // 根据状态过滤
        if ($statusFilter !== null) {
            switch ($statusFilter) {
                case "0":
                    $whereClauses[] = "ticket_state = 0";
                    break;
                case "1":
                    $whereClauses[] = "ticket_state = 1";
                    break;
                case "2":
                    $whereClauses[] = "ticket_state = 2";
                    break;
                case "3":
                    $whereClauses[] = "ticket_state = 3";
                    break;
            }
        }

        // 根据类型过滤
        if ($typeFilter !== null) {
            switch ($typeFilter) {
                case "N":
                    $whereClauses[] = 'ticket_type = "N"';
                    break;
                case "V":
                    $whereClauses[] = 'ticket_type = "V"';
                    break;
                case "Y":
                    $whereClauses[] = 'ticket_type = "Y"';
                    break;
            }
        }

        // 根据搜索词过滤
        if ($search !== null) {
            $whereClauses[] = 'ticket_number LIKE CONCAT("%", ?, "%")';
            $params[] = $search;
        }

        // 添加 WHERE 子句
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        // 准备 SQL 语句
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param(str_repeat("s", count($params)), ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $tableData[] = [
                    "id" => $row["id"],
                    "ticketNumber" => $row["ticket_number"],
                    "ticketType" => $row["ticket_type"],
                    "ticketStatus" => $row["ticket_state"],
                    "entryTime" => $row["entry_time"],
                ];
            }
        }
        // 获取总记录数
        $totalRecords = count($tableData);
        // 每页显示的记录数
        $recordsPerPage = 20;
        // 计算总页数
        $totalPages = ceil($totalRecords / $recordsPerPage);

        // 获取当前页数，默认为1
        $currentPage = isset($_GET["page"]) ? $_GET["page"] : 1;
        // 计算偏移量
        $offset = ($currentPage - 1) * $recordsPerPage;

        // 截取当前页的数据
        $pagedData = array_slice($tableData, $offset, $recordsPerPage);

        // 在数据前添加总页数信息
        array_unshift($pagedData, ["totalPage" => $totalPages]);

        return $pagedData;
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
    $conn->close();
}
?>
