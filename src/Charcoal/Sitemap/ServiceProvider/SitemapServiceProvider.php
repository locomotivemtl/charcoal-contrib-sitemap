<?php

namespace Charcoal\Sitemap\ServiceProvider;

use Charcoal\Factory\GenericFactory;
use Charcoal\Sitemap\Service\Builder;
use Charcoal\Sitemap\Service\SitemapPresenter;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

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
         * @param  Container $container the service locator.
         * @return Builder
         */
        $container['charcoal/sitemap/builder'] = function (Container $container) {
            $builder = new Builder([
                'base-url'                => $container['base-url'],
                'model/collection/loader' => $container['model/collection/loader'],
                'sitemap/presenter'       => $container['sitemap/presenter'],
                'translator'              => $container['translator'],
                'view'                    => $container['view'],
            ]);

            $config = $container['config'];

            $builder->setObjectHierarchy($config->get('sitemap'));

            return $builder;
        };

        /**
         * @param  Container $container
         * @return SitemapPresenter
         */
        $container['sitemap/presenter'] = function (Container $container) {
            $transformerFactory = isset($container['transformer/factory'])
                ? $container['transformer/factory']
                : $container['sitemap/transformer/factory'];

            return new SitemapPresenter(
                $transformerFactory,
                $container['cache/facade'],
                $container['translator'],
            );
        };

        /**
         * Generic transformer factory.
         *
         * For a class name app/model/class, it will resolve to App/Transformer/Object/ClassTransformer
         *
         * @param  Container $container
         * @return GenericFactory
         */
        $container['sitemap/transformer/factory'] = function (Container $container) {
            return new GenericFactory([
                'arguments'        => [
                    'container' => $container,
                    'logger'    => $container['logger'],
                ],
                'resolver_options' => [
                    'suffix'       => 'Transformer',
                    'replacements' => [
                        'App/Model/'    => 'App/Transformer/Sitemap/',
                        'app/model'     => 'app/transformer/sitemap/',
                        'App\\Model\\'  => 'App\\Transformer\\Sitemap\\',
                        'App/'          => 'App/Transformer/Sitemap/',
                        'app/'          => 'app/transformer/sitemap/',
                        'App\\'         => 'App\\Transformer\\Sitemap\\',
                        '-'             => '',
                        '/'             => '\\',
                        '.'             => '_',
                    ],
                ],
            ]);
        };
    }
}
