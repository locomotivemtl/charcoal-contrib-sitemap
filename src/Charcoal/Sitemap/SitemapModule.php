<?php

namespace Charcoal\Sitemap;

// from charcoal-app
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
     * @return AbstractModule
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
     * @return void
     */
    private function setupPublicRoutes()
    {
        $config = [
            'route'      => '/sitemap.xml',
            'controller' => 'charcoal/sitemap/action/sitemap',
            'methods'    => ['GET'],
            'ident'      => 'charcoal/sitemap/action/sitemap'
        ];

        $container = $this->app()->getContainer();

        $this->app()->map($config['methods'], $config['route'], function (
            RequestInterface $request,
            ResponseInterface $response,
            array $args = []) use ($config, $container) {

            $routeController = $this['route/controller/action/class'];

            $route = $container['route/factory']->create($routeController, [
                'config' => $config,
                'logger' => $this['logger']
            ]);

            return $route($this, $request, $response);
        });
    }

}
