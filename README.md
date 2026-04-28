Charcoal Sitemap
===============

[![License][badge-license]][charcoal-contrib-sitemap]
[![Latest Stable Version][badge-version]][charcoal-contrib-sitemap]

A [Charcoal][charcoal-app] service for generating a sitemap.



## Table of Contents

-   [Installation](#installation)
    -   [Dependencies](#dependencies)
-   [Service Provider](#service-provider)
    -   [Services](#services)
-   [Configuration](#configuration)
-   [Usage](#usage)
    -   [Using the builder](#using-the-builder)
    -   [Sitemap.xml](#sitemap.xml)
-   [Credits](#credits)
-   [License](#license)



## Installation

The preferred (and only supported) method is with Composer:

```shell
$ composer require locomotivemtl/charcoal-contrib-sitemap
```



### Dependencies

#### Required

-   **[PHP 7.4+](https://php.net)**
-   **[locomotivemtl/charcoal-app][charcoal-app]**: ^0.8
-   **[locomotivemtl/charcoal-core][charcoal-core]**: ^0.3
-   **[locomotivemtl/charcoal-factory][charcoal-factory]**: ^0.4
-   **[locomotivemtl/charcoal-object][charcoal-object]**: ^0.4
-   **[locomotivemtl/charcoal-translator][charcoal-translator]**: ^0.3



## Service Provider

### Services

- **charcoal/sitemap/builder** Instance of `Charcoal\Sitemap\Service\Builder`.
  Used to generate collections of links from the configured models.



## Configuration

The Sitemap can be configured from the application configset under the
`sitemap` key. You can setup which objects to be included and available
translations (l10n).

Most options are renderable by objects using your application's chosen
template syntax (Mustache used in examples below).

### Default Options

```jsonc
{
    /**
     * The service's configuration point.
     */
    "sitemap": {
        /**
         * One or more groups to customize how objects should be processed.
         *
         * The array key is an arbitrary identifier for the grouping of models.
         */
        "<group-name>": {
            /**
             * Whether or not to include links to translations.
             *
             * - `true` — Multilingual. Include all translations
             *   (see `locales.languages`).
             * - `false` — Unilingual. Include only the default language
             *   (see `locales.default_language`).
             */
            "l10n": false,
            /**
             * The language to include a link to if group is unilingual.
             *
             * If `l10n` is `true`, this option is ignored.
             *
             * Defaults to the application's current language.
             */
            "locale": "<current-language>",
            /**
             * Whether or not to check if the routable object
             * has an active route (`RoutableInterface#isActiveRoute()`)
             *
             * - `true` — Include only routable objects with active routes.
             * - `false` — Ignore if a routable object's route is active.
             */
            "check_active_routes": false,
            /**
             * Whether or not to prepend relative URIs with
             * the application's base URI (see `base_url`).
             *
             * - `true` — Use only the object's URI (see `sitemap.*.objects.*.url`).
             * - `false` — Prepend the base URI if object's URI is relative.
             */
            "relative_urls": false,
            /**
             * The transformer to parse each model included in `objects`.
             *
             * Either a PHP FQCN or snake-case equivalent.
             */
            "transformer": "<class-string>",
            /**
             * Map of models to include in the sitemap.
             */
            "objects": {
                /**
                 * One or more models to customize and include in the sitemap.
                 *
                 * The array key must be the model's object type,
                 * like `app/model/foo-bar`, or fully-qualified name (FQN),
                 * like `App\Model\FooBar`.
                 */
                "<object-type>": {
                    /**
                     * The transformer to parse the object.
                     *
                     * Either a PHP FQCN or snake-case equivalent.
                     */
                    "transformer": "<class-string>",
                    /**
                     * The URI of the object for the `<loc>` element.
                     */
                    "url": "{{ url }}",
                    /**
                     * The name of the object. Can be used in a
                     * custom sitemap builder or XML generator.
                     */
                    "label": "{{ title }}",
                    /**
                     * Map of arbitrary object data that can be used
                     * in a custom sitemap builder or XML generator.
                     */
                    "data": {},
                    /**
                     * List or map of collection filters of which objects
                     * to include in the sitemap.
                     *
                     * ```json
                     * "<filter-name>": {
                     *     "property": "active",
                     *     "value": true
                     * }
                     * ```
                     */
                    "filters": [],
                    /**
                     * List or map of collection orders to sort the objects
                     * in the sitemap.
                     *
                     * ```json
                     * "<order-name>": {
                     *     "property": "position",
                     *     "direction": "ASC"
                     * }
                     * ```
                     */
                    "orders": [],
                    /**
                     * Map of models to include in the sitemap
                     * below this model.
                     *
                     * Practical to group related models.
                     */
                    "children": {
                        /**
                         * One or more models to customize and include in the sitemap.
                         */
                        "<object-type>": {
                            /**
                             * A constraint on the parent object to determine
                             * if the child model's objects should be included
                             * in the sitemap.
                             */
                            "condition": null
                        }
                    }
                }
            }
        }
    }
}
```

Each model can override the following options of their group:
`l10n`, `locale`, `check_active_routes`, `relative_urls`.


### Example

The example below, identified as `footer_sitemap`, is marked as multilingual
using the `l10n` option which will include all translations.

```json
{
    "sitemap": {
        "footer_sitemap": {
            "l10n": true,
            "check_active_routes": true,
            "relative_urls": false,
            "transformer": "charcoal/sitemap/transformer/routable",
            "objects": {
                "app/object/section": {
                    "transformer": "\\App\\Transformer\\Sitemap\\Section",
                    "label": "{{ title }}",
                    "url": "{{ url }}",
                    "filters": {
                        "active": {
                            "property": "active",
                            "value": true
                        }
                    },
                    "data": {
                        "id": "{{ id }}",
                        "metaTitle": "{{ metaTitle }}"
                    },
                    "children": {
                        "app/object/section-children": {
                            "condition": "{{ isAnObjectParent }}"
                        }
                    }
                }
            }
        }
    }
}
```



## Usage

### Using the builder

The builder returns only an array. You need to make your own conversation if you need
another format.

The Sitemap module will include all necessary service providers and set the route (sitemap.xml) directly. Include the module:

```json
"modules": {
    "charcoal/sitemap/sitemap": {}
}
```

Also add the necessary JS to allow sitemap generation from the back-end interface:

```json
    "assets": {
        "collections": {
            "js": {
                "files": [
                    "vendor/locomotivemtl/charcoal-contrib-sitemap/assets/scripts/contrib-sitemap.js"
                ]
            }
        }
    }
```

Given the settings above:

```php
$builder = $container['charcoal/sitemap/builder'];
$sitemap = $builder->build('footer_sitemap'); // footer_sitemap is the ident of the settings you want.
```
You can also use the `SitemapBuilderAwareTrait`, which includes the setter and getter for the sitemap builder, in order
to use it with minimal code in every necessary class.



### Sitemap.xml

This contrib provides a route for `sitemap.xml` that dynamically loads the `xml` config and outputs it 
as an XML for crawlers to read.

You can generate a `sitemap.xml` file with the script `vendor/bin/charcoal sitemap/generate` and the admin action `admin/sitemap/generate`. It is recommanded to add a cron job to regenerate the file to avoid outdated sitemap.xml file.



## Credits

-   [Locomotive](https://locomotive.ca/)



## License

Charcoal is licensed under the MIT license. See [LICENSE](LICENSE) for details.



[charcoal-contrib-sitemap]:  https://packagist.org/packages/locomotivemtl/charcoal-contrib-sitemap
[charcoal-app]:              https://packagist.org/packages/locomotivemtl/charcoal-app
[charcoal-core]:             https://packagist.org/packages/locomotivemtl/charcoal-core
[charcoal-factory]:          https://packagist.org/packages/locomotivemtl/charcoal-factory
[charcoal-object]:           https://packagist.org/packages/locomotivemtl/charcoal-object
[charcoal-translator]:       https://packagist.org/packages/locomotivemtl/charcoal-translator

[badge-license]:      https://img.shields.io/packagist/l/locomotivemtl/charcoal-contrib-sitemap.svg?style=flat-square
[badge-version]:      https://img.shields.io/packagist/v/locomotivemtl/charcoal-contrib-sitemap.svg?style=flat-square
[badge-scrutinizer]:  https://img.shields.io/scrutinizer/g/locomotivemtl/charcoal-contrib-sitemap.svg?style=flat-square
[badge-coveralls]:    https://img.shields.io/coveralls/locomotivemtl/charcoal-contrib-sitemap.svg?style=flat-square
[badge-travis]:       https://img.shields.io/travis/com/locomotivemtl/charcoal-contrib-sitemap.svg?style=flat-square

[psr-1]:  https://www.php-fig.org/psr/psr-1/
[psr-2]:  https://www.php-fig.org/psr/psr-2/
[psr-3]:  https://www.php-fig.org/psr/psr-3/
[psr-4]:  https://www.php-fig.org/psr/psr-4/
[psr-6]:  https://www.php-fig.org/psr/psr-6/
[psr-7]:  https://www.php-fig.org/psr/psr-7/
[psr-11]: https://www.php-fig.org/psr/psr-11/
