<?php

namespace Charcoal\Sitemap\ServiceProvider;

use Charcoal\Factory\GenericFactory;
use Charcoal\Sitemap\Service\Builder;
use Charcoal\Sitemap\Service\SitemapPresenter;
use Psr\Container\ContainerInterface;

/**
 * The Sitemap Contrib Service Provider.
 */
class SitemapServiceProvider
{
    /**
     * Register the contrib's services.
     *
     * @param  ContainerInterface $container The service locator.
     * @return void
     */
    public function register(ContainerInterface $container)
    {
        /**
         * @param  ContainerInterface $container the service locator.
         * @return Builder
         */
        $container->set('charcoal/sitemap/builder', function (ContainerInterface $container) {
            $builder = new Builder([
                'base-url'                => $container->get('base-url'),
                'model/collection/loader' => $container->get('model/collection/loader'),
                'sitemap/presenter'       => $container->get('sitemap/presenter'),
                'translator'              => $container->get('translator'),
                'view'                    => $container->get('view'),
            ]);

            $config = $container->get('config');

            $builder->setObjectHierarchy($config->get('sitemap'));

            return $builder;
        });

        /**
         * @param  ContainerInterface $container
         * @return SitemapPresenter
         */
        $container->set('sitemap/presenter', function (ContainerInterface $container) {
            $transformerFactory = $container->has('transformer/factory')
                ? $container->get('transformer/factory')
                : $container->get('sitemap/transformer/factory');

            return new SitemapPresenter(
                $transformerFactory,
                $container->get('cache/facade'),
                $container->get('translator'),
            );
        });

        /**
         * Generic transformer factory.
         *
         * For a class name app/model/class, it will resolve to App/Transformer/Object/ClassTransformer
         *
         * @param  ContainerInterface $container
         * @return GenericFactory
         */
        $container->set('sitemap/transformer/factory', function (ContainerInterface $container) {
            return new GenericFactory([
                'arguments'        => [
                    'container' => $container,
                    'logger'    => $container->get('logger'),
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
        });
    }
}
