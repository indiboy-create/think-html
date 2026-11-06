<?php
declare(strict_types=1);

namespace Youvision\ThinkHtml;

use think\Template;
use Youvision\ThinkHtml\Exception\GenerateException;

/**
 * 静态网站生成器
 *
 * 读取模板目录或模板文件，结合站点数据与页面数据渲染，
 * 并将结果写入输出目录，最终返回生成的 HTML 文件路径。
 */
class Generator
{
    protected array $config;

    /**
     * 内置默认配置，与 config/html.php 保持一致，便于脱离 ThinkPHP 独立使用
     */
    protected array $defaults = [
        'template_path'    => null,
        'output_path'      => null,
        'default_template' => 'index',
        'view_suffix'      => 'html',
        'cache_path'       => null,
        'overwrite'        => true,
        'suffix'           => 'html',
        'charset'          => 'utf-8',
        'tpl_begin'        => '{',
        'tpl_end'          => '}',
        'taglib_begin'     => '{',
        'taglib_end'       => '}',
        'debug'            => false,
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaults, $config);
    }

    /**
     * 生成单个静态页面
     *
     * @param array $data 页面数据，包含 site_id（站点根目录）、titleurl（HTML 路径）、template（模板名）等字段
     *
     * @return string 生成文件的绝对路径
     */
    public function make(array $data): string
    {
        $siteId   = $this->resolveSiteId((string) ($data['site_id'] ?? ''));
        $relative = $this->resolveRelativePath((string) ($data['titleurl'] ?? ''));

        if ($siteId !== '') {
            $relative = $siteId . '/' . $relative;
        }

        $target = $this->outputRoot() . '/' . $relative;

        $this->ensureDirectory(dirname($target));

        if (is_file($target) && !$this->config['overwrite']) {
            $this->debug($target);

            return $target;
        }

        $html = $this->render($data);

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
     * 规范化站点 ID，作为输出根目录下的站点目录名
     */
    protected function resolveSiteId(string $site_id): string
    {
        $site_id = trim($site_id);

        if ($site_id === '') {
            return '';
        }

        return (string) preg_replace('/[^0-9A-Za-z_\-]/', '', $site_id);
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
    protected function render(array $data): string
    {
        $templateFile = $this->resolveTemplateFile($data);
        $template     = basename($templateFile);
        $engine       = $this->engine(dirname($templateFile));

        $vars = array_merge($data, [
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
     * 模板路径支持模板目录（template）或模板文件（template/default.html）
     */
    protected function resolveTemplateFile(array $data): string
    {
        $configured = $this->config['template_path'];

        if (is_string($configured) && $configured !== '') {
            $file = $this->locateTemplate($configured, $data);

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
            is_string($configured) && $configured !== '' ? $configured : '未指定',
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

        $names = array_unique([
            $this->resolveTemplate($data),
            (string) $this->config['default_template'],
            'default',
        ]);

        foreach ($names as $name) {
            $file = $path . '/' . $name . $suffix;

            if (is_file($file)) {
                return $file;
            }
        }

        return null;
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