<?php

namespace ShipKit\Core\Upgrade;

use InvalidArgumentException;

/**
 * The project's `shipkit.json`: which version of the ShipKit template its files came from, so
 * `shipkit:upgrade` knows where to start. A release stamps it, and so does each upgrade. It may
 * also name another template `source` (a fork or a mirror) and paths to `exclude` from upgrades.
 */
class TemplateVersion
{
    public const string FILE = 'shipkit.json';

    public const string DEFAULT_SOURCE = 'https://github.com/shipwithian/shipkit.git';

    /**
     * What ShipKit's own development branch records, where no release has stamped a version yet.
     */
    public const string DEVELOPMENT = 'dev';

    public const string VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';

    /**
     * The recorded version, or null where none was recorded.
     *
     * @throws InvalidArgumentException when the file is not valid JSON or holds something that is not a version
     */
    public function version(): ?string
    {
        $version = $this->read()['template'] ?? null;

        if ($version === null || $version === self::DEVELOPMENT) {
            return null;
        }

        if (! is_string($version) || ! preg_match(self::VERSION_PATTERN, $version)) {
            throw new InvalidArgumentException(self::FILE.' must record the template version as major.minor.patch, such as "5.3.0".');
        }

        return $version;
    }

    public function source(): string
    {
        $source = $this->read()['source'] ?? null;

        return is_string($source) && $source !== '' ? $source : self::DEFAULT_SOURCE;
    }

    /**
     * Paths, files or folders, that upgrades leave alone.
     *
     * @return list<string>
     */
    public function excluded(): array
    {
        $excluded = $this->read()['exclude'] ?? [];

        return is_array($excluded) ? array_values(array_filter($excluded, is_string(...))) : [];
    }

    public function stamp(string $version): void
    {
        file_put_contents(
            base_path(self::FILE),
            json_encode(['template' => $version] + $this->read(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $path = base_path(self::FILE);

        if (! is_file($path)) {
            return [];
        }

        $contents = json_decode((string) file_get_contents($path), true);

        if (! is_array($contents)) {
            throw new InvalidArgumentException(self::FILE.' is not valid JSON. It records the template version, for example {"template": "5.3.0"}.');
        }

        return $contents;
    }
}
