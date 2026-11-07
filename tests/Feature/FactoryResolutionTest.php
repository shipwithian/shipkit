<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

it('resolves every factory to an existing model', function () {
    $unresolved = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(database_path('factories'), FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! str_ends_with($file->getPathname(), '.php')) {
            continue;
        }

        $class = 'Database\\Factories\\'.str_replace('/', '\\', Str::beforeLast(Str::after($file->getPathname(), database_path('factories').'/'), '.php'));

        try {
            $model = (new $class)->modelName();

            if (! is_subclass_of($model, Model::class)) {
                $unresolved[] = "{$class} resolves to [{$model}], which is not a model";
            }
        } catch (Throwable $exception) {
            $unresolved[] = "{$class}: {$exception->getMessage()}";
        }
    }

    expect($unresolved)->toBeEmpty(implode(PHP_EOL, $unresolved));
});
