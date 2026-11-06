<?php
declare(strict_types=1);

use Youvision\ThinkHtml\Generator;

require dirname(__DIR__) . '/vendor/autoload.php';

$root   = dirname(__DIR__);
$output = $root . '/tests/build';
$cache  = sys_get_temp_dir() . '/think-html-test';

removeDirectory($output);
if (is_dir($cache)) {
    removeDirectory($cache);
}

$generator = new Generator([
    'template_path' => $root . '/template',
    'output_path'   => $output,
    'cache_path'    => $cache,
    'debug'         => true,
]);

$site = [
    'name'        => '示例站点',
    'title'       => '示例站点 - 首页',
    'keywords'    => '静态站点,ThinkPHP',
    'description' => '使用 think-html 生成的静态站点',
];

$categories = [
    ['name' => '新闻中心', 'url' => '/news/', 'children' => [
        ['name' => '公司动态', 'url' => '/news/company/'],
    ]],
    ['name' => '产品中心', 'url' => '/product/'],
];

$articles = [
    ['title' => '第一篇文章', 'url' => '/article/1.html', 'published_text' => '2026-11-07'],
    ['title' => '第二篇文章', 'url' => '/article/2.html', 'published_text' => '2026-11-06'],
];

$cases = [];

// 1. 首页：titleurl 为空时输出 index.html
$cases[] = [
    'expected' => 'index.html',
    'site'     => $site,
    'data'     => [
        'titleurl'   => '',
        'categories' => $categories,
        'articles'   => $articles,
    ],
];

// 2. 栏目列表页：自动识别 category 使用 list 模板，目录形式自动补 index.html
$cases[] = [
    'expected' => 'news/index.html',
    'site'     => $site,
    'data'     => [
        'titleurl'   => '/news/',
        'category'   => [
            'name'            => '新闻中心',
            'page_title'      => '新闻中心',
            'page_keywords'   => '新闻,资讯',
            'page_description' => '新闻中心列表',
        ],
        'articles'   => $articles,
        'pagination' => ['page' => 1, 'pages' => 3, 'prev' => '', 'next' => '/news/2.html'],
    ],
];

// 3. 详情页：自动识别 article 使用 detail 模板，正文保留原始 HTML
$cases[] = [
    'expected' => 'article/123.html',
    'site'     => $site,
    'data'     => [
        'titleurl' => '/article/123.html',
        'article'  => [
            'title'            => '文章标题',
            'page_title'       => '文章标题',
            'page_keywords'    => '关键词',
            'page_description' => '文章摘要',
            'author'           => '作者',
            'published_text'   => '2026-11-07',
            'content_html'     => '<p>正文<b>加粗</b></p>',
        ],
    ],
];

// 4. 第三个参数覆盖 titleurl 指定输出路径
$cases[] = [
    'expected' => 'custom/override/page.html',
    'site'     => $site,
    'data'     => [
        'titleurl' => '/ignored/path.html',
        'article'  => [
            'title'            => '自定义路径',
            'page_title'       => '自定义路径',
            'page_keywords'    => '',
            'page_description' => '',
            'author'           => '',
            'published_text'   => '2026-11-07',
            'content_html'     => '<p>通过第三个参数指定输出路径</p>',
        ],
    ],
    'file'     => 'custom/override/page.html',
];

// 5. titleurl 不带扩展名时自动补全
$cases[] = [
    'expected' => 'about.html',
    'site'     => $site,
    'data'     => [
        'titleurl'   => '/about',
        'categories' => $categories,
        'articles'   => $articles,
    ],
];

// 6. 路径穿越与非法字符被清理，仍落在输出根目录内
$cases[] = [
    'expected' => 'safe/page.html',
    'site'     => $site,
    'data'     => [
        'titleurl'   => '../evil/../../safe/page.html',
        'categories' => $categories,
        'articles'   => $articles,
    ],
];

$passed = 0;
$failed = 0;

foreach ($cases as $index => $case) {
    $file = $generator->make($case['site'], $case['data'], $case['file'] ?? null);
    $relative = str_replace('\\', '/', substr($file, strlen(str_replace('\\', '/', $output)) + 1));

    check(
        '用例 ' . ($index + 1) . ' 输出路径',
        $relative === $case['expected'],
        sprintf('期望 %s，实际 %s', $case['expected'], $relative),
        $passed,
        $failed
    );

    check(
        '用例 ' . ($index + 1) . ' 文件存在',
        is_file($file),
        '文件不存在：' . $file,
        $passed,
        $failed
    );
}

$content = (string) file_get_contents($output . '/index.html');
check('首页包含站点标题', str_contains($content, '示例站点 - 首页'), '未找到站点标题', $passed, $failed);
check('首页渲染分类', str_contains($content, '新闻中心'), '未找到分类', $passed, $failed);
check('首页渲染子分类', str_contains($content, '公司动态'), '未找到子分类', $passed, $failed);
check('首页渲染文章列表', str_contains($content, '第一篇文章'), '未找到文章', $passed, $failed);

$detail = (string) file_get_contents($output . '/article/123.html');
check('详情页正文未被转义', str_contains($detail, '<p>正文<b>加粗</b></p>'), '正文 HTML 被转义', $passed, $failed);

$list = (string) file_get_contents($output . '/news/index.html');
check('列表页渲染分页', str_contains($list, '/news/2.html'), '未找到分页链接', $passed, $failed);

// 覆盖开关：overwrite=false 时保留原内容
$guard = new Generator([
    'template_path' => $root . '/template',
    'output_path'   => $output,
    'cache_path'    => $cache,
    'overwrite'     => false,
]);
$guard->make($site, ['titleurl' => 'index.html', 'categories' => [], 'articles' => []]);
$guardContent = (string) file_get_contents($output . '/index.html');
check('覆盖开关生效', str_contains($guardContent, '第一篇文章'), 'overwrite=false 时文件被覆盖', $passed, $failed);

echo PHP_EOL . sprintf('通过 %d 项，失败 %d 项', $passed, $failed) . PHP_EOL;
exit($failed > 0 ? 1 : 0);

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