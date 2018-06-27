Charcoal Sitemap
===============

[![License][badge-license]][charcoal-contrib-sitemap]
[![Latest Stable Version][badge-version]][charcoal-contrib-sitemap]
[![Code Quality][badge-scrutinizer]][dev-scrutinizer]
[![Coverage Status][badge-coveralls]][dev-coveralls]
[![Build Status][badge-travis]][dev-travis]

A [Charcoal][charcoal-app] set of tools to deal with sitemap.



## Table of Contents

-   [Installation](#installation)
    -   [Dependencies](#dependencies)
-   [Service Provider](#service-provider)
    -   [Parameters](#parameters)
    -   [Services](#services)
-   [Configuration](#configuration)
-   [Usage](#usage)
    -   [Using the builder](#using-the-builder)
    -   [Sitemap.xml](#sitemap.xml)
-   [Development](#development)
    -  [Development Dependencies](#development-dependencies)
    -  [Coding Style](#coding-style)
-   [Credits](#credits)
-   [License](#license)



## Installation

The preferred (and only supported) method is with Composer:

```shell
$ composer require locomotivemtl/charcoal-contrib-sitemap
```

### Dependencies
- [Charcoal-factory][charcoal-app] ~0.8
- [Charcoal-factory][charcoal-factory] ~0.4
- [Charcoal-object][charcoal-object] ~0.4
- [Charcoal-translator][charcoal-translator] ~0.3

#### Required

-   [**PHP 5.6+**](https://php.net): _PHP 7_ is recommended.


## Service Provider

The following services are provided with the use of [charcoal-contrib-sitemap][charcoal-contrib-sitemap]

### Services

- **charcoal/sitemap/builder** instance of `Sitemap\Builder`

## Configuration

Configuration are set in the config file of the project under the `sitemap` key.
You can setup objects to be displayed in multiple languages (l10n) or not. Most
properties are renderable by objects. Let's take the example below:

```json
    {
        "sitemap": {
            "footer_sitemap": {
                "l10n": true,
                "check_active_routes": true,
                "objects": {
                    "boilerplate/object/section": {
                        "label": "{{title}}",
                        "url": "{{url}}",
                        "filters": {
                            "active": {
                                "property": "active",
                                "val": true
                            }
                        },
                        "data": {
                            "id": "{{id}}",
                            "metaTitle": "{{metaTitle}}"
                        },
                        "children": {
                            "boilerplate/object/section-children": {
                                "condition": "{{isAnObjectParent}}"
                            }
                        }
                    }
                }
            }
        }
    }
```
The `footer_sitemap` sitemap is defined to be `l10n` (will output all languages as alternates) and defines
the `boilerplate/object/section` object to create the list. Note that is the object is of RoutableInterface,
it will automatically test the `isActiveRoute` condition. You can disable the option by setting `check_active_routes`
to `false` on the list. The section object has `children`, in that case `boilerplate/object/section-children`, 
which will be output under the `boilerplate/object/section` on the condition `isAnObjectParent` called on the parent.

## Usage

### Using the builder

The builder returns only an array. You need to make your own conversation if you need
another format.

Include the service provider:

```json
"charcoal/sitemap/service-provider/sitemap": {}
```

Given the settings above:

```php
$builder = $container['charcoal/sitemap/builder'];
$sitemap = $builder->build('footer_sitemap'); // footer_sitemap is the ident of the settings you want.
```

### Sitemap.xml
This contrib provides a route for `sitemap.xml` that dynamically loads the `xml` config and outputs it 
as an XML for crawlers to read.

```php
// Config.php
// [...]

// Import routes
$this->addFile(__DIR__ . '/routes.json');
$this->addFile(__DIR__.'/../vendor/locomotivemtl/charcoal-contrib-sitemap/config/routes.json');

// [...]
```

## Development

To install the development environment:

```shell
$ composer install
```

To run the scripts (phplint, phpcs, and phpunit):

```shell
$ composer test
```

### Development Dependencies

-   [php-coveralls/php-coveralls][phpcov]
-   [phpunit/phpunit][phpunit]
-   [squizlabs/php_codesniffer][phpcs]



### Coding Style

The charcoal-contrib-sitemap module follows the Charcoal coding-style:

-   [_PSR-1_][psr-1]
-   [_PSR-2_][psr-2]
-   [_PSR-4_][psr-4], autoloading is therefore provided by _Composer_.
-   [_phpDocumentor_](http://phpdoc.org/) comments.
-   [phpcs.xml.dist](phpcs.xml.dist) and [.editorconfig](.editorconfig) for coding standards.

> Coding style validation / enforcement can be performed with `composer phpcs`. An auto-fixer is also available with `composer phpcbf`.

## Credits

-   [Locomotive](https://locomotive.ca/)

## License

Charcoal is licensed under the MIT license. See [LICENSE](LICENSE) for details.


[charcoal-contrib-sitemap]:  https://packagist.org/packages/locomotivemtl/charcoal-contrib-sitemap
[charcoal-app]:              https://packagist.org/packages/locomotivemtl/charcoal-app
[charcoal-factory]:          https://packagist.org/packages/locomotivemtl/charcoal-factory
[charcoal-object]:           https://packagist.org/packages/locomotivemtl/charcoal-object
[charcoal-translator]:       https://packagist.org/packages/locomotivemtl/charcoal-translator
[charcoal-view]:             https://packagist.org/packages/locomotivemtl/charcoal-view

[dev-scrutinizer]:    https://scrutinizer-ci.com/g/locomotivemtl/charcoal-contrib-sitemap/
[dev-coveralls]:      https://coveralls.io/r/locomotivemtl/charcoal-contrib-sitemap
[dev-travis]:         https://travis-ci.org/locomotivemtl/charcoal-contrib-sitemap

[badge-license]:      https://img.shields.io/packagist/l/locomotivemtl/charcoal-contrib-sitemap.svg?style=flat-square
[badge-version]:      https://img.shields.io/packagist/v/locomotivemtl/charcoal-contrib-sitemap.svg?style=flat-square
[badge-scrutinizer]:  https://img.shields.io/scrutinizer/g/locomotivemtl/charcoal-contrib-sitemap.svg?style=flat-square
[badge-coveralls]:    https://img.shields.io/coveralls/locomotivemtl/charcoal-contrib-sitemap.svg?style=flat-square
[badge-travis]:       https://img.shields.io/travis/locomotivemtl/charcoal-contrib-sitemap.svg?style=flat-square

[psr-1]:  https://www.php-fig.org/psr/psr-1/
[psr-2]:  https://www.php-fig.org/psr/psr-2/
[psr-3]:  https://www.php-fig.org/psr/psr-3/
[psr-4]:  https://www.php-fig.org/psr/psr-4/
[psr-6]:  https://www.php-fig.org/psr/psr-6/
[psr-7]:  https://www.php-fig.org/psr/psr-7/
[psr-11]: https://www.php-fig.org/psr/psr-11/
