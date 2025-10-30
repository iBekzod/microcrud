<?php

namespace Microcrud;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Microcrud\Middlewares\LogHttpRequest;
use Microcrud\Middlewares\LocaleMiddleware;
use Microcrud\Middlewares\TimezoneMiddleware;

class MicrocrudServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Validate configuration on boot if enabled
        if (Config::get('microcrud.features.validate_on_boot', true)) {
            $this->validateConfiguration();
        }

        //        // Include the package classmap autoloader
        //        if (\File::exists(__DIR__.'/../vendor/autoload.php'))
        //        {
        //            include __DIR__.'/../vendor/autoload.php';
        //        }

        /**
         * Routes
         */

        // Method 1
        // A simple include, but in the routes files, controllers should be called by their namespace
        // include __DIR__.'/routes/web.php';

        // Method 2
        // A Better method, extend the app routes by adding a group with a specified namespace

        //        $this->app->router->group(['namespace' => 'Yk\LaravelPackageExample\App\Http\Controllers'],
        //            function(){
        //                require __DIR__.'/routes/web.php';
        //            }
        //        );

        /**
         * Views
         * use: view('PackageName::view_name');
         */
        //        $this->loadViewsFrom(__DIR__.'/resources/views', 'Yk\LaravelPackageExample');

        /*
        * php artisan vendor:publish
        * Existing files will not be published
        */

        //        // Publish views to resources/views/vendor/vendor-name/package-name
        //        $this->publishes(
        //            [
        //                __DIR__.'/resources/views' => base_path('resources/views/vendor/yk/laravel-package-example'),
        //            ]
        //        );

        //        // Publish assets to public/vendor/vendor-name/package-name
        //        $this->publishes([
        //            __DIR__.'/public' => public_path('vendor/yk/laravel-package-example'),
        //        ], 'public');

        //        // Publish configurations to config/vendor/vendor-name/package-name
        //        // Config::get('vendor.yk.laravel-package-example')
        //        $this->publishes([
        //            __DIR__.'/config' => config_path('vendor/yk/laravel-package-example'),
        //        ]);
        //
        $kernel = $this->app['Illuminate\Contracts\Http\Kernel'];
        //        $kernel->pushMiddleware('Yk\LaravelPackageExample\App\Http\Middleware\MiddlewareExample');
        $kernel->pushMiddleware(LocaleMiddleware::class);
        $kernel->pushMiddleware(TimezoneMiddleware::class);
        $kernel->pushMiddleware(LogHttpRequest::class);
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'microcrud_translations');
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/microcrud.php' => config_path('microcrud.php'),
            ], 'config');
            $this->publishes([
                __DIR__ . '/../config/schema.php' => config_path('schema.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../config/database.php' => config_path('database.php'),
            ], 'config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/uploader'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/uploader'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/uploader'),
            ], 'lang');*/

            // Registering package commands.
            // $this->commands([]);
        }
        /**
         * Register migrations, so they will be automatically run when the php artisan migrate command is executed.
         */
        //        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        /**
         * Register commands, so you may execute them using the Artisan CLI.
         */
        //        if ($this->app->runningInConsole()) {
        //            $this->commands([
        //                \Yk\LaravelPackageExample\App\Console\Commands\Hello::class,
        //            ]);
        //        }

    }

    /**
     * Validate MicroCRUD configuration on boot.
     *
     * @return void
     */
    protected function validateConfiguration()
    {
        try {
            // Check cache configuration
            if (Config::get('microcrud.cache.enabled', false)) {
                $driver = Config::get('cache.default');

                try {
                    $store = Cache::getStore();
                    $supportsTagging = method_exists($store, 'tags') &&
                                     in_array($driver, ['redis', 'memcached', 'dynamodb']);

                    if (!$supportsTagging && Config::get('microcrud.cache.validate_tagging', true)) {
                        Log::warning("MicroCRUD: Cache driver '{$driver}' doesn't support tagging. " .
                                   "Cache operations may not work as expected. " .
                                   "Consider using Redis or Memcached, or set MICROCRUD_CACHE_VALIDATE_TAGGING=false.");
                    }
                } catch (\Exception $e) {
                    Log::warning("MicroCRUD: Cache validation failed: {$e->getMessage()}");
                }
            }

            // Check queue configuration
            if (Config::get('microcrud.queue.enabled', false)) {
                $connection = Config::get('queue.default');

                if (!$connection || $connection === 'null') {
                    Log::warning("MicroCRUD: Queue is enabled but no queue connection is configured. " .
                               "Set QUEUE_CONNECTION in your .env file.");
                } elseif ($connection === 'sync') {
                    Log::info("MicroCRUD: Queue connection is 'sync'. Jobs will run synchronously, not in the background.");
                }
            }
        } catch (\Exception $e) {
            // Don't let validation errors break the application
            Log::error("MicroCRUD: Configuration validation error: {$e->getMessage()}");
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        /**
         * Merge configurations
         * Config::get('packages.Yk.LaravelPackageExample')
         */
        //        $this->mergeConfigFrom(
        //            __DIR__.'/config/app.php', 'packages.Yk.LaravelPackageExample.app'
        //        );
        //
        //        $this->app->bind('ClassExample', function(){
        //            return $this->app->make('Yk\LaravelPackageExample\Classes\ClassExample');
        //        });

    }
}
