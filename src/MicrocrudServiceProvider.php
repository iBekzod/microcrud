<?php

namespace Microcrud;

use Illuminate\Support\ServiceProvider;
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
