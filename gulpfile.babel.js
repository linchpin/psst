'use strict';

var plugins       = require('gulp-load-plugins');
var yargs         = require('yargs');
var gulp          = require('gulp');
var rimraf        = require('rimraf');
var yaml          = require('js-yaml');
var fs            = require('fs');
var webpackStream = require('webpack-stream');
var webpack2      = require('webpack');
var named         = require('vinyl-named');
var autoprefixer  = require('autoprefixer');

// Load all Gulp plugins into one variable
const $ = plugins();

// Check for --production flag
let PRODUCTION = !!(yargs.argv.production);

// Load settings from settings.yml
const { COMPATIBILITY, PORT, UNCSS_OPTIONS, PATHS, LOCAL_PATH } = loadConfig();

let sassConfig = {
  mode: (PRODUCTION ? true : false)
};

// Define default webpack object
let webpackConfig = {
  mode: (PRODUCTION ? 'production' : 'development'),
  module: {
    rules: [
      {
        test: /\.js$/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [ "@babel/preset-env" ],
            compact: false
          }
        }
      }
    ]
  },
  externals: {
    jquery: 'jQuery'
  },
  devtool: ! PRODUCTION && 'source-map'
};

/**
 * Load in additional config files
 */
function loadConfig() {
  let ymlFile = fs.readFileSync('config.yml', 'utf8');
  return yaml.load(ymlFile);
}

/**
 * Set production mode during the build process
 *
 * @param done
 */
function setProductionMode(done) {
  PRODUCTION = false;
  webpackConfig.mode = 'production';
  webpackConfig.devtool = false;

  sassConfig.production = true;
  done();
}

// Build the "dist" folder by running all of the below tasks
// Sass must be run later so UnCSS can search for used classes in the others assets.
gulp.task('build:production',
  gulp.series(setProductionMode, clean, javascript, sass)); // removed copy from (javascript, images, copy)

// Build the site, run the server, and watch for file changes
gulp.task('default',
  gulp.series(clean, javascript, sass, gulp.parallel(watch))); // removed server from middle

// This happens every time a build starts
function clean(done) {
  rimraf('css', done);
  rimraf('js', done);
}

// Copy files out of the assets folder
// This task skips over the "img", "js", and "scss" folders, which are parsed separately
function copy() {
  return gulp.src(PATHS.assets)
    .pipe(gulp.dest('other/assets'));
}

// In production, the CSS is compressed
function sass() {

  const postCssPlugins = [
    // Autoprefixer
    autoprefixer(),

    // UnCSS - Uncomment to remove unused styles in production
    // PRODUCTION && uncss.postcssPlugin(UNCSS_OPTIONS),
  ].filter(Boolean);

  return gulp.src('assets/scss/*.scss')
    .pipe($.sourcemaps.init())
    .pipe($.sass({
      includePaths: PATHS.sass
    })
      .on('error', $.sass.logError))
    .pipe($.postcss(postCssPlugins))
    .pipe($.if(sassConfig.production, $.cleanCss({ compatibility: 'ie11' })))
    .pipe($.if(!sassConfig.production, $.sourcemaps.write()))
    .pipe(gulp.dest('css'));
}

// In production, the file is minified
function javascript() {
  return gulp.src(PATHS.entries)
    .pipe(named())
    .pipe($.sourcemaps.init())
    .pipe(webpackStream(webpackConfig, webpack2))
    .pipe($.if(PRODUCTION, $.uglify()
      .on('error', e => { console.log(e); })
    ))
    .pipe($.if(!PRODUCTION, $.sourcemaps.write()))
    .pipe(gulp.dest('js'));
}

// In production, the images are compressed
function images() {
  return gulp.src('assets/img/**/*')
    .pipe($.if(PRODUCTION, $.imagemin([
      $.imagemin.jpegtran({ progressive: true }),
    ])))
    .pipe(gulp.dest('images'));
}

// Watch for changes to static assets, Sass, and JavaScript
function watch() {
  // gulp.watch(PATHS.assets, copy);
  gulp.watch('assets/scss/**/*.scss').on('all', sass);
  gulp.watch('assets/js/**/*.js').on('all', javascript);
}
