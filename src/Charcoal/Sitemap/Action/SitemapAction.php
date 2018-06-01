<?php

namespace Charcoal\Sitemap\Action;

use SimpleXMLElement;

use Pimple\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use Charcoal\App\Action\AbstractAction;
use Charcoal\Translator\TranslatorAwareTrait;

/**
 * Class SitemapAction
 */
class SitemapAction extends AbstractAction
{
    use TranslatorAwareTrait;

    /**
     * @var string
     */
    protected $baseUrl;

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
        $this->baseUrl = $container['base-url'];
    }

    /**
     * Gets a psr7 request and response and returns a response.
     *
     * Called from `__invoke()` as the first thing.
     *
     * @param RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $this->setMode(self::MODE_XML);

        $sitemap = $this->sitemapBuilder->build('xml');
        $this->xml = $this->toXml($sitemap);

        $this->setSuccess(true);

        return $response;
    }

    protected function toXml($map)
    {
        $str = '<?xml version="1.0" encoding="UTF-8"?><urlset '
              .'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
              .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
              .'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"/>';

        $xml = new SimpleXmlElement($str);

        foreach ($map as $m) {
            foreach ($m as $obj) {

                $currentUrl = ltrim($obj['url'], '/');
                if (parse_url($currentUrl, PHP_URL_HOST) === null) {
                    $currentUrl = $this->baseUrl . $currentUrl;
                }

                if (parse_url($currentUrl, PHP_URL_HOST) != parse_url($this->baseUrl, PHP_URL_HOST) &&
                    parse_url($currentUrl, PHP_URL_HOST) !== null) {
                    continue;
                }

                $url = $xml->addChild('url');
                $url->addChild('loc', $currentUrl);
                if ($obj['last_modified']) {
                    $url->addChild('lastmod', $obj['last_modified']);
                }

                if ($obj['priority']) {
                    $url->addChild('priority', $obj['priority']);
                }

                if ($obj['alternates']) {
                    foreach ($obj['alternates'] as $alt) {

                        $altUrl = ltrim($alt['url'], '/');
                        if (parse_url($altUrl, PHP_URL_HOST) === null) {
                            $altUrl = $this->baseUrl . $altUrl;
                        }

                        if (parse_url($altUrl, PHP_URL_HOST) != parse_url($this->baseUrl, PHP_URL_HOST) &&
                            parse_url($altUrl, PHP_URL_HOST) !== null) {
                            continue;
                        }
                        $xhtml = $url->addChild('xhtml:link', null,'xhtml');
                        $xhtml->addAttribute('rel', 'alternate');
                        $xhtml->addAttribute('hreflang', $alt['lang']);
                        $xhtml->addAttribute('href', $altUrl);
                        unset($xhtml);
                    }
                }
            }
        }

        return $xml->asXml();
    }

    public function results()
    {
        return $this->xml;
    }

}
