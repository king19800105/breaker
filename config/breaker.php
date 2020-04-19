<?php

return [
    // apcu redis
    'drive'   => 'redis',
    // 默认
    'default' => 'main',
    // 定义一组规则
    'main'    => [
        // 探测的周期，限制 5～100
        'cycle'           => 30,
        // 断路器打开超时时间
        'open_timeout'    => 8,
        // 触发的阀值次数，如：在30秒当周期内，发生10次错误就打开断路器，限制5～10000次
        'threshold'       => 10,
        // 触发当百分比，和threshold一起做限制，当为0时，表示忽略，限制0.05 ~ 1.00
        'percent'         => 0.90,
        // 请求次数最小样本，最小样本 < threshold
        'min_sample'      => 5,
        // 每次半开状态后，继续open后延时重当试叠加时间，0表示不叠加，最大一次打开时间为60秒为止
        'lengthen'        => 0,
        // 状态转移设置，半开状态下，成功次数达标关闭，和失败次数达标继续开启
        'half_op_success' => 2,
        'half_op_fail'    => 1,
    ],
//    'other' => []
];
