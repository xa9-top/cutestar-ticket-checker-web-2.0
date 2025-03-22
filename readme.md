## 星萌检票姬WEB2.0

### 项目概述

本项目基于[星萌检票姬WEB](https://gitee.com/miku_cute/cutestar-ticket-checker-web)制作

### 文档
这里是[开发文档/使用文档](https://github.com/xa9-top/cutestar-ticket-checker-web-2.0/wiki/)

**项目目标:**

- 建立一个功能完善的检票系统，实现票务管理、检票、售票等功能
- 基于开源项目星萌检票姬WEB，进行二次开发，增加更多实用功能

**技术栈:**

- **前端:** HTML, CSS, JavaScript
- **后端:** PHP = 7.x
- **数据库:** MySQL = 5.x

**功能模块:**

- **系统登录:**  使用前需要登录系统，防止未授权访问

- **票号生成:** 票种管理、票号生成、导出制票数据
- **售票系统:** 扫码售票、退票
- **检票系统:** 扫码检票
- **后台管理:** 票务管理(手动售票、检票、黑名单等操作)、数据统计

### 系统架构

**简要说明：**

- **系统分为前端和后端两部分:** 前端负责用户交互，后端处理业务逻辑和数据存储
- **检票系统核心功能:** 票号生成、票务管理、售票、检票、数据统计

### 功能实现细节

- **系统登录:**
  - 密码明文储存在`conf.php`内

- **票号生成:**
  - 支持添加多种票种
  - 批量生成票号
  - AES密钥生成功能
  - 可单独设置每张票的状态
  - 可导出数据为csv，方便制作票卡
- **售票、检票:**
  - 支持摄像头扫码和手动输入票数据(扫码机)两种方式
  - 将入场时间保存到数据库，方便查询和统计
- **后台管理:**
  - 可以显示各种状态的票的数量
  - 支持将用户加入黑名单，防止其入场
  - 可在后台管理黑名单用户


### 常见问题

1. **问:** 后台管理显示空白？检票或售票没有查到票信息？票号生成报错？

   **答:** 去查查MySQL的用户名，数据库名及密码是否设置正确？`conf.php`中MySQL连接配置是否正确？有没有导入SQL文件？PHP或MySQL的版本是否正确？

2. **问:** 可不可以同时使用多个售票/检票/后台管理等?

   **答:** 包可以的，上述所有页面都基于PHP提供的API来进行操作

3. **问:** 如何制作票卡?

   **答:** 在生成票号之后，导出票数据，然后将下载下来的csv中的ticket_data做成二维码即可(纯文本)，推荐使用[草料二维码](https://cli.im/)批量生成

## 星萌检票姬WEB项目 部署指南

### 准备工作

- **服务器环境:**
  - **操作系统:** 能运行Nginx/Apache和PHP即可，推荐使用宝塔面板
  - **Web 服务器:** Nginx/Apache
  - **运行环境:** MySQL=5.x, PHP=7.x

### 部署步骤

各种环境的安装我就不过多赘述了，直接进入正题

1. **从 GitHub 克隆项目到服务器:**

   ```shell
   git clone https://github.com/xa9-top/cutestar-ticket-checker-web-2.0
   ```

2. **创建数据库:**

   - 在 MySQL 中创建一个新的数据库，并为其设置用户名和密码
   - 将项目根目录下的 SQL 文件导入到新创建的数据库中

3. **配置` conf.php `文件:**

   在项目根目录找到 `conf.php`文件，根据您的数据库配置进行修改：

   ```php
   <?
       $db_host = "localhost";  // MySQL服务器
       $db_port = 3306;  // MySQL端口
       $db_username = "checker";  // MySQL用户名  
       $db_password = "";  // MySQL密码
       $db_name = "checker";  // MySQL数据库名
       $cookie_expire = 720;  // cookie有效期，单位分钟
       $user_password = "";  // 登录密码
   ?>
   ```

### 注意

- **PHP 版本:** 确保 PHP 版本是 7.x， MySQL版本是5.x

- **关于安全上下文:**  因为浏览器限制，在安全上下文中才可调用摄像头，所以需要为站点配置SSL证书或手动允许网页调用摄像头(Chrome内核浏览器打开[chrome://flags/#unsafely-treat-insecure-origin-as-secure](chrome://flags/#unsafely-treat-insecure-origin-as-secure)，然后在高亮的选项输入目标域名将右边的按钮并改为"已启用"即可，如下图)

  ![](https://github.com/xa9-top/cutestar-ticket-checker-web-2.0/blob/master/Markdown-res/unsafely-treat-insecure-origin-as-secure.png)

### 额外建议

- **备份:** 定期备份数据库

**通过以上步骤，您就可以成功部署星萌检票姬WEB项目了**

**如果您在部署过程中遇到任何问题，请仔细查看项目的文档或向我寻求帮助(本人QQ 2042499767)**

**请根据您的具体环境和项目配置进行相应的调整**

**祝您部署成功！**

**如果您有其他问题，欢迎随时提出**
