<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

function check_session()
{
    // check if ther is an active session
    return isset($_SESSION['user']);
}

function printData($data, $die = true)
{
    echo '<pre>';
    if (is_object($data) || is_array($data)) {
        print_r($data);
    } else {
        echo $data;
    }

    if ($die) {
        die('<br>FIM</br>');
    }
}

function logger($msg = '', $level = 'info')
{
    // create log channel
    $log = new Logger('app.logs');
    $log->pushHandler(new StreamHandler(LOGS_PATH));

    switch ($level) {
        case 'info':
            $log->info($msg);
            break;
        case 'notice':
            $log->notice($msg);
            break;
        case 'warning':
            $log->warning($msg);
            break;
        case 'error':
            $log->error($msg);
            break;
        case 'critical':
            $log->critical($msg);
            break;
        case 'alert':
            $log->alert($msg);
            break;
        case 'emergency':
            $log->emergency($msg);
            break;

        default:
            $log->info($msg);
            break;
    }
}

function aes_encrypt($value)
{
    // encrypt this value
    return bin2hex(openssl_encrypt($value, 'aes-256-cbc', OPENSSL_KEY, OPENSSL_RAW_DATA, OPENSSL_IV));
}

function aes_decrypt($value)
{
    // decrypt this value
    if (strlen($value) % 2 != 0) {
        return false;
    }

    return openssl_decrypt(hex2bin($value), 'aes-256-cbc', OPENSSL_KEY, OPENSSL_RAW_DATA, OPENSSL_IV);
}

function get_active_user_name()
{
    return $_SESSION['user']->name;
}

function hash_text($text, $i = 3, $e = 3)
{
    $count = strlen($text);
    $ocult = "";

    if ($count > 6) {
        $init = substr($text, 0, $i);
        $mid = str_repeat('*', $count - 6);
        $end = substr($text, -$e);

        $ocult = $init . $mid . $end;
    } else {
        $ocult = str_repeat('*', $count);
    }

    return $ocult;
}