<?php

namespace Chareka\AutoMigrate\Commands;

use Chareka\AutoMigrate\Parsers\CliPrettier;
use Chareka\AutoMigrate\Parsers\ComponentParser;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeAModelCommand extends Command
{
    use CliPrettier;

    protected $signature = 'make:amodel {class} {--force}';
    private $filesystem;
    private $modelParser;
    private $factoryParser;

    public function handle()
    {
        $this->filesystem = new Filesystem;

        $this->modelParser = new ComponentParser(
            'App\\Models',
            config('livewire.view_path'),
            $this->argument('class')
        );

        $this->factoryParser = new ComponentParser(
            'Database\\Factories',
            config('livewire.view_path'),
            $this->argument('class') . 'Factory'
        );

        if ($this->filesystem->exists($this->modelParser->classPath()) && !$this->option('force')) {
            $this->writeError('<comment>Model exists:</comment> ' . $this->modelParser->relativeClassPath());
            $this->writeWarning('Use the <info>--force</info> to overwrite it.');

            return;
        }

        $this->deleteUserMigration();
        $this->makeStubs();

        $this->writeInfo('<info>Model created:</info> ' . $this->modelParser->relativeClassPath());
        $this->writeInfo('<info>Factory created:</info> ' . $this->factoryPath('relativeClassPath'));
    }

    private function deleteUserMigration()
    {
        if ($this->modelParser->className() != 'User') {
            return;
        }

        $path = 'database/migrations/2014_10_12_000000_create_users_table.php';
        $file = base_path($path);

        if ($this->filesystem->exists($file)) {
            $this->filesystem->delete($file);

            $this->writeError('<info>Migration deleted:</info> ' . $path);
        }
    }

    private function makeStubs()
    {
        $prefix = $this->modelParser->className() == 'User' ? 'User' : null;

        $stubs = [
            $this->modelParser->classPath() => $prefix . 'Model.php',
            $this->factoryPath('classPath') => $prefix . 'Factory.php',
        ];

        $replaces = [
            'DummyFactoryClass' => $this->factoryParser->className(),
            'DummyFactoryNamespace' => $this->factoryParser->classNamespace(),
            'DummyModelClass' => $this->modelParser->className(),
            'DummyModelNamespace' => $this->modelParser->classNamespace(),
        ];

        foreach ($stubs as $path => $stub) {
            $contents = Str::replace(
                array_keys($replaces),
                $replaces,
                $this->filesystem->get(config('auto-migrate.stub_path') . '/' . $stub)
            );

            $this->filesystem->ensureDirectoryExists(dirname($path));
            $this->filesystem->put($path, $contents);
        }
    }

    private function factoryPath($method)
    {
        return Str::replaceFirst(
            'app/Database/Factories',
            'database/factories',
            $this->factoryParser->$method()
        );
    }
}
