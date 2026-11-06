# think-html

基于 ThinkPHP 8 的静态网站生成器。读取模板目录下的 ThinkPHP 模板，结合站点数据与页面数据渲染，生成静态 HTML 文件。

## 环境要求

- PHP >= 8.0
- ThinkPHP 8
- `topthink/think-view` ^2.0（由 ThinkPHP 8 项目提供，本包不会随包自动安装）

## 安装

```bash
composer require youvision/think-html
```

本包不声明模板引擎的运行时依赖，安装时不会自动拉取 `topthink/think-view`。ThinkPHP 8 项目默认已内置该视图包；若项目中不存在，请先执行：

```bash
composer require topthink/think-view
```

包通过 `composer.json` 的 `extra.think.services` 自动注册服务。若未生效，执行：

```bash
php think service:discover
```

## 配置

配置位于 `config/html.php`，复制到项目中按需修改。常用配置项：

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `template_path` | `null` | 模板路径，支持模板文件、主题目录、模板根目录；`null` 时自动探测 |
| `site_path` | `null` | 站点数据文件，支持 `.php`（返回数组）与 `.json` |
| `theme` | `default` | 模板主题，对应模板根目录下的一级目录 |
| `output_path` | `null` | 静态文件输出目录，`null` 时使用 `public` 目录 |
| `default_template` | `index` | 无法自动识别页面类型时使用的模板 |
| `view_suffix` | `html` | 模板文件后缀 |
| `cache_path` | `null` | 模板编译缓存目录，`null` 时使用 `runtime/html` |
| `overwrite` | `true` | 是否覆盖已存在的同名文件 |
| `suffix` | `html` | `titleurl` 无扩展名时自动补全的后缀 |
| `charset` | `utf-8` | 生成文件编码 |
| `debug` | `false` | 是否在命令行打印生成的文件路径 |

## 快速开始

### 站点数据文件

`$site_path` 指向站点数据文件，支持 PHP 数组与 JSON。

```php
// site.php
return [
    'name'        => '示例站点',
    'title'       => '示例站点 - 首页',
    'keywords'    => '静态站点,ThinkPHP',
    'description' => '使用 think-html 生成的静态站点',
];
```

```json
{
    "name": "示例站点",
    "title": "示例站点 - 首页",
    "keywords": "静态站点,ThinkPHP",
    "description": "使用 think-html 生成的静态站点"
}
```

### 助手函数

```php
$data = [
    'titleurl' => '/article/123.html',
    'article'  => [
        'title'            => '文章标题',
        'page_title'       => '文章标题',
        'page_keywords'    => '关键词',
        'page_description' => '文章摘要',
        'author'           => '作者',
        'published_text'   => '2026-11-07',
        'content_html'     => '<p>正文</p>',
    ],
];

// 三个参数：$data、站点数据文件、模板路径
$file = html_make($data, base_path('site.php'), base_path('template'));
```

### 生成器

```php
use Youvision\ThinkHtml\Generator;

$generator = app(Generator::class);
$file      = $generator->make($data, $site_path, $template_path);
```

### 批量生成

```php
$files = html_build($pages, $site_path, $template_path);
```

## 参数说明

| 参数 | 说明 |
| --- | --- |
| `$data` | 页面数据，`titleurl` 决定输出路径；`template` 可指定模板名 |
| `$site_path` | 站点数据文件路径，支持 `.php` 与 `.json`；省略时使用配置 `site_path` |
| `$template_path` | 模板路径，支持模板文件、主题目录、模板根目录；省略时使用配置 `template_path` |

## 输出路径规则

输出路径完全由 `$data['titleurl']` 决定。

| titleurl | 实际输出 |
| --- | --- |
| 空字符串 / 未设置 | `index.html` |
| `/news/` | `news/index.html` |
| `/article/123.html` | `article/123.html` |
| `/about` | `about.html` |
| `https://example.com/news/` | `news/index.html` |
| `../a/../b.html` | `b.html` |

生成器会自动去除域名、查询字符串与锚点，清理目录穿越和 Windows 非法字符，保证文件始终写入输出目录内。

## 模板选择

1. `$data['template']` 显式指定，如 `index`、`list`、`detail`；
2. `$data` 包含 `article` 键时使用 `detail` 模板；
3. `$data` 包含 `category` 键时使用 `list` 模板；
4. 其他情况使用 `default_template`（默认 `index`）。

找不到对应的页面模板时，自动回退到 `default` 模板（即 `default.html`）。

## 模板目录

```text
template/
├── default.html          # 默认模板，找不到 index/list/detail 时回退
└── default/              # 可选，按页面类型拆分
    ├── index.html
    ├── list.html
    └── detail.html
```

`$template_path` 三种写法均可：

```php
html_make($data, $site_path, base_path('template'));                // 模板根目录
html_make($data, $site_path, base_path('template/default'));        // 主题目录
html_make($data, $site_path, base_path('template/default.html'));   // 模板文件
```

未指定时优先读取项目根目录的 `template`，不存在时回退到包内自带模板。

## 模板变量

模板中可直接使用 `$site`、`$data` 以及 `$data` 中的全部键：

- 首页 `index`：`site`、`categories`、`articles`
- 列表页 `list`：`site`、`category`、`articles`、`pagination`
- 详情页 `detail`：`site`、`article`、`data`

模板采用 ThinkPHP 模板语法，普通变量默认经过 `htmlentities` 转义；需要输出原始 HTML 时使用 `|raw` 过滤器。

## 自测

```bash
composer test
```

脚本位于 `tests/verify.php` 与 `tests/service-check.php`。