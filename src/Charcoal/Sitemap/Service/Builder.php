<?php

namespace Charcoal\Sitemap\Service;

use Charcoal\Loader\CollectionLoader;
use Charcoal\Object\RoutableInterface;
use Charcoal\Translator\TranslatorAwareTrait;
use Charcoal\View\ViewableInterface;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Slim\Http\Uri;

/**
 * Sitemap builder from object hierarchy
 *
 * classname:
 *    filters: array (charcoal-core doc)
 *    orders: array (charcoal-core doc)
 *    children: array (same as objects)
 *        condition: Parent condition (loop those children on a parent renderable condition)
 */
class Builder
{
    use TranslatorAwareTrait;

    /**
     * @var UriInterface
     */
    private $baseUrl;

    /**
     * Store the collection loader instance.
     *
     * @var CollectionLoader
     */
    private $collectionLoader;

    /**
     * Object hierarchy as defined in the config.json
     *
     * @var array<string, array<string, mixed>>
     */
    private $objectHierarchy;

    /**
     * @var SitemapPresenter
     */
    private $sitemapPresenter;

    /**
     * Create the Sitemap Builder.
     *
     * @param  array $data Class dependencies.
     * @throws InvalidArgumentException When no model factory is defined.
     * @throws InvalidArgumentException When no collection loader is defined.
     */
    public function __construct(array $data)
    {
        if (!isset($data['model/collection/loader'])) {
            throw new InvalidArgumentException('Collection Loader must be defined in the SitemapBuilder Service.');
        }

        if (!isset($data['base-url'])) {
            throw new InvalidArgumentException('Base URL must be defined in the SitemapBuilder Service.');
        }

        if (!isset($data['translator'])) {
            throw new InvalidArgumentException('Translator must be defined in the SitemapBuilder Service.');
        }

        $this->setBaseUrl($data['base-url']);
        $this->setCollectionLoader($data['model/collection/loader']);
        $this->setSitemapPresenter($data['sitemap/presenter']);
        $this->setTranslator($data['translator']);

        return $this;
    }

    /**
     * Necessary options.
     *
     * Most options within the objects range are renderable
     * Options outsite the 'objects' will impact objects and
     * can be overwriten for each.
     *
     * ```json
     * {
     *     'l10n': true, // Setting l10n mode
     *     'objects': {
     *         'boilerplate/object': {
     *             'data': {
     *                 'id': '{{id}}',
     *                 'l10n': false // This object doesn't require l10n in that context
     *             }
     *         }
     *     }
     * }
     * ```
     *
     * @return array Renderable options.
     */
    protected function defaultOptions()
    {
        return [
            'locale'              => $this->translator()->getLocale(),
            'l10n'                => true,
            'check_active_routes' => true,
            'relative_urls'       => true,
            'transformer'         => null,
            'objects'             => [
                'label'    => '{{title}}',
                'url'      => '{{url}}',
                'children' => [],
                'data'     => [],
            ]
        ];
    }

    /**
     * Build the sitemap array.
     *
     * @return array The actual sitemap.
     */
    public function build($ident = 'default')
    {
        $h = $this->objectHierarchy();

        if (!$h) {
            return [];
        }

        if (!isset($h[$ident])) {
            throw new InvalidArgumentException(strtr('Sitemap %ident not defined.', [
                '%ident' => $ident
            ]));
        }

        if (!isset($h[$ident]['objects'])) {
            throw new InvalidArgumentException(strtr('No objects defined in %ident sitemap.', [
                '%ident' => $ident
            ]));
        }

        $opts    = $h[$ident];
        $objects = $opts['objects'];

        $out = [];

        $defaults = $this->defaultOptions();

        // Unnecessary for the following merge
        unset($opts['objects']);
        $defaults = array_merge($defaults, $opts);

        $objectOptions = $defaults['objects'];
        foreach ($objects as $class => $options) {
            $options = array_merge($objectOptions, $options);

            if (!isset($options['l10n'])) {
                $options['l10n'] = $defaults['l10n'];
            }

            if (!isset($options['locale'])) {
                $options['locale'] = $defaults['locale'];
            }

            if (!isset($options['check_active_routes'])) {
                $options['check_active_routes'] = $defaults['check_active_routes'];
            }

            if (!isset($options['relative_urls'])) {
                $options['relative_urls'] = $defaults['relative_urls'];
            }

            if (!isset($options['transformer'])) {
                $options['transformer'] = $defaults['transformer'];
            }

            $out[] = $this->buildObject($class, $options);
        }

        return $out;
    }

