<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
/**
 * AdminBeautify 专用 OAuth 登录插件
 *
 * @package AB-OAuth
 * @author  LHL
 * @version 1.0.1
 * @link    https://github.com/lhl77/Typecho-Plugin-AdminBeautify
 */
class AdminBeautifyOAuth_Plugin implements Typecho_Plugin_Interface
{
    const TABLE_NAME = 'ab_oauth_user';

    public static function activate()
    {
        Helper::addRoute('ab_oauth', '/ab-oauth', 'AdminBeautifyOAuth_Widget', 'oauth');
        Helper::addRoute('ab_oauth_callback', '/ab-oauth-callback', 'AdminBeautifyOAuth_Widget', 'callback');
        Helper::addRoute('ab_oauth_callback_type', '/ab-oauth-callback/[type:string]', 'AdminBeautifyOAuth_Widget', 'callback');
        Helper::addRoute('ab_oauth_toggle', '/ab-oauth-toggle', 'AdminBeautifyOAuth_Widget', 'toggle');
        Helper::addRoute('ab_oauth_missing', '/ab-oauth-missing', 'AdminBeautifyOAuth_Widget', 'missing');

        Typecho_Plugin::factory('admin/footer.php')->end = array(__CLASS__, 'renderFooter');

        self::installTable();
        self::ensureDefaultConfig();
        return _t('AdminBeautifyOAuth 已启用');
    }

