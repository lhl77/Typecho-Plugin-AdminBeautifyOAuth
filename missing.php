<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; } ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>账号不存在 - OAuth 登录</title>
<?php
try {
    $pluginOptions = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautify');
    $preset = isset($pluginOptions->login_colorPreset) ? (string) $pluginOptions->login_colorPreset : 'purple';
    
    $colorPresets = array(
        'purple'   => array('#7d5260', '#9e7b8a'),
        'blue'     => array('#556270', '#7a8a9e'),
        'pink'     => array('#74565f', '#9e7a85'),
        'green'    => array('#55624c', '#7a8a6e'),
        'orange'   => array('#725a42', '#9e8062'),
        'red'      => array('#775654', '#a27a78'),
        'teal'     => array('#4a6363', '#6a8a8a'),
        'indigo'   => array('#5a4fd9', '#7b6ef2'),
        'sunset'   => array('#d38d1a', '#e06b3a'),
        'ocean'    => array('#0da0d8', '#39c1dd'),
        'forest'   => array('#2f7a3b', '#7fbf3a'),
        'lavender' => array('#8f6ee8', '#b89cfb'),
    );
    if ($preset === 'custom') {
        $primary = isset($pluginOptions->login_primaryColor) && trim((string) $pluginOptions->login_primaryColor) !== '' ? trim((string) $pluginOptions->login_primaryColor) : '#7d5260';
        $primary2 = isset($pluginOptions->login_primaryColor2) && trim((string) $pluginOptions->login_primaryColor2) !== '' ? trim((string) $pluginOptions->login_primaryColor2) : '#9e7b8a';
    } else {
        $colors = isset($colorPresets[$preset]) ? $colorPresets[$preset] : $colorPresets['purple'];
        $primary = $colors[0];
        $primary2 = $colors[1];
    }
    
    $bgImage = trim(isset($pluginOptions->login_bgImage) ? (string) $pluginOptions->login_bgImage : '');
    $bgCss = $bgImage !== '' ? "url(" . htmlspecialchars($bgImage, ENT_QUOTES, 'UTF-8') . ")" : "none";
    
    $blurType = isset($pluginOptions->login_blurType) && in_array($pluginOptions->login_blurType, array('none', 'filter', 'backdrop'), true) ? $pluginOptions->login_blurType : 'filter';
    
    $blurSize = isset($pluginOptions->login_blurSize) ? (int) $pluginOptions->login_blurSize : 12;
    if ($blurSize < 0) $blurSize = 0;
    if ($blurSize > 80) $blurSize = 80;
    
    $customCss = isset($pluginOptions->login_customCss) ? (string) $pluginOptions->login_customCss : '';
    
    $themeMode = isset($pluginOptions->login_themeMode) ? (string) $pluginOptions->login_themeMode : 'auto';
    if (!in_array($themeMode, array('auto', 'light', 'dark'), true)) {
        $themeMode = 'auto';
    }
    $jsThemeMode = json_encode($themeMode);

    $showToggle = !isset($pluginOptions->login_showThemeToggle) || (string) $pluginOptions->login_showThemeToggle !== '0';
    $customJs = isset($pluginOptions->login_customJs) ? (string) $pluginOptions->login_customJs : '';
} catch (Exception $e) {
    $primary = '#7d5260';
    $primary2 = '#9e7b8a';
    $bgCss = 'none';
    $blurSize = 12;
    $blurType = 'filter';
    $customCss = '';
    $jsThemeMode = '"auto"';
    $showToggle = true;
    $customJs = '';
}

$stylePath = dirname(__DIR__) . '/AdminBeautify/assets/pages/login/style.php';
if (is_file($stylePath)) {
    include $stylePath;
}
?>
<style>
.lb-wrap{padding:20px}
.lb-card{max-width:460px;position:relative}
.ab-missing-body{padding:30px 10px 10px}
.ab-missing-icon{
display:flex;
align-items:center;
justify-content:center;
width:56px;
height:56px;
margin:0 auto 16px;
border-radius:999px;
color:#fff;
font-weight:700;
font-size:28px;
background:linear-gradient(135deg,var(--lb-primary),var(--lb-primary2));
}
.ab-missing-title{
font-size:22px;
font-weight:700;
line-height:1.2;
margin:0 0 10px;
text-align:center;
color:var(--lb-on-surface);
}
.ab-missing-text{
font-size:14px;
line-height:1.75;
color:var(--lb-on-surface-muted);
text-align:center;
margin:0;
}
.ab-missing-actions{margin-top:24px}
.ab-missing-btn{
display:block;
width:100%;
text-align:center;
text-decoration:none;
padding:10px 16px;
border-radius:12px;
font-size:15px;
font-weight:600;
letter-spacing:.3px;
color:#fff !important;
background:linear-gradient(135deg,var(--lb-primary),var(--lb-primary2));
box-shadow: 0 4px 12px color-mix(in srgb, var(--lb-primary) 30%, transparent);
transition: opacity 0.2s, box-shadow 0.2s;
box-sizing:border-box;
}
.ab-missing-btn:hover {
opacity: 0.9;
box-shadow: 0 6px 16px color-mix(in srgb, var(--lb-primary) 40%, transparent);
}
</style>
</head>
<body>
<div class="lb-wrap">
    <div class="lb-bg"></div>
    <div class="lb-bg-overlay"></div>
    <div class="lb-card">
        <?php if ($showToggle): ?>
        <!-- <button type="button" class="lb-theme-toggle" aria-label="切换主题" id="ab-theme-btn" style="position:absolute;top:20px;right:20px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lb-icon-sun" style="display:none;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lb-icon-moon" style="display:block;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button> -->
        <?php endif; ?>

        <div class="ab-missing-body">
            <div class="ab-missing-icon">!</div>
            <h1 class="ab-missing-title">账号不存在</h1>
            <p class="ab-missing-text">该第三方账号尚未绑定任何本地用户，<br>请返回登录页使用已绑定账号登录。</p>
            <div class="ab-missing-actions">
                <a class="ab-missing-btn" href="<?php echo htmlspecialchars(Typecho_Common::url('login.php', $this->options->adminUrl), ENT_QUOTES, 'UTF-8'); ?>">返回登录界面</a>
            </div>
        </div>
        <div class="lb-footer-theme">Theme <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify" target="_blank" rel="noopener noreferrer">AdminBeautify</a> by <a href="https://blog.lhl.one" target="_blank" rel="noopener noreferrer">LHL</a></div>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('ab-theme-btn');
    if (!btn) return;
    var root = document.documentElement;
    var sun = btn.querySelector('.lb-icon-sun');
    var moon = btn.querySelector('.lb-icon-moon');
    
    function updateIcon() {
        var isDark = root.getAttribute('data-lb-theme') === 'dark';
        if (isDark) {
            sun.style.display = 'block';
            moon.style.display = 'none';
        } else {
            sun.style.display = 'none';
            moon.style.display = 'block';
        }
    }
    
    updateIcon();
    
    var observer = new MutationObserver(function() { updateIcon(); });
    observer.observe(root, { attributes: true, attributeFilter: ['data-lb-theme'] });
    
    btn.addEventListener('click', function(){
        var isDark = root.getAttribute('data-lb-theme') === 'dark';
        var next = isDark ? 'light' : 'dark';
        root.setAttribute('data-lb-theme', next);
        try { localStorage.setItem('lb-theme', next); } catch(e){}
    });
})();

<?php if (!empty($customJs)) echo $customJs; ?>
</script>
</body>
</html>
