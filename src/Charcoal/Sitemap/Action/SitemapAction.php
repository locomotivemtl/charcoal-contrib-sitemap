<?php

namespace Charcoal\Sitemap\Action;

use Charcoal\App\Action\AbstractAction;
use Pimple\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

/**
 * Class SitemapAction
 */
class SitemapAction extends AbstractAction
{
    /**
     * @var \Psr\Http\Message\UriInterface
     */
    protected $baseUrl;

    /**
     * The sitemap XML as a string.
     *
     * @var string|null
     */
    protected $sitemapXml;

    /**
     * Map of registered XML namespaces.
     *
     * @var array<string, string>
     */
    protected $xmlNamespaces = [
        'xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
        'xhtml' => 'http://www.w3.org/1999/xhtml',
        'xsi'   => 'http://www.w3.org/2001/XMLSchema-instance',
    ];

    /**
     * Map of registered XSI namespaces.
     *
     * @var array<string, string>
     */
    protected $xsiNamespaces = [
        'schemaLocation' => 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd',
    ];

    /**
     * Inject dependencies from a DI Container.
     *
     * @param  Container $container A dependencies container instance.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->sitemapBuilder = $container['charcoal/sitemap/builder'];
        $this->baseUrl        = $container['base-url'];
    }

    /**
     * Returns an HTTP response with the sitemap XML.
     *
     * @param RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $this->setMode(self::MODE_XML);

        $collections = $this->sitemapBuilder->build('xml');
        $this->sitemapXml = $this->createXmlFromCollections($collections);

        $this->setSuccess(true);

        return $response;
    }

    /**
     * The XML string.
     *
     * @return string|null
     */
    public function results()
    {
        return $this->sitemapXml;
    }

    /**
     * Adds an alternate to a given XML element.
     *
     * @param  SimpleXMLElement     $urlElement The XML document to mutate.
     * @param  array<string, mixed> $alternate     A sitemap link.
     * @return void
     */
    protected function addAlternateToXml(SimpleXMLElement $urlElement, array $alternate)
    {
        $alternateUrl = ltrim($alternate['url'], '/');
        if (parse_url($alternateUrl, PHP_URL_HOST) === null) {
            $alternateUrl = $this->baseUrl.$alternateUrl;
        }

        if ($this->isExternalHost($alternateUrl)) {
            return;
        }

        $linkElement = $urlElement->addChild('xhtml:link', null, $this->xmlNamespaces['xhtml']);
        $linkElement->addAttribute('rel', 'alternate');
        $linkElement->addAttribute('hreflang', $alternate['lang']);
        $linkElement->addAttribute('href', $alternateUrl);
    }

    /**
     * Adds a single collection of links to a given XML element.
     *
     * @param  SimpleXMLElement           $urlsetElement The XML document to mutate.
     * @param  list<array<string, mixed>> $collection    List of sitemap locations.
     * @return void
     */
    protected function addCollectionToXml(SimpleXMLElement $urlsetElement, array $collection)
    {
        foreach ($collection as $link) {
            $this->addLinkToXml($urlsetElement, $link);
        }
    }

    /**
     * Adds a link, and any children and alternates, to a given XML element.
     *
     * @param  SimpleXMLElement     $urlsetElement The XML document to mutate.
     * @param  array<string, mixed> $link          A sitemap location.
     * @return void
     */
    protected function addLinkToXml(SimpleXMLElement $urlsetElement, array $link)
    {
        $linkUrl = ltrim($link['url'], '/');
        if (parse_url($linkUrl, PHP_URL_HOST) === null) {
            $linkUrl = $this->baseUrl.$linkUrl;
        }

        if (!$this->isExternalHost($linkUrl)) {
            $urlElement = $urlsetElement->addChild('url');
            $urlElement->addChild('loc', $linkUrl);

            if ($link['last_modified']) {
                $urlElement->addChild('lastmod', $link['last_modified']);
            }

            if ($link['priority']) {
                $urlElement->addChild('priority', $link['priority']);
            }

            if ($link['alternates']) {
                foreach ($link['alternates'] as $alternate) {
                    $this->addAlternateToXml($urlElement, $alternate);
                }
            }
        }

        if ($link['children']) {
            foreach ($link['children'] as $children) {
                $this->addCollectionToXml($urlsetElement, $children);
            }
        }
    }

    /**
     * Creates a new XML object.
     *
     * @return SimpleXmlElement
     */
    protected function createXmlEnvelope()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
              .'<urlset'
              .' xmlns="'.$this->xmlNamespaces['xmlns'].'"'
              .' xmlns:xhtml="'.$this->xmlNamespaces['xhtml'].'"'
              .' xmlns:xsi="'.$this->xmlNamespaces['xsi'].'"'
              .' xsi:schemaLocation="'.$this->xsiNamespaces['schemaLocation'].'"'
              .'/>';

        return new SimpleXmlElement($xml);
    }

    /**
     * Converts many collections of links into an XML document.
     *
     * @param  list<list<array<string, mixed>>> $collections Lists of sitemap locations.
     * @return string|null
     */
    protected function createXmlFromCollections(array $collections)
    {
        $urlsetElement = $this->createXmlEnvelope();

        foreach ($collections as $collection) {
            $this->addCollectionToXml($urlsetElement, $collection);
        }

        $xml = $urlsetElement->asXml();

        if (is_string($xml)) {
            return $xml;
        }

        return null;
    }

    /**
     * Converts a single collection of links into an XML document.
     *
     * @param  list<array<string, mixed>> $collection List of sitemap locations.
     * @return string|null
     */
    protected function createXmlFromCollection(array $collection)
    {
        $urlsetElement = $this->createXmlEnvelope();

        $this->addCollectionToXml($urlsetElement, $collection);

        $xml = $urlsetElement->asXml();

        if (is_string($xml)) {
            return $xml;
        }

        return null;
    }

    /**
     * Determines if a host is defined and matches the host of
     * the application's base URI.
     *
     * @param  string $uri The URI to test.
     * @return bool
     */
    protected function isExternalHost($uri)
    {
        $target = parse_url($uri, PHP_URL_HOST);
        $origin = parse_url($this->baseUrl, PHP_URL_HOST);

        return ($target !== null && $target !== $origin);
    }
}
