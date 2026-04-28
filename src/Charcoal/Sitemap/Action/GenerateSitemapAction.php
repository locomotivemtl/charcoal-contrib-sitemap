<?php

namespace Charcoal\Sitemap\Action;

use Charcoal\App\Action\AbstractAction;
use Charcoal\Sitemap\Service\SitemapGenerator;
use Pimple\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GenerateSitemapAction
 */
class GenerateSitemapAction extends AbstractAction
{
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
        parent::setDependencies($container);

        $this->sitemapGenerator = $container['charcoal/sitemap/generator'];
        $this->basePath = $container['config']['base_path'] ?? getcwd();
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
        $this->setSuccess(false);
        $this->setMode(self::MODE_JSON);
        $xml = $this->sitemapGenerator->generate();

        if (!empty($xml)) {
            $sitemapPath = rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'sitemap.xml';
            $this->setSuccess(true);

            if (file_put_contents($sitemapPath, $xml, LOCK_EX) === false) {
                $this->setSuccess(false);
                throw new \RuntimeException(sprintf('Unable to write sitemap to "%s".', $sitemapPath));
            }
        }

        return $response;
    }

    /**
     * The XML string.
     *
     * @return string|null
     */
    public function results()
    {
        return [ 'success' => $this->success() ];
    }
}
