<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; } ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>账号不存在 - OAuth 登录</title>
    <style>
        :root {
            --md-primary:#6750a4;
            --md-surface:#fffbfe;
            --md-on-surface:#1c1b1f;
            --md-outline:#cac4d0;
            --md-surface-container:#f3edf7;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --md-primary:#d0bcff;
                --md-surface:#1c1b1f;
                --md-on-surface:#e6e1e5;
                --md-outline:#49454f;
                --md-surface-container:#211f26;
            }
        }
        * { box-sizing:border-box; }
        body {
            margin:0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--md-primary) 18%, transparent), transparent 52%), var(--md-surface);
            color:var(--md-on-surface);
            font:14px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            padding:16px;
        }
        .card {
            width:min(460px, 100%);
            background:var(--md-surface-container);
            border:1px solid var(--md-outline);
            border-radius:24px;
            padding:24px;
            box-shadow:0 10px 30px rgba(0,0,0,.16);
        }
        .icon {
            width:56px; height:56px;
            border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            background:color-mix(in srgb, var(--md-primary) 20%, transparent);
            color:var(--md-primary);
            font-size:30px;
            margin-bottom:10px;
        }
        h1 { margin:0 0 6px; font-size:22px; }
        p { margin:0; opacity:.85; }
        .actions { margin-top:18px; display:flex; gap:10px; }
        .btn {
            display:inline-flex; align-items:center; justify-content:center;
            padding:10px 16px;
            border-radius:999px;
            text-decoration:none;
            border:1px solid var(--md-outline);
            color:var(--md-on-surface);
            background:transparent;
            font-weight:600;
        }
        .btn.primary { background:var(--md-primary); color:#fff; border-color:transparent; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">!</div>
    <h1>账号不存在</h1>
    <p>该第三方账号未绑定任何本地用户。</p>
    <p>请返回登录界面，使用已绑定的第三方账号登录。</p>
    <div class="actions">
        <a class="btn primary" href="<?php echo htmlspecialchars(Typecho_Common::url('login.php', $this->options->adminUrl)); ?>">返回登录界面</a>
    </div>
</div>
</body>
</html>
