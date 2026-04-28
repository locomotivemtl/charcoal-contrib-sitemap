<?php

namespace Charcoal\Sitemap\Service;

use Pimple\Container;
use SimpleXmlElement;
use Slim\Http\Uri;

/**
 * The Sitemap Generator service.
 * Generates a sitemap XML document from a collection of links.
 * Uses the hardcoded "xml" ident to match the sitemap builder configuration.
 *
 */
class SitemapGenerator
{
    protected Builder $sitemapBuilder;
    protected ?Uri $baseUrl = null;
    protected ?string $sitemapXml = null;

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
     * @param Builder $sitemapBuilder
     */
    public function __construct(Container $dependencies)
    {
        $sitemapBuilder = $dependencies['charcoal/sitemap/builder'] ?? null;

        if (!$sitemapBuilder) {
            throw new \InvalidArgumentException('A sitemap builder is required to generate the sitemap.');
        }

        $this->baseUrl = $dependencies['base-url'] ?? null;

        $this->setSitemapBuilder($sitemapBuilder);
    }

    /**
     * Set the sitemap builder.
     * @param Builder $sitemapBuilder The sitemap builder to use for generating the sitemap.
     * @return void
     */
    protected function setSitemapBuilder(Builder $sitemapBuilder): void
    {
        $this->sitemapBuilder = $sitemapBuilder;
    }

    /**
     * Generates the sitemap XML.
     * Returns the XML as a string, or null if the sitemap could not be generated.
     */
    public function generate(): ?string
    {
        $collections = $this->sitemapBuilder->build('xml');

        return $this->createXmlFromCollections($collections);
    }

    /**
     * Adds an alternate to a given XML element.
     *
     * @param  SimpleXMLElement     $urlElement The XML document to mutate.
     * @param  array<string, mixed> $alternate     A sitemap link.
     * @return void
     */
    protected function addAlternateToXml(SimpleXMLElement $urlElement, array $alternate): void
    {
        $alternateUrl = ltrim($alternate['url'], '/');
        if (parse_url($alternateUrl, PHP_URL_HOST) === null) {
            $alternateUrl = $this->baseUrl . $alternateUrl;
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
    protected function addCollectionToXml(SimpleXMLElement $urlsetElement, array $collection): void
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
    protected function addLinkToXml(SimpleXMLElement $urlsetElement, array $link): void
    {
        $linkUrl = ltrim($link['url'], '/');
        if (parse_url($linkUrl, PHP_URL_HOST) === null) {
            $linkUrl = $this->baseUrl . $linkUrl;
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
    protected function createXmlEnvelope(): SimpleXmlElement
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset';
        $xml .= ' xmlns="' . $this->xmlNamespaces['xmlns'] . '"';
        $xml .= ' xmlns:xhtml="' . $this->xmlNamespaces['xhtml'] . '"';
        $xml .= ' xmlns:xsi="' . $this->xmlNamespaces['xsi'] . '"';
        $xml .= ' xsi:schemaLocation="' . $this->xsiNamespaces['schemaLocation'] . '"';
        $xml .= '/>';

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
    protected function createXmlFromCollection(array $collection): ?string
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
    protected function isExternalHost(string $uri): bool
    {
        $target = parse_url($uri, PHP_URL_HOST);
        $origin = parse_url($this->baseUrl, PHP_URL_HOST);

        return ($target !== null && $target !== $origin);
    }
}
