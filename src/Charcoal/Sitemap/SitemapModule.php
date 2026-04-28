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
    const ADMIN_CONFIG = 'vendor/locomotivemtl/charcoal-contrib-sitemap/config/admin.json';

    /**
     * Setup the module's dependencies.
     *
     * @return self
     */
    public function setup()
    {
        $container = $this->app()->getContainer();

        if (PHP_SAPI == 'cli') {
            $this->setupScriptRoutes();
        } else {
            $this->setupPublicRoutes();
        }

        $sitemapServiceProvider = new SitemapServiceProvider();
        $container->register($sitemapServiceProvider);

        return $this;
    }

    /**
     * Register the 'sitemap.xml' route and sitemap generation action route.
     * Sitemap.xml will be generated on the fly by the SitemapAction controller.
     * If a file sitemap.xml already exists, it will be served instead of generating a new one.
     *
     * @return void
     */
    private function setupPublicRoutes()
    {
        $config = [
            [
                'route'      => '/sitemap.xml',
                'methods'    => [ 'GET' ],
                'controller' => 'charcoal/sitemap/action/sitemap',
                'ident'      => 'charcoal/sitemap/action/sitemap',
            ]
        ];

        $container = $this->app()->getContainer();

        foreach ($config as $routeConfig) {
            $this->app()->map($routeConfig['methods'], $routeConfig['route'], function (
                RequestInterface $request,
                ResponseInterface $response,
                array $args = []
            ) use ($routeConfig, $container) {
                $routeControllerClass = $this['route/controller/action/class'];

                $routeController = $container['route/factory']->create($routeControllerClass, [
                    'config' => $routeConfig,
                    'logger' => $this['logger'],
                ]);

                return $routeController($this, $request, $response);
            });
        }
    }

    /**
     * Register the '/sitemap/generate' route (vendor/bin/charcoal sitemap/generate).
     * This route is meant to be used as a CLI command to generate the sitemap.xml file
     * Sitemap should be regenerated when content is updated.
     *
     * @return void
     */
    private function setupScriptRoutes()
    {
        $config = [
            'route'      => '/sitemap/generate',
            'methods'    => [ 'GET' ],
            'controller' => 'charcoal/sitemap/script/generate-sitemap',
            'ident'      => 'charcoal/sitemap/script/generate-sitemap',
        ];

        $container = $this->app()->getContainer();

        $this->app()->map($config['methods'], $config['route'], function (
            RequestInterface $request,
            ResponseInterface $response,
            array $args = []
        ) use ($config, $container) {
            $routeControllerClass = $this['route/controller/script/class'];

            $routeController = $container['route/factory']->create($routeControllerClass, [
                'config' => $config,
                'logger' => $this['logger'],
            ]);

            return $routeController($this, $request, $response);
        });
    }
}
