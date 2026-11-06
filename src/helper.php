<?php
declare(strict_types=1);

use Youvision\ThinkHtml\Generator;

if (!function_exists('html_make')) {
    /**
     * 生成单个静态页面
     *
     * @param array $data 页面数据，包含 site_id（站点根目录）、titleurl（HTML 路径）、template（模板名）等字段
     */
    function html_make(array $data): string
    {
        return app(Generator::class)->make($data);
    }
}