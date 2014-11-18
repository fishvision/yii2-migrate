Yii2 Migrate
========================

Installation
------------
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

* Either run

```
php composer.phar require --prefer-dist "fishvision/yii2-migrate" "*"
```

or add

```json
"fishvision/yii2-migrate" : "*"
```

to the require section of your application's `composer.json` file.

* Add a new controller map in `controllerMap` section of your application's configuration file, for example:

```php
'controllerMap' => [
    'migrate' => [
        'class' => 'fishvision\migrate\controllers\MigrateController',
        'autoDiscover' => true,
        'migrationPaths' => [
            '@vendor/fishvision',
        ],
    ],
],
```
Options
-------
| *Option*        | *Type*          | *Description*  |
| :-------------: |:-------------:| :------------|
| **autoDiscover**  | bool | If true, the application will recursively look for additional migrations within the directories specified by `migrationPaths` |
| **migrationPaths** | array |   An array of path aliases to recursively look under for additional migrations. |

Usage
-----
```
./yii migrate
```
***Note***: extension does not make any changes to the default migration usage. It simply alters the paths in which to look for migrations.