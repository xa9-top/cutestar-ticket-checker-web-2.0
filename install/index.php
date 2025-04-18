<?php
$confsaved = false;
if (isset($_GET["install"])){
    $conf_file = __DIR__ . '/../conf.php';
    require '../user/adminauth.php';
    
    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
        $config = [
            'host' => $_POST['host'],
            'port' => (int)$_POST['port'],
            'user' => $_POST['user'],
            'pass' => $_POST['pass'],
            'dbname' => $_POST['dbname'],
            'cookie' => (int)$_POST['cookie'],
            'user_pass' => $_POST['user_pass'],
            'admin_pass' => $_POST['admin_pass']
        ];
    
        // 测试MySQL连接
        $conn = @mysqli_connect($config['host'], $config['user'], $config['pass'], '', $config['port']);
        if ($conn) {
            // 生成配置文件内容
            $conf_content = '<?php
    $db_host = "' . $config['host'] . '";  // MySQL服务器
    $db_port = ' . $config['port'] . ';  // MySQL端口
    $db_username = "' . $config['user'] . '";  // MySQL用户名  
    $db_password = "' . $config['pass'] . '";  // MySQL密码
    $db_name = "' . $config['dbname'] . '";  // MySQL数据库名
    $cookie_expire = ' . $config['cookie'] . ';  // cookie有效期，单位分钟
    $user_password = "' . $config['user_pass'] . '";  // 用户登录密码
    $admin_password = "'. $config['admin_pass']. '";  // 管理员登录密码
?>';
    
            file_put_contents($conf_file, $conf_content);
            $confsaved = true;

            // 创建数据库和表结构
            if (!mysqli_select_db($conn, $config['dbname'])) {
                if (!mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `{$config['dbname']}` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci")) {
                    $error = '数据库创建失败: ' . mysqli_error($conn);
                    mysqli_close($conn);
                    exit;
                }
            }
            mysqli_select_db($conn, $config['dbname']);
            
            $tables_exist = mysqli_query($conn, "SHOW TABLES LIKE 'tickets'");
            if (mysqli_num_rows($tables_exist) == 0) {
                mysqli_begin_transaction($conn);
                $all_success = true;
                
                $sql_statements = [
                    "CREATE TABLE `tickets` (
                        `id` int(11) NOT NULL,
                        `ticket_name` varchar(50) NOT NULL,
                        `ticket_type` varchar(3) NOT NULL,
                        `ticket_number` varchar(50) NOT NULL,
                        `ticket_data` varchar(256) NOT NULL,
                        `ticket_state` int(11) NOT NULL,
                        `entry_time` varchar(50) DEFAULT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
                    "CREATE TABLE `ticket_types` (
                        `id` int(11) NOT NULL,
                        `name` varchar(50) NOT NULL,
                        `prefix` varchar(3) NOT NULL,
                        `max_number` varchar(50) DEFAULT '0'
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                    "ALTER TABLE `tickets` ADD PRIMARY KEY (`id`)",
                    "ALTER TABLE `ticket_types` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `prefix` (`prefix`)",
                    "ALTER TABLE `tickets` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT",
                    "ALTER TABLE `ticket_types` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT",
                    "ALTER TABLE `tickets` ADD INDEX(`ticket_data`);"
                ];
    
                foreach ($sql_statements as $sql) {
                    if (!mysqli_query($conn, $sql)) {
                        $all_success = false;
                        $error = '数据库初始化失败: ' . mysqli_error($conn);
                        break;
                    }
                }
    
                if ($all_success) {
                    mysqli_commit($conn);
                    $success = '配置保存成功且数据库已初始化！';
                } else {
                    mysqli_rollback($conn);
                }
            } else {
                $success = '配置保存成功！数据库已存在';
            }
            mysqli_close($conn);
        } else {
            $error = 'MySQL连接失败: ' . mysqli_connect_error();
        }
    }
    if ($confsaved){
        $db_host = $config['host'];
        $db_port = $config['port'];
        $db_username = $config['user'];
        $db_password = $config['pass'];
        $db_name = $config['dbname'];
        $cookie_expire = $config['cookie'];
        $user_password = $config['user_pass'];
        $admin_password = $config['admin_pass'];
    } else {
        require_once($conf_file);
    }
    // 处理SQL导入
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['save'])) {
        // 创建数据库连接
        $conn = @mysqli_connect($db_host, $db_username, $db_password, '', $db_port);
        
        if ($conn) {
            mysqli_select_db($conn, $db_name);
            $sql_statements = [
        "USE `$db_name`;",
        "DROP TABLE IF EXISTS tickets, ticket_types;",
        "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'",
        "SET time_zone = '+00:00'",
        "CREATE TABLE `tickets` (
            `id` int(11) NOT NULL,
            `ticket_name` varchar(50) NOT NULL,
            `ticket_type` varchar(3) NOT NULL,
            `ticket_number` varchar(50) NOT NULL,
            `ticket_data` varchar(256) NOT NULL,
            `ticket_state` int(11) NOT NULL,
            `entry_time` varchar(50) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        "CREATE TABLE `ticket_types` (
            `id` int(11) NOT NULL,
            `name` varchar(50) NOT NULL,
            `prefix` varchar(3) NOT NULL,
            `max_number` varchar(50) DEFAULT '0'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE `tickets` ADD PRIMARY KEY (`id`)",
        "ALTER TABLE `ticket_types` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `prefix` (`prefix`)",
        "ALTER TABLE `tickets` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT",
        "ALTER TABLE `ticket_types` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT",
        "ALTER TABLE `tickets` ADD INDEX(`ticket_data`);"
        ];
    
        mysqli_begin_transaction($conn);
        $all_success = true;
    
        foreach ($sql_statements as $index => $sql) {
            if (!mysqli_query($conn, $sql)) {
                $all_success = false;
                $import_error = 'SQL执行错误 (语句#' . ($index+1) . '): ' . mysqli_error($conn);
                break;
            }
        }
    
        if ($all_success) {
            mysqli_commit($conn);
            // 验证表结构
            $result = mysqli_query($conn, "SHOW TABLES LIKE 'tickets'");
            if (mysqli_num_rows($result) > 0) {
                $import_success = '数据库重置成功！';
            } else {
                $import_error = '表结构创建失败';
            }
        } else {
            mysqli_rollback($conn);
        }
            mysqli_close($conn);
        }
    }
} else if(isset($_GET["auth"])) {
    require '../user/adminauth.php';
    echo "success";
} else {
    echo'<script>fetch("?auth=true").then(response => response.text()).then(text => {if (text.trim() === "authfail") {window.location.href = "/user/login.php?from="+ encodeURIComponent(window.location.href);} else {window.location.href = "?install=true";}})</script>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>系统安装</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            color: #fff;
            background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.2)), url('/image/bg.jpg') center/cover fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .dashboard {
            background: rgba(255, 255, 255, 0.65);
            padding: 40px 50px;
            border-radius: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(12px);
            width: 450px;
            text-align: center;
            transition: transform 0.4s cubic-bezier(0.18, 0.89, 0.32, 1.28);
        }

        .dashboard:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        h1 {
            margin-bottom: 2rem;
            font-size: 2.2em;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.8rem;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid rgba(113, 198, 238, 0.3);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            color: #2c3e50;
        }

        input:focus {
            outline: none;
            border-color: #71c6ee;
            box-shadow: 0 0 8px rgba(113, 198, 238, 0.3);
        }

        button {
            background: rgba(113, 198, 238, 0.85);
            color: #fff;
            padding: 14px 30px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        button:hover {
            background: rgba(100, 195, 230, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .alert {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-size: 14px;
        }

        .success {
            background: rgba(223, 240, 216, 0.9);
            border: 1px solid #d6e9c6;
            color: #3c763d;
        }

        .error {
            background: rgba(242, 222, 222, 0.9);
            border: 1px solid #ebccd1;
            color: #a94442;
        }
        .container { 
          max-width: 600px;
          margin: auto;
          padding: 20px;
          display: flex;
          flex-direction: column;
          gap: 1.5rem;
        }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 8px; }
        .alert { padding: 10px; margin: 10px 0; }
        .success { background: #dff0d8; }
        .error { background: #f2dede; }
        /* 弹窗样式 */
        .modal {
            display: none;
            /* 默认隐藏 */
            position: fixed;
            /* 固定位置 */
            z-index: 1;
            /* 位于顶层 */
            left: 0;
            top: 0;
            width: 100%;
            /* 全宽 */
            height: 100%;
            /* 全高 */
            overflow: auto;
            /* 如果需要滚动条 */
            background-color: rgba(0, 0, 0, 0.4);
            /* 背景色，带有透明度 */
        }

        /* 弹窗内容框 */
        .modal-content {
            background-color: #f1f1f1;
            margin: 15% auto;
            /* 位于页面中心 */
            padding: 20px;
            border: 1px solid #888;
            width: 60%;
            /* 宽度 */
            max-width: 400px;
            /* 最大宽度 */
            height: auto;
            /* 高度自动 */
            border-radius: 8px;
            /* 圆角 */
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            /* 添加阴影 */
            opacity: 0;
            /* 初始不透明度为0 */
            transform: scale(0.9);
            /* 初始缩放为0.9 */
            animation: fadeIn 0.3s ease forwards;
            /* 添加动画 */
        }

        /* 入场动画 */
        @keyframes fadeIn {
            0% {
                opacity: 0;
                transform: scale(0.9);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* 出场动画 */
        @keyframes fadeOut {
            0% {
                opacity: 1;
                transform: scale(1);
            }

            100% {
                opacity: 0;
                transform: scale(0.9);
            }
        }

        /* 添加出场动画的类 */
        .modal-content.out {
            animation: fadeOut 0.3s ease forwards;
        }

        /* 按钮样式 */
        .modal-button {
            background-color: #3B99FC;
            color: white;
            padding: 14px 20px;
            margin: 8px 0;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            /* 圆角 */
        }

        .modal-button:hover {
            opacity: 0.8;
        }

        /* “否”按钮样式 */
        #cancel-button {
            background-color: #a9a9a9;
            /* 灰色 */
        }

        /* 按钮容器样式 */
        .button-container {
            display: flex;
            justify-content: space-between;
            /* 按钮分布在容器两端 */
        }
        
        .modaltext {
            color: #000;
        }
    </style>
</head>
<body>
    <!-- 弹窗 --> 
    <div id="myModal" class="modal"> 
        <!-- 弹窗内容 --> 
        <div class="modal-content"> 
            <span class="close modaltext" id="close-button" style="cursor: pointer;">&times;</span> 
            <p class="modaltext" id="modal-message">前端好难啊啊啊啊啊啊</p> 
            <div class="button-container"> 
            <button class="modal-button" id="cancel-button">否</button> 
            <button class="modal-button" id="confirm-button">是</button> 
            </div> 
        </div> 
    </div> 
    <div class="container">
        <h1>系统配置</h1>

        <?php if(isset($error)): ?>
            <div class="alert error" id='1'><?= $error ?></div>
        <?php elseif(isset($success)): ?>
            <div class="alert success" id='1'><?= $success ?></div>
        <?php endif; ?>
        <?php if(isset($import_success)): ?>
            <div class="alert success" id='2'><?= $import_success ?></div>
        <?php elseif(isset($import_error)): ?>
            <div class="alert error" id='2'><?= $import_error ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>MySQL地址：</label>
                <input type="text" name="host" value="<?= $db_host ?>" required>
            </div>

            <div class="form-group">
                <label>MySQL端口：</label>
                <input type="number" name="port" value="<?= $db_port ?>" required>
            </div>

            <div class="form-group">
                <label>数据库用户：</label>
                <input type="text" name="user" value="<?= $db_username ?>" required>
            </div>

            <div class="form-group">
                <label>数据库密码：</label>
                <input type="text" name="pass" value="<?= $db_password ?>" required>
            </div>

            <div class="form-group">
                <label>数据库名称：</label>
                <input type="text" name="dbname" value="<?= $db_name ?>" required>
            </div>

            <div class="form-group">
                <label>Cookie有效期（分钟）：</label>
                <input type="number" name="cookie" value="<?= $cookie_expire ?>" required>
            </div>

            <div class="form-group">
                <label>用户登录密码：</label>
                <input type="text" name="user_pass" value="<?= $user_password ?>" required>
            </div>

            <div class="form-group">
                <label>管理员登录密码：</label>
                <input type="text" name="admin_pass" value="<?= $admin_password ?>" required>
            </div>

            <button type="submit" name="save">保存配置并初始化数据库</button>
            <button type="button" name="reset" style="margin-left:20px" onclick="showModal('重置数据库将覆盖当前数据库，是否继续？', this.form)">重置数据库</button>
        </form>
    </div>
    <script>
        // 显示弹窗
        function showModal(message, form) {
            var modal = document.getElementById("myModal");
            var messageElement = document.getElementById("modal-message");
            var confirmButton = document.getElementById("confirm-button");
            var cancelButton = document.getElementById("cancel-button");
            var closeButton = document.getElementById("close-button");

            messageElement.textContent = message;

            // 点击“是”按钮时执行的函数
            confirmButton.onclick = function() {
                modal.classList.add('out');
                setTimeout(function() {
                    modal.style.display = "none";
                    modal.classList.remove('out'); // 移除出场动画类
                }, 300); // 等待动画结束
                form.submit();
            }

            // 点击“否”按钮时隐藏弹窗
            cancelButton.onclick = function() {
                modal.classList.add('out');
                setTimeout(function() {
                    modal.style.display = "none";
                    modal.classList.remove('out'); // 移除出场动画类
                }, 300); // 等待动画结束
                return false;
            }

            // 点击“X”时触发出场动画
            closeButton.onclick = function() {
                modal.classList.add('out');
                setTimeout(function() {
                    modal.style.display = "none";
                    modal.classList.remove('out'); // 移除出场动画类
                }, 300); // 等待动画结束
                return false;
            };

            // 点击弹窗外部时触发出场动画
            window.onclick = function(event) {
                if (event.target === modal) {
                    modal.classList.add('out');
                    setTimeout(function() {
                        modal.style.display = "none";
                        modal.classList.remove('out'); // 移除出场动画类
                    }, 300); // 等待动画结束
                    return false;
                }
            };

            // 显示弹窗
            modal.classList.add('show'); // 添加'show'类以触发过渡效果
            modal.style.display = "block";
            // 点击“X”时触发出场动画

        }
    </script> 
</body>
</html>
