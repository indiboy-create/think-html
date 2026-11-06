<?php
declare(strict_types=1);

namespace Youvision\ThinkHtml;

use think\Service as ThinkService;

/**
 * ThinkPHP 8 服务注册
 *
 * composer.json 中通过 extra.think.services 自动加载，
 * 负责合并配置、绑定生成器实例并注册全局助手函数所需的容器别名。
 */
class Service extends ThinkService
{
    public function register(): void
    {
        $default = require dirname(__DIR__) . '/config/html.php';
        $current = (array) $this->app->config->get('html', []);

        foreach (array_merge($default, $current) as $key => $value) {
            $this->app->config->set('html.' . $key, $value);
        }

        $this->app->bind('think-html', function ($app) {
            return new Generator((array) $app->config->get('html', []));
        });

        $this->app->bind(Generator::class, 'think-html');
    }

    public function boot(): void
    {
    }
}