<?php

namespace Charcoal\Sitemap\ServiceProvider;

// From Pimple
use Pimple\Container;
use Pimple\ServiceProviderInterface;

use Charcoal\Sitemap\Service\Builder;


/**
 * The Sitemap Contrib Service Provider.
 */
class SitemapServiceProvider implements ServiceProviderInterface
{
    /**
     * Register the contrib's services.
     *
     * @param  Container $container The service locator.
     * @return void
     */
    public function register(Container $container)
    {
        /**
         * @param Container $container
         * @return Builder
         */
        $container['charcoal/sitemap/builder'] = function (Container $container) {
            $builder = new Builder([
                'base-url'                => $container['base-url'],
                'model/factory'           => $container['model/factory'],
                'model/collection/loader' => $container['model/collection/loader'],
                'translator'              => $container['translator']
            ]);

            $config = $container['config'];

            $builder->setObjectHierarchy($config->get('sitemap'));

            return $builder;
        };

    }
}
