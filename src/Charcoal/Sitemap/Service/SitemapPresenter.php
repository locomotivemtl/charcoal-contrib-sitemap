<?php

namespace Charcoal\Sitemap\Service;

use ArrayAccess;
use Charcoal\Cache\Facade\CachePoolFacade;
use Charcoal\Factory\FactoryInterface;
use Charcoal\Translator\TranslatorAwareTrait;
use InvalidArgumentException;
use Traversable;

/**
 * Presenter provides a presentation and transformation layer for a "model".
 *
 * It transforms (serializes) any data model (objects or array) into a presentation array, according to a
 * **transformer**.
 *
 * A **transformer** defines the morph rules
 *
 * - A simple array or Traversable object, contain
 */
class SitemapPresenter
{
    use TranslatorAwareTrait;

    /**
     * @var FactoryInterface $transformer
     */
    private $transformerFactory;

    /**
     * @var string $getterPattern
     */
    private $getterPattern;

    /**
     * @var CachePoolFacade
     */
    protected $cacheFacade;

    /**
     * @param array|Traversable|callable $transformer   The data-view transformation array (or Traversable) object.
     * @param string                     $getterPattern The string pattern to match string with. Must have a single
     *                                                  catch-block.
     */
    public function __construct($transformerFactory, $cacheFacade, $translator, $getterPattern = '~{{(\w*?)}}~')
    {
        $this->setCacheFacade($cacheFacade);
        $this->setTransformerFactory($transformerFactory);
        $this->setTranslator($translator);
        $this->getterPattern = $getterPattern;
    }

    /**
     * @param  mixed       $obj         The model or value object.
     * @param  string|null $transformer The specific transformer to use.
     * @return array Normalized data, suitable as presentation (view) layer
     */
    public function transform($obj, $transformer = null)
    {
        if (!is_object($transformer)) {
            $transformer = $this->getTransformerFactory()->create($transformer ?? $obj->objType());
        }

        $key = sprintf(
            '%s_%s_%s_%s',
            get_class($transformer),
            $obj->objType(),
            $obj->id(),
            $this->translator()->getLocale(),
        );

        $that = $this;

        return $this->getCacheFacade()->get($key,
            function () use ($obj, $transformer, $that) {
                return $that->transmogrify($obj, $transformer($obj));
            });
    }

    /**
     * Transmogrify an object into an other structure.
     *
     * @param mixed $obj Source object.
     * @param mixed $val Modifier.
     * @throws InvalidArgumentException If the modifier is not callable, traversable (array) or string.
     * @return mixed The transformed data (type depends on modifier).
     */
    private function transmogrify($obj, $val)
    {
        // Callbacks (lambda or callable) are supported. They must accept the source object as argument.
        if (!is_string($val) && is_callable($val)) {
            return $val($obj);
        }

        // Arrays or traversables are handled recursively.
        // This also converts / casts any Traversable into a simple array.
        if (is_array($val) || $val instanceof Traversable) {
            $data = [];
            foreach ($val as $k => $v) {
                if (!is_string($k)) {
                    if (is_string($v)) {
                        $data[$v] = $this->objectGet($obj, $v);
                    } else {
                        $data[] = $v;
                    }
                } else {
                    $data[$k] = $this->transmogrify($obj, $v);
                }
            }
            return $data;
        }

        // Strings are handled by rendering {{property}}  with dynamic object getter pattern.
        if (is_string($val)) {
            return preg_replace_callback($this->getterPattern, function (array $matches) use ($obj) {
                return $this->objectGet($obj, $matches[1]);
            }, $val);
        }

        if (is_numeric($val)) {
            return $val;
        }

        if (is_bool($val)) {
            return !!$val;
        }

        if ($val === null) {
            return null;
        }

        // Any other
        throw new InvalidArgumentException(
            sprintf(
                'Presenter\'s transmogrify val needs to be callable, traversable (array) or a string. "%s" given.',
                gettype($val)
            )
        );
    }

    /**
     * General-purpose dynamic object "getter".
     *
     * This method tries to fetch a "property" from any type of object (or array),
     * trying to figure out the best possible way:
     *
     * - Method call (`$obj->property()`)
     * - Public property get (`$obj->property`)
     * - Array access, if available (`$obj[property]`)
     * - Returns the property unchanged, otherwise
     *
     * @param mixed  $obj          The model (object or array) to retrieve the property's value from.
     * @param string $propertyName The property name (key) to retrieve from model.
     * @throws InvalidArgumentException If the property name is not a string.
     * @return mixed The object property, if available. The property name, unchanged, if it's not available.
     */
    private function objectGet($obj, $propertyName)
    {
        if (is_callable([$obj, $propertyName])) {
            return $obj->{$propertyName}();
        }

        if (isset($obj->{$propertyName})) {
            return $obj->{$propertyName};
        }

        if (is_string($propertyName) && (is_array($obj) || $obj instanceof ArrayAccess) && (isset($obj[$propertyName]))) {
            return $obj[$propertyName];
        }

        return null;
    }

    /**
     * @return FactoryInterface
     */
    protected function getTransformerFactory()
    {
        return $this->transformerFactory;
    }

    /**
     * @param FactoryInterface $transformerFactory
     * @return Presenter
     */
    public function setTransformerFactory(FactoryInterface $transformerFactory)
    {
        $this->transformerFactory = $transformerFactory;
        return $this;
    }

    /**
     * @return mixed
     */
    protected function getCacheFacade()
    {
        return $this->cacheFacade;
    }

    /**
     * @param mixed $cacheFacade
     * @return Presenter
     */
    protected function setCacheFacade($cacheFacade)
    {
        $this->cacheFacade = $cacheFacade;
        return $this;
    }
}
