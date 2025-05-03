<?php

namespace bng\System;

use bng\Controllers\Main;
use Exception;

class Router
{
    public static function dispatch()
    {
        // main route values
        $httpverb = $_SERVER['REQUEST_METHOD'];
        $controller = isset($_GET['ct']) ? $_GET['ct'] : 'main';
        $method = isset($_GET['mt']) ? $_GET['mt'] : 'index';

        // method parameters
        $parameters = $_GET;

        // remove controller from parameters
        if(key_exists("ct", $parameters)) {
            unset($parameters["ct"]);
        }

        // remove method from parameters
        if(key_exists("mt", $parameters)) {
            unset($parameters["mt"]);
        }

        // tries to instanciate the controller and execute the method
        try {
            $class = "bng\Controllers\\$controller";
            $controller = new $class();
            $controller->$method(...$parameters);
        } catch (\Throwable $th) {
            die("Acesso inválido");
        }
    }
}