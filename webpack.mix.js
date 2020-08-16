const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix
  .js('resources/js/app.js', 'public/js')
  .sourceMaps()
  .sass('resources/sass/splash.scss', 'public/css')
  .sass('resources/sass/blog.scss', 'public/css')
  .copyDirectory('resources/img', 'public/img')
  .copyDirectory('resources/wp-includes', 'public/wp-includes')
  .copyDirectory('resources/wp-admin', 'public/wp-admin')
;
