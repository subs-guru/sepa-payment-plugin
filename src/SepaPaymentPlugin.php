<?php
namespace SubsGuru\SEPA;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use SubsGuru\Core\Payments\PaymentGatewayRepository;

class SepaPaymentPlugin extends BasePlugin
{
    public function middleware($middleware)
    {
        // Add middleware here.
        return $middleware;
    }

    public function console($commands)
    {
        // Add console commands here.
        return $commands;
    }

    public function bootstrap(PluginApplicationInterface $app)
    {
        parent::bootstrap($app);

        PaymentGatewayRepository::add('SubsGuru\\SEPA\\Payments\\Gateway\\SEPAPaymentGateway');
    }

    public function routes($routes)
    {
        parent::routes($routes);

        $routes->plugin(
            'SubsGuru/SEPA',
            ['path' => '/sepa'],
            function (RouteBuilder $routes) {
                $routes->fallbacks(DashedRoute::class);
            }
        );
    }
}