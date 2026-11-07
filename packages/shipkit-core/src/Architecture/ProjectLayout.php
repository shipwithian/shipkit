<?php

namespace ShipKit\Core\Architecture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Where a ShipKit project keeps its code, read from the project itself instead of assumed.
 *
 * Works before the application boots, because Pest evaluates `arch()` expectations when it loads a
 * test file. The application namespace comes from the project's PSR-4 mapping for `app/`, and the
 * project's ShipKit settings come straight from `config/shipkit.php`.
 */
class ProjectLayout
{
    /**
     * Frontend paths that hold generated code, which the frontend checks do not read.
     *
     * @var list<string>
     */
    public const array GENERATED_FRONTEND_PATHS = ['resources/js/actions/', 'resources/js/routes/', 'resources/js/wayfinder/', 'resources/js/types/generated.ts'];

    /**
     * Folders under `app/Actions` whose classes a package names and shapes, such as Fortify's actions.
     *
     * @var list<string>
     */
    public const array PACKAGE_ACTION_FOLDERS = ['Fortify'];

    private static ?self $current = null;

    private ?string $appNamespace = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $config = null;

    public function __construct(private readonly string $basePath) {}

    /**
     * The layout of the project whose `vendor/` directory loaded ShipKit.
     */
    public static function current(): self
    {
        return self::$current ??= new self(Application::inferBasePath());
    }

    public function path(string $path = ''): string
    {
        return rtrim($this->basePath.'/'.ltrim($path, '/'), '/');
    }

    /**
     * The application's root namespace, such as `App\`.
     */
    public function appNamespace(): string
    {
        return $this->appNamespace ??= $this->resolveAppNamespace();
    }

    public function appPath(string $path = ''): string
    {
        return $this->path('app'.($path === '' ? '' : '/'.$path));
    }

    /**
     * The namespace under the application namespace for a path below `app/`, such as `Http\Controllers`.
     */
    public function appNamespaceFor(string $path): string
    {
        return $this->appNamespace().str_replace('/', '\\', trim($path, '/'));
    }

    /**
     * The features: one folder each under `app/Actions` or `app/Queries`, except folders a package
     * names, such as Fortify's actions.
     *
     * @return list<string>
     */
    public function features(): array
    {
        $folders = [
            ...(glob($this->appPath('Actions/*'), GLOB_ONLYDIR) ?: []),
            ...(glob($this->appPath('Queries/*'), GLOB_ONLYDIR) ?: []),
        ];

        $features = array_values(array_unique(array_diff(array_map(basename(...), $folders), self::PACKAGE_ACTION_FOLDERS)));
        sort($features);

        return $features;
    }

    /**
     * Namespaces of the features' `Actions` or `Queries` folders that exist, across every feature or within one.
     *
     * @return list<string>
     */
    public function featureNamespaces(string $folder, ?string $onlyFeature = null): array
    {
        $namespaces = [];

        foreach ($onlyFeature === null ? $this->features() : [$onlyFeature] as $feature) {
            if (is_dir($this->appPath("{$folder}/{$feature}"))) {
                $namespaces[] = $this->appNamespaceFor("{$folder}/{$feature}");
            }
        }

        return $namespaces;
    }

    /**
     * Classes outside Laravel's standard folder for their kind, each with the folder it belongs in:
     * an `app/Domain` folder, an Action outside `app/Actions/{Feature}`, a Query outside
     * `app/Queries/{Feature}`, or an Eloquent model outside `app/Models`.
     *
     * @return list<string>
     */
    public function misplacedClasses(): array
    {
        $violations = [];

        if (is_dir($this->appPath('Domain'))) {
            $violations[] = "app/Domain exists. ShipKit uses Laravel's standard folders: Actions in app/Actions/{Feature}, Queries in app/Queries/{Feature}, models in app/Models, policies in app/Policies, exceptions in app/Exceptions";
        }

        foreach ($this->phpFiles('app') as $path) {
            $relative = substr($path, strlen($this->path()) + 1);
            $name = basename($path, '.php');
            $inActions = str_starts_with($relative, 'app/Actions/');
            $inQueries = str_starts_with($relative, 'app/Queries/');

            if (str_ends_with($name, 'Action') && ! $inActions) {
                $violations[] = "{$relative}: an Action lives in app/Actions/{Feature}";
            }

            if (str_ends_with($name, 'Query') && ! $inQueries) {
                $violations[] = "{$relative}: a Query lives in app/Queries/{Feature}";
            }

            if (($inActions || $inQueries) && substr_count($relative, '/') < 3) {
                $violations[] = "{$relative}: group it by feature, in app/".($inActions ? 'Actions' : 'Queries').'/{Feature}';
            }

            if (! str_starts_with($relative, 'app/Models/') && $this->declaresModel($path)) {
                $violations[] = "{$relative}: an Eloquent model lives in app/Models";
            }
        }

        return $violations;
    }

    /**
     * Whether a file below `app/` declares an Eloquent model, by its loaded class or, for a class
     * the autoloader cannot reach, by what it extends.
     */
    private function declaresModel(string $path): bool
    {
        $class = $this->classForAppFile($path);

        if (class_exists($class)) {
            return is_subclass_of($class, Model::class);
        }

        return (bool) preg_match('/\bextends\s+\\\\?(?:[\w\\\\]*\\\\)?(?:Model|Pivot|MorphPivot|Authenticatable)\b/', (string) file_get_contents($path));
    }

    /**
     * Every PHP file below a project directory, or none when it does not exist.
     *
     * @return list<string>
     */
    public function phpFiles(string $directory): array
    {
        $path = $this->path($directory);

        if (! is_dir($path)) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Every TypeScript source below `resources/js`, relative to the project, except generated code.
     *
     * @return list<string>
     */
    public function frontendFiles(): array
    {
        $path = $this->path('resources/js');

        if (! is_dir($path)) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($this->path()) + 1);

            $isGenerated = array_filter(self::GENERATED_FRONTEND_PATHS, fn (string $generated): bool => str_starts_with($relative, $generated)) !== [];

            if (in_array($file->getExtension(), ['ts', 'tsx'], true) && ! $isGenerated) {
                $files[] = $relative;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * The class a PHP file below `app/` declares, following the PSR-4 mapping.
     */
    public function classForAppFile(string $path): string
    {
        $relative = substr($path, strlen($this->appPath()) + 1, -strlen('.php'));

        return $this->appNamespaceFor($relative);
    }

    /**
     * A setting from the project's `config/shipkit.php`, readable before the application boots.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $this->config ??= $this->loadConfig();

        return Arr::get($this->config, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        $path = $this->path('config/shipkit.php');
        $config = is_file($path) ? require $path : [];

        return is_array($config) ? $config : [];
    }

    private function resolveAppNamespace(): string
    {
        $path = $this->path('composer.json');
        $composer = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        foreach ($composer['autoload']['psr-4'] ?? [] as $namespace => $paths) {
            foreach ((array) $paths as $path) {
                if (trim((string) $path, '/') === 'app') {
                    return (string) $namespace;
                }
            }
        }

        throw new RuntimeException("{$path} does not map a PSR-4 namespace to app/.");
    }
}
