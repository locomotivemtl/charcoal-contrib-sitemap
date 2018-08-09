<?php

namespace Charcoal\Sitemap\Mixin;

use Charcoal\Sitemap\Service\Builder;

trait SitemapBuilderAwareTrait
{
    /**
     * @var Builder
     */
    protected $sitemapBuilder;

    /**
     * @return Builder
     */
    public function sitemapBuilder()
    {
        return $this->sitemapBuilder;
    }

    /**
     * @param Builder $sitemapBuilder
     * @return SitemapBuilderAwareTrait
     */
    public function setSitemapBuilder($sitemapBuilder)
    {
        $this->sitemapBuilder = $sitemapBuilder;
        return $this;
    }
}
