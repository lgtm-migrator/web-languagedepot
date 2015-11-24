var gulp = require('gulp');
var gutil = require('gulp-util');
var child_process = require('child_process');
var async = require('async');
var template = require('lodash.template');
var rename = require("gulp-rename");
var less = require('gulp-less');
var uglify = require('gulp-uglify');
var concat = require('gulp-concat');
var sourcemaps = require('gulp-sourcemaps');

var execute = function(command, options, callback) {
  if (options == undefined) {
    options = {};
  }
  command = template(command, options);
  if (!options.silent) {
    gutil.log(gutil.colors.green(command));
  }
  if (!options.dryRun) {
    child_process.exec(command, function(err, stdout, stderr) {
      gutil.log(stdout);
      gutil.log(gutil.colors.yellow(stderr));
      callback(err);
    });
  } else {
    callback(null);
  }
};

var start_server = function(options, cb) {
  var child = child_process.spawn(options.command, options.arguments, {
    detached: true,
    cwd: process.cwd,
    env: process.env,
    stdio: ['pipe', 'pipe', 'pipe']
//    stdio: ['ignroe', process.stdout, process.stderr]
//    stdio: ['ignore', 'pipe', 'pipe']
  });

  child.once('close', cb);
  child.unref();

  if (child.stdout) child.stdout.on('data', function(data) {
//    gutil.log(gutil.colors.yellow('boo '));
    gutil.log(gutil.colors.yellow(data));
//    console.log(data);
    var sentinal = options.sentinal;
    if (data.toString().indexOf(sentinal) != -1) {
      cb(null, child);
    }
  });
  if (child.stderr) child.stderr.on('data', function(data) {
    gutil.log(gutil.colors.red(data));
    var sentinal = options.sentinal;
    if (data.toString().indexOf(sentinal) != -1) {
      cb(null, child);
    }
  });
};

var paths = {
  src_ng: ['src/app-ng/**/*.js', 'src/app-ng/**/*.html', 'src/assets/*'],
  src_less: ['src/app-ng/**/*.less'],
  src_api: ['src/api/**/*.php'],
  test: ['tests/api/**/*.php']
};

// livereload
var livereload = require('gulp-livereload');
var lr = require('tiny-lr');
var server = lr();

gulp.task('default', function() {
  // place code for your default task here
});

gulp.task('do-reload', function() {
  return gulp.src('src/index.php').pipe(livereload(server));
});

gulp.task('reload', function() {
  server.listen(35729, function(err) {
    if (err) {
      return console.log(err);
    }
    gulp.watch(paths.src_ng, [ 'do-reload' ]);
    gulp.watch(paths.src_less, [ 'less' ]);
  });
});

gulp.task('less', function() {
  gulp.src(paths.src_less)
    .pipe(sourcemaps.init())
    .pipe(less())
    .pipe(concat('site.css'))
    .pipe(sourcemaps.write())
    .pipe(gulp.dest('src/assets')
  );
});

gulp.task('upload', function(cb) {
  var options = {
    dryRun: false,
    silent : false,
    src : "src",
    dest : "root@public.languagedepot.org:/var/www/languagedepot.org_admin/htdocs/"
  };
  execute(
    'rsync -rzlt --chmod=Dug=rwx,Fug=rw,o-rwx --delete --exclude-from="upload-exclude.txt" --stats --rsync-path="sudo -u www-data rsync" --rsh="ssh" <%= src %>/ <%= dest %>',
    options,
    cb
  );
});

gulp.task('db-copy-public', function(cb) {
  var options = {
    dryRun : false,
    silent : true,
    dest : "root@public.languagedepot.org",
    password : process.env.password_db,
    user: process.env.USER
  };
  execute(
    'ssh -C <%= dest %> mysqldump -u <%= user %> --password=<%= password %> languagedepot | mysql -u <%= user %> --password=<%= password %> -D languagedepot',
    options,
    cb
  );
});

gulp.task('db-copy-private', function(cb) {
  var options = {
    dryRun : false,
    silent : true,
    dest : "root@public.languagedepot.org",
    password : process.env.password_db,
    user: process.env.USER
  };
  execute(
    'ssh -C <%= dest %> mysqldump -u <%= user %> --password=<%= password %> languagedepotpvt | mysql -u <%= user %> --password=<%= password %> -D languagedepotpvt',
    options,
    cb
  );
});

gulp.task('db-backup', function(cb) {
	  var options = {
	    dryRun : false,
	    silent : true,
	    password : process.env.password_db,
	    user: process.env.USER
	  };
	  execute(
	    'mysqldump -u <%= user %> --password=<%= password %> languagedepot languagedepotpvt | gzip > data/backup.sql.gz',
	    options,
	    cb
	  );
	});

gulp.task('start-webdriver', function(cb) {
  var options = {
      command: '/usr/local/bin/webdriver-manager',
      arguments: ['start'],
      sentinal: 'Started org.openqa.jetty.jetty.Server'
  };
  start_server(options, cb);
});

gulp.task('start-php', function(cb) {
  var options = {
      command: '/usr/bin/php',
      arguments: ['-S', 'localhost:8000', '-t', 'htdocs'],
      sentinal: 'Press Ctrl-C to quit.'
  };
  start_server(options, cb);
//execute('/usr/bin/php -S localhost:8000 -t htdocs &', null, cb);
});

gulp.task('test-e2e', function(cb) {
  execute(
    'protractor tests/e2e/phantom-conf.js',
    null,
    cb
  );
});

gulp.task('test-chrome', function(cb) {
  execute(
    'protractor tests/e2e/chrome-conf.js',
    null,
    cb
  );
});

gulp.task('test-current', function(cb) {
  execute(
    'protractor tests/e2e/phantom-conf.js',
    null,
    cb
  );
});

gulp.task('test', function(cb) {
  execute('/usr/bin/env php src/vendor/bin/phpunit -c phpunit.xml', null, function(err) {
    cb(null); // Swallow the error propagation so that gulp doesn't display a nodejs backtrace.
  });
});

gulp.task('watch', function() {
  gulp.watch([paths.src_api, paths.test], ['test']);
});
