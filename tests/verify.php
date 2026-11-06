<?php
declare(strict_types=1);

use Youvision\ThinkHtml\Generator;

require dirname(__DIR__) . '/vendor/autoload.php';

$root       = dirname(__DIR__);
$output     = $root . '/tests/build';
$cache      = sys_get_temp_dir() . '/think-html-test';
$tplRoot    = sys_get_temp_dir() . '/think-html-templates';
$defaultDir = $root . '/template';

$templates = [
    'index' => <<<'HTML'
<title>{$title}</title>
<h1>{$name}</h1>
<ul class="nav">
{volist name="categories" id="c"}
    <li><a href="{$c.url}">{$c.name}</a></li>
{/volist}
</ul>
<ul class="articles">
{volist name="articles" id="a"}
    <li><a href="{$a.url}">{$a.title}</a></li>
{/volist}
</ul>
HTML,
    'list' => <<<'HTML'
<title>{$page_title}</title>
<h1>{$name}</h1>
<ul class="articles">
{volist name="articles" id="a"}
    <li><a href="{$a.url}">{$a.title}</a></li>
{/volist}
</ul>
{if condition="$pagination.pages > 1"}
<div class="pagination">
    {if condition="$pagination.prev != ''"}<a href="{$pagination.prev}">上一页</a>{/if}
    <a href="{$pagination.next}">下一页</a>
</div>
{/if}
HTML,
    'detail' => <<<'HTML'
<title>{$page_title}</title>
<h1>{$title}</h1>
<div class="meta">{$author} {$published_text}</div>
<div class="content">{$content_html|raw}</div>
HTML,
];

removeDirectory($output);
removeDirectory($tplRoot);

if (is_dir($cache)) {
    removeDirectory($cache);
}

if (!mkdir($tplRoot, 0755, true) && !is_dir($tplRoot)) {
    throw new RuntimeException('无法创建测试模板目录：' . $tplRoot);
}

foreach ($templates as $name => $content) {
    file_put_contents($tplRoot . '/' . $name . '.html', $content);
}

$generator = new Generator([
    'template_path' => $tplRoot,
    'output_path'   => $output,
    'cache_path'    => $cache,
    'debug'         => true,
]);

$home = [
    'name'        => '示例站点',
    'title'       => '示例站点 - 首页',
    'keywords'    => '静态站点,ThinkPHP',
    'description' => '使用 think-html 生成的静态站点',
];

$categoryFields = [
    'name'             => '新闻中心',
    'title'            => '新闻中心',
    'page_title'       => '新闻中心',
    'page_keywords'    => '新闻,资讯',
    'page_description' => '新闻中心列表',
];

$articleFields = [
    'title'            => '文章标题',
    'page_title'       => '文章标题',
    'page_keywords'    => '关键词',
    'page_description' => '文章摘要',
    'author'           => '作者',
    'published_text'   => '2026-11-07',
    'content_html'     => '<p>正文<b>加粗</b></p>',
];

$categories = [
    ['name' => '新闻中心', 'url' => '/news/'],
    ['name' => '产品中心', 'url' => '/product/'],
];

$articles = [
    ['title' => '第一篇文章', 'url' => '/article/1.html', 'published_text' => '2026-11-07'],
    ['title' => '第二篇文章', 'url' => '/article/2.html', 'published_text' => '2026-11-06'],
];

$cases = [
    [
        'name'     => '首页（site_id 作为根目录）',
        'expected' => '10001/index.html',
        'data'     => array_merge($home, [
            'site_id'    => '10001',
            'titleurl'   => 'index.html',
            'template'   => 'index',
            'categories' => $categories,
            'articles'   => $articles,
        ]),
    ],
    [
        'name'     => '列表页（显式 template）',
        'expected' => '10001/news/index.html',
        'data'     => array_merge($categoryFields, [
            'site_id'    => '10001',
            'titleurl'   => '/news/',
            'template'   => 'list',
            'articles'   => $articles,
            'pagination' => ['page' => 1, 'pages' => 3, 'prev' => '', 'next' => '/news/2.html'],
        ]),
    ],
    [
        'name'     => '详情页（显式 template）',
        'expected' => '10002/article/123.html',
        'data'     => array_merge($articleFields, [
            'site_id'  => '10002',
            'titleurl' => '/article/123.html',
            'template' => 'detail',
        ]),
    ],
    [
        'name'     => '未指定 template 使用默认模板',
        'expected' => '10002/auto/1.html',
        'data'     => array_merge($home, [
            'site_id'    => '10002',
            'titleurl'   => '/auto/1.html',
            'categories' => [],
            'articles'   => [],
        ]),
    ],
    [
        'name'     => 'titleurl 无扩展名自动补全',
        'expected' => '10001/about.html',
        'data'     => array_merge($home, [
            'site_id'    => '10001',
            'titleurl'   => '/about',
            'template'   => 'index',
            'categories' => $categories,
            'articles'   => $articles,
        ]),
    ],
    [
        'name'     => 'site_id 目录隔离',
        'expected' => '10003/index.html',
        'data'     => array_merge($home, [
            'site_id'    => '10003',
            'titleurl'   => 'index.html',
            'template'   => 'index',
            'categories' => $categories,
            'articles'   => $articles,
        ]),
    ],
];

