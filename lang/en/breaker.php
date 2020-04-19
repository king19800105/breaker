<?php

return [
    'reason'                => 'breaker configuration error, reason:',
    'app_id'                => 'breaker appId is illegal.',
    'cycle_time'            => 'breaker cycle time must be between :min ~ :max.',
    'open_timeout'          => 'breaker open timeout must be between :min ~ :max.',
    'threshold'             => 'breaker error threshold must be between :min ~ :max.',
    'percent'               => 'breaker error percent must be between :min ~ :max.',
    'min_sample'            => 'breaker min sample must be between :min ~ :max.',
    'lengthen'              => 'breaker half open lengthen must be between :min ~ :max.',
    'core_params'           => 'breaker core parameters are not set.',
    'op_success'            => 'breaker half open state, success count be between :min ~ :max.',
    'op_fail'               => 'breaker half open state, fail count be between :min ~ :max.',
    'option'                => 'breaker option is illegal.',
    'threshold_less_sample' => 'breaker the error threshold cannot be smaller than the smallest sample.',
    'cycle_less_timeout'    => 'the circuit breaker execution cycle cannot be less than the opening time.'
];
