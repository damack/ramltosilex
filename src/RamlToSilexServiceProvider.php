<?php

namespace Damack\RamlToSilex;

use Raml\Parser;
use Silex\Application;
use Silex\Api\BootableProviderInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class RamlToSilexServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{
    public function register(Container $app)
    {
        $app['ramlToSilex.initializer'] = $app->protect(function () use ($app) {
            if (!is_readable($ramlFile = $app['ramlToSilex.raml_file'])) {
                throw new \RuntimeException("API config file is not readable");
            }

            $configFile = json_decode(file_get_contents($app['ramlToSilex.config_file']));
            $app['ramlToSilex.apiDefinition'] = (new Parser())->parse($ramlFile, false);
            $app['ramlToSilex.routes'] = $app['ramlToSilex.apiDefinition']->getResourcesAsUri()->getRoutes();
            if (property_exists($configFile, 'routeAccess')) {
                $app['ramlToSilex.routeAccess'] = $configFile->routeAccess;
            }
            if (property_exists($configFile, 'controllers')) {
                $app['ramlToSilex.customControllerMapping'] = $configFile->controllers;
            }
            $app['ramlToSilex.restController'] = function () use ($app) {
                return new RestController($app);
            };

            $app['ramlToSilex.routeBuilder'] = function () {
                return new RouteBuilder();
            };

        });

        $app['ramlToSilex.builder'] = function () use ($app) {
            $app['ramlToSilex.initializer']();

            $controllers = $app['ramlToSilex.routeBuilder']->build($app, 'ramlToSilex.restController');
            $app['controllers']->mount('', $controllers);
        };
    }

    public function boot(Application $app)
    {
        $app['ramlToSilex.builder'];
    }
}
