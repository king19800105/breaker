<?php

return [
    'apcu_cli'              => '断路器apcu驱动不建议在cli模式下使用，建议绑定redis驱动',
    'reason'                => '断路器配置错误，原因：',
    'app_id'                => '断路器应用名称非法',
    'cycle_time'            => '断路器探测时间必须在 :min ~ :max 秒之间',
    'open_timeout'          => '断路器持续时间必须在 :min ~ :max 秒之间',
    'threshold'             => '断路器错误次数阀值必须在 :min ~ :max 之间',
    'percent'               => '断路器错误百分比阀值必须在 :min ~ :max 之间',
    'min_sample'            => '断路器最小样本参数值必须在 :min ~ :max 之间',
    'lengthen'              => '断路器半开状态时间延长设置值必须在 :min ~ :max 之间',
    'core_params'           => '断路器核心参数未设置，请检查',
    'op_success'            => '断路器半开状态下，成功次数必须在 :min ~ :max 之间',
    'op_fail'               => '断路器半开状态下，失败次数必须在 :min ~ :max 之间',
    'time_limit'            => '断路器业务执行超时时间设置非法',
    'err_result'            => '断路器错误结果设置非法',
    'option'                => '断路器参数对象非法',
    'threshold_less_sample' => '错误阀值不能小于最小样本!',
    'cycle_less_timeout'    => '断路器执行周期不能小于开启时间'
];
