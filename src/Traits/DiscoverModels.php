<?php

namespace Chareka\AutoMigrate\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;

trait DiscoverModels
{
    /**
     * @throws ReflectionException
     */
    public function discoverModels(): Collection
    {
        $models = collect();

        $models->push(...$this->discoverBaseModels());
        $models->push(...$this->discoverModularModels());

        return $models->sortBy('order');
    }

    /**
     * @throws ReflectionException
     */
    private function discoverBaseModels(): array
    {
        $models = [];
        $paths = [app_path('Models'), app_path('Entities')];
        $namespace = app()->getNamespace();

        foreach ($paths as $path) {
            if (!is_dir($path)) return $models;

            foreach ((new Finder)->in($path) as $file) {
                if ($file->isDir()) continue;

                $model = $namespace . str_replace(
                        ['/', '.php'],
                        ['\\', ''],
                        Str::after($file->getRealPath(), realpath(app_path()) . DIRECTORY_SEPARATOR)
                    );

                if (!$this->isValidModel($model)) continue;

                $models[] = [
                    'name' => $model,
                    'object' => $object = app($model),
                    'order' => $object->migrationOrder ?? 0,
                ];
            }
        }

        return $models;
    }

    /**
     * @throws ReflectionException
     */
    private function discoverModularModels(): array
    {
        if (!is_dir(base_path('modules'))) return [];

        $models = [];

        foreach (glob(base_path('modules/*'), GLOB_ONLYDIR) as $module) {
            $path = $module . '/Models';
            $paths = [$module . '/Models', $module . '/Entities'];

            foreach ($paths as $path) {
                if (!is_dir($path)) continue;

                foreach ((new Finder)->files()->in($path) as $file) {
                    if ($file->isDir()) continue;

                    $namespace = str_replace('/', '\\', str_replace(base_path(), '', $file->getRealPath()));
                    $namespace = str_replace('.php', '', $namespace);
                    $namespace = str_replace('\\modules\\', 'Modules\\', $namespace);
                    $class = ltrim($namespace, '\\');

                    if (!$this->isValidModel($class)) continue;

                    $models[] = [
                        'name' => $class,
                        'object' => $object = app($class),
                        'order' => $object->migrationOrder ?? 0,
                    ];
                }
            }
        }

        return $models;
    }

    /**
     * @throws ReflectionException
     */
    private function isValidModel($model): bool
    {
        //        if(!class_exists($model)) return false;

        $reflector = new ReflectionClass($model);

        if (!$reflector->isInstantiable() || !method_exists($model, 'migration')) return false;

        return true;
    }
}
