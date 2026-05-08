# 防诈助手微信小程序 MVP

## 工程位置

```text
apps/mp-wechat
```

## 技术形态

- 微信小程序原生工程。
- 用户端接口前缀：`/api/v1`。
- 默认联调服务地址：`http://127.0.0.1:8000`，配置文件为 `apps/mp-wechat/utils/config.js`。
- 微信开发者工具导入目录：`apps/mp-wechat`。
- 本地联调需要在微信开发者工具中关闭合法域名校验，或配置本地开发域名。

## 页面范围

- 首页：`pages/home/index`
- 帮您看：`pages/image/index`
- 帮您听：`pages/audio/index`
- 分析报告：`pages/report/index`
- 历史记录：`pages/history/index`
- 我的：`pages/me/index`
- 用户协议 / 隐私政策：`pages/content/index`

## 联调流程

1. 启动后端本地服务：

```bash
cd www/service.antifraud.local.com
php -S 127.0.0.1:8000 -t public
```

2. 打开微信开发者工具，导入 `apps/mp-wechat`。
3. 首页进入“帮您看”或“帮您听”。
4. MVP 阶段可在页面文本框里输入规则分析文本，例如：

```text
保证收益，稳赚不赔，名额有限
```

或：

```text
不要告诉家人，把验证码发给我，共享屏幕
```

5. 提交后进入报告页，可从历史记录再次查看。

## 当前能力

- 自动登录并保存 token。
- 创建文件上传凭证。
- 图片分析联调。
- 录音采集和录音分析联调。
- 风险报告展示。
- 历史记录筛选。
- 点数余额和点数流水。
- 协议、隐私、客服入口。
- 报告分享入口。

## MVP 限制

- 当前文件上传只走凭证和文件元信息，尚未上传真实文件到对象存储。
- OCR/ASR 暂由页面文本框模拟转写结果。
- 录音真实文件暂未上传，后续对象存储接入后替换。
- 小程序 `appid` 使用 `touristappid`，正式调试时需替换为实际 AppID。
- 生产环境需要配置合法请求域名、隐私协议和接口 HTTPS。
