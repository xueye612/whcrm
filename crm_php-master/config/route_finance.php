<?php
// +----------------------------------------------------------------------
// | Description: Finance routes
// +----------------------------------------------------------------------

return [
    '__rest__'=>[
    ],

    // [收支类型]
    'finance/type/index'  => ['finance/type/index',  ['method' => 'POST']],
    'finance/type/save'   => ['finance/type/save',   ['method' => 'POST']],
    'finance/type/update' => ['finance/type/update', ['method' => 'POST']],
    'finance/type/delete' => ['finance/type/delete', ['method' => 'POST']],

    // [收支流水]
    'finance/record/index'  => ['finance/record/index',  ['method' => 'POST']],
    'finance/record/read'   => ['finance/record/read',   ['method' => 'POST']],
    'finance/record/save'   => ['finance/record/save',   ['method' => 'POST']],
    'finance/record/update' => ['finance/record/update', ['method' => 'POST']],
    'finance/record/delete' => ['finance/record/delete', ['method' => 'POST']],

    // [收支计划]
    'finance/plan/index'  => ['finance/plan/index',  ['method' => 'POST']],
    'finance/plan/read'   => ['finance/plan/read',   ['method' => 'POST']],
    'finance/plan/save'   => ['finance/plan/save',   ['method' => 'POST']],
    'finance/plan/update' => ['finance/plan/update', ['method' => 'POST']],
    'finance/plan/delete' => ['finance/plan/delete', ['method' => 'POST']],

    // [支付方式]
    'finance/paymentmethod/index'  => ['finance/PaymentMethod/index',  ['method' => 'POST']],
    'finance/paymentmethod/save'   => ['finance/PaymentMethod/save',   ['method' => 'POST']],
    'finance/paymentmethod/update' => ['finance/PaymentMethod/update', ['method' => 'POST']],
    'finance/paymentmethod/delete' => ['finance/PaymentMethod/delete', ['method' => 'POST']],

    // MISS route
    '__miss__' => 'admin/base/miss',
];
