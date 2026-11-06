<?php
declare(strict_types=1);

use Youvision\ThinkHtml\Generator;

if (!function_exists('html_make')) {
    /**
     * 生成单个静态页面
     *
     * @param array       $data          页面数据，titleurl 字段用于确定输出路径
     * @param string|null $site_path     站点数据文件路径，支持 .php（返回数组）与 .json
     * @param string|null $template_path 模板目录，可为模板根目录或主题目录
     */
    function html_make(array $data, ?string $site_path = null, ?string $template_path = null): string
    {
        return app(Generator::class)->make($data, $site_path, $template_path);
    }
}

if (!function_exists('html_build')) {
    /**
     * 批量生成静态页面
     *
     * @param array       $pages         页面数据集合，每个元素对应一个页面
     * @param string|null $site_path     站点数据文件路径
     * @param string|null $template_path 模板目录
     *
     * @return string[] 生成成功的文件路径列表
     */
    function html_build(array $pages, ?string $site_path = null, ?string $template_path = null): array
    {
        return app(Generator::class)->build($pages, $site_path, $template_path);
    }
}