Yii2 Migrate v1.0
========================
Extended database migration support for Yii2.

Description
-----------
By default (as of `2.0.1`), Yii only looks in a single directory to apply/revert database migrations. You are able to override the directory to search in via the `--migrationPath` option, however, this is only useful in applying/reverting a small subset of migrations, and not an entire system as a whole. 

####Use case
A large system may contain a significant amount of individually maintained extensions/modules. If these individual packages interact with the database in any way, they may each contain their own set of database migrations. Moreover, different extensions/modules may require as dependencies additional packages, and the migrations for these packages need to be carefully applied in the order they were created. Currently, this would have to be completed manually by the developer, as Yii's native migration support can only look in one directory at a time. 

This extension solves that problem, by allowing you to specify a range of directories in which the migration command should iterate through to attempt to locate migrations. In that way, systems with migrations in 100+ directories can be easily maintained by simply registering that directory.

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
| Option        | Type          | Description  |
| :-------------: |:-------------:| :------------|
| `autoDiscover`  | ```bool``` | If true, the application will recursively look for additional migrations within the directories specified by `migrationPaths` |
| `migrationPaths` | ```array``` |   An array of path aliases to recursively look under for additional migrations. |

Usage
-----
```
./yii migrate
```
***Note***: extension does not make any changes to the default migration usage. It simply alters the paths in which to look for migrations. 
