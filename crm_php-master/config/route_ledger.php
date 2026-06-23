<?php
// +----------------------------------------------------------------------
// | Description: Ledger routes
// +----------------------------------------------------------------------

return [
    '__rest__'=>[
    ],

    // [客户台账]
    'ledger/index'  => ['ledger/ledger/index',  ['method' => 'POST']],
    'ledger/read'   => ['ledger/ledger/read',   ['method' => 'POST']],
    'ledger/save'   => ['ledger/ledger/save',   ['method' => 'POST']],
    'ledger/update' => ['ledger/ledger/update', ['method' => 'POST']],
    'ledger/delete' => ['ledger/ledger/delete', ['method' => 'POST']],
    'ledger/excelExport' => ['ledger/ledger/excelExport', ['method' => 'POST']],
    'ledger/customer/list' => ['ledger/ledger/customerList', ['method' => 'POST']],
    'ledger/record/list' => ['ledger/record/list', ['method' => 'POST']],
    'ledger/record/add'  => ['ledger/record/add',  ['method' => 'POST']],

    // MISS route
    '__miss__' => 'admin/base/miss',
];
