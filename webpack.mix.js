const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix
  .js('resources/js/app.js', 'public/js')
  .js('resources/js/resume.js', 'public/js')
  .js('resources/js/currently-watching.js', 'public/js')
  .js('resources/js/font-loader.js', 'public/js')
  .vue()
  .copy('resources/config/config.json','public')
  .sourceMaps()
  .postCss('resources/css/app.css', 'public/css', [
    require('@tailwindcss/postcss'),
    require('autoprefixer'),
  ])
  .postCss('resources/css/blog.css', 'public/css', [
    require('@tailwindcss/postcss'),
    require('autoprefixer'),
  ])
  .copyDirectory('resources/img', 'public/img')
  .copyDirectory('resources/wp-includes', 'public/wp-includes')
  .copyDirectory('resources/wp-admin', 'public/wp-admin')
;
mix.js('resources/js/canvas-ui/app.js', 'public/js/canvas-ui.js').vue()
    .postCss('resources/css/canvas-ui.css', 'public/css/canvas-ui.css', [
      require('@tailwindcss/postcss'),
      require('autoprefixer'),
    ]);
