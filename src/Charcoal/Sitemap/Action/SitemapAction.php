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
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
              .'<urlset'
              .' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
              .' xmlns:xhtml="http://www.w3.org/1999/xhtml"'
              .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
              .' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"'
              .'/>';

        $urlsetEl = new SimpleXmlElement($xml);

        foreach ($map as $objs) {
            foreach ($objs as $obj) {
                $objUrl = ltrim($obj['url'], '/');
                if (parse_url($objUrl, PHP_URL_HOST) === null) {
                    $objUrl = $this->baseUrl.$objUrl;
                }

                if (parse_url($objUrl, PHP_URL_HOST) != parse_url($this->baseUrl, PHP_URL_HOST) &&
                    parse_url($objUrl, PHP_URL_HOST) !== null) {
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

                        if (parse_url($altUrl, PHP_URL_HOST) != parse_url($this->baseUrl, PHP_URL_HOST) &&
                            parse_url($altUrl, PHP_URL_HOST) !== null) {
                            continue;
                        }

                        $linkEl = $urlEl->addChild('xhtml:link', null, 'xhtml');
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
}
