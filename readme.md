Magic42 CatalogImagesClean Extension
=====================

Facts
-----
- version: 1.0.0
- extension key: Magic42_CatalogImagesClean
- [extension on GitHub](https://github.com/natedawg92/CatalogImageClean)

Description
-----------
Magento 2 Module to Clean Catalog Images able to Remove Unused Images from the file system and missing images from the database.

Requirements
------------
- PHP >= 7.0.0
- Mage_Core

Compatibility
-------------
- Magento >= 2.0

Installation Instructions
-------------------------
```
composer config repositories.catalogimagesclean git git@github.com:natedawg92/CatalogImageClean.git
composer require nathanday/module-catalog-image-clean
php bin/magento module:enable NathanDay_CatalogImageClean
php bin/magento setup:upgrade
```

Uninstallation
--------------
```
php bin/magento module:uninstall NathanDay_CatalogImageClean
```

Support
-------
If you have any issues with this extension, open an issue on [GitHub](https://github.com/natedawg92/CatalogImageClean/issues).

Contribution
------------
Any contribution is highly appreciated. The best way to contribute code is to open a [pull request on GitHub](https://help.github.com/articles/using-pull-requests).

Developer
---------
[Nathan Day](mailto:nathanday92@gmail.com)

Licence
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

Copyright
---------
(c) 2019 Magic42
