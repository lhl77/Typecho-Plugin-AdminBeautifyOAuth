<h1 align="center">AdminBeautifyOAuth</h1>

<p align="center">
  <strong>Typecho 的 AdminBeautify 专用 OAuth 第三方登录插件</strong>
</p>

<p align="center">
  在登录页显示第三方登录按钮，并在个人设置页完成绑定 / 解绑。
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Typecho-%3E%3D1.2.1-orange?style=flat-square" alt="Typecho >= 1.2.1">
  <img src="https://img.shields.io/badge/PHP-%3E%3D7.2-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP >= 7.2">
  <img src="https://img.shields.io/badge/AdminBeautify-Required-6750A4?style=flat-square" alt="AdminBeautify Required">
  <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautifyOAuth/issues"><img src="https://img.shields.io/github/issues/lhl77/Typecho-Plugin-AdminBeautifyOAuth?style=flat-square" alt="Issues"></a>
  <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautifyOAuth/stargazers"><img src="https://img.shields.io/github/stars/lhl77/Typecho-Plugin-AdminBeautifyOAuth?style=flat-square&logo=github" alt="GitHub Stars"></a>
</p>

<p align="center">
  快捷链接：
  <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautifyOAuth">GitHub</a> |
  <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautifyOAuth/issues">问题反馈</a> |
  <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify">AdminBeautify</a>
</p>

---
## 截图
![](https://i.see.you/2026/05/07/zy5S/a50802dffe2f858862ca771f2badc364.jpg)

## 功能特色

| 功能 | 说明 |
| ---- | ---- |
| 登录页 OAuth 登录 | 在 AdminBeautify 登录页插入第三方登录按钮 |
| 个人中心账号绑定 | 在个人设置页绑定 / 解绑第三方账号 |
| 两种登录按钮样式 | 支持精简图标模式与完整按钮模式 |
| SDK 自动扫描 | 自动识别 sdk 目录中的 `*SDK.class.php` 平台 |
| 颜色集中管理 | 通过 `sdk/color.php` 管理按钮、图标背景与文字色 |
| 配置可视化编辑 | 后台直接编辑平台、启用状态、Client ID、Client Secret |
| 导入导出配置 | 支持导出到剪贴板、导出 JSON、从剪贴板 / 文件导入 |

## 安装

### 方式一：下载压缩包

1. 下载仓库源码或发布包
2. 解压后将目录重命名为 `AdminBeautifyOAuth`
3. 上传到 Typecho 的 `usr/plugins/` 目录
4. 后台进入 控制台 -> 插件，启用 `AdminBeautifyOAuth`

### 方式二：Git 克隆

```bash
cd /your-site/usr/plugins/
git clone https://github.com/lhl77/Typecho-Plugin-AdminBeautifyOAuth.git AdminBeautifyOAuth
```

目录示例：

```text
your-site/
└── usr/
    └── plugins/
        └── AdminBeautifyOAuth/
```

## 使用说明

1. 先确保 `AdminBeautify` 已安装并启用
2. 进入插件设置页面
3. 为目标平台填写 `Client ID` 与 `Client Secret`
4. 使用“复制回调地址”按钮，将地址填入对应开放平台后台
5. 选择登录页显示样式：`精简显示` 或 `完整显示`
6. 保存配置后，在登录页与个人设置页查看效果

## SDK 自动扫描

插件会自动扫描以下目录中的 SDK 文件：

```text
sdk/*SDK.class.php
```

只要目录中存在匹配文件，对应平台就会自动出现在插件配置中，无需再手动修改平台列表代码。

平台颜色与显示扩展信息来自：

```text
sdk/color.php
```

`color.php` 当前支持的字段：

- `background`：按钮或图标背景色
- `text`：按钮文字色
- `title`：可选，自定义显示名称

## 注意事项

- 若登录页未显示按钮，请优先检查以下内容：
- `AdminBeautify` 是否已启用
- 对应平台是否已启用
- `Client ID / Client Secret` 是否已正确填写
- 回调地址是否已配置到平台后台


## 相关项目

- AdminBeautify: https://github.com/lhl77/Typecho-Plugin-AdminBeautify
- AdminBeautifyOAuth: https://github.com/lhl77/Typecho-Plugin-AdminBeautifyOAuth

---

<p align="center">
  Made by <a href="https://github.com/lhl77">LHL</a>
</p>
