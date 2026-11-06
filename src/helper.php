<?php
declare(strict_types=1);

use Youvision\ThinkHtml\Generator;

if (!function_exists('html_make')) {
    /**
     * 生成单个静态页面
     *
     * @param array       $site 站点数据
     * @param array       $data 页面数据，titleurl 字段用于确定输出路径
     * @param string|null $file 自定义输出文件路径，为 null 时使用 $data['titleurl']
     */
    function html_make(array $site, array $data, ?string $file = null): string
    {
        return app(Generator::class)->make($site, $data, $file);
    }
}

if (!function_exists('html_build')) {
    /**
     * 批量生成静态页面
     *
     * @param array $site  站点数据
     * @param array $pages 页面数据集合，每个元素对应一个页面
     *
     * @return string[] 生成成功的文件路径列表
     */
    function html_build(array $site, array $pages): array
    {
        return app(Generator::class)->build($site, $pages);
    }
}