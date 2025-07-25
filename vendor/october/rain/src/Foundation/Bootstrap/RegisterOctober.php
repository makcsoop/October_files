<?php namespace October\Rain\Foundation\Bootstrap;

use October\Rain\Support\Str;
use October\Rain\Composer\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
use October\Rain\Extension\Container as OctoberContainer;

/**
 * RegisterOctober specific features
 */
class RegisterOctober
{
    /**
     * cachePaths used by the system
     */
    protected $cachePaths = [
        'cms',
        'cms/cache',
        'cms/combiner',
        'cms/twig',
        'framework',
        'framework/cache',
        'framework/views',
        'temp',
        'temp/public',
    ];

    /**
     * storagePaths used by the system
     */
    protected $storagePaths = [
        'app',
        'app/media',
        'app/uploads',
        'framework',
        'framework/sessions',
        'logs',
    ];

    /**
     * bootstrap the application
     */
    public function bootstrap(Application $app)
    {
        // Register singletons
        $app->singleton('string', function () {
            return new \October\Rain\Support\Str;
        });

        // Change paths based on config
        if ($storagePath = $app['config']->get('system.storage_path')) {
            $app->useStoragePath($this->parseConfiguredPath($app, $storagePath));
        }

        if ($cachePath = $app['config']->get('system.cache_path')) {
            $app->useCachePath($this->parseConfiguredPath($app, $cachePath));
        }

        if ($pluginsPath = $app['config']->get('system.plugins_path')) {
            $app->usePluginsPath($this->parseConfiguredPath($app, $pluginsPath));
        }

        if ($themesPath = $app['config']->get('system.themes_path')) {
            $app->useThemesPath($this->parseConfiguredPath($app, $themesPath));
        }

        // Make system paths
        if ($app->cachePath() === $app->storagePath()) {
            $this->makeSystemPaths($app->cachePath(), array_unique(
                array_merge($this->cachePaths, $this->storagePaths)
            ));
        }
        else {
            $this->makeSystemPaths($app->cachePath(), $this->cachePaths);
            $this->makeSystemPaths($app->storagePath(), $this->storagePaths);
        }

        // Configure the custom class loader
        $this->configureClassLoader($app);

        // Clear service container
        OctoberContainer::clearExtensions();
    }

    /**
     * configureClassLoader initializes the class loader cache
     */
    protected function configureClassLoader(Application $app)
    {
        $loader = ClassLoader::instance();

        $loader->initManifest($app->getCachedClassesPath());

        $app->after(function () use ($loader) {
            $loader->build();
        });
    }

    /**
     * parseConfiguredPath will include the base path if necessary
     */
    protected function parseConfiguredPath(Application $app, string $path): string
    {
        return Str::startsWith($path, '/')
            ? $path
            : $app->basePath($path);
    }

    /**
     * makeSystemPaths will attempt to ensure the required system paths exist
     */
    protected function makeSystemPaths(string $rootPath, array $subPaths): void
    {
        if (file_exists($rootPath)) {
            return;
        }

        @mkdir($rootPath);

        foreach ($subPaths as $path) {
            $subPath = $rootPath.DIRECTORY_SEPARATOR.$path;
            if (file_exists($subPath)) {
                continue;
            }

            @mkdir($subPath);
        }
    }
}
