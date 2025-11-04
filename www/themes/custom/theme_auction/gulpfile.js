/**
 * Type "gulp" on the command line to watch file changes.
 */
'use strict';
var $ = require('gulp-load-plugins')();
var autoprefixer = require('gulp-autoprefixer');
var concat = require('gulp-concat');
var cssmin = require('gulp-cssmin');
var babel = require('gulp-babel');
var drupalBreakpoints = require('drupal-breakpoints-scss');
var gulp = require('gulp');
var importer = require('node-sass-globbing');
var insert = require('gulp-insert');
var livereload = require('gulp-livereload');
var plumber = require('gulp-plumber');
var sass = require('gulp-sass');
var sourcemaps = require('gulp-sourcemaps');
var stripCssComments = require('gulp-strip-css-comments');
var uglify = require('gulp-uglify');
var rename = require('gulp-rename');
var sassLint = require('gulp-sass-lint');
var cp = require('child_process');
var susy_main = require.resolve('susy');

var paths = {
  rootPath: {
    project: __dirname + '/',
    theme: __dirname + '/'
  },
  theme: {
    root: __dirname + '/',
    css: __dirname + '/' + 'css/',
    js: __dirname + '/' + 'js/',
    sass: __dirname + '/' + 'source/scss/',
    source_js: __dirname + '/' + 'source/js/',
    fonts: __dirname + '/' + 'fonts/'
  }
};

var options = {
  autoprefixer: {
    browsers: ['> 1%']
  },
  concat: {
    files: [
      paths.theme.js + 'custom/**/*.js'
    ]
  },
  eslint: {
    files: [
      paths.theme.js + 'custom/**/*.js'
    ]
  },
  sass: {
    importer: importer,
    //outputStyle: 'compressed',
    includePaths: [
      'node_modules/breakpoint-sass/stylesheets/',
      'node_modules/susy/sass/',
      'node_modules/compass-mixins/lib/',
      'node_modules/font-awesome/scss'
    ]
  },
  scssLint: {
    // maxBuffer default is 300 * 1024
    'maxBuffer': 1000 * 1024,
    rules: {
      'class-name-format': 0,
      'empty-args': 0,
      'empty-line-between-blocks': 0,
      'force-element-nesting': 0,
      'nesting-depth': 0,
      'no-vendor-prefixes': 0,
      'property-sort-order': 0
    },
    'config': paths.rootPath.project + '.scss-lint.yml'
  },
  uglify: {
    compress: {
      unused: false
    }
  }
};

// JS tasks.
gulp.task('js-watch', function() {
  gulp.src(options.eslint.files)
      .pipe($.eslint())
      .pipe($.eslint.format());
  gulp.src(options.concat.files)
      .pipe(concat('script.js'))
      .pipe(gulp.dest('js_min'))
      .pipe(uglify(options.uglify))
      .pipe(gulp.dest('js_min'));
});

// Sass tasks.
gulp.task('sass-watch', function() {
  gulp.src(paths.theme.sass + '/*.scss')
      .pipe(sassLint(options.scssLint))
      .pipe(sassLint.format())
      .pipe(sassLint.failOnError());
  gulp.src(paths.theme.sass + '*.scss')
      .pipe(plumber())
      .pipe(sourcemaps.init())
      .pipe(sass(options.source_sass).on('error', sass.logError))
      .pipe(autoprefixer(options.autoprefixer))
      .pipe(stripCssComments({preserve: false}))
      .pipe(sourcemaps.write('./css-map'))
      .pipe(gulp.dest('./css'));
});

gulp.task('default', function() {
  livereload.listen();
  gulp.watch('./source/scss/**/*.scss', ['sass-watch']);
  gulp.watch('./js/**/*.js', ['js-watch']);
  gulp.watch(['./css/print-style.css', './css/main.css', './**/*.twig', './js_min/*.js'], function(files) {
    livereload.changed(files)
  });
});