<?php

namespace Charcoal\Sitemap\Script;

use Charcoal\App\Script\AbstractScript;
use Charcoal\Sitemap\Service\SitemapGenerator;
use RuntimeException;
use Pimple\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GenerateSitemapScript extends AbstractScript
{
    /**
     * The sitemap builder service.
     */
    protected SitemapGenerator $sitemapGenerator;

    /**
     * The sitemap XML as a string.
     *
     * @var string|null
     */
    protected $sitemapXml;

    protected $basePath;

    /**
     * Inject dependencies from a DI Container.
     *
     * @param  Container $container A dependencies container instance.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        $this->sitemapGenerator = $container['charcoal/sitemap/generator'];
        $this->basePath = $container['config']['base_path'] ?? getcwd();

        parent::setDependencies($container);
    }

    public function run(RequestInterface $request, ResponseInterface $response)
    {
        $xml = $this->sitemapGenerator->generate();

        if (!empty($xml)) {
            $sitemapPath = rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'sitemap.xml';

            if (file_put_contents($sitemapPath, $xml, LOCK_EX) === false) {
                throw new RuntimeException(sprintf('Unable to write sitemap to "%s".', $sitemapPath));
            }
        }

        return $response;
    }
}
