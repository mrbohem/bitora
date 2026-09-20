<?php

namespace App\Services\Docker;

use Illuminate\Support\Facades\File;

class PhpExtensionResolver
{
    /**
     * @return array{extensions: list<string>, system_dependencies: list<string>}
     */
    public function resolve(?string $applicationPath): array
    {
        $requiredExtensions = $this->requiredExtensions($applicationPath);

        return [
            'extensions' => array_values(array_map(
                static fn (string $extension): string => substr($extension, 4),
                array_intersect($requiredExtensions, array_keys($this->systemDependencies()))
            )),
            'system_dependencies' => $this->systemDependenciesFor($requiredExtensions),
        ];
    }

    /**
     * @return list<string>
     */
    private function requiredExtensions(?string $applicationPath): array
    {
        if ($applicationPath === null) {
            return [];
        }

        $requiredExtensions = [];

        foreach (['composer.json', 'composer.lock'] as $filename) {
            $path = "{$applicationPath}/{$filename}";

            if (! File::exists($path)) {
                continue;
            }

            $composer = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $packages = $filename === 'composer.json'
                ? [$composer]
                : ($composer['packages'] ?? []);

            foreach ($packages as $package) {
                foreach (($package['require'] ?? []) as $name => $constraint) {
                    if (str_starts_with($name, 'ext-')) {
                        $requiredExtensions[$name] = true;
                    }
                }
            }
        }

        $extensions = array_keys($requiredExtensions);
        $knownExtensions = array_merge(
            array_keys($this->systemDependencies()),
            $this->builtInExtensions()
        );
        $unsupportedExtensions = array_diff($extensions, $knownExtensions);

        if ($unsupportedExtensions !== []) {
            throw new \RuntimeException(
                'Unsupported PHP extensions required by Composer: '.implode(', ', $unsupportedExtensions)
            );
        }

        return $extensions;
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function systemDependenciesFor(array $extensions): array
    {
        $dependencies = $this->systemDependencies();
        $packages = [];

        foreach ($extensions as $extension) {
            $packages = array_merge($packages, $dependencies[$extension] ?? []);
        }

        return array_values(array_unique($packages));
    }

    /**
     * @return array<string, list<string>>
     */
    private function systemDependencies(): array
    {
        return [
            'ext-calendar' => [],
            'ext-bcmath' => [],
            'ext-gd' => [],
            'ext-intl' => ['icu-dev'],
            'ext-imap' => ['imap-dev', 'krb5-dev'],
            'ext-ldap' => ['openldap-dev'],
            'ext-gmp' => ['gmp-dev'],
            'ext-mbstring' => [],
            'ext-pcntl' => [],
            'ext-pdo' => [],
            'ext-pdo_mysql' => [],
            'ext-pdo_pgsql' => [],
            'ext-pdo_sqlite' => [],
            'ext-xsl' => ['libxslt-dev'],
            'ext-bz2' => ['bzip2-dev'],
            'ext-readline' => ['readline-dev'],
            'ext-sockets' => [],
            'ext-zip' => [],
            'ext-enchant' => ['enchant2-dev'],
        ];
    }

    /**
     * @return list<string>
     */
    private function builtInExtensions(): array
    {
        return [
            // PHP core extensions available in the official PHP image.
            'ext-ctype', 'ext-date', 'ext-filter', 'ext-hash', 'ext-iconv',
            'ext-json', 'ext-libxml', 'ext-openssl', 'ext-pcre', 'ext-phar',
            'ext-posix', 'ext-reflection', 'ext-session', 'ext-spl',
            'ext-standard', 'ext-tokenizer', 'ext-zlib',
            'ext-dom', 'ext-fileinfo', 'ext-simplexml', 'ext-xml',
            'ext-xmlreader', 'ext-xmlwriter',

            // Extensions installed by the generated Laravel image baseline.
            'ext-bcmath', 'ext-curl', 'ext-exif', 'ext-gd', 'ext-mbstring',
            'ext-pcntl', 'ext-pdo', 'ext-pdo_mysql', 'ext-pdo_pgsql',
            'ext-pdo_sqlite', 'ext-sockets', 'ext-zip', 'ext-intl',
        ];
    }
}
