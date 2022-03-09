<?php

require_once __DIR__ . '/../v4/modules/payments/task/MassTransfersRequeue.php';

$worker = new MassTransfersRequeue();
$worker->run([]);