$passed = 0;
$failed = 0;

foreach ($cases as $case) {
    [$file, $relative] = makeFile($generator, $case['data']);

    check(
        $case['name'] . ' 输出路径',
        $relative === $case['expected'],
        sprintf('期望 %s，实际 %s', $case['expected'], $relative),
        $passed,
        $failed
    );

    check(
        $case['name'] . ' 文件存在',
        is_file($file),
        '文件不存在：' . $file,
        $passed,
        $failed
    );
}

$content = (string) file_get_contents($output . '/10001/index.html');
check('模板使用扁平字段', str_contains($content, '示例站点 - 首页'), '未找到标题', $passed, $failed);
check('首页渲染分类', str_contains($content, '新闻中心'), '未找到分类', $passed, $failed);
check('首页渲染文章列表', str_contains($content, '第一篇文章'), '未找到文章', $passed, $failed);

$detail = (string) file_get_contents($output . '/10002/article/123.html');
check('详情页渲染扁平字段', str_contains($detail, '文章标题'), '未找到文章标题', $passed, $failed);
check('详情页正文未被转义', str_contains($detail, '<p>正文<b>加粗</b></p>'), '正文 HTML 被转义', $passed, $failed);

$list = (string) file_get_contents($output . '/10001/news/index.html');
check('列表页渲染分页', str_contains($list, '/news/2.html'), '未找到分页链接', $passed, $failed);

// 找不到指定模板时回退 default.html
$fallback = new Generator([
    'template_path' => $defaultDir,
    'output_path'   => $output,
    'cache_path'    => $cache,
]);
$fallbackFile = $fallback->make(array_merge($articleFields, [
    'site_id'  => '10004',
    'titleurl' => '/fallback/page.html',
    'template' => 'detail',
]));
check(
    '找不到模板时回退 default.html',
    is_file($fallbackFile) && str_contains((string) file_get_contents($fallbackFile), '默认模板'),
    '未回退到 default.html',
    $passed,
    $failed
);

// 配置为模板文件时直接使用
$fileGenerator = new Generator([
    'template_path' => $tplRoot . '/index.html',
    'output_path'   => $output,
    'cache_path'    => $cache,
]);
$fileOut = $fileGenerator->make(array_merge($home, [
    'site_id'    => '10005',
    'titleurl'   => '/file.html',
    'categories' => $categories,
    'articles'   => $articles,
]));
check(
    '配置模板文件直接渲染',
    is_file($fileOut) && str_contains((string) file_get_contents($fileOut), '示例站点'),
    '未使用指定模板文件',
    $passed,
    $failed
);

// 覆盖开关：overwrite=false 时保留原内容
$guard = new Generator([
    'template_path' => $tplRoot,
    'output_path'   => $output,
    'cache_path'    => $cache,
    'overwrite'     => false,
]);
$guard->make(array_merge($home, [
    'site_id'    => '10001',
    'titleurl'   => 'index.html',
    'template'   => 'index',
    'categories' => [],
    'articles'   => [],
]));
$guardContent = (string) file_get_contents($output . '/10001/index.html');
check('覆盖开关生效', str_contains($guardContent, '第一篇文章'), 'overwrite=false 时文件被覆盖', $passed, $failed);

// 配置文件仅暴露模板路径
$packageConfig = require $root . '/config/html.php';
check(
    '配置文件仅需 template_path',
    array_keys($packageConfig) === ['template_path'],
    '配置文件包含多余参数：' . implode(',', array_keys($packageConfig)),
    $passed,
    $failed
);

// 未配置模板路径且项目目录无模板时回退包内 template
$currentDir = (string) getcwd();
$autoFile   = null;

try {
    chdir(sys_get_temp_dir());

    $auto     = new Generator([
        'output_path' => $output,
        'cache_path'  => $cache,
    ]);
    $autoFile = $auto->make(array_merge($articleFields, [
        'site_id'  => '10006',
        'titleurl' => '/auto/package.html',
        'template' => 'detail',
    ]));
} finally {
    chdir($currentDir);
}

check(
    '找不到模板时使用包内模板',
    $autoFile !== null
    && is_file($autoFile)
    && str_contains((string) file_get_contents($autoFile), '默认模板'),
    '未回退到包内 template',
    $passed,
    $failed
);

echo PHP_EOL . sprintf('通过 %d 项，失败 %d 项', $passed, $failed) . PHP_EOL;
exit($failed > 0 ? 1 : 0);

/**
 * @return array{0: string, 1: string}
 */
function makeFile(Generator $generator, array $data): array
{
    $file     = $generator->make($data);
    $relative = str_replace('\\', '/', substr($file, strlen(str_replace('\\', '/', (string) $GLOBALS['output'])) + 1));

    return [$file, $relative];
}

function check(string $name, bool $condition, string $message, int &$passed, int &$failed): void
{
    if ($condition) {
        $passed++;
        echo '[通过] ' . $name . PHP_EOL;

        return;
    }

    $failed++;
    echo '[失败] ' . $name . '：' . $message . PHP_EOL;
}

function removeDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
}