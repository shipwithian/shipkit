<?php

namespace ShipKit\Core\Architecture;

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
     * The bounded contexts: one folder each under `app/Domain`.
     *
     * @return list<string>
     */
    public function contexts(): array
    {
        $folders = glob($this->appPath('Domain/*'), GLOB_ONLYDIR) ?: [];

        return array_map(basename(...), $folders);
    }

    /**
     * Namespaces of the given context folders that exist, across every context or within one.
     *
     * @param  list<string>|string  $folders
     * @return list<string>
     */
    public function contextNamespaces(array|string $folders, ?string $onlyContext = null): array
    {
        $namespaces = [];

        foreach ($onlyContext === null ? $this->contexts() : [$onlyContext] as $context) {
            foreach ((array) $folders as $folder) {
                if (is_dir($this->appPath("Domain/{$context}/{$folder}"))) {
                    $namespaces[] = $this->appNamespaceFor("Domain/{$context}/{$folder}");
                }
            }
        }

        return $namespaces;
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
