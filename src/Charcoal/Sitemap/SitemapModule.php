<?php

namespace Charcoal\Sitemap;

use Charcoal\App\Module\AbstractModule;
use Charcoal\Sitemap\ServiceProvider\SitemapServiceProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Sitemap Module
 */
class SitemapModule extends AbstractModule
{
    /**
     * Setup the module's dependencies.
     *
     * @return self
     */
    public function setup()
    {
        $container = $this->app()->getContainer();

        $this->setupPublicRoutes();

        $sitemapServiceProvider = new SitemapServiceProvider();
        $container->register($sitemapServiceProvider);

        return $this;
    }

    /**
     * Register the 'sitemap.xml' route.
     *
     * @return void
     */
    private function setupPublicRoutes()
    {
        $config = [
            'route'      => '/sitemap.xml',
            'methods'    => [ 'GET' ],
            'controller' => 'charcoal/sitemap/action/sitemap',
            'ident'      => 'charcoal/sitemap/action/sitemap',
        ];

        $container = $this->app()->getContainer();

        $this->app()->map($config['methods'], $config['route'], function (
            RequestInterface $request,
            ResponseInterface $response,
            array $args = []
        ) use ($config, $container) {
            $routeControllerClass = $this['route/controller/action/class'];

            $routeController = $container['route/factory']->create($routeControllerClass, [
                'config' => $config,
                'logger' => $this['logger'],
            ]);

            return $routeController($this, $request, $response);
        });
    }
}
