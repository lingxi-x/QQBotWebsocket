<?php
return [
    // 在开放平台获取
    'app_id'        => getenv('QQBOT_APP_ID') ?: 'YOUR_APPID',
    'client_secret' => getenv('QQBOT_CLIENT_SECRET') ?: 'YOUR_SECRET',

    // 是否使用沙箱： wss://sandbox.api.sgroup.qq.com/websocket
    'api_base'      => getenv('QQBOT_API_BASE') ?: 'https://api.sgroup.qq.com',
    'wss_url'       => getenv('QQBOT_WSS_URL') ?: 'wss://api.sgroup.qq.com/websocket/',

    // 订阅的事件 intents（示例：C2C/群@/公域@）
    // GROUP_AND_C2C_EVENT (1<<25) | PUBLIC_GUILD_MESSAGES (1<<30)
    'intents'       => (1 << 25) | (1 << 30),

    // 分片设置（单链接）
    'shard'         => [0, 1],

    // 日志级别：debug|info|error
    'log_level'     => getenv('QQBOT_LOG_LEVEL') ?: 'info',

    // 主动消息禁用标志（2025-04-21 起官方下线主动推送，默认只做被动回复）
    'disable_active_push' => true,
];
