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
     * @param  Builder $sitemapBuilder
     * @return self
     */
    public function setSitemapBuilder(Builder $sitemapBuilder)
    {
        $this->sitemapBuilder = $sitemapBuilder;
        return $this;
    }
}
