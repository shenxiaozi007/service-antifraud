# 防诈助手 uni-app 用户端

## 工程位置

```text
apps/client
```

## 技术形态

- `uni-app + Vue 3 + TypeScript`
- 一套代码面向 H5、微信小程序、App。
- 后续用户端主线开发都在 `apps/client`。
- 原生小程序 `apps/mp-wechat` 仅保留为历史原型参考。

## 本地联调

后端：

```bash
cd www/service.antifraud.local.com
php -S 127.0.0.1:8000 -t public
```

前端 H5：

```bash
cd apps/client
npm install
npm run dev:h5
```

默认接口地址在：

```text
apps/client/src/api/config.ts
```

## 多端构建

H5：

```bash
cd apps/client
npm run build:h5
```

微信小程序：

```bash
cd apps/client
npm run build:mp-weixin
```

App 后续可用 HBuilderX / uni-app App 构建链路继续接入。

## 页面范围

- 首页：`src/pages/home/index.vue`
- 帮您看：`src/pages/image/index.vue`
- 帮您听：`src/pages/audio/index.vue`
- 报告：`src/pages/report/index.vue`
- 历史记录：`src/pages/history/index.vue`
- 我的：`src/pages/me/index.vue`
- 协议/隐私：`src/pages/content/index.vue`

## 当前限制

- OCR/ASR 仍由文本框模拟。
- 文件上传仍只走后端上传凭证，占位对象存储。
- 微信支付仍为 mock。
- 正式上线 H5 需要 HTTPS 域名；小程序需要真实 AppID 和合法域名。
