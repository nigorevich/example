<?
	$permissions = array(
		'view' => array(
			'methods' => array('page'),
			'name' => 'Просмотр списка',
			'css' => array(
				'/v4/modules/payments/layouts/css/default.css'
				),
			'js' => array(
				'/v4/modules/payments/layouts/js/default.js'
				)
			),
		'object' => array(
			'name' => 'Просмотр объекта',
            'methods' => array('downloadAct'),
            'css' => array(),
			'js' => [
				'/v4/modules/payments/layouts/js/object.js',
                '/v4/modules/payments/layouts/js/refund.js'
				]
			),

		'refresh' => array(
			'name' => 'Обновление статуса',
			'css' => array(),
			'js' => array(
				'/v4/modules/payments/layouts/js/refresh.js'
				)
			),
        'stuckHold' => [
            'name' => 'Зависшие холды',
            'methods' => ['stuckHold', 'changeHoldStatus', 'stuckHoldList'],
            'js' => [
                '/v4/modules/payments/layouts/js/stuckHold.js'
            ]
        ],
	);
