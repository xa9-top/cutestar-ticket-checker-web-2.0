<?php
include '../conf.php'; 
// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname, $port);
  
// 检测连接  
if ($conn->connect_error) {  
    die("连接失败: " . $conn->connect_error);  
}  
  
$prefix = $_POST['prefix']; // 用户输入的前缀，这里假设从POST请求中获取  
$ticket_name = $_POST['ticket_name'];
$ticket_count = $_POST['ticket_count']; // 用户输入的票号生成数量，这里假设从POST请求中获取  
$aes_key = $_POST['aes_key'];

$result = mysqli_query($conn, "SELECT id FROM tickets");
$genid = mysqli_num_rows($result); // 获取结果集中的行数

// 生成票号  
for ($i = 1; $i <= $ticket_count; $i++) {  
    $genid++;
    $timestamp = time(); // 当前的时间戳  
    $random_hex = bin2hex(random_bytes(4)); // 4个字节的随机16进制数，共8位  
    $ticket_number = $prefix . str_pad($i, 4, '0', STR_PAD_LEFT); // 拼接成票号，为了更加易读，取消了随机码在前端的展示
    $ticket_predata = $prefix . str_pad($i, 4, '0', STR_PAD_LEFT) . "_" . $random_hex ; // 拼接成票号前缀+票号+随机数  
    //对票号进行base64编码
    //$ticket_data = base64_encode($ticket_predata);
    //对票号进行aes加密
    $ticket_data = bin2hex(openssl_encrypt($ticket_predata, 'aes-128-ecb', $aes_key, 0, ''));
    // 插入到数据库中  
    $sql = "INSERT INTO tickets (id, ticket_name,ticket_type,ticket_number,ticket_data,ticket_state) VALUES ('$genid','$ticket_name','$prefix','$ticket_number','$ticket_data','3')";  
    if ($conn->query($sql) === TRUE) {  
        echo "票号生成成功: " . $ticket_number . "<br>";  
    } else {  
        echo "Error: " . $sql . "<br>" . $conn->error;  
    }  
}  
  
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
    前缀：<input type="text" id="prefix" name="prefix"><br>
    类型：<input type="text" id="ticketName" name="ticket_name"><br> 
    票号数量：<input type="text" id="ticketCount" name="ticket_count"><br>  
    AES密钥：<input type="text" id="aesKey" name="aes_key"><br>
    <input type="submit" id="submit" value="生成票号">  
  </form>  
  <p>只需点击1次生成按钮，即可生成指定数量的票号。</p>
  <p>注意：请不要手动刷新页面。</p>
  <p>单击"生成票号"后请用鼠标滚轮或拖动页面滚动条查看提交的票号数量全部生成完毕后再生成其他</p>
  <p>生成的票号将会在页面中显示出来，可以使用另一个页面一键导出csv。</p>

  
  <div id="result"></div>  
  
  
  <script>  
    document.getElementById('ticketForm').addEventListener('submit', function(event) {  
      event.preventDefault(); // 阻止表单提交的默认行为  
      var prefix = document.getElementById('prefix').value;  
      var ticketName = document.getElementById('ticketName').value;
      var ticketCount = document.getElementById('ticketCount').value;  
      var aesKey = document.getElementById('aesKey').value;
      var xhr = new XMLHttpRequest();  
      xhr.open('POST', 'ticket_generator.php', true);  
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');  
      xhr.onreadystatechange = function() {  
        if (xhr.readyState === 4 && xhr.status === 200) {  
          var response = JSON.parse(xhr.responseText);  
          if (response.status === 'success') {  
            var resultDiv = document.getElementById('result');  
            resultDiv.innerHTML = '<p>生成的票号如下：</p>';  
            for (var i = 0; i < response.ticket_numbers.length; i++) {  
              resultDiv.innerHTML += '<p>' + response.ticket_numbers[i] + '</p>';  
            }  
          } else {  
            alert('生成票号失败：' + response.message);  
          }  
        }  
      };  
      xhr.send('prefix=' + encodeURIComponent(prefix) + '&ticket_name=' + encodeURIComponent(ticket_name) + '&ticket_count=' + encodeURIComponent(ticketCount) +'&aes_key=' + encodeURIComponent(aesKey));  
    // 以上代码用于在表单提交后，通过AJAX向后端发送请求，获取生成的票号

  </script>  
  
  <?php
$conn = new mysqli($servername, $username, $password, $dbname);  
$sql = "SELECT id,ticket_number,ticket_name,ticket_data,ticket_state,entry_time FROM tickets";  
$result = mysqli_query($conn, $sql);  
if (mysqli_num_rows($result) > 0) {  
  while ($row = mysqli_fetch_assoc($result)) {  
    echo "<p>ID:" . strval($row["id"]) . " 票号:" . $row["ticket_number"] . " 票类型:" . $row["ticket_name"] . " 票状态:" . $row["ticket_state"] . " 票数据:". $row["ticket_data"] . " 入场时间:" . $row["entry_time"] . "</p>";  
  }  
} else {  
  echo "暂无票号";  
}  

?>
</body>  
</html>