    /**
     * Build object from the given hierarchy
     *
     * @param  string            $class   Classname.
     * @param  array             $options Associated options.
     * @param  ViewableInterface $parent  Parent object to render on.
     * @return array Local sitemap.
     */
    protected function buildObject($class, $options, ViewableInterface $parent = null, $level = 0)
    {
        // If the render of a condition is false or empty, dont process the object.
        if ($parent && isset($options['condition'])) {
            if (!$parent->view()->render($options['condition'], $parent)) {
                return [];
            }
        }


        $loader = $this->collectionLoader()->setModel($class);

        // From the filters
        if (isset($options['filters'])) {
            $filters = $options['filters'];
            if ($parent) {
                $filters = $this->renderData($parent, $filters, $options['transformer']);
            }
            $loader->addFilters($filters);
        }

        // From the orders
        if (isset($options['orders'])) {
            $orders = $options['orders'];
            if ($parent) {
                $orders = $this->renderData($parent, $orders, $options['transformer']);
            }
            $loader->addOrders($orders);
        }

        // Loading
        $list = $loader->load();

        // Processing the objects and rendering data
        $out      = [];
        $children = isset($options['children']) ? $options['children'] : [];
        $level++;

        // Options
        $l10n              = $options['l10n'];
        $defaultLocale     = $options['locale'];
        $checkActiveRoutes = $options['check_active_routes'];
        $relativeUrls      = $options['relative_urls'];
        $transformer       = $options['transformer'];

        // Locales
        $availableLocales = $l10n
            ? $this->translator()->availableLocales()
            : [ $defaultLocale ];

        foreach ($availableLocales as $locale) {

            // Get opposite languages locales
            $oppositeLang = [];
            foreach ($availableLocales as $l) {
                if ($l === $locale) {
                    continue;
                }
                $oppositeLang[] = $l;
            }

            // Set the local to the current locale before looping the list.
            $this->translator()->setLocale($locale);
            foreach ($list as $object) {
                // When checking active routes, do not display routes that are not active
                if ($checkActiveRoutes && $object instanceof RoutableInterface && !$object->isActiveRoute()) {
                    continue;
                }

                // Hierarchical (children, when defined)
                $cs = [];
                if (!empty($children)) {
                    foreach ($children as $cname => $opts) {
                        $opts = array_merge($this->defaultOptions(), $opts);
                        $cs[] = $this->buildObject($cname, $opts, $object, $level);
                    }
                }

                $url = trim($this->renderData($object, $options['url'], $transformer));
                if (!$relativeUrls) {
                    $url = $this->withBaseUrl($url);
                }

                $tmp = [
                    'label'    => trim($this->renderData($object, $options['label'], $transformer)),
                    'url'      => $url,
                    'children' => $cs,
                    'data'     => $this->renderData($object, $options['data'], $transformer),
                    'level'    => $level,
                    'lang'     => $locale,
                ];

                // If you need a priority, fix your own rules
                $priority = '';
                if (isset($options['priority']) && $options['priority']) {
                    $priority = $this->renderData($object, (string)$options['priority'], $transformer);
                }
                $tmp['priority'] = $priority;

                // If you need a date of last modification, fix your own rules
                $last = '';
                if (isset($options['last_modified']) && $options['last_modified']) {
                    $last = $this->renderData($object, $options['last_modified'], $transformer);
                }
                $tmp['last_modified'] = $last;

                // Opposite Languages
                // Meant to be alternate, thus the lack of data rendering
                $alternates = [];
                foreach ($oppositeLang as $ol) {
                    $this->translator()->setLocale($ol);

                    if ($checkActiveRoutes && $object instanceof RoutableInterface && !$object->isActiveRoute()) {
                        continue;
                    }

                    $url = trim($this->renderData($object, $options['url'], $transformer));
                    if (!$relativeUrls) {
                        $url = $this->withBaseUrl($url);
                    }

                    $alternates[] = [
                        'url'  => $url,
                        'lang' => $ol,
                    ];
                }

                $tmp['alternates'] = $alternates;

                $this->translator()->setLocale($locale);
                $out[] = $tmp;
            }
        }

        return $out;
    }

