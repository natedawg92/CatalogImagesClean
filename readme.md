NathanDay CatalogImagesClean Extension
=====================

Facts
-----
- version: 1.0.0
- extension key: NathanDay_CatalogImagesClean
- [extension on GitHub](https://github.com/natedawg92/CatalogImagesClean)

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
composer config repositories.catalogimagesclean git git@github.com:natedawg92/CatalogImagesClean.git
composer require nathanday/module-catalog-images-clean
php bin/magento module:enable NathanDay_CatalogImagesClean
php bin/magento setup:upgrade
```

Uninstallation
--------------
```
php bin/magento module:uninstall NathanDay_CatalogImagesClean
```

Usage
-----

**Info Command**

```
php bin/magento catalog:images:info
Description:
  Information about Unused and/or Missing Images

Usage:
  catalog:images:info [options]

Options:
  -u, --unused          Info on unused product images
  -m, --missing         Info on missing product images
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

```
For Details of both Unused and Missing images you do not need to specify any particular flag, if you want information on one or the other specify which flag you require

Sample Output

```

======================================
Catalog Product Image Information
======================================
53 Unique Images in Database
802 Images in Filesystem
0 Missing Images
749 Unused Images

```

for more detailed information add `-v` to the command and this will print out a list of unused and/or missing images where there is any.
in the case of missing images the verbose flag will print a list of files and how many times that file appears in the gallery records.

**Clean Command**

```
php bin/magento catalog:images:clean

Description:
  Clean Catalog Images, Delete Unused Images in the Filesystem and/or Remove records in the Database for Missing Images

Usage:
  catalog:images:clean [options]

Options:
  -u, --unused          Delete unused product images
  -m, --missing         Remove missing product image Records
  -d, --dry-run         Dry Run, don't make any changes
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

**Important**: Before utilising this command it is recommended to take a Database and Media Backup.

The clean command will process through all actions to remove any Missing Image records or delete any unused images. As with the info command you can specify an individual subset of images to clean e.g. `--missing` will only action the clean command for missing images.

you can specify the `--dry-run` flag which will print out what actions would be taken.

The verbose flag `-v` can also be added to the command to print a list of the Images that will be updated.

When using this command without the `--dry-run` flag you will shown a recomendation to make an appropriate backup before proceeding at which point you will be asked to confirm you want to proceed. 

after this point the command will proceed to Update your database to remove any instances of missing image recors and/or Delete files from the media directory that are not associated with any product, depending on your choice of which subsets to clean.


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
(c) 2020 Nathan Day
