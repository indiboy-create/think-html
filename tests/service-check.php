<?php
declare(strict_types=1);

// 模拟 ThinkPHP 8 的容器与配置，验证服务注册逻辑（vendor 中仅安装 think-template）
namespace think {
    class Config
    {
        protected array $config = [];

        public function get(string $name = '', mixed $default = null): mixed
        {
            if ($name === '') {
                return $this->config;
            }

            $value = $this->config;

            foreach (explode('.', $name) as $key) {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    return $default;
                }

                $value = $value[$key];
            }

            return $value;
        }

        public function set(array|string $name, mixed $value = null, string $range = ''): void
        {
            if (is_array($name)) {
                foreach ($name as $key => $item) {
                    $this->set($key, $item, $range);
                }

                return;
            }

            $keys   = explode('.', $name);
            $target = &$this->config;

            foreach ($keys as $index => $key) {
                if ($index === count($keys) - 1) {
                    $target[$key] = $value;

                    break;
                }

                if (!isset($target[$key]) || !is_array($target[$key])) {
                    $target[$key] = [];
                }

                $target = &$target[$key];
            }
        }
    }

    class App
    {
        public Config $config;

        protected array $bindings = [];

        protected array $instances = [];

        public function __construct()
        {
            $this->config = new Config();
        }

        public function bind(string $abstract, mixed $concrete = null, bool $shared = true): void
        {
            $this->bindings[$abstract] = $concrete;
        }

        public function make(string $abstract): mixed
        {
            if (isset($this->instances[$abstract])) {
                return $this->instances[$abstract];
            }

            $concrete = $this->bindings[$abstract] ?? $abstract;

            if ($concrete instanceof \Closure) {
                $instance = $concrete($this);
            } elseif (is_string($concrete) && $concrete !== $abstract) {
                $instance = $this->make($concrete);
            } else {
                $instance = new $abstract();
            }

            return $this->instances[$abstract] = $instance;
        }
    }

    abstract class Service
    {
        protected App $app;

        public function __construct(App $app)
        {
            $this->app = $app;
        }

        public function register(): void
        {
        }

        public function boot(): void
        {
        }
    }
}

namespace {
    use Youvision\ThinkHtml\Generator;
    use Youvision\ThinkHtml\Service;

    require dirname(__DIR__) . '/vendor/autoload.php';

    $app = new think\App();

    // 模拟项目自定义配置，应覆盖包默认值
    $app->config->set('html.theme', 'custom');

    (new Service($app))->register();

    $passed = 0;
    $failed = 0;

    check(
        '项目配置覆盖默认配置',
        $app->config->get('html.theme') === 'custom',
        'theme 未被项目配置覆盖',
        $passed,
        $failed
    );
    check(
        '默认配置已合并',
        $app->config->get('html.default_template') === 'index',
        'default_template 默认值缺失',
        $passed,
        $failed
    );
    $actualKeys   = array_keys($app->config->get('html'));
    $expectedKeys = array_keys(require dirname(__DIR__) . '/config/html.php');
    sort($actualKeys);
    sort($expectedKeys);

    check(
        '配置项完整',
        $actualKeys === $expectedKeys,
        '合并后的配置项与配置文件不一致',
        $passed,
        $failed
    );
    check(
        '容器可解析生成器',
        $app->make(Generator::class) instanceof Generator,
        'Generator 绑定失败',
        $passed,
        $failed
    );
    check(
        '别名解析同一实例',
        $app->make('think-html') === $app->make(Generator::class),
        'think-html 别名未共享实例',
        $passed,
        $failed
    );

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
}