    /**
     * Recursive data renderer
     *
     * @param  ViewableInterface $obj  Object to render on.
     * @param  mixed             $data Pretty much anything to be rendered
     * @return mixed Rendered data.
     */
    protected function renderData(ViewableInterface $obj, $data, $transformer = null)
    {
        if (is_scalar($data)) {
            $presentedObject = $this->sitemapPresenter()->transform($obj, $transformer);
            return $obj->view()->render($data, $presentedObject);
        }

        if (is_array($data)) {
            $out = [];
            foreach ($data as $key => $content) {
                $out[$key] = $this->renderData($obj, $content, $transformer);
            }
            return $out;
        }
    }

    /**
     * @return array Object hierarchy
     */
    protected function objectHierarchy()
    {
        return $this->objectHierarchy;
    }

    /**
     * Set the object hierarchy list
     *
     * @param array $hierarchy List.
     * @return self
     */
    public function setObjectHierarchy($hierarchy)
    {
        $this->objectHierarchy = $hierarchy;
        return $this;
    }

    /**
     * Retrieve the model collection loader.
     *
     * @throws RuntimeException If the collection loader is missing.
     * @return CollectionLoader
     */
    public function collectionLoader()
    {
        if (!isset($this->collectionLoader)) {
            throw new RuntimeException(sprintf(
                'Collection Loader is not defined for [%s]',
                get_class($this)
            ));
        }

        return $this->collectionLoader;
    }

    /**
     * Set a model collection loader.
     *
     * @param  CollectionLoader $loader The model collection loader.
     * @return void
     */
    protected function setCollectionLoader(CollectionLoader $loader)
    {
        $this->collectionLoader = $loader;
    }

    /**
     * @return SitemapPresenter
     */
    public function sitemapPresenter()
    {
        return $this->sitemapPresenter;
    }

    /**
     * @param SitemapPresenter $presenter
     * @return Builder
     */
    public function setSitemapPresenter(SitemapPresenter $presenter)
    {
        $this->sitemapPresenter = $presenter;
        return $this;
    }

    /**
     * Get the website's base URL.
     *
     * @return UriInterface
     */
    protected function baseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Set the website's base URL.
     *
     * @param  UriInterface|string $baseUrl URL
     * @return self
     */
    public function setBaseUrl($baseUrl)
    {
        if (!($baseUrl instanceof UriInterface)) {
            $baseUrl = Uri::createFromString($baseUrl);
        }

        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * Returns the URI with the base URI prepended, if not absolute.
     *
     * @param  $uri The URI to parse.
     * @return string
     */
    protected function withBaseUrl($uri)
    {
        $uri = strval($uri);
        if ($uri) {
            $parts = parse_url($uri);
            if (!isset($parts['scheme'])) {
                if (!in_array($uri[0], ['/', '#', '?'])) {
                    $path  = isset($parts['path']) ? $parts['path'] : '';
                    $query = isset($parts['query']) ? $parts['query'] : '';
                    $hash  = isset($parts['fragment']) ? $parts['fragment'] : '';

                    $uri = $this->baseUrl()
                        ->withPath($path)
                        ->withQuery($query)
                        ->withFragment($hash);
                }
            }
        }

        return $uri;
    }
}
