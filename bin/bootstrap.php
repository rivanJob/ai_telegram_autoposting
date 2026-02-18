<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
AutoPoster\Core\Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(AutoPoster\Core\Env::get('APP_TIMEZONE', 'UTC'));
