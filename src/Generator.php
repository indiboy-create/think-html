<?php
declare(strict_types=1);

namespace Youvision\ThinkHtml;

use think\Template;
use Youvision\ThinkHtml\Exception\GenerateException;

/**
 * 静态网站生成器
 *
 * 读取 template/{theme} 目录下的模板，结合站点数据与页面数据渲染，
 * 并将结果写入输出目录，最终返回生成的 HTML 文件路径。
 */
class Generator
{
    protected array $config;

    /**
     * 内置默认配置，与 config/html.php 保持一致，便于脱离 ThinkPHP 独立使用
     */
    protected array $defaults = [
        'template_path'   => null,
        'site_path'       => null,
        'theme'           => 'default',
        'output_path'     => null,
        'default_template' => 'index',
        'view_suffix'     => 'html',
        'cache_path'      => null,
        'overwrite'       => true,
        'suffix'          => 'html',
        'charset'         => 'utf-8',
        'tpl_begin'       => '{',
        'tpl_end'         => '}',
        'taglib_begin'    => '{',
        'taglib_end'      => '}',
        'debug'           => false,
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaults, $config);
    }

    /**
     * 生成单个静态页面
     *
     * @param array       $data          页面数据，titleurl 决定输出路径，template 可指定模板
     * @param string|null $site_path     站点数据文件路径，支持 .php（返回数组）与 .json
     * @param string|null $template_path 模板目录，可为模板根目录或主题目录
     *
     * @return string 生成文件的绝对路径
     */
    public function make(array $data, ?string $site_path = null, ?string $template_path = null): string
    {
        $relative = $this->resolveRelativePath((string) ($data['titleurl'] ?? ''));
        $target   = $this->outputRoot() . '/' . $relative;

        $this->ensureDirectory(dirname($target));

        if (is_file($target) && !$this->config['overwrite']) {
            $this->debug($target);

            return $target;
        }

        $site = $this->loadSite((string) ($site_path ?? ''));
        $html = $this->render($site, $data, $template_path);

        $charset = strtolower((string) $this->config['charset']);
        if (!in_array($charset, ['utf-8', 'utf8'], true) && function_exists('mb_convert_encoding')) {
            $html = mb_convert_encoding($html, $this->config['charset'], 'UTF-8');
        }

        if (file_put_contents($target, $html, LOCK_EX) === false) {
            throw new GenerateException(sprintf('写入静态文件失败：%s', $target));
        }

        $this->debug($target);

        return $target;
    }

    /**
     * 批量生成静态页面
     *
     * @param array       $pages         页面数据集合，每个元素的结构与 make() 的 $data 一致
     * @param string|null $site_path     站点数据文件路径
     * @param string|null $template_path 模板目录
     *
     * @return string[] 生成成功的文件路径列表
     */
    public function build(array $pages, ?string $site_path = null, ?string $template_path = null): array
    {
        $files = [];

        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            $files[] = $this->make($page, $site_path, $template_path);
        }

        return $files;
    }

    /**
     * 解析 titleurl 或自定义路径，得到相对于输出根目录的安全路径
     */
    public function resolveRelativePath(string $path): string
    {
        $path = trim($path);

        if ($path !== '' && preg_match('#^[a-z][a-z0-9+.\-]*://#i', $path)) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $path   = is_string($parsed) ? $parsed : '';
        }

        $path = str_replace('\\', '/', $path);

        if (str_contains($path, '?') || str_contains($path, '#')) {
            $path = (string) preg_split('/[?#]/', $path, 2)[0];
        }

        $path        = ltrim($path, '/');
        $isDirectory = str_ends_with($path, '/');
        $segments    = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segment = (string) preg_replace('/[\x00-\x1F<>:"|?*]/', '', $segment);

            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        if ($segments === []) {
            return 'index.' . $this->config['suffix'];
        }

        $path = implode('/', $segments);

        if ($isDirectory) {
            $path .= '/index';
        }

        if (pathinfo($path, PATHINFO_EXTENSION) === '') {
            $path .= '.' . $this->config['suffix'];
        }

        return $path;
    }

    /**
     * 渲染模板并返回 HTML 内容
     */
    protected function render(array $site, array $data, ?string $template_path = null): string
    {
        $templateFile = $this->resolveTemplateFile($data, $template_path);
        $template     = basename($templateFile);
        $engine       = $this->engine(dirname($templateFile));

        $vars = array_merge($data, [
            'site' => $site,
            'data' => $data,
        ]);

        ob_start();
        ob_implicit_flush(false);

        try {
            $engine->fetch($templateFile, $vars);
            $html = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();

            throw new GenerateException(
                sprintf('渲染模板 [%s] 失败：%s', $template, $e->getMessage()),
                0,
                $e
            );
        }

        if (!is_string($html)) {
            throw new GenerateException(sprintf('渲染模板 [%s] 失败：输出缓冲异常', $template));
        }

        return $html;
    }

    /**
     * 确定使用的模板名称
     */
    protected function resolveTemplate(array $data): string
    {
        $template = trim((string) ($data['template'] ?? ''));

        if ($template === '') {
            if (array_key_exists('article', $data)) {
                $template = 'detail';
            } elseif (array_key_exists('category', $data)) {
                $template = 'list';
            } else {
                $template = (string) $this->config['default_template'];
            }
        }

        $suffix = '.' . $this->config['view_suffix'];

        if (str_ends_with($template, $suffix)) {
            $template = substr($template, 0, -strlen($suffix));
        }

        return $template;
    }

    /**
     * 读取站点数据文件
     */
    protected function loadSite(string $site_path): array
    {
        if ($site_path === '') {
            $site_path = $this->config['site_path'];
        }

        if (!is_string($site_path) || $site_path === '') {
            return [];
        }

        if (!is_file($site_path)) {
            throw new GenerateException(sprintf('站点数据文件不存在：%s', $site_path));
        }

        $extension = strtolower(pathinfo($site_path, PATHINFO_EXTENSION));

        if ($extension === 'php') {
            $site = require $site_path;
        } else {
            $content = file_get_contents($site_path);

            if ($content === false) {
                throw new GenerateException(sprintf('无法读取站点数据文件：%s', $site_path));
            }

            $site = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new GenerateException(sprintf('站点数据文件解析失败：%s（%s）', $site_path, json_last_error_msg()));
            }
        }

        if (!is_array($site)) {
            throw new GenerateException(sprintf('站点数据文件必须为数组：%s', $site_path));
        }

        return $site;
    }

    /**
     * 创建模板引擎实例
     */
    protected function engine(string $viewDir): Template
    {
        if (!class_exists(Template::class)) {
            throw new GenerateException(
                '未检测到模板引擎 think\Template，请先安装 topthink/think-view（ThinkPHP 8 项目默认已内置）'
            );
        }

        $cachePath = $this->cacheRoot();
        $this->ensureDirectory($cachePath);

        return new Template([
            'view_path'    => rtrim(str_replace('\\', '/', $viewDir), '/') . '/',
            'view_suffix'  => $this->config['view_suffix'],
            'cache_path'   => $cachePath . '/',
            'tpl_begin'    => $this->config['tpl_begin'],
            'tpl_end'      => $this->config['tpl_end'],
            'taglib_begin' => $this->config['taglib_begin'],
            'taglib_end'   => $this->config['taglib_end'],
        ]);
    }

    /**
     * 定位模板文件
     *
     * $template_path 支持三种形式：
     * 1. 模板文件：template/default.html
     * 2. 主题目录：template/default
     * 3. 模板根目录：template（自动拼接 theme，并支持回退 default 模板）
     */
    protected function resolveTemplateFile(array $data, ?string $template_path = null): string
    {
        $paths = [];

        if (is_string($template_path) && $template_path !== '') {
            $paths[] = $template_path;
        }

        $configured = $this->config['template_path'];

        if (is_string($configured) && $configured !== '') {
            $paths[] = $configured;
        }

        foreach ($paths as $path) {
            $file = $this->locateTemplate($path, $data);

            if ($file !== null) {
                return $file;
            }
        }

        $candidates = [];
        $base       = $this->basePath();

        if ($base !== null) {
            $candidates[] = $base . '/template';
        }

        $candidates[] = rtrim(str_replace('\\', '/', (string) getcwd()), '/') . '/template';
        $candidates[] = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/template';

        foreach (array_unique($candidates) as $dir) {
            $file = $this->locateTemplate($dir, $data);

            if ($file !== null) {
                return $file;
            }
        }

        throw new GenerateException(sprintf(
            '未找到模板文件，模板路径：%s；自动探测目录：%s',
            $paths === [] ? '未指定' : implode('、', $paths),
            implode('、', $candidates)
        ));
    }

    /**
     * 在文件或目录中查找匹配的模板文件
     */
    protected function locateTemplate(string $path, array $data): ?string
    {
        $path   = rtrim(str_replace('\\', '/', $path), '/');
        $suffix = '.' . ltrim((string) $this->config['view_suffix'], '.');

        if (is_file($path)) {
            return $path;
        }

        if (is_file($path . $suffix)) {
            return $path . $suffix;
        }

        if (!is_dir($path)) {
            return null;
        }

        $themePath = $this->resolveThemePath($path);
        $names     = array_unique([
            $this->resolveTemplate($data),
            (string) $this->config['default_template'],
            'default',
        ]);

        foreach ($names as $name) {
            foreach (array_unique([$themePath, $path]) as $dir) {
                $file = $dir . '/' . $name . $suffix;

                if (is_file($file)) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * 解析模板目录：传入模板根目录时自动拼接主题，传入主题目录时直接使用
     */
    protected function resolveThemePath(string $path): string
    {
        $path  = rtrim(str_replace('\\', '/', $path), '/');
        $theme = (string) $this->config['theme'];

        if ($path === '') {
            throw new GenerateException('模板目录不能为空');
        }

        if ($theme !== '' && is_dir($path . '/' . $theme)) {
            return $path . '/' . $theme;
        }

        return $path;
    }

    /**
     * 静态文件输出根目录
     */
    protected function outputRoot(): string
    {
        $root = $this->config['output_path'];

        if (!is_string($root) || $root === '') {
            if (function_exists('public_path')) {
                $root = public_path();
            } elseif (($base = $this->basePath()) !== null) {
                $root = $base . '/public';
            } else {
                $root = (string) getcwd() . '/public';
            }
        }

        return rtrim(str_replace('\\', '/', $root), '/');
    }

    /**
     * 模板编译缓存目录
     */
    protected function cacheRoot(): string
    {
        $path = $this->config['cache_path'];

        if (!is_string($path) || $path === '') {
            if (function_exists('runtime_path')) {
                $path = runtime_path() . 'html';
            } else {
                $path = sys_get_temp_dir() . '/think-html/runtime';
            }
        }

        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * ThinkPHP 应用根目录，独立使用时返回 null
     */
    protected function basePath(): ?string
    {
        if (function_exists('base_path')) {
            return rtrim(str_replace('\\', '/', base_path()), '/');
        }

        return null;
    }

    protected function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new GenerateException(sprintf('无法创建目录：%s', $directory));
        }
    }

    protected function debug(string $file): void
    {
        if ($this->config['debug']) {
            echo $file . PHP_EOL;
        }
    }
}