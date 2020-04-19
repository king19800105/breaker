# breaker
- laravel 断路器支持

### 安装和配置

- 安装

```
composer require anthony/breaker
```

- 绑定provider文件

```
// config/app.php 添加

/*
 * Package Service Providers...
 */
\Anthony\Breaker\Providers\BreakerServiceProvider::class,
// ...
```

- 发布配置文件

```
php artisan vendor:publish
```

### 使用

- 基本使用

```php
// 设置参数
$option = (new Option())
    // 断路器id，不可重复
    ->setAppId('user_controller:get_user')
    // 设置一个周期时长
    ->setCycleTime(30)
    // 断路器打开的持续时间
    ->setOpenTimeout(8)
    // 一个周期触发断路器打开的错误阀值
    ->setThreshold(10)
    // 一个周期触发断路器打开的错误请求占比
    ->setPercent(0.95)
    // 最小请求样本
    ->setMinSample(5)
    // 半开状态下每次重试的时间延长（重试次数 * lengthen）
    ->setLengthen(0)
    // 半开状态下成功次数和失败次数的状态转移
    ->setHalfOpenStatusMove(2, 1);

/* @var ICircuitBreaker $breaker 断路器对象 */
$breaker = app(ICircuitBreaker::class, ['option' => $option]);
// 执行的业务逻辑
$handler = function () {
    return $this->userService->users();
};
// 自定义校验器，$data表示校验的结果，$elapsed表示程序耗时
$checker =  function (array $data, float $elapsed) {
    // 结果不为空，并且执行时间少于3秒表示成功，其他表示失败
    return !empty($data) || $elapsed < 3;
};
// 设置断路器打开时的返回值
$breakerOpenResponse = [];
return $breaker->run($handler, $checker, $breakerOpenResponse);
```

- 自定义中间件使用

```php
// 创建 app/Http/Middleware/CircuitBreaker.php 中间件文件

// 并在 app/Http/Kernel.php 中注册
protected $routeMiddleware = [
    'breaker' => \App\Http\Middleware\CircuitBreaker::class,
    // ...
];

// 路由中添加中间件
$router->post('users', 'UserController@getUsers')->middleware('breaker:user.v1.getUsers')->name('user.v1.getUsers');

// 编写CircuitBreaker.php文件
public function handle(Request $request, \Closure $next, $name, $select = null)
{
    if (null === $select) {
        $select = config('breaker.default');
    }

    $configs = config('breaker.' . $select);
    if (empty($configs)) {
        return $next($request);
    }

    $option = (new Option())
        ->setAppId($name)
        ->setCycleTime($configs['cycle'])
        ->setOpenTimeout($configs['open_timeout'])
        ->setThreshold($configs['threshold'])
        ->setPercent($configs['percent'])
        ->setMinSample($configs['min_sample'])
        ->setLengthen($configs['lengthen'])
        ->setHalfOpenStatusMove($configs['half_op_success'], $configs['half_op_fail']);

    /* @var ICircuitBreaker $breaker */
    $breaker = app(ICircuitBreaker::class, ['option' => $option]);
    // 执行的业务逻辑
    $handler = function () use ($next, $request) {
        return $next($request);
    };
    // 校验执行是否成功
    $checker =  function (JsonResponse $response, float $elapsed) use ($configs) {
        $ret = null !== $response->exception;
        $isTimeout = $elapsed >= 3;
        return !$ret && !$isTimeout;
    };
    // 断路器打开时返回的数据
    $breakerOpenResponse = response()->json($this->format());
    return $breaker->run($handler, $checker, $breakerOpenResponse);
}
```
