<?php
// +----------------------------------------------------------------------
// | think-html 静态网站生成配置
// | 说明：配置项为 null 时由生成器自动探测，通常无需手动填写
// +----------------------------------------------------------------------

return [
    // 模板根目录，模板实际读取路径为：模板根目录 / 主题目录 / 模板文件
    // null：自动探测，优先使用项目根目录下的 template 目录，其次使用本包自带的 template 目录
    'template_path' => null,

    // 默认模板主题（即 template 下的一级目录名），例如 default
    'theme' => 'default',

    // 静态文件输出根目录，生成的 HTML 文件都会写入该目录
    // null：自动探测，优先使用 ThinkPHP 的 public 目录，其次使用当前运行目录下的 public 目录
    'output_path' => null,

    // 默认模板文件，当 $data 中既未指定 template 也无法自动识别页面类型时使用
    // 可选值：index（首页）、list（栏目列表页）、detail（内容详情页）
    'default_template' => 'index',

    // 模板文件后缀（不含点号），需与模板目录中的文件名一致
    'view_suffix' => 'html',

    // 模板编译缓存目录
    // null：自动探测，优先使用 ThinkPHP 的 runtime 目录，其次使用系统临时目录
    'cache_path' => null,

    // 是否覆盖已存在的同名静态文件
    // true：每次生成都覆盖；false：文件已存在时跳过并直接返回原路径
    'overwrite' => true,

    // 静态文件后缀（不含点号），当 titleurl 未携带扩展名时自动补全
    // 例如 titleurl 为 news/2026 时生成 news/2026.html
    'suffix' => 'html',

    // 生成文件的字符编码，默认 UTF-8
    // 设置为非 UTF-8 时需要 PHP 的 mbstring 扩展支持转码
    'charset' => 'utf-8',

    // 模板标签定界符，普通标签与标签库标签保持一致即可
    'tpl_begin' => '{',
    'tpl_end' => '}',
    'taglib_begin' => '{',
    'taglib_end' => '}',

    // 是否在生成成功后向标准输出打印生成结果，便于命令行构建时查看进度
    'debug' => false,
];