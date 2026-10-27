<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpGen\Database\Introspector;
use PhpGen\Jobs\WebhookProcessor;

$db = new Introspector();
$processor = new WebhookProcessor($db);
$result = $processor->process(50);

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;