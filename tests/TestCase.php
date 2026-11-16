<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $name = $app['config']->get('database.default');
        $connection = $app['config']->get('database.connections.'.$name);
        if (! (($connection['driver'] === 'sqlite' && $connection['database'] === ':memory:')
            || ($connection['driver'] === 'mariadb' && $connection['database'] === 'preventiti_tests'))) {
            throw new \RuntimeException('Las pruebas solo pueden usar SQLite en memoria o preventiti_tests; no se ejecutó RefreshDatabase.');
        }
        return $app;
    }

    protected function assertForeignKeyIntegrity(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            $this->assertSame([], \Illuminate\Support\Facades\DB::select('PRAGMA foreign_key_check'));
            return;
        }
        $keys = \Illuminate\Support\Facades\DB::select('SELECT TABLE_NAME AS child_table,COLUMN_NAME AS child_column,REFERENCED_TABLE_NAME AS parent_table,REFERENCED_COLUMN_NAME AS parent_column FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
        foreach ($keys as $key) {
            $orphans = \Illuminate\Support\Facades\DB::selectOne("SELECT COUNT(*) AS total FROM `{$key->child_table}` c LEFT JOIN `{$key->parent_table}` p ON c.`{$key->child_column}` = p.`{$key->parent_column}` WHERE c.`{$key->child_column}` IS NOT NULL AND p.`{$key->parent_column}` IS NULL");
            $this->assertSame(0, (int) $orphans->total, $key->child_table.'.'.$key->child_column);
        }
    }
}
