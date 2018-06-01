<?php

namespace Charcoal\Sitemap\Service;

// From 'charcoal-factory'
use Charcoal\Factory\FactoryInterface;

// From 'charcoal-core'
use Charcoal\Loader\CollectionLoader;

// From 'charcoal-object'
use Charcoal\Object\CategoryInterface;
use Charcoal\Object\HierarchicalInterface;
use Charcoal\Object\RoutableInterface;


// From 'charcoal-translator'
use Charcoal\Translator\TranslatorAwareTrait;

// From 'charcoal-view'
use Charcoal\View\ViewableInterface;

use RuntimeException;
use InvalidArgumentException;

/**
 * Sitemap builder from object hierarchy
 * Code example:
    {
        "sitemap": {
            "xml": {
                "objects": {
                    "l10n": true,
                    "boilerplate/object/section": {
                        "label": "{{title}}",
                        "url": "{{url}}", // Might be null if routable.
                        "filters": {
                            "active": {
                                "property": "active",
                                "val": true
                            }
                        },
                        "children": {
                            "boilerplate/object/an-object": {
                                "condition": "{{isAnObjectParent}}"
                            }
                        }
                    }
                }
            }
        }
    }
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
     * Object hierarchy as defined in the config.json
     *
     * @var array
     */
    private $objectHierarchy;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * Store the factory instance.
     *
     * @var FactoryInterface
     */
    protected $modelFactory;

    /**
     * Store the factory instance.
     *
     * @var FactoryInterface
     */
    private $collectionLoader;

    /**
     * Class constructor.
     *
     * @throws InvalidArgumentException When no model factory is defined.
     * @throws InvalidArgumentException When no collection loader is defined.
     * @param array $data Dependencies.
     * @return self
     */
    public function __construct(array $data)
    {
        if (!isset($data['model/factory'])) {
            throw new InvalidArgumentException('Model Factory must be defined in the SitemapBuilder Service.');
        }

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
        $this->setModelFactory($data['model/factory']);
        $this->setCollectionLoader($data['model/collection/loader']);
        $this->setTranslator($data['translator']);

        return $this;
    }

    /**
     * Website base URL
     *
     * @param string $baseUrl URL
     * @return self
     */
    protected function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * @return string Website Base URL
     */
    protected function baseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Set an model factory.
     *
     * @param  FactoryInterface $factory The factory to create models.
     * @return void
     */
    protected function setModelFactory(FactoryInterface $factory)
    {
        $this->modelFactory = $factory;
    }

    /**
     * Retrieve the model factory.
     *
     * @throws RuntimeException If the model factory is missing.
     * @return FactoryInterface
     */
    public function modelFactory()
    {
        if (!isset($this->modelFactory)) {
            throw new RuntimeException(sprintf(
                'Model Factory is not defined for [%s]',
                get_class($this)
            ));
        }

        return $this->modelFactory;
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
     * @return array Object hierarchy
     */
    protected function objectHierarchy()
    {
        return $this->objectHierarchy;
    }

    /**
     * Necessary options.
     * Most options within the objects range are renderable
     * Options outsite the 'objects' will impact objects and
     * can be overwriten for each.
     * {
     *     'l10n': true, // Setting l10n mode
     *     'objects': {
     *         'boilerplate/object':{
     *             'data': {
     *                 'id': '{{id}}',
     *                 'l10n': false // This object doesn't require l10n in that context
     *             }
     *         }
     *     }
     * }
     *
     * @return array Renderable options.
     */
    protected function defaultOptions()
    {
        return [
            'locale'  => $this->translator()->getLocale(),
            'l10n'    => true,
            'objects' => [
                'label'     => '{{title}}',
                'url'       => '{{url}}',
                'children'  => [],
                'data'      => []
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

        $opts = $h[$ident];
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
            $out[] = $this->buildObject($class, $options);
        }

        return $out;
    }

    /**
     * Build object from the given hierarchy
     *
     * @param  string             $class   Classname.
     * @param  array              $options Associated options.
     * @param  ViewableInterface  $parent  Parent object to render on.
     * @return array                       Local sitemap.
     */
    protected function buildObject($class, $options, ViewableInterface $parent=null, $level = 0)
    {
        // If the render of a condition is false or empty, dont process the object.
        if ($parent && isset($options['condition'])) {
            if (!$parent->view()->render($options['condition'], $parent)) {
                return [];
            }
        }

        $factory = $this->modelFactory();
        $obj = $factory->create($class);

        $loader = $this->collectionLoader()->setModel($obj);

        if (isset($options['filters'])) {
            $filters = $options['filters'];
            if ($parent) {
                $filters = $this->renderData($parent, $filters);
            }
            $loader->addFilters($filters);
        }

        if (isset($options['orders'])) {
            $orders = $options['orders'];
            if ($parent) {
                $orders = $this->renderData($parent, $orders);
            }
            $loader->addOrders($orders);
        }

        $category = ($obj instanceof CategoryInterface);
        $hierarchical = ($obj instanceof HierarchicalInterface);
        if ($hierarchical || $category) {
            if ($parent) {
                $loader->addFilter('master', $parent->id());
            } else {
                $loader->addFilter('master', '', ['operator' => 'IS NULL']);
            }
        }

        $list = $loader->load();

        $out = [];
        $children = isset($options['children']) ? $options['children'] : [];
        $level++;

        $l10n = $options['l10n'];
        $locale = $options['locale'];

        $availableLocales = $l10n ? $this->translator()->availableLocales() : [ $locale ];

        foreach ($availableLocales as $locale) {

            $currentLocale = $locale;
            $oppositeLang = [];
            foreach ($availableLocales as $l) {
                if ($l == $locale) {
                    continue;
                }
                $oppositeLang[] = $l;
            }

            $this->translator()->setLocale($locale);
            foreach ($list as $object) {
                $cs = [];
                if (!empty($children)) {
                    foreach($children as $cname => $opts) {
                        $opts = array_merge($this->defaultOptions(), $opts);
                        $cs[] = $this->buildObject($cname, $opts, $object, $level);
                    }
                }

                $tmp = [
                    'label' => trim($this->renderData($object, $options['label'])),
                    'url' => trim($this->renderData($object, '{{#withBaseUrl}}'.$options['url']. '{{/withBaseUrl}}')),
                    'children' => $cs,
                    'data' => $this->renderData($object, $options['data']),
                    'level' => $level,
                    'lang' => $locale
                ];

                $priority = '';
                if (isset($options['priority']) && $options['priority']) {
                    $priority = $this->renderData($object, (string)$options['priority']);
                }
                $tmp['priority'] = $priority;

                $last = '';
                if (isset($options['last_modified']) && $options['last_modified']) {
                    $last = $this->renderData($object, $options['last_modified']);
                }
                $tmp['last_modified'] = $last;

                $alternates = [];
                foreach ($oppositeLang as $ol) {
                    $this->translator()->setLocale($ol);
                    $alternates[] = [
                        'url' => trim($this->renderData($object, '{{#withBaseUrl}}'.$options['url']. '{{/withBaseUrl}}')),
                        'lang' => $ol
                    ];
                }

                $tmp['alternates'] = $alternates;

                $this->translator()->setLocale($currentLocale);
                $out[] = $tmp;
            }
        }


        return $out;
    }

    /**
     * Recursive data renderer
     *
     * @param  ViewableInterface $obj  Object to render on.
     * @param  mixed $data             Pretty much anything to be rendered
     * @return mixed                   Rendered data.
     */
    protected function renderData(ViewableInterface $obj, $data)
    {
        if (is_scalar($data)) {
            return $obj->view()->render($data, $obj);
        }

        if (is_array($data)) {
            $out = [];
            foreach ($data as $key => $content) {
                $out[$key] = $this->renderData($obj, $content);
            }
            return $out;
        }
    }

}
