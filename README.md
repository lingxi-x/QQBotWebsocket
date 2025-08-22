# QQ官方机器人
QQ官方机器人的PHP实现，本项目仅在PHP7.4版本下测试正常，其他版本暂未测试

### 安装说明
- 运行环境：PHP 7.4，ext-json、ext-curl
- 安装依赖：`composer install`
- 配置：复制并修改 `config/config.php` 或配置环境变量 `QQBOT_*`
- 启动：`php bin/bot.php`

### 事件订阅 Intents
默认订阅：
- GROUP_AND_C2C_EVENT (1<<25)
- PUBLIC_GUILD_MESSAGES (1<<30)

### 被动回复
- 单聊：`POST /v2/users/{openid}/messages`
- 群聊：`POST /v2/groups/{group_openid}/messages`

> 建议优先使用 **被动回复**，官方已在 2025-04-21 调整主动推送策略，不再提供主动推送能力。

### 示例代码
请看 /src/Handlers/ 文件夹

### 说明
本框架仅做参考，如需使用，请自行拓展api与其他功能。
> 作者QQ：1559141584