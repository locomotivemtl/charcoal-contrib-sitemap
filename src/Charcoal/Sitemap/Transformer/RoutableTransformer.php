<?php

namespace Charcoal\Sitemap\Transformer;

use Charcoal\Model\ModelInterface;

class RoutableTransformer
{
    /**
     * @param ModelInterface $model
     * @return array
     */
    public function __invoke(ModelInterface $model)
    {
        return [
            'url' => (string)$model->url(),
            'title' => (string)$model['title']
        ];
    }
}
