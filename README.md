# Library for the CILogon Service

[![License](https://img.shields.io/badge/license-NCSA-brightgreen.svg)](https://github.com/cilogon/service-lib/master/LICENSE)
[![Scrutinizer](https://img.shields.io/scrutinizer/g/cilogon/service-lib/master.svg)](https://scrutinizer-ci.com/g/cilogon/service-lib/)

This package contains the library used by the [CILogon Service](https://github.com/cilogon/service).

This package is compliant with [PSR-1][], [PSR-4][], and [PSR-12][]. If you notice compliance oversights, please send
a patch via pull request.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md
[PSR-12]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-12-extended-coding-style-guide.md

## Requirements

The following versions of PHP are supported.

* PHP 8.0
* PHP 8.1
* PHP 8.2
* PHP 8.3

## Installation

To install, use composer:

```
composer require cilogon/service-lib
```

## Mulit-language (i18n) Support

All text output to the user is wrapped by a `gettext()` ( shorthand `_()` )
function call. [gettext](https://www.gnu.org/software/gettext/) enables the
text to be displayed in multiple languages by setting the
[locale](https://en.wikipedia.org/wiki/Locale_(computer_software)) for the
program at runtime. While this is great for the user, it means that the
programmer must update translation files anytime a text string in the code is
changed or added. This is handled by the
[gettext\_php\_to\_po.php](https://github.com/cilogon/service/blob/main/gettext_php_to_po.php)
script. See [Internationalization Support](https://github.com/cilogon/service#internationalization-support) for more information.

## License

The University of Illinois/NCSA Open Source License (NCSA). Please see [License File](https://github.com/cilogon/service-lib/blob/master/LICENSE) for more information.