    public static function deactivate()
    {
        Helper::removeRoute('ab_oauth');
        Helper::removeRoute('ab_oauth_callback');
        Helper::removeRoute('ab_oauth_callback_type');
        Helper::removeRoute('ab_oauth_toggle');
        Helper::removeRoute('ab_oauth_missing');
        return _t('AdminBeautifyOAuth 已禁用');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        self::ensureDefaultConfig();
        $routeHealthy = self::hasCallbackTypeRoute();

        $providersJson = self::defaultProvidersJson();
        try {
            $opt = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautifyOAuth');
            if (!empty($opt->providers_json)) {
                $providersJson = (string) $opt->providers_json;
            }
        } catch (Exception $e) {
        }

        $adminReady = self::isAdminBeautifyReady();
        if (!$adminReady) {
            $providersHidden = new Typecho_Widget_Helper_Form_Element_Hidden(
                'providers_json',
                null,
                $providersJson
            );
            $form->addInput($providersHidden);
            echo '<div class="ab-oauth-panel">'
                . '<div class="ab-oauth-head">'
                . '<strong>AdminBeautify 专用 OAuth 第三方登录插件</strong>'
                . '<p style="margin:.5em 0 0;color:#dc2626">未检测到 AdminBeautify，OAuth 代理将自动禁用。请先安装：<a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify" target="_blank" rel="noopener">https://github.com/lhl77/Typecho-Plugin-AdminBeautify</a></p>'
                . '</div>'
                . '</div>';
            return;
        }

        $providersInput = new Typecho_Widget_Helper_Form_Element_Textarea(
            'providers_json',
            null,
            $providersJson,
            _t('OAuth 配置数据'),
            _t('由下方可视化编辑器自动维护，请勿手动修改。')
        );
        // 隐藏原生 textarea 标签行
        echo '<style>ul[id^="typecho-option-item-providers_json-"]{display:none!important}</style>';
        $form->addInput($providersInput);

        // 登录界面显示样式设置
        $loginStyleValue = 'compact';
        try {
            $opt2 = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautifyOAuth');
            if (!empty($opt2->login_style)) {
                $loginStyleValue = (string)$opt2->login_style;
            }
        } catch (Exception $e) {}
        $loginStyleInput = new Typecho_Widget_Helper_Form_Element_Radio(
            'login_style',
            array('compact' => '精简显示', 'full' => '完整显示'),
            $loginStyleValue,
            _t('登录界面显示样式'),
            _t('精简：只显示各平台图标按钮；完整：每行显示图标和"通过 xxx 登录"文字。')
        );
        $form->addInput($loginStyleInput);

        $tips = '<div class="ab-oauth-panel">'
            . '<div class="ab-oauth-head">'
            . '<strong>AdminBeautify 专用 OAuth 第三方登录插件</strong>'
            . '<p>支持：QQ / 微信 / GitHub / Google / MSN / 新浪 / 豆瓣 / 百度 / OIDC 等。</p>'
            . '</div>'
            . '<div class="ab-oauth-guide">'
            . '<span><b>显示名称</b>：按钮与列表展示文字</span>'
            . '<span><b>Client ID</b>：平台分配的 AppKey / Client ID</span>'
            . '<span><b>Client Secret</b>：平台分配的 AppSecret / Client Secret</span>'
            . '</div>'
            . '<div class="ab-oauth-toolbar"><button type="button" id="ab-oauth-add-row" class="ab-oauth-btn">+ 新增平台</button><button type="button" id="ab-oauth-export-clip" class="ab-oauth-btn-tonal">导出到剪贴板</button><button type="button" id="ab-oauth-export-file" class="ab-oauth-btn-tonal">导出 .json</button><button type="button" id="ab-oauth-import-clip" class="ab-oauth-btn-outlined">从剪贴板导入</button><label id="ab-oauth-import-file-label" class="ab-oauth-btn-outlined">从文件导入<input type="file" id="ab-oauth-import-file" accept=".json,application/json" style="display:none"></label></div>'
            . '<div id="ab-oauth-config-editor" style="margin-top:16px !important;"></div>'
            . '<div class="ab-oauth-help">'
            . '<p>回调地址获取方式：点击对应配置的"复制回调地址"按钮即可一键复制。</p>'
            . '<p>使用文档：<a href="https://blog.lhl.one/artical/1199.html" target="_blank" rel="noopener">https://blog.lhl.one/artical/1199.html</a></p>'
            . '</div>'
            . '</div>';

        if (!$routeHealthy) {
            $tips = '<div class="ab-oauth-route-alert">'
                . '<strong>检测到路由未更新</strong>'
                . '<p>当前站点缺少新版回调路由 <code>/ab-oauth-callback/[type]</code>，请执行一次“禁用 -> 启用”以完成路由注册。</p>'
                . '<p><b>重要：</b>禁用再启用前，请先点击“导出到剪贴板”或“导出 .json”备份配置，避免配置丢失。</p>'
                . '</div>'
                . $tips;
        }
        $callbackBase = htmlspecialchars(Typecho_Common::url('/ab-oauth-callback/', Typecho_Widget::widget('Widget_Options')->index), ENT_QUOTES);

        echo '<style>
#ab-oauth-config-editor,
.ab-oauth-panel{--ab-md-surface:var(--md-surface,#fff);--ab-md-on:var(--md-on-surface,#1f1b24);--ab-md-on-subtle:var(--md-on-surface-variant,#6b7280);--ab-md-outline:var(--md-outline-variant,#d5d7de);--ab-md-primary:var(--md-primary,#6750a4);--ab-md-danger:#ef4444}
#ab-oauth-config-editor{display:grid;gap:16px;margin-bottom:12px}
.ab-oauth-row{display:grid;grid-template-columns:120px 1fr 1.1fr 1.1fr auto auto auto;gap:10px;align-items:start;padding:14px 16px;border-radius:14px;background:var(--md-surface-container,#f3edf7);border:1px solid var(--ab-md-outline)}
.ab-oauth-field{display:flex;flex-direction:column;gap:4px;min-width:0}
.ab-oauth-label{font-size:11px;line-height:1;font-weight:600;color:var(--ab-md-on-subtle);user-select:none}
.ab-oauth-row input,.ab-oauth-row select{width:100%;height:34px;padding:5px 10px;border:1px solid var(--ab-md-outline);border-radius:8px;background:var(--ab-md-surface);color:var(--ab-md-on);font-size:13px;transition:border-color 0.2s;box-sizing:border-box}
.ab-oauth-row input:focus,.ab-oauth-row select:focus{border-color:var(--ab-md-primary);outline:none}
.ab-oauth-row input::placeholder{color:color-mix(in srgb,var(--ab-md-on-subtle) 55%, transparent)}
.ab-oauth-row .ab-oauth-del{border:none;background:var(--ab-md-danger);color:#fff;border-radius:8px;height:34px;padding:0 12px;cursor:pointer;font-weight:600;font-size:13px;margin-top:15px;white-space:nowrap}
.ab-oauth-row .ab-oauth-copy{border:1px solid var(--ab-md-outline);background:transparent;color:var(--ab-md-on-subtle);border-radius:8px;height:34px;padding:0 10px;cursor:pointer;font-size:12px;margin-top:15px;white-space:nowrap;transition:background 0.15s,color 0.15s}
.ab-oauth-row .ab-oauth-copy:hover{background:color-mix(in srgb,var(--ab-md-primary) 10%, transparent);color:var(--ab-md-primary);border-color:var(--ab-md-primary)}
.ab-oauth-enabled{display:flex;align-items:center;height:34px;gap:6px;padding:0 10px;border:1px dashed var(--ab-md-outline);border-radius:8px;color:var(--ab-md-on-subtle);cursor:pointer;margin-top:15px;white-space:nowrap}
.ab-oauth-enabled input{width:15px;height:15px;accent-color:var(--ab-md-primary)}
.ab-oauth-head p{margin:.4em 0 .8em;color:var(--ab-md-on-subtle)}
.ab-oauth-guide{display:grid;gap:6px;padding:12px 16px;border:1px solid var(--ab-md-outline);border-radius:16px;background:color-mix(in srgb,var(--ab-md-primary) 5%, var(--ab-md-surface));margin-bottom:16px}
.ab-oauth-guide span{font-size:12px;color:var(--ab-md-on-subtle)}
.ab-oauth-guide b{color:var(--ab-md-on)}
.ab-oauth-help{font-size:12px;color:var(--ab-md-on-subtle);margin-top:12px}
.ab-oauth-help a{color:var(--ab-md-primary);text-decoration:none;font-weight:600}
.ab-oauth-route-alert{margin-bottom:14px;padding:12px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--ab-md-primary) 35%, #ef4444 65%);background:color-mix(in srgb,#ef4444 8%,var(--ab-md-surface));color:var(--ab-md-on)}
.ab-oauth-route-alert strong{display:block;font-size:13px;margin-bottom:4px;color:#b42318}
.ab-oauth-route-alert p{margin:4px 0;font-size:12px;line-height:1.6}
.ab-oauth-route-alert code{padding:1px 6px;border-radius:999px;background:color-mix(in srgb,var(--ab-md-primary) 12%,transparent);color:var(--ab-md-primary)}
.ab-oauth-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:4px}
#ab-oauth-add-row,.ab-oauth-btn{border:none;background:var(--ab-md-primary);color:var(--md-on-primary,#fff);border-radius:999px;padding:10px 20px;font-weight:600;font-size:14px;cursor:pointer;box-shadow:0 4px 10px color-mix(in srgb,var(--md-primary,#6750a4) 30%, transparent)}
.ab-oauth-btn-tonal{border:none;background:color-mix(in srgb,var(--ab-md-primary) 12%,var(--ab-md-surface));color:var(--ab-md-primary);border-radius:999px;padding:10px 20px;font-weight:600;font-size:14px;cursor:pointer}
.ab-oauth-btn-outlined{border:1.5px solid var(--ab-md-primary);background:transparent;color:var(--ab-md-primary);border-radius:999px;padding:10px 20px;font-weight:600;font-size:14px;cursor:pointer}
[data-theme="dark"] #ab-oauth-config-editor,
[data-theme="dark"] .ab-oauth-panel{--ab-md-surface:var(--md-dark-surface-container,#211f26);--ab-md-on:var(--md-dark-on-surface,#e6e1e5);--ab-md-on-subtle:var(--md-dark-on-surface-variant,#cac4d0);--ab-md-outline:var(--md-dark-outline-variant,#49454f);--ab-md-primary:var(--md-dark-primary,#d0bcff)}
[data-theme="dark"] .ab-oauth-row{background:var(--md-dark-surface-container-low,#1c1b1f)}
[data-theme="dark"] #ab-oauth-add-row,[data-theme="dark"] .ab-oauth-btn{color:#1c1b1f;box-shadow:0 4px 10px color-mix(in srgb,var(--ab-md-primary) 15%, transparent)}
@media (max-width: 1080px){.ab-oauth-row{grid-template-columns:1fr 1fr;gap:10px}.ab-oauth-row .ab-oauth-del,.ab-oauth-enabled,.ab-oauth-row .ab-oauth-copy{grid-column:1 / -1;margin-top:0}}
</style>';

        echo $tips;

        echo '<script>(function(){
var catalog=' . json_encode(self::providerCatalog(), JSON_UNESCAPED_UNICODE) . ';
var cbBase=' . json_encode($callbackBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
function fallbackCopy(text){
    var ta=document.createElement("textarea");ta.value=text;
    ta.style.cssText="position:fixed;top:-9999px;left:-9999px;opacity:0";
    document.body.appendChild(ta);ta.select();
    try{document.execCommand("copy");}catch(e){}
    document.body.removeChild(ta);
}
function safeParse(v){try{var d=JSON.parse(v||"[]");return Array.isArray(d)?d:[];}catch(e){return [];}}
function cleanText(v){return (v==null?"":String(v)).replace(/\s+/g," ").trim();}
function createInput(className, placeholder, value){
    var input=document.createElement("input");
    input.className=className;
    input.placeholder=placeholder;
    input.value=value||"";
    return input;
}
function wrapField(labelText, node){
    var wrap=document.createElement("div");
    wrap.className="ab-oauth-field";
    var label=document.createElement("span");
    label.className="ab-oauth-label";
    label.textContent=labelText;
    wrap.appendChild(label);
    wrap.appendChild(node);
    return wrap;
}
function rowTemplate(item, sync){
  var row=document.createElement("div");
  row.className="ab-oauth-row";
    var select=document.createElement("select");
    select.className="ab-type";
    var keys=Object.keys(catalog);
    for(var i=0;i<keys.length;i++){
        var k=keys[i];
        var opt=document.createElement("option");
        opt.value=k;
        opt.textContent=(catalog[k]&&catalog[k].title)?catalog[k].title:k;
        if(k===item.type){opt.selected=true;}
        select.appendChild(opt);
    }
    var titleInput=createInput("ab-title","显示名称",item.title||"");
    var keyInput=createInput("ab-key","APP_KEY / Client ID",item.appKey||"");
    var secretInput=createInput("ab-secret","APP_SECRET / Client Secret",item.appSecret||"");
    var label=document.createElement("label");
    label.className="ab-oauth-enabled";
    var enabled=document.createElement("input");
    enabled.type="checkbox";
    enabled.className="ab-enabled";
    enabled.checked=!!item.enabled;
    label.appendChild(enabled);
    label.appendChild(document.createTextNode(" 启用"));
    var delBtn=document.createElement("button");
    delBtn.type="button";
    delBtn.className="ab-oauth-del";
    delBtn.textContent="删除";

    row.appendChild(wrapField("登录平台", select));
    row.appendChild(wrapField("显示名称", titleInput));
    row.appendChild(wrapField("Client ID", keyInput));
    row.appendChild(wrapField("Client Secret", secretInput));
    row.appendChild(label);
    var copyBtn=document.createElement("button");
    copyBtn.type="button";
    copyBtn.className="ab-oauth-copy";
    copyBtn.textContent="复制回调地址";
    copyBtn.addEventListener("click",function(){
        var type=row.querySelector(".ab-type").value;
        var cbUrl=cbBase+encodeURIComponent(type);
        if(navigator.clipboard){navigator.clipboard.writeText(cbUrl).then(function(){copyBtn.textContent="已复制 ✓";setTimeout(function(){copyBtn.textContent="复制回调地址";},1800);}).catch(function(){fallbackCopy(cbUrl);});}
        else{fallbackCopy(cbUrl);}
    });
    row.appendChild(copyBtn);
    row.appendChild(delBtn);

    delBtn.addEventListener("click",function(){row.remove();sync();});
    var fields=row.querySelectorAll("input,select");
    for(var j=0;j<fields.length;j++){
        fields[j].addEventListener("input",sync);
        fields[j].addEventListener("change",sync);
    }
  return row;
}
function init(textarea, root, addBtn){
    function sync(){
        var data=[];
        var rows=root.querySelectorAll(".ab-oauth-row");
        for(var i=0;i<rows.length;i++){
            var row=rows[i];
            data.push({
                type: row.querySelector(".ab-type").value,
                title: cleanText(row.querySelector(".ab-title").value),
                appKey: cleanText(row.querySelector(".ab-key").value),
                appSecret: cleanText(row.querySelector(".ab-secret").value),
                enabled: !!row.querySelector(".ab-enabled").checked
            });
        }
        textarea.value=JSON.stringify(data);
    }

  var data=safeParse(textarea.value);
  if(!data.length){data=[{type:"github",title:"GitHub",appKey:"",appSecret:"",enabled:true}];}
    for(var i=0;i<data.length;i++){
        root.appendChild(rowTemplate(data[i]||{}, sync));
    }
  sync();
    addBtn.addEventListener("click",function(){
        root.appendChild(rowTemplate({type:"github",title:"GitHub",appKey:"",appSecret:"",enabled:true}, sync));
        sync();
    });
}
function tryMount(retry){
    var textarea=document.querySelector("textarea[name=providers_json],textarea[name$=\"[providers_json]\"],textarea[id*=\"providers_json\"]");
    var root=document.getElementById("ab-oauth-config-editor");
    var addBtn=document.getElementById("ab-oauth-add-row");
    if(!textarea||!root||!addBtn){
        if(retry<12){setTimeout(function(){tryMount(retry+1);},120);} 
        return;
    }
    if(root.getAttribute("data-ab-mounted")==="1") return;
    root.setAttribute("data-ab-mounted","1");
    textarea.style.display="none";
    init(textarea, root, addBtn);
    // 导出到剪贴板
    document.getElementById("ab-oauth-export-clip").addEventListener("click",function(){
        var btn=this;
        var txt=textarea.value||"[]";
        if(navigator.clipboard){navigator.clipboard.writeText(txt).then(function(){btn.textContent="已复制 ✓";setTimeout(function(){btn.textContent="导出到剪贴板";},2000);});}
        else{var ta=document.createElement("textarea");ta.value=txt;document.body.appendChild(ta);ta.select();try{document.execCommand("copy");}catch(e){}document.body.removeChild(ta);btn.textContent="已复制 ✓";setTimeout(function(){btn.textContent="导出到剪贴板";},2000);}
    });
    // 导出 .json 文件
    document.getElementById("ab-oauth-export-file").addEventListener("click",function(){
        var blob=new Blob([textarea.value||"[]"],{type:"application/json"});
        var url=URL.createObjectURL(blob);
        var a=document.createElement("a");a.href=url;a.download="ab-oauth-config.json";document.body.appendChild(a);a.click();
        setTimeout(function(){URL.revokeObjectURL(url);document.body.removeChild(a);},500);
    });
    // 从剪贴板导入
    document.getElementById("ab-oauth-import-clip").addEventListener("click",function(){
        if(navigator.clipboard&&navigator.clipboard.readText){
            navigator.clipboard.readText().then(function(txt){importJson(txt);}).catch(function(){alert("无法读取剪贴板，请使用\"从文件导入\"。");});
        } else{alert("浏览器不支持读取剪贴板，请使用\"从文件导入\"。");}
    });
    // 从文件导入
    document.getElementById("ab-oauth-import-file").addEventListener("change",function(){
        var file=this.files[0]; if(!file) return;
        var reader=new FileReader();
        reader.onload=function(e){importJson(e.target.result);};
        reader.readAsText(file);
        this.value="";
    });
    function importJson(txt){
        var data; try{data=JSON.parse(txt);}catch(e){alert("配置格式错误，请检查 JSON 内容。");return;}
        if(!Array.isArray(data)){alert("配置格式错误：顶层应为数组。");return;}
        root.innerHTML="";
        var s=function(){var d=[];var rs=root.querySelectorAll(".ab-oauth-row");for(var i=0;i<rs.length;i++){var r=rs[i];d.push({type:r.querySelector(".ab-type").value,title:cleanText(r.querySelector(".ab-title").value),appKey:cleanText(r.querySelector(".ab-key").value),appSecret:cleanText(r.querySelector(".ab-secret").value),enabled:!!r.querySelector(".ab-enabled").checked});}textarea.value=JSON.stringify(d);};
        for(var i=0;i<data.length;i++){root.appendChild(rowTemplate(data[i]||{},s));} s();
        alert("导入成功，请记得保存设置。");
    }
}
if(document.readyState==="loading"){
    document.addEventListener("DOMContentLoaded",function(){tryMount(0);});
}else{
    tryMount(0);
}
})();</script>';
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
        // 增加一个占位 input 避免已保存过设置的用户在调用 $form->getInput($key)->value() 时报 null 异常
        $placeholder = new Typecho_Widget_Helper_Form_Element_Hidden('ab_oauth_profile_moved', null, '1');
        $form->addInput($placeholder);
        
        // 隐藏默认的插件个人设置区域
        echo '<style>#personal-AdminBeautifyOAuth{display:none!important}</style>';
    }

    public static function renderFooter()
    {
        if (!self::isAdminBeautifyReady()) {
            return;
        }

        $user = Typecho_Widget::widget('Widget_User');
        $isLogin = !$user->hasLogin();
        $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';

        if ($isLogin) {
            $providers = self::options('', true);
            if (!empty($providers) && strpos($uri, 'register.php') === false) {
                self::renderLoginOAuthHint($providers);
            }
            return;
        }

        if (strpos($uri, 'profile.php') !== false) {
            self::renderProfileSidebarOAuth(self::profileProviders(), (int)$user->uid);
        }
    }

    private static function renderLoginOAuthHint($providers)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $iconsBaseUrl = Typecho_Common::url('AdminBeautifyOAuth/icons', $options->pluginUrl);
        $sdkColors = self::sdkButtonColors();

        $loginStyle = 'compact';
        try {
            $opt = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautifyOAuth');
            if (!empty($opt->login_style)) $loginStyle = (string)$opt->login_style;
        } catch (Exception $e) {}

        $buttons = array();
        foreach ($providers as $type => $meta) {
            $styleColor = isset($sdkColors[$type]) && is_array($sdkColors[$type]) ? $sdkColors[$type] : array();
            $background = !empty($styleColor['background']) ? (string)$styleColor['background'] : $meta['color'];
            $text = !empty($styleColor['text']) ? (string)$styleColor['text'] : '#ffffff';
            $buttons[] = array(
                'type'       => $type,
                'title'      => $meta['title'],
                'color'      => $meta['color'],
                'background' => $background,
                'text'       => $text,
                'label'      => self::firstCharLabel($meta['title']),
                'icon'       => $iconsBaseUrl . '/' . $type . '.svg',
                'href'       => Typecho_Common::url('/ab-oauth?type=' . rawurlencode($type), $options->index),
            );
        }

        echo '<script>(function(){
var btns=' . self::jsonEncodeForScript($buttons, '[]') . ';
var style=' . json_encode($loginStyle) . ';
function mount(){
  var form=document.querySelector("#login-form, form[action*=login]")||document.querySelector("form");
  if(!form||document.getElementById("ab-oauth-login-hint")) return;
  var box=document.createElement("div");
  box.id="ab-oauth-login-hint";
  box.className="ab-oauth-login-hint";
  var actionsHtml="";
  var titleHtml="<div class=\"ab-oauth-login-title\"><span class=\"ab-oauth-title-line\"></span><span class=\"ab-oauth-title-text\">OAuth \u767b\u5f55</span><span class=\"ab-oauth-title-line\"></span></div>";
  if(style==="full"){
    btns.forEach(function(i){
            actionsHtml+="<a class=\"ab-oauth-login-btn ab-oauth-login-btn--full\" href=\""+i.href+"\" style=\"--ab-oauth-color:"+i.color+";--ab-oauth-bg:"+i.background+";--ab-oauth-text:"+i.text+"\"><span class=\"ab-oauth-login-icon\" data-label=\""+i.label+"\"><img src=\""+i.icon+"\" alt=\""+i.title+"\" width=\"20\" height=\"20\" onerror=\"this.style.display=&quot;none&quot;;this.parentNode.textContent=this.parentNode.getAttribute(&quot;data-label&quot;);\"></span><span class=\"ab-oauth-login-text\">\u901a\u8fc7 "+i.title+" \u767b\u5f55</span></a>";
    });
    box.innerHTML=titleHtml+"<div class=\"ab-oauth-login-actions ab-oauth-login-actions--full\">"+actionsHtml+"</div>";
  } else {
    btns.forEach(function(i){
            actionsHtml+="<a class=\"ab-oauth-login-btn\" href=\""+i.href+"\" title=\""+i.title+"\" aria-label=\""+i.title+"\" data-label=\""+i.label+"\" style=\"--ab-oauth-color:"+i.color+";--ab-oauth-bg:"+i.background+";--ab-oauth-text:"+i.text+"\"><img src=\""+i.icon+"\" alt=\""+i.title+"\" width=\"24\" height=\"24\" onerror=\"this.style.display=&quot;none&quot;;this.parentNode.textContent=this.parentNode.getAttribute(&quot;data-label&quot;);\"></a>";
    });
    box.innerHTML=titleHtml+"<div class=\"ab-oauth-login-actions\">"+actionsHtml+"</div>";
  }
  form.appendChild(box);
}
if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",mount); else mount();
})();</script>';

        echo '<style>
.ab-oauth-login-hint{margin-top:12px;padding:8px 0 4px;text-align:center}
.ab-oauth-login-title{display:flex;align-items:center;gap:8px;margin-bottom:10px;color:var(--md-on-surface-variant,#79747e);font-size:11px;font-weight:500;letter-spacing:.06em}
.ab-oauth-title-line{flex:1;height:1px;background:rgba(0,0,0,.08);}
.ab-oauth-title-text{white-space:nowrap}
/* 精简模式：icon 格 */
.ab-oauth-login-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
.ab-oauth-login-btn{width:42px;height:42px;border:1px solid color-mix(in srgb,var(--ab-oauth-bg,#6750a4) 88%,#000 12%);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none!important;color:var(--ab-oauth-text,#fff);background:var(--ab-oauth-bg,#6750a4);box-shadow:0 1px 4px color-mix(in srgb,var(--ab-oauth-bg,#6750a4) 25%, transparent);transition:box-shadow .18s ease,background-color .18s ease,border-color .18s ease,color .18s ease;overflow:hidden}
.ab-oauth-login-btn img{width:24px;height:24px;object-fit:contain;display:block;pointer-events:none}
.ab-oauth-login-btn:hover{color:var(--ab-oauth-text,#fff)!important;text-decoration:none!important;box-shadow:0 4px 12px color-mix(in srgb,var(--ab-oauth-bg,#6750a4) 40%, transparent);background-color:color-mix(in srgb,var(--ab-oauth-text,#fff) 8%,var(--ab-oauth-bg,#6750a4));border-color:color-mix(in srgb,var(--ab-oauth-bg,#6750a4) 78%,#000 22%)}
.ab-oauth-login-btn:active{color:var(--ab-oauth-text,#fff)!important;text-decoration:none!important;box-shadow:0 1px 4px color-mix(in srgb,var(--ab-oauth-bg,#6750a4) 28%, transparent);background-color:color-mix(in srgb,var(--ab-oauth-text,#fff) 12%,var(--ab-oauth-bg,#6750a4));border-color:color-mix(in srgb,var(--ab-oauth-bg,#6750a4) 72%,#000 28%)}
/* 完整模式：一行一个，匹配 AB primary button */
.ab-oauth-login-actions--full{flex-direction:column;align-items:stretch;gap:8px}
.ab-oauth-login-btn--full{width:100%;height:40px;box-sizing:border-box;border:1px solid color-mix(in srgb,var(--ab-oauth-bg,#6750a4) 88%,#000 12%);border-radius:var(--md-radius-full,999px);padding:0 24px;gap:6px;justify-content:center;font-size:15px;font-weight:500;letter-spacing:.02em;line-height:1;box-shadow:var(--md-elevation-1,0 1px 2px rgba(0,0,0,.15));text-decoration:none!important;position:relative;overflow:hidden;background:var(--ab-oauth-bg,#6750a4);color:var(--ab-oauth-text,#fff)!important}
.ab-oauth-login-btn--full:hover{color:var(--ab-oauth-text,#fff)!important;text-decoration:none!important;box-shadow:var(--md-elevation-2,0 2px 6px rgba(0,0,0,.2));background-color:color-mix(in srgb,var(--ab-oauth-text,#fff) 8%,var(--ab-oauth-bg,#6750a4));border-color:color-mix(in srgb,var(--ab-oauth-bg,#6750a4) 78%,#000 22%)}
.ab-oauth-login-btn--full:active{color:var(--ab-oauth-text,#fff)!important;text-decoration:none!important;background-color:color-mix(in srgb,var(--ab-oauth-text,#fff) 12%,var(--ab-oauth-bg,#6750a4));border-color:color-mix(in srgb,var(--ab-oauth-bg,#6750a4) 72%,#000 28%);box-shadow:var(--md-elevation-1,0 1px 2px rgba(0,0,0,.15))}
.ab-oauth-login-icon{width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;border-radius:4px}
.ab-oauth-login-btn--full .ab-oauth-login-icon{position:absolute;left:18px;top:50%;transform:translateY(-50%)}
.ab-oauth-login-icon img{width:20px;height:20px;object-fit:contain;display:block;pointer-events:none}
.ab-oauth-login-text{display:block;width:100%;text-align:center;padding:0 28px;box-sizing:border-box}
[data-lb-theme="dark"] .ab-oauth-title-line{background:rgba(255,255,255,.08)!important;}
[data-lb-theme="dark"] .ab-oauth-login-title{color:var(--md-dark-on-surface-variant,#938f99)}
</style>';
    }

    private static function renderProfileSidebarOAuth($providers, $uid)
    {
        $db = Typecho_Db::get();
        $rows = $db->fetchAll(
            $db->select()
               ->from('table.' . self::TABLE_NAME)
               ->where('uid = ?', (int)$uid)
        );

        $bound = array();
        foreach ($rows as $row) {
            $bound[$row['type']] = array(
                'nickname' => isset($row['nickname']) ? (string)$row['nickname'] : '',
                'updated' => isset($row['updated']) ? (string)$row['updated'] : '',
            );
        }

        $options = Typecho_Widget::widget('Widget_Options');
        $settingsUrl = Typecho_Common::url('options-plugin.php?config=AdminBeautifyOAuth', $options->adminUrl);
        $sdkColors = self::sdkButtonColors();
        $items = array();
        foreach ($providers as $type => $meta) {
            $isBound = isset($bound[$type]);
            $isConfigured = !empty($meta['configured']);
            $actionUrl = $isConfigured
                ? Typecho_Common::url('/ab-oauth-toggle?action=' . ($isBound ? 'unbind' : 'bind') . '&type=' . rawurlencode($type), $options->index)
                : $settingsUrl;
            $iconBg = (isset($sdkColors[$type]['background']) && $sdkColors[$type]['background'] !== '')
                ? (string)$sdkColors[$type]['background']
                : $meta['color'];
            $items[] = array(
                'type' => $type,
                'title' => $meta['title'],
                'color' => $iconBg,
                'configured' => $isConfigured,
                'bound' => $isBound,
                'nickname' => $isBound ? $bound[$type]['nickname'] : '',
                'actionUrl' => $actionUrl,
                'btnText' => $isConfigured ? ($isBound ? '移除' : '绑定') : '去配置',
            );
        }

        // 用 base64 编码含 "AdminBeautify" 的字符串，防止 AB AJAX 脚本执行器
        // 跳过含该关键词的内联脚本（AB 会 skip textContent 含 'AdminBeautify' 的 <script>）
        $itemsB64 = base64_encode(json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $settingsUrlB64 = base64_encode($settingsUrl);
        $iconsBaseUrlB64 = base64_encode(Typecho_Common::url('AdminBeautifyOAuth/icons', $options->pluginUrl));

        echo '<script>(function(){
function _ab64(s){try{return decodeURIComponent(escape(atob(s)));}catch(e){return atob(s);}}
var items=JSON.parse(_ab64("' . $itemsB64 . '"));
var settingsUrl=_ab64("' . $settingsUrlB64 . '");
var iconsBaseUrl=_ab64("' . $iconsBaseUrlB64 . '");
function buildCard(){
    var card=document.createElement("div");
    card.id="ab-oauth-profile-card";
    card.className="ab-oauth-profile-card";
    card.innerHTML="<div class=\"ab-oauth-profile-title\"><span class=\"ab-oauth-section-icon\"><span class=\"material-icons-round\">link</span></span><span>OAuth \u7b2c\u4e09\u65b9\u767b\u5f55</span></div><div class=\"ab-oauth-profile-sub\">\u5728\u6b64\u7ed1\u5b9a\u6216\u79fb\u9664\u7b2c\u4e09\u65b9\u767b\u5f55</div><div class=\"ab-oauth-profile-list\"></div>";
    var list=card.querySelector(".ab-oauth-profile-list");
    if(!items.length){
        var empty=document.createElement("div");
        empty.className="ab-oauth-profile-empty";
        empty.innerHTML="\u6682\u672a\u542f\u7528\u4efb\u4f55\u767b\u5f55\u5e73\u53f0\u3002\u8bf7\u5148\u5230 <a href=\""+settingsUrl+"\">\u63d2\u4ef6\u8bbe\u7f6e</a> \u4e2d\u542f\u7528\u3002";
        list.appendChild(empty);
    } else {
        items.forEach(function(i){
            var row=document.createElement("div");
            row.className="ab-oauth-profile-row"+(i.bound?" is-bound":"")+(!i.configured?" is-unconfigured":"");
            var state=i.configured?(i.bound?(i.nickname?"\u5df2\u7ed1\u5b9a\uff1a"+i.nickname:"\u5df2\u7ed1\u5b9a"):"\u672a\u7ed1\u5b9a"):"\u672a\u914d\u7f6e AppKey / Secret";
            var fl=String(i.title||"O").slice(0,1).toUpperCase();
            row.innerHTML="<div class=\"ab-oauth-left\"><span class=\"ab-oauth-dot\" style=\"--ab-oauth-color:"+i.color+"\" data-letter=\""+fl+"\"><img class=\"ab-oauth-icon-img\" src=\""+iconsBaseUrl+"/"+i.type+".svg\" onerror=\"var p=this.parentNode;p.removeChild(this);p.textContent=p.dataset.letter\"></span><div><div class=\"ab-oauth-name\">"+i.title+"</div><div class=\"ab-oauth-state\">"+state+"</div></div></div><a class=\"ab-oauth-btn\" href=\""+i.actionUrl+"\">"+i.btnText+"</a>";
            list.appendChild(row);
        });
    }
    return card;
}
function placeCard(card){
    // 根据 sidebar 宽度决定插入位置
    var profileCard=document.querySelector(".ab-profile-card");
    var content=document.querySelector(".ab-profile-content");
    if(!profileCard) return;
    var sidebarWidth=(profileCard.parentNode||{}).offsetWidth||999;
    var isMobile=window.innerWidth<=768;
    if(content && (sidebarWidth < 310 || isMobile)){
        // sidebar 太窄时移入 content 底部，样式适配 ab-profile-section
        if(content.lastChild!==card) content.appendChild(card);
        card.setAttribute("data-mode","content");
        card.style.position="";
        card.style.top="";
    } else {
        card.setAttribute("data-mode","sidebar");
        // 正常：作为 ab-profile-card 的兄弟，紧随其后
        if(card.parentNode!==profileCard.parentNode || card.previousElementSibling!==profileCard){
            profileCard.parentNode.insertBefore(card, profileCard.nextSibling);
        }
        // sticky: 紧贴在 ab-profile-card 下方
        // 读取 ab-profile-card 自身的 sticky top（theme 定义为 24px），动态适配
        var pTop=parseFloat(window.getComputedStyle(profileCard).top)||24;
        card.style.position="sticky";
        card.style.top=(pTop+profileCard.offsetHeight+12)+"px";
    }
}
function mount(retry){
    var profileCard=document.querySelector(".ab-profile-card");
    if(!profileCard){
        if((retry||0)<30) setTimeout(function(){mount((retry||0)+1);},150);
        return;
    }
    if(window.__abOAuthRo){window.__abOAuthRo.disconnect();window.__abOAuthRo=null;}
    var old=document.getElementById("ab-oauth-profile-card");
    if(old) old.remove();
    window.__abOAuthCard=null;
    var card=buildCard();
    placeCard(card);
    window.__abOAuthCard=card;
    // 响应窗口宽度变化重新定位
    if(window.ResizeObserver){
        var ro=new ResizeObserver(function(){if(window.__abOAuthCard===card)placeCard(card);});
        if(profileCard.parentNode) ro.observe(profileCard.parentNode);
        window.__abOAuthRo=ro;
    }
}
function onPageload(e){
    var url=(e&&e.detail&&e.detail.url)?String(e.detail.url):String(window.location.href||"");
    if(url.indexOf("profile.php")===-1){
        if(window.__abOAuthRo){window.__abOAuthRo.disconnect();window.__abOAuthRo=null;}
        var old=document.getElementById("ab-oauth-profile-card");
        if(old) old.remove();
        window.__abOAuthCard=null;
        return;
    }
    if(window.__abOAuthRo){window.__abOAuthRo.disconnect();window.__abOAuthRo=null;}
    var old2=document.getElementById("ab-oauth-profile-card");
    if(old2) old2.remove();
    window.__abOAuthCard=null;
    mount(0);
}
// 使用全局引用防止重复绑定，同时允许 AJAX 重新注入时替换旧监听器
if(window.__abOAuthUnlisten) window.__abOAuthUnlisten();
window.__abOAuthUnlisten=function(){document.removeEventListener("ab:pageload",onPageload);};
document.addEventListener("ab:pageload",onPageload);
if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",function(){mount(0);}); else mount(0);
})();</script>';

        echo '<style>
/* === sidebar card === */
.ab-oauth-profile-card{margin-top:12px;padding:20px;border-radius:var(--md-radius-xl,24px);background:var(--md-surface-container-low,#f7f2fa);border:1px solid var(--md-outline-variant,#cac4d0)}
.ab-oauth-profile-title{font-size:16px;font-weight:700;color:var(--md-on-surface,#1c1b1f);display:flex;align-items:center;gap:8px}
.ab-oauth-section-icon{display:inline-flex;align-items:center;justify-content:center;color:var(--md-primary,#6750a4);flex-shrink:0}
.ab-oauth-section-icon .material-icons-round{font-size:20px}
.ab-oauth-profile-sub{font-size:12px;color:var(--md-on-surface-variant,#49454f);margin-top:4px}
.ab-oauth-profile-list{display:grid;gap:8px;margin-top:16px}
.ab-oauth-profile-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-radius:16px;background:var(--md-surface-container,#f3edf7);border:1px solid var(--md-outline-variant,#cac4d0)}
.ab-oauth-profile-empty{padding:12px 14px;border-radius:12px;border:1px dashed var(--md-outline-variant,#cac4d0);color:var(--md-on-surface-variant,#49454f);font-size:13px;line-height:1.6}
.ab-oauth-profile-empty a{color:var(--md-primary,#6750a4)}
.ab-oauth-left{display:flex;align-items:center;gap:10px;min-width:0;flex:1 1 0}
.ab-oauth-dot{width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;background:var(--ab-oauth-color,#6750a4);flex-shrink:0;overflow:hidden}
.ab-oauth-icon-img{width:24px;height:24px;object-fit:cover;display:block}
.ab-oauth-name{font-size:14px;font-weight:600;color:var(--md-on-surface,#1c1b1f);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ab-oauth-state{font-size:12px;color:var(--md-on-surface-variant,#49454f);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ab-oauth-btn{padding:6px 14px;border-radius:999px;text-decoration:none;border:1px solid var(--md-outline,#79747e);color:var(--md-on-surface,#1c1b1f);font-size:13px;font-weight:600;background:transparent;transition:all 0.2s;white-space:nowrap;flex-shrink:0}
.ab-oauth-profile-row.is-bound .ab-oauth-btn{border-color:var(--md-error,#b3261e);color:var(--md-error,#b3261e)}
.ab-oauth-profile-row.is-unconfigured .ab-oauth-btn{border-color:var(--md-primary,#6750a4);color:var(--md-primary,#6750a4)}
/* === dark mode === */
[data-theme="dark"] .ab-oauth-profile-card{background:var(--md-dark-surface-container-low,#1c1b1f);border-color:var(--md-dark-outline-variant,#49454f)}
[data-theme="dark"] .ab-oauth-profile-row{background:var(--md-dark-surface-container,#211f26);border-color:var(--md-dark-outline-variant,#49454f)}
[data-theme="dark"] .ab-oauth-name{color:var(--md-dark-on-surface,#e6e1e5)}
[data-theme="dark"] .ab-oauth-state{color:var(--md-dark-on-surface-variant,#cac4d0)}
[data-theme="dark"] .ab-oauth-btn{color:var(--md-dark-on-surface,#e6e1e5);border-color:var(--md-dark-outline,#938f99)}
[data-theme="dark"] .ab-oauth-profile-empty{border-color:var(--md-dark-outline-variant,#49454f);color:var(--md-dark-on-surface-variant,#cac4d0)}
[data-theme="dark"] .ab-oauth-profile-empty a{color:var(--md-dark-primary,#d0bcff)}
/* === content 模式：适配 ab-profile-section UI === */
.ab-oauth-profile-card[data-mode="content"]{margin-top:0;border-radius:var(--md-radius-lg,16px)!important;padding:0!important;overflow:hidden}
.ab-oauth-profile-card[data-mode="content"] .ab-oauth-profile-title{padding:16px 24px;border-bottom:1px solid var(--md-outline-variant,#cac4d0);background:var(--md-surface-container,#f3edf7);font-size:1em!important;font-weight:600;margin:0;gap:10px}
.ab-oauth-profile-card[data-mode="content"] .ab-oauth-profile-sub{display:none}
.ab-oauth-profile-card[data-mode="content"] .ab-oauth-profile-list{padding:12px 20px 16px;margin-top:0}
[data-theme="dark"] .ab-oauth-profile-card[data-mode="content"] .ab-oauth-profile-title{background:var(--md-dark-surface-container,#211f26)}
/* === mobile === */
@media(max-width:575px){
  .ab-oauth-profile-card[data-mode="content"] .ab-oauth-profile-title{padding:14px 16px}
  .ab-oauth-profile-card[data-mode="content"] .ab-oauth-profile-list{padding:8px 12px 12px}
  .ab-oauth-state{max-width:none}
  .ab-oauth-btn{min-width:52px;text-align:center}
}
</style>';
    }

    private static function profileProviders()
    {
        $raw = '';
        try {
            $opt = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautifyOAuth');
            $raw = isset($opt->providers_json) ? (string)$opt->providers_json : '';
        } catch (Exception $e) {
        }

        $list = json_decode($raw, true);
        if (!is_array($list)) {
            $list = array();
        }

        $catalog = self::providerCatalog();
        $res = array();
        foreach ($list as $row) {
            if (!is_array($row) || empty($row['type'])) {
                continue;
            }
            $pType = strtolower(trim((string)$row['type']));
            if (!isset($catalog[$pType])) {
                continue;
            }
            if (empty($row['enabled'])) {
                continue;
            }
            $appKey = isset($row['appKey']) ? trim((string)$row['appKey']) : '';
            $appSecret = isset($row['appSecret']) ? trim((string)$row['appSecret']) : '';
            $res[$pType] = array(
                'title' => !empty($row['title']) ? trim((string)$row['title']) : $catalog[$pType]['title'],
                'color' => $catalog[$pType]['color'],
                'configured' => ($appKey !== '' && $appSecret !== ''),
            );
        }

        return $res;
    }

    private static function firstCharLabel($text)
    {
        $s = trim((string)$text);
        if ($s === '') {
            return '?';
        }

        if (function_exists('mb_substr')) {
            $ch = mb_substr($s, 0, 1, 'UTF-8');
        } elseif (preg_match('/./u', $s, $m)) {
            $ch = $m[0];
        } else {
            $ch = substr($s, 0, 1);
        }

        return preg_match('/[a-z]/i', $ch) ? strtoupper($ch) : $ch;
    }

    private static function jsonEncodeForScript($value, $fallback = 'null')
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json = json_encode($value, $flags);
        return ($json === false) ? $fallback : $json;
    }

    private static function sdkButtonColors()
    {
        $file = __DIR__ . '/sdk/color.php';
        if (!is_file($file)) {
            return array();
        }

        $colors = require $file;
        return is_array($colors) ? $colors : array();
    }

    private static function hasCallbackTypeRoute()
    {
        try {
            $routingTable = Typecho_Widget::widget('Widget_Options')->routingTable;
            if (!is_array($routingTable)) {
                return false;
            }

            if (isset($routingTable['ab_oauth_callback_type'])
                && isset($routingTable['ab_oauth_callback_type']['url'])
                && $routingTable['ab_oauth_callback_type']['url'] === '/ab-oauth-callback/[type:string]') {
                return true;
            }

            foreach ($routingTable as $route) {
                if (is_array($route) && isset($route['url']) && $route['url'] === '/ab-oauth-callback/[type:string]') {
                    return true;
                }
            }
        } catch (Exception $e) {
        }

        return false;
    }

    public static function isAdminBeautifyReady()
    {
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $plugins = isset($options->plugins) && is_array($options->plugins) ? $options->plugins : array();
            $activated = (isset($plugins['activated']) && is_array($plugins['activated'])) ? $plugins['activated'] : array();
            return array_key_exists('AdminBeautify', $activated);
        } catch (Exception $e) {
            return false;
        }
    }

    public static function options($type = '', $onlyEnabled = true)
    {
        $raw = '';
        try {
            $opt = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautifyOAuth');
            $raw = isset($opt->providers_json) ? (string)$opt->providers_json : '';
        } catch (Exception $e) {
        }

        $list = json_decode($raw, true);
        if (!is_array($list)) $list = array();

        $catalog = self::providerCatalog();
        $res = array();
        foreach ($list as $row) {
            if (!is_array($row) || empty($row['type'])) continue;
            $pType = strtolower(trim($row['type']));
            if (!isset($catalog[$pType])) continue;
            $enabled = !empty($row['enabled']);
            $appKey = isset($row['appKey']) ? trim((string)$row['appKey']) : '';
            $appSecret = isset($row['appSecret']) ? trim((string)$row['appSecret']) : '';
            if ($onlyEnabled && !$enabled) continue;
            if ($appKey === '' || $appSecret === '') continue;
            $res[$pType] = array(
                'id' => $appKey,
                'key' => $appSecret,
                'title' => !empty($row['title']) ? trim((string)$row['title']) : $catalog[$pType]['title'],
                'color' => $catalog[$pType]['color'],
            );
        }

        if ($type !== '') {
            $type = strtolower($type);
            return isset($res[$type]) ? $res[$type] : array();
        }
        return $res;
    }

    public static function providerCatalog()
    {
        $sdkTypes = self::scanSdkTypes();
        $sdkColors = self::sdkButtonColors();
        $catalog = array();

        foreach ($sdkTypes as $type) {
            $color = '#6750a4';
            if (isset($sdkColors[$type]['background']) && $sdkColors[$type]['background'] !== '') {
                $color = (string)$sdkColors[$type]['background'];
            }
            $title = self::providerDisplayTitle($type);
            if (isset($sdkColors[$type]['title']) && $sdkColors[$type]['title'] !== '') {
                $title = (string)$sdkColors[$type]['title'];
            }

            $catalog[$type] = array(
                'title' => $title,
                'color' => $color,
            );
        }

        return $catalog;
    }

    private static function defaultProviders()
    {
        $catalog = self::providerCatalog();
        $result = array();

        foreach ($catalog as $type => $meta) {
            $result[] = array(
                'type' => $type,
                'title' => isset($meta['title']) ? (string)$meta['title'] : strtoupper($type),
                'appKey' => '',
                'appSecret' => '',
                'enabled' => ($type === 'github'),
            );
        }

        return $result;
    }

    private static function scanSdkTypes()
    {
        $sdkDir = __DIR__ . '/sdk';
        $files = glob($sdkDir . '/*SDK.class.php');
        if (!is_array($files) || empty($files)) {
            return array();
        }

        $types = array();
        foreach ($files as $file) {
            $base = basename($file);
            if (!preg_match('/^([A-Za-z0-9_]+)SDK\\.class\\.php$/', $base, $m)) {
                continue;
            }

            $type = strtolower($m[1]);
            if ($type === '') {
                continue;
            }
            $types[$type] = true;
        }

        $ordered = array_keys($types);
        sort($ordered, SORT_STRING);

        return $ordered;
    }

    private static function providerDisplayTitle($type)
    {
        $type = strtolower((string)$type);
        if ($type === 'qq') {
            return 'QQ';
        }
        if ($type === 'oidc' || $type === 'customlogin') {
            return 'OIDC';
        }
        if (strlen($type) <= 3) {
            return strtoupper($type);
        }
        return ucfirst($type);
    }

    private static function defaultProvidersJson()
    {
        return json_encode(self::defaultProviders(), JSON_UNESCAPED_UNICODE);
    }

    private static function ensureDefaultConfig()
    {
        try {
            Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautifyOAuth');
            return;
        } catch (Exception $e) {
        }

        Helper::configPlugin('AdminBeautifyOAuth', array(
            'providers_json' => self::defaultProvidersJson(),
            'login_style'    => 'compact',
        ));
    }

    public static function installTable()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $table = $prefix . self::TABLE_NAME;

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `uid` int(10) unsigned NOT NULL DEFAULT 0,
          `type` varchar(32) NOT NULL DEFAULT '',
          `openid` varchar(100) NOT NULL DEFAULT '',
          `nickname` varchar(100) NOT NULL DEFAULT '',
          `avatar` varchar(255) NOT NULL DEFAULT '',
          `access_token` text NULL,
          `refresh_token` text NULL,
          `expires_in` int(10) unsigned NOT NULL DEFAULT 0,
          `created` int(10) unsigned NOT NULL DEFAULT 0,
          `updated` int(10) unsigned NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uniq_type_openid` (`type`,`openid`),
          KEY `idx_uid` (`uid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $db->query($sql, Typecho_Db::WRITE);
    }
}
