<?php

namespace fishvision\migrate\controllers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use Yii;
use yii\db\Query;
use yii\helpers\ArrayHelper;

/**
 * Class MigrateController
 * @package fishvision\migrate\controllers
 */
class MigrateController extends \yii\console\controllers\MigrateController
{
    /**
     * @var bool|array
     */
    private $cachedMigrations = false;

    /**
     * @var bool
     */
    public $autoDiscover = false;

    /**
     * @var array
     */
    public $migrationPaths = [];

    /**
     * @inheritdoc
     */
    protected function addMigrationHistory($version)
    {
        // If path supplied, fetch class name
        if (preg_match('/(m(\d{6}_\d{6})_.*?)\.php$/i', $version, $matches)) {
            $version = $matches[1];
        }

        $command = $this->db->createCommand();
        $command->insert($this->migrationTable, [
            'version' => $version,
            'apply_time' => time(),
        ])->execute();
    }

    /**
     * @inheritdoc
     */
    protected function removeMigrationHistory($version)
    {
        // If path supplied, fetch class name
        if (preg_match('/(m(\d{6}_\d{6})_.*?)\.php$/i', $version, $matches)) {
            $version = $matches[1];
        }

        $command = $this->db->createCommand();
        $command->delete($this->migrationTable, [
            'version' => $version,
        ])->execute();
    }

    /**
     * Returns the migrations that are not applied.
     * @return array list of new migrations
     */
    protected function getNewMigrations()
    {
        // Migrations already applied
        $applied = [];
        foreach ($this->getMigrationHistory(null) as $version => $time) {
            if (strstr($version, '/') !== false) {
                $parts = explode('/', $version);
                $version = end($parts);
            }
            $applied[substr($version, 1, 13)] = true;
        }

        $migrations = $this->getMigrationPaths();

        // Remove applied migrations from the array
        foreach ($migrations as $name => $path) {
            $class = substr($name, 1, 13);
            if (isset($applied[$class])) {
                unset($migrations[$name]);
            }
        }

        return $migrations;
    }

    /**
     * @return array
     */
    private function getMigrationPaths()
    {
        if ($this->cachedMigrations !== false) {
            return $this->cachedMigrations;
        }

        // Paths to look for
        $paths = [$this->migrationPath];
        if ($this->autoDiscover === true) {
            $paths = array_merge($paths, $this->migrationPaths);
        }

        $migrations = [];

        // Recursively iterate through each path
        foreach ($paths as $path) {
            /* @var $path array|string */
            if (is_array($path)) {
                foreach ($path as $migrationPath) {
                    /* @var $migrationPath string */
                    $migrations = ArrayHelper::merge($migrations, $this->getMigrationsInPath($migrationPath));
                }
            } elseif (is_string($path)) {
                $migrations = ArrayHelper::merge($migrations, $this->getMigrationsInPath($path));
            }
        }
        ksort($migrations);

        $this->cachedMigrations = $migrations;

        return $migrations;
    }

    /**
     * @param $path
     * @return array
     */
    protected function getMigrationsInPath($path)
    {
        $migrations = [];

        // Search recursively for migrations
        $recursiveDirectory = new RecursiveDirectoryIterator(Yii::getAlias($path));
        $recursiveIterator = new RecursiveIteratorIterator($recursiveDirectory);
        $recursiveRegex = new RegexIterator(
            $recursiveIterator,
            '/^.*[\\\\\\/]migrations[\\\\\\/]m(\d{6}_\d{6})_.*?\.php$/i',
            RecursiveRegexIterator::GET_MATCH
        );

        // Add to array which will be sortable by filename
        foreach ($recursiveRegex as $file) {
            /* @var $file string */
            $file = str_replace('\\', '/', $file);
            $fileParts = explode('/', $file[0]);
            $filename = end($fileParts);

            // Only add files not already applied
            if (is_file($file[0])) {
                $migrations[$filename] = $file[0];
            }
        }

        ksort($migrations);

        return $migrations;
    }

    /**
     * Creates a new migration instance.
     * @param string $class the migration class name
     * @return \yii\db\MigrationInterface the migration instance
     */
    protected function createMigration($class)
    {
        if (file_exists($class)) {
            $file = $class;
            if (preg_match('/(m(\d{6}_\d{6})_.*?)\.php$/i', $class, $matches)) {
                $class = $matches[1];
            }
        } else {
            $file = $this->migrationPath . DIRECTORY_SEPARATOR . $class . '.php';
        }

        require_once($file);

        return new $class(['db' => $this->db]);
    }

    /**
     * @param $class
     * @return mixed
     */
    private function getPathFromClass($class)
    {
        foreach ($this->getMigrationPaths() as $name => $path) {
            if (stristr($path, $class) !== false) {
                return $path;
            }
        }
    }

    /**
     * Downgrades with the specified migration class.
     * @param string $class the migration class name
     * @return boolean whether the migration is successful
     */
    protected function migrateDown($class)
    {
        if ($class === self::BASE_MIGRATION) {
            return true;
        }
        $class = $this->getPathFromClass($class);

        echo "*** reverting $class\n";
        $start = microtime(true);
        $migration = $this->createMigration($class);
        if ($migration->down() !== false) {
            $this->removeMigrationHistory($class);
            $time = microtime(true) - $start;
            echo "*** reverted $class (time: " . sprintf("%.3f", $time) . "s)\n\n";

            return true;
        } else {
            $time = microtime(true) - $start;
            echo "*** failed to revert $class (time: " . sprintf("%.3f", $time) . "s)\n\n";

            return false;
        }
    }

    /**
     * @inheritdoc
     */
    protected function getMigrationHistory($limit)
    {
        if ($this->db->schema->getTableSchema($this->migrationTable, true) === null) {
            $this->createMigrationHistoryTable();
        }
        $query = new Query;
        $rows = $query->select(['version', 'apply_time'])
            ->from($this->migrationTable)
            ->orderBy('version DESC')
            ->limit($limit)
            ->createCommand($this->db)
            ->queryAll();
        $history = ArrayHelper::map($rows, 'version', 'apply_time');
        unset($history[self::BASE_MIGRATION]);

        $pathHistory = [];
        foreach ($history as $class => $timestamp) {
            $pathHistory[$this->getPathFromClass($class)] = $timestamp;
        }

        return $pathHistory;
    }
}
