# 钉钉登录 Flarum 插件 | DingTalk Login for Flarum

[![Latest Stable Version](https://img.shields.io/packagist/v/jiushutech/flarum-dingtalk-login.svg)](https://packagist.org/packages/jiushutech/flarum-dingtalk-login)
[![License](https://img.shields.io/packagist/l/jiushutech/flarum-dingtalk-login.svg)](https://packagist.org/packages/jiushutech/flarum-dingtalk-login)
[![Flarum](https://img.shields.io/badge/Flarum-1.8%2B-orange)](https://flarum.org)

[English](#english) | [中文](#中文)

---

<a name="中文"></a>
## 中文

一款功能完整的钉钉登录 Flarum 扩展插件，支持 PC 扫码登录、H5 内嵌免登、企业专属登录等特性。

### ✨ 功能特性

#### 核心功能
- 🔐 **钉钉 OAuth 2.0 登录** - 支持 PC 端扫码登录
- 📱 **H5 内嵌免登** - 在钉钉客户端内自动完成登录
- 🔗 **账号绑定** - 支持现有用户绑定/解绑钉钉账号
- 👤 **自动注册** - 未关联用户可自动创建新账号

#### 登录控制
- ⚡ **强制绑定** - 要求所有用户必须绑定钉钉账号
- 🚫 **仅钉钉登录** - 禁用原生登录，仅允许钉钉登录
- 🏢 **企业专属登录** - 仅允许指定企业的用户登录
- 🛡️ **管理员豁免** - 指定用户可绕过登录限制

#### 增强功能
- 📊 **登录日志** - 记录所有登录行为，支持查询和导出
- 🔄 **信息同步** - 同步钉钉昵称、头像
- 🌐 **多语言支持** - 支持中文和英文

### 📋 环境要求

- Flarum 1.8.0+
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.2+

### 🚀 安装

```bash
composer require jiushutech/flarum-dingtalk-login
```

然后在 Flarum 后台启用扩展。

### ⚙️ 配置

#### 1. 创建钉钉应用

1. 登录 [钉钉开放平台](https://open.dingtalk.com/)
2. 创建一个企业内部应用或第三方应用
3. 获取 **AppKey** 和 **AppSecret**
4. 如需 H5 免登功能，还需获取 **AgentId** 和 **CorpId**

#### 2. 配置应用权限

在钉钉开放平台的应用管理中，需要添加以下权限：

**必需权限：**
- `通讯录个人信息读权限` - 用于获取用户基本信息

**配置步骤：**
1. 进入应用管理 → 权限管理
2. 搜索并添加上述权限
3. 等待权限审核通过（部分权限需要审核）

> ⚠️ **重要提示**：如果遇到 `Forbidden.AccessDenied.AccessTokenPermissionDenied` 错误，说明应用缺少必要的权限配置。

#### 3. 配置回调地址

在钉钉开放平台配置回调地址：

```
https://你的论坛域名/auth/dingtalk/callback
```

#### 4. 后台配置

在 Flarum 后台 → 扩展 → 钉钉登录 中配置：

| 配置项 | 说明 |
|--------|------|
| AppKey | 钉钉应用的 AppKey |
| AppSecret | 钉钉应用的 AppSecret |
| AgentId | H5 微应用的 AgentId（可选） |
| CorpId | 企业 CorpId（可选） |

### 📖 使用说明

#### PC 扫码登录

1. 用户点击登录页面的「钉钉扫码登录」按钮
2. 弹出钉钉扫码窗口
3. 用户使用钉钉 APP 扫码确认
4. 登录成功后自动跳转

#### H5 内嵌免登

1. 在钉钉客户端内打开论坛
2. 插件自动检测钉钉环境
3. 调用钉钉 JSAPI 获取免登授权码
4. 自动完成登录

#### 账号绑定

1. 已登录用户进入「设置」页面
2. 点击「绑定钉钉」按钮
3. 完成钉钉授权后绑定成功

### 🔧 高级配置

#### 强制绑定模式

开启后，未绑定钉钉的用户将无法：
- 发帖、回复
- 查看主题内容
- 进行其他操作

用户必须先绑定钉钉账号才能继续使用。

#### 仅钉钉登录模式

开启后：
- 登录页面仅显示钉钉登录按钮
- 原生登录接口被禁用
- 豁免用户仍可使用原生登录

#### 企业专属登录

开启后：
- 仅允许指定企业的钉钉用户登录
- 需要配置允许的企业 CorpId 列表
- 非指定企业用户将被拒绝登录

### 🔒 安全说明

- OAuth 流程使用 state 参数防止 CSRF 攻击
- 后台接口验证管理员权限
- 所有与钉钉 API 的通信使用 HTTPS

### 🛠️ 开发

#### 构建前端资源

```bash
cd js
npm install
npm run build
```

#### 监听模式开发

```bash
npm run dev
```

---

<a name="english"></a>
## English

A full-featured DingTalk login extension for Flarum, supporting PC QR code login, H5 auto-login, enterprise-only login, and more.

### ✨ Features

#### Core Features
- 🔐 **DingTalk OAuth 2.0 Login** - PC QR code scanning login
- 📱 **H5 Auto-Login** - Automatic login within DingTalk client
- 🔗 **Account Binding** - Bind/unbind DingTalk account for existing users
- 👤 **Auto Registration** - Automatically create accounts for new DingTalk users

#### Login Control
- ⚡ **Force Binding** - Require all users to bind DingTalk account
- 🚫 **DingTalk Only** - Disable native login, only allow DingTalk login
- 🏢 **Enterprise Only** - Only allow users from specified enterprises
- 🛡️ **Admin Exemption** - Specified users can bypass login restrictions

#### Enhanced Features
- 📊 **Login Logs** - Record all login activities with export support
- 🔄 **Info Sync** - Sync DingTalk nickname and avatar
- 🌐 **Multi-language** - Support Chinese and English

### 📋 Requirements

- Flarum 1.8.0+
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.2+

### 🚀 Installation

```bash
composer require jiushutech/flarum-dingtalk-login
```

Then enable the extension in Flarum admin panel.

### ⚙️ Configuration

#### 1. Create DingTalk Application

1. Login to [DingTalk Open Platform](https://open.dingtalk.com/)
2. Create an internal enterprise app or third-party app
3. Get **AppKey** and **AppSecret**
4. For H5 auto-login, also get **AgentId** and **CorpId**

#### 2. Configure Permissions

Add the following permissions in DingTalk Open Platform:

**Required Permissions:**
- `Contact Personal Info Read` - For getting user basic info

**Steps:**
1. Go to App Management → Permission Management
2. Search and add the above permissions
3. Wait for permission approval (some permissions require review)

> ⚠️ **Important**: If you encounter `Forbidden.AccessDenied.AccessTokenPermissionDenied` error, it means the app lacks necessary permissions.

#### 3. Configure Callback URL

Configure callback URL in DingTalk Open Platform:

```
https://your-forum-domain/auth/dingtalk/callback
```

#### 4. Admin Configuration

Configure in Flarum Admin → Extensions → DingTalk Login:

| Setting | Description |
|---------|-------------|
| AppKey | DingTalk app AppKey |
| AppSecret | DingTalk app AppSecret |
| AgentId | H5 mini-app AgentId (optional) |
| CorpId | Enterprise CorpId (optional) |

### 📖 Usage

#### PC QR Code Login

1. User clicks "Login with DingTalk" button on login page
2. DingTalk QR code popup appears
3. User scans QR code with DingTalk app
4. Auto redirect after successful login

#### H5 Auto-Login

1. Open forum within DingTalk client
2. Plugin auto-detects DingTalk environment
3. Calls DingTalk JSAPI to get auth code
4. Auto complete login

#### Account Binding

1. Logged-in user goes to "Settings" page
2. Click "Bind DingTalk" button
3. Complete DingTalk authorization to bind

### 🔧 Advanced Configuration

#### Force Binding Mode

When enabled, users without DingTalk binding cannot:
- Create posts or replies
- View topic content
- Perform other operations

Users must bind DingTalk account first to continue.

#### DingTalk Only Mode

When enabled:
- Login page only shows DingTalk login button
- Native login API is disabled
- Exempt users can still use native login

#### Enterprise Only Mode

When enabled:
- Only DingTalk users from specified enterprises can login
- Need to configure allowed enterprise CorpId list
- Users from other enterprises will be rejected

### 🔒 Security

- OAuth flow uses state parameter to prevent CSRF attacks
- Admin API endpoints verify administrator permissions
- All DingTalk API communications use HTTPS

### 🛠️ Development

#### Build Frontend Assets

```bash
cd js
npm install
npm run build
```

#### Development Watch Mode

```bash
npm run dev
```

---

## 📄 License | 许可证

MIT License

## 🤝 Contributing | 贡献

Welcome to submit Issues and Pull Requests!

欢迎提交 Issue 和 Pull Request！

## 📞 Support | 支持

If you have any questions, please report in [GitHub Issues](https://github.com/jiushutech/flarum-dingtalk-login/issues).

如有问题，请在 [GitHub Issues](https://github.com/jiushutech/flarum-dingtalk-login/issues) 中反馈。
