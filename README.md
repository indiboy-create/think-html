# think-html

基于 ThinkPHP 8 的静态网站生成器。读取模板目录或模板文件，结合页面数据渲染，生成静态 HTML 文件。

入口只有一个参数：

```php
$data = [
    'site_id'  => '10001',
    'titleurl' => '/article/123.html',
    'template' => 'detail',
];

$file = html_make($data);
```

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

`config/html.php` 只有一个配置项：

```php
return [
    // 模板目录
    // null：自动探测，优先使用项目根目录下的 template，找不到时使用包内自带的 template
    // 也可填写模板目录（template）或模板文件（template/default.html）
    'template_path' => null,
];
```

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `template_path` | `null` | 模板路径，支持模板目录或模板文件；`null` 时自动探测 |

其余运行参数由生成器内置默认值提供，不写入配置文件。静态文件默认输出到 `public` 目录。

## 快速开始

```php
$data = [
    'site_id'  => '10001',
    'titleurl' => '/article/123.html',
    'template' => 'detail',
];

$file = html_make($data);
```

生成结果：`public/10001/article/123.html`

### 数据字段

| 字段 | 说明 |
| --- | --- |
| `site_id` | 站点根目录，生成文件位于 `public/{site_id}/` 下 |
| `titleurl` | HTML 路径名称，决定站内文件路径；空值输出 `index.html`，以 `/` 结尾输出 `index.html` |
| `template` | 模板名称，对应模板目录下的 `{template}.html`；省略时按数据自动识别 |
| 其他字段 | 全部平铺在 `$data` 顶层，直接作为模板变量使用 |

完整示例：

```php
$data = [
    'site_id'          => '10001',
    'titleurl'         => '/article/123.html',
    'template'         => 'detail',
    'title'            => '文章标题',
    'page_title'       => '文章标题',
    'page_keywords'    => '关键词',
    'page_description' => '文章摘要',
    'author'           => '作者',
    'published_text'   => '2026-11-07',
    'content_html'     => '<p>正文</p>',
];
```

### 生成器

```php
use Youvision\ThinkHtml\Generator;

$generator = app(Generator::class);
$file      = $generator->make($data);
```

## 输出路径规则

输出路径为：`输出目录 / site_id / titleurl`。

| site_id | titleurl | 实际输出 |
| --- | --- | --- |
| `10001` | `index.html` | `10001/index.html` |
| `10001` | `/news/` | `10001/news/index.html` |
| `10001` | `/article/123.html` | `10001/article/123.html` |
| `10001` | `/about` | `10001/about.html` |


## 模板选择

1. `$data['template']` 显式指定（推荐），如 `index`、`list`、`detail`；
2. 未指定时，若 `$data` 含 `article` 键使用 `detail` 模板；
3. 未指定时，若 `$data` 含 `category` 键使用 `list` 模板；
4. 其他情况使用 `default_template`（默认 `index`）。

扁平数据通常直接指定 `template`。找不到对应的页面模板时，自动回退到 `default` 模板（即 `default.html`）。

## 模板目录

模板目录下直接存放模板文件：

```text
template/
├── default.html          # 默认模板，找不到 index/list/detail 时回退
├── index.html            # 可选，首页
├── list.html             # 可选，列表页
└── detail.html           # 可选，详情页
```

包内只自带 `default.html`，项目没有模板时直接使用它。需要按页面类型渲染时，在项目根目录创建 `template` 并添加 `index.html`、`list.html`、`detail.html` 即可。

`template_path` 两种写法均可：

```php
'template_path' => base_path('template'),                 // 模板目录
'template_path' => base_path('template/default.html'),    // 模板文件
```

优先级：项目根目录 `template` → 包内 `template`。

## 模板变量

模板中可直接使用 `$data` 以及 `$data` 中的全部键，字段全部平铺在顶层：

- 通用：`data`（页面数据原样）
- 页面字段：`title`、`page_title`、`page_keywords`、`page_description`、`content_html` 等
- 首页 `index`：`name`、`title`、`categories`、`articles`
- 列表页 `list`：`page_title`、`name`、`articles`、`pagination`
- 详情页 `detail`：`title`、`page_title`、`author`、`published_text`、`content_html`

模板采用 ThinkPHP 模板语法，普通变量默认经过 `htmlentities` 转义；需要输出原始 HTML 时使用 `|raw` 过滤器。

## 自测

```bash
composer test
```

脚本位于 `tests/verify.php`。