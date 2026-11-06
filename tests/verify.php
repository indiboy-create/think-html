<?php
declare(strict_types=1);

use Youvision\ThinkHtml\Exception\GenerateException;
use Youvision\ThinkHtml\Generator;

require dirname(__DIR__) . '/vendor/autoload.php';

$root       = dirname(__DIR__);
$output     = $root . '/tests/build';
$cache      = sys_get_temp_dir() . '/think-html-test';
$sitePhp    = $root . '/tests/site.php';
$siteJson   = $root . '/tests/site.json';
$tplRoot    = $root . '/tests/template';
$tplTheme   = $root . '/tests/template/default';
$defaultDir = $root . '/template';
$defaultFile = $root . '/template/default.html';

removeDirectory($output);
if (is_dir($cache)) {
    removeDirectory($cache);
}

$generator = new Generator([
    'template_path' => $tplRoot,
    'output_path'   => $output,
    'cache_path'    => $cache,
    'debug'         => true,
]);

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
        'name'          => '首页（模板根目录）',
        'expected'      => 'index.html',
        'data'          => [
            'titleurl'   => '',
            'categories' => $categories,
            'articles'   => $articles,
        ],
        'site_path'     => $sitePhp,
        'template_path' => $tplRoot,
    ],
    [
        'name'          => '列表页（主题目录 + JSON 站点）',
        'expected'      => 'news/index.html',
        'data'          => [
            'titleurl'   => '/news/',
            'category'   => [
                'name'             => '新闻中心',
                'page_title'       => '新闻中心',
                'page_keywords'    => '新闻,资讯',
                'page_description' => '新闻中心列表',
            ],
            'articles'   => $articles,
            'pagination' => ['page' => 1, 'pages' => 3, 'prev' => '', 'next' => '/news/2.html'],
        ],
        'site_path'     => $siteJson,
        'template_path' => $tplTheme,
    ],
    [
        'name'          => '详情页（模板根目录）',
        'expected'      => 'article/123.html',
        'data'          => [
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
        'site_path'     => $sitePhp,
        'template_path' => $tplRoot,
    ],
    [
        'name'          => '回退 default 模板（模板根目录）',
        'expected'      => 'fallback/page.html',
        'data'          => [
            'titleurl'   => '/fallback/page.html',
            'categories' => $categories,
        ],
        'site_path'     => $sitePhp,
        'template_path' => $defaultDir,
    ],
    [
        'name'          => '直接指定模板文件',
        'expected'      => 'file/page.html',
        'data'          => [
            'titleurl' => '/file/page.html',
        ],
        'site_path'     => $sitePhp,
        'template_path' => $defaultFile,
    ],
    [
        'name'          => '无扩展名自动补全',
        'expected'      => 'about.html',
        'data'          => [
            'titleurl'   => '/about',
            'categories' => $categories,
            'articles'   => $articles,
        ],
        'site_path'     => $sitePhp,
        'template_path' => $tplRoot,
    ],
    [
        'name'          => '路径穿越清理',
        'expected'      => 'safe/page.html',
        'data'          => [
            'titleurl'   => '../evil/../../safe/page.html',
            'categories' => $categories,
            'articles'   => $articles,
        ],
        'site_path'     => $sitePhp,
        'template_path' => $tplRoot,
    ],
];

$passed = 0;
$failed = 0;

foreach ($cases as $case) {
    [$file, $relative] = makeFile($generator, $case['data'], $case['site_path'], $case['template_path'], $output);

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

$content = (string) file_get_contents($output . '/index.html');
check('首页加载 PHP 站点数据', str_contains($content, '示例站点 - 首页'), '未找到站点标题', $passed, $failed);
check('首页渲染分类', str_contains($content, '新闻中心'), '未找到分类', $passed, $failed);
check('首页渲染文章列表', str_contains($content, '第一篇文章'), '未找到文章', $passed, $failed);

$detail = (string) file_get_contents($output . '/article/123.html');
check('详情页正文未被转义', str_contains($detail, '<p>正文<b>加粗</b></p>'), '正文 HTML 被转义', $passed, $failed);

$list = (string) file_get_contents($output . '/news/index.html');
check('列表页加载 JSON 站点数据', str_contains($list, '示例站点 JSON'), '未加载 JSON 站点数据', $passed, $failed);
check('列表页渲染分页', str_contains($list, '/news/2.html'), '未找到分页链接', $passed, $failed);

$fallback = (string) file_get_contents($output . '/fallback/page.html');
check('回退 default 模板内容', str_contains($fallback, '默认模板'), '未回退到 default.html', $passed, $failed);

$fileTemplate = (string) file_get_contents($output . '/file/page.html');
check('模板文件直接渲染', str_contains($fileTemplate, '默认模板'), '未使用指定模板文件', $passed, $failed);

// 不传 site_path / template_path 时使用配置默认值
$defaults = new Generator([
    'template_path' => $tplRoot,
    'site_path'     => $siteJson,
    'output_path'   => $output,
    'cache_path'    => $cache,
]);
[$defaultFile, $defaultRelative] = makeFile($defaults, [
    'titleurl'   => 'default/config.html',
    'categories' => [],
    'articles'   => [],
], null, null, $output);
check(
    '使用配置中的站点与模板路径',
    $defaultRelative === 'default/config.html'
    && is_file($defaultFile)
    && str_contains((string) file_get_contents($defaultFile), '示例站点 JSON'),
    '默认配置未生效',
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
$guard->make(['titleurl' => 'index.html', 'categories' => [], 'articles' => []], $sitePhp, $tplRoot);
$guardContent = (string) file_get_contents($output . '/index.html');
check('覆盖开关生效', str_contains($guardContent, '第一篇文章'), 'overwrite=false 时文件被覆盖', $passed, $failed);

// 站点文件不存在时抛出异常
try {
    $generator->make(['titleurl' => 'missing.html'], $root . '/tests/not-exists.php', $tplRoot);
    check('站点文件缺失异常', false, '未抛出异常', $passed, $failed);
} catch (GenerateException $e) {
    check(
        '站点文件缺失异常',
        str_contains($e->getMessage(), '站点数据文件不存在'),
        '异常信息不正确：' . $e->getMessage(),
        $passed,
        $failed
    );
}

echo PHP_EOL . sprintf('通过 %d 项，失败 %d 项', $passed, $failed) . PHP_EOL;
exit($failed > 0 ? 1 : 0);

/**
 * @return array{0: string, 1: string}
 */
function makeFile(Generator $generator, array $data, ?string $sitePath, ?string $templatePath, string $output): array
{
    $file     = $generator->make($data, $sitePath, $templatePath);
    $relative = str_replace('\\', '/', substr($file, strlen(str_replace('\\', '/', $output)) + 1));

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