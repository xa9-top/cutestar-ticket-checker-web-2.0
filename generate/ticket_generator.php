<?php
include '../conf.php'; 
// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);
  
// 检测连接  
if ($conn->connect_error) {  
    die("连接失败: " . $conn->connect_error);  
}  
  
$type = $_POST['type']; // 用户输入的语音类型
$ticket_name = $_POST['ticket_name']; // 用户输入的票种名
$ticket_prefix = $_POST['ticket_prefix']; // 用户输入的票号前缀
$ticket_count = $_POST['ticket_count']; // 用户输入的票号生成数量
$first_ID = $_POST['first_ID']; // 用户输入的起始票号
$aes_key = $_POST['aes_key']; // 用户输入的AES密钥（可选，然而暂时没啥用）

$result = mysqli_query($conn, "SELECT id FROM tickets");
$ticket_id = $first_ID;

// 生成票号  
for ($i = 1; $i <= $ticket_count; $i++) {  

    $timestamp = time(); // 当前的时间戳  
    $random_hex = bin2hex(random_bytes(4)); // 4个字节的随机16进制数，共8位  
    $ticket_number = $ticket_prefix . str_pad($ticket_id, 4, '0', STR_PAD_LEFT); // 拼接成票号，为了更加易读，取消了随机码在前端的展示
    $ticket_predata = $ticket_prefix . str_pad($ticket_id, 4, '0', STR_PAD_LEFT) . "_" . $random_hex ; // 拼接成票号前缀+票号+随机数  
    //对票号进行base64编码
    //$ticket_data = base64_encode($ticket_predata);
    //对票号进行aes加密,去除了多余的bin2hex，如果扫码枪确实大小写不分的话……建议换把枪。miku表示随便买了一堆枪都没有这情况。
    $ticket_data = openssl_encrypt($ticket_predata, 'aes-256-ecb', $aes_key, 0, '');
    // 插入到数据库中  
    $sql = "INSERT INTO tickets (ticket_name,ticket_type,ticket_number,ticket_data,ticket_state) VALUES ('$ticket_name','$type','$ticket_number','$ticket_data','3')";  
    /*
    以下是以前的逐行打印生成票码的逻辑，现在直接显示总数。
    if ($conn->query($sql) === TRUE) {  
        echo "票号生成成功: " . $ticket_number . "<br>";  
    } else {  
        echo "Error: " . $sql . "<br>" . $conn->error;  
    }  
    */
    $conn->query($sql);
    $ticket_id++;
}  
$i--;
echo "票号生成成功: " . $i . "张";
// 关闭连接  
$conn->close();  
?>
  
<html>  
<head>  
  <title>票号生成器</title>  
</head>  
<body>  
  <h2>票号生成器</h2>  
  <p>请输入前缀和要生成的票号数量：</p>  
  <form id="ticketForm" action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="post">  
    播报语音类型：<select name="type">  
      <option value="N">普通票</option>  
      <option value="V">VIP票</option>  
      <option value="Y">夜场票</option>  
    </select>  <br>
    票种名：<input type="text" id="ticketName" name="ticket_name"><br> 
    票号前缀：<input type="text" id="ticketPrefix" name="ticket_prefix"><br>
    生成数量：<input type="text" id="ticketCount" name="ticket_count"><br>  
    起始ID：<input type="text" id="firstID" name="first_ID" value="1"><br>
    AES密钥：<input type="text" id="aesKey" name="aes_key"><br>
    <input type="submit" id="submit" value="生成票号">  
  </form>  
  <p>只需点击1次生成按钮，即可生成指定数量的票号。</p>
  <p>注意：请不要手动刷新页面。生成数量超过3000可能会导致前端显示504错误，但后端会继续计算直到php超时或者生成完。</p>
  <p>单击"生成票号"后等待页面自动刷新即可，查询请去后台。</p>
  <p>可以使用另一个页面一键导出票号csv。</p>

</body>  
</html>
