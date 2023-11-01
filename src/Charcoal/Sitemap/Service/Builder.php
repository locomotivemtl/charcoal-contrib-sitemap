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
 */
class Builder
{
    use TranslatorAwareTrait;

    /**
     * Temporarily store, during build time, the object hierarchy options
     * merged with the default sitemap options.
     *
     * @var array<string, mixed>
     */
    protected $buildSitemapOptions = [];

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
     * Retrieves the default options for sitemap hierarchy.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultSitemapOptions()
    {
        return [
            'locale'              => $this->translator()->getLocale(),
            'l10n'                => true,
            'check_active_routes' => true,
            'relative_urls'       => true,
            'transformer'         => null,
            'objects'             => [],
        ];
    }

    /**
     * Retrieves the default options for a model collection.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultObjectOptions()
    {
        return [
            'label' => '{{title}}',
            'url'   => '{{url}}',
        ];
    }

    /**
     * Retrieves the default options for a model collection.
     *
     * @return array<string, mixed>
     */
    protected function getCommonSitemapObjectOptionKeys()
    {
        return [
            'locale',
            'l10n',
            'check_active_routes',
            'relative_urls',
            'transformer',
        ];
    }

    /**
     * Build the sitemap array.
     *
     * @return array The actual sitemap.
     */
    public function build($ident = 'default')
    {
        $maps = $this->objectHierarchy();

        if (!$maps) {
            return [];
        }

        if (!isset($maps[$ident])) {
            throw new InvalidArgumentException(sprintf('Sitemap [%s]: Hierarchy not defined', $ident));
        }

        if (!isset($maps[$ident]['objects'])) {
            throw new InvalidArgumentException(sprintf('Sitemap [%s]: No objects defined', $ident));
        }

        $mapDefaults = $this->getDefaultSitemapOptions();
        $objDefaults = $this->getDefaultObjectOptions();

        $commonOptionKeys = $this->getCommonSitemapObjectOptionKeys();

        $this->buildSitemapOptions = array_merge($mapDefaults, $maps[$ident]);

        $output = [];

        foreach ($this->buildSitemapOptions['objects'] as $objType => $objOptions) {
            $objOptions = array_merge($objDefaults, $objOptions);

            foreach ($commonOptionKeys as $key) {
                if (!isset($objOptions[$key]) && isset($this->buildSitemapOptions[$key])) {
                    $objOptions[$key] = $this->buildSitemapOptions[$key];
                }
            }

            $output[] = $this->buildObject($objType, $objOptions);
        }

        $this->buildSitemapOptions = [];

        return $output;
    }

    /**
     * Build object collection from the given hierarchy.
     *
     * @param  class-string         $objType    The object type to collect.
     * @param  array<string, mixed> $objOptions The object's collection options.
     * @param  ViewableInterface    $parentObj  The object's parent object to filter by.
     * @param  int                  $level      The current depth of the sitemap hierarchy.
     * @return array Local sitemap.
     */
    protected function buildObject($objType, $objOptions, ViewableInterface $parentObj = null, $level = 0)
    {
        // If the render of a condition is false or empty, dont process the object.
        if ($parentObj && isset($objOptions['condition'])) {
            if (!$parentObj->view()->renderTemplate($objOptions['condition'], $parentObj)) {
                return [];
            }
        }

        $defaultLocale     = isset($objOptions['locale']) ? $objOptions['locale'] : $this->translator()->getLocale();
        $l10n              = isset($objOptions['l10n']) ? $objOptions['l10n'] : true;
        $checkActiveRoutes = isset($objOptions['check_active_routes']) ? $objOptions['check_active_routes'] : true;
        $relativeUrls      = isset($objOptions['relative_urls']) ? $objOptions['relative_urls'] : true;
        $transformer       = isset($objOptions['transformer']) ? $objOptions['transformer'] : null;
        $availableLocales  = $l10n ? $this->translator()->availableLocales() : [ $defaultLocale ];

        $objDefaults       = $this->getDefaultObjectOptions();
        $commonOptionKeys  = $this->getCommonSitemapObjectOptionKeys();

        $loader = $this->collectionLoader()->setModel($objType);

        if (isset($objOptions['filters'])) {
            $filters = $objOptions['filters'];
            if ($parentObj) {
                $filters = $this->renderData($parentObj, $filters, $transformer);
            }
            $loader->addFilters($filters);
        }

        if (isset($objOptions['orders'])) {
            $orders = $objOptions['orders'];
            if ($parentObj) {
                $orders = $this->renderData($parentObj, $orders, $transformer);
            }
            $loader->addOrders($orders);
        }

        $objCollection = $loader->load();

        $out = [];
        $level++;

        foreach ($availableLocales as $locale) {
            // Get opposite languages locales
            $oppositeLang = [];
            foreach ($availableLocales as $l) {
                if ($l !== $locale) {
                    $oppositeLang[] = $l;
                }
            }

            // Set the local to the current locale before looping the list.
            $this->translator()->setLocale($locale);
            foreach ($objCollection as $object) {
                // When checking active routes, do not display routes that are not active
                if ($checkActiveRoutes && $object instanceof RoutableInterface && !$object->isActiveRoute()) {
                    continue;
                }

                // Hierarchical (children, when defined)
                $children = [];
                if (!empty($objOptions['children'])) {
                    foreach ($objOptions['children'] as $childType => $childOptions) {
                        $childOptions = array_merge($objDefaults, $childOptions);

                        foreach ($commonOptionKeys as $key) {
                            if (!isset($childOptions[$key]) && isset($this->buildSitemapOptions[$key])) {
                                $childOptions[$key] = $this->buildSitemapOptions[$key];
                            }
                        }

                        $children[] = $this->buildObject($childType, $childOptions, $object, $level);
                    }
                }

                $url = trim($this->renderData($object, $objOptions['url'], $transformer));
                if (!$relativeUrls) {
                    $url = $this->withBaseUrl($url);
                }

                $tmp = [
                    'label'    => trim($this->renderData($object, $objOptions['label'], $transformer)),
                    'url'      => $url,
                    'children' => $children,
                    'data'     => isset($objOptions['data']) ? $this->renderData($object, $objOptions['data'], $transformer) : [],
                    'level'    => $level,
                    'lang'     => $locale,
                ];

                // If you need a priority, fix your own rules
                $priority = '';
                if (isset($objOptions['priority']) && $objOptions['priority']) {
                    $priority = $this->renderData($object, (string)$objOptions['priority'], $transformer);
                }
                $tmp['priority'] = $priority;

                // If you need a date of last modification, fix your own rules
                $last = '';
                if (isset($objOptions['last_modified']) && $objOptions['last_modified']) {
                    $last = $this->renderData($object, $objOptions['last_modified'], $transformer);
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

                    $url = trim($this->renderData($object, $objOptions['url'], $transformer));
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
            return $obj->view()->renderTemplate($data, $presentedObject);
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
