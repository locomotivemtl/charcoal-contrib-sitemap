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

        $sitemapLinks = $this->sitemapBuilder->build('xml');
        $this->sitemapXml = $this->toXml($sitemapLinks);

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
     * Convert the collection of links into an XML document.
     *
     * @param  array $map The collection of links.
     * @return string|null
     */
    protected function toXml($map)
    {
        $xmlNs = [
            'xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
            'xhtml' => 'http://www.w3.org/1999/xhtml',
            'xsi'   => 'http://www.w3.org/2001/XMLSchema-instance',
        ];

        $xsiNs = [
            'schemaLocation' => 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd',
        ];

        $xhtmlNs = 'http://www.w3.org/1999/xhtml';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
              .'<urlset'
              .' xmlns="'.$xmlNs['xmlns'].'"'
              .' xmlns:xhtml="'.$xmlNs['xhtml'].'"'
              .' xmlns:xsi="'.$xmlNs['xsi'].'"'
              .' xsi:schemaLocation="'.$xsiNs['schemaLocation'].'"'
              .'/>';

        $urlsetEl = new SimpleXmlElement($xml);

        foreach ($map as $objs) {
            foreach ($objs as $obj) {
                $objUrl = ltrim($obj['url'], '/');
                if (parse_url($objUrl, PHP_URL_HOST) === null) {
                    $objUrl = $this->baseUrl.$objUrl;
                }

                if ($this->isExternalHost($objUrl)) {
                    continue;
                }

                $urlEl = $urlsetEl->addChild('url');
                $urlEl->addChild('loc', $objUrl);

                if ($obj['last_modified']) {
                    $urlEl->addChild('lastmod', $obj['last_modified']);
                }

                if ($obj['priority']) {
                    $urlEl->addChild('priority', $obj['priority']);
                }

                if ($obj['alternates']) {
                    foreach ($obj['alternates'] as $alt) {
                        $altUrl = ltrim($alt['url'], '/');
                        if (parse_url($altUrl, PHP_URL_HOST) === null) {
                            $altUrl = $this->baseUrl.$altUrl;
                        }

                        if ($this->isExternalHost($altUrl)) {
                            continue;
                        }

                        $linkEl = $urlEl->addChild('xhtml:link', null, $xmlNs['xhtml']);
                        $linkEl->addAttribute('rel', 'alternate');
                        $linkEl->addAttribute('hreflang', $alt['lang']);
                        $linkEl->addAttribute('href', $altUrl);
                    }
                }
            }
        }

        $xml = $urlsetEl->asXml();

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
