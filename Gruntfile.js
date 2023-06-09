/* jshint node:true */
module.exports = function (grunt) {
    'use strict';
    const sass = require('node-sass');
    grunt.initConfig({

        // Setting folder templates.
        dirs: {
            css: 'assets/css',
            js: 'assets/js'
        },

        // JavaScript linting with JSHint.
        jshint: {
            options: {
                jshintrc: '.jshintrc'
            },
            all: [
                'Gruntfile.js',
                '<%= dirs.js %>/admin/*.js',
                '!<%= dirs.js %>/admin/*.min.js',
                '<%= dirs.js %>/frontend/*.js',
                '!<%= dirs.js %>/frontend/*.min.js'
            ]
        },

        // Sass linting with Stylelint.
        stylelint: {
            options: {
                configFile: '.stylelintrc'
            },
            all: [
                '<%= dirs.css %>/*.scss'
            ]
        },

        // Minify .js files.
        uglify: {
            options: {
                ie8: true,
                parse: {
                    strict: false
                },
                output: {
                    comments: /@license|@preserve|^!/
                }
            },
            admin: {
                files: [{
                        expand: true,
                        cwd: '<%= dirs.js %>/admin/',
                        src: [
                            '*.js',
                            '!*.min.js'
                        ],
                        dest: '<%= dirs.js %>/admin/',
                        ext: '.min.js'
                    }]
            },
            frontend: {
                files: [{
                        expand: true,
                        cwd: '<%= dirs.js %>/frontend/',
                        src: [
                            '*.js',
                            '!*.min.js'
                        ],
                        dest: '<%= dirs.js %>/frontend/',
                        ext: '.min.js'
                    }]
            }
        },

        // Compile all .scss files.
        sass: {
            compile: {
                options: {
                    implementation: sass
                },
                files: [{
                        expand: true,
                        cwd: '<%= dirs.css %>/',
                        src: ['*.scss'],
                        dest: '<%= dirs.css %>/',
                        ext: '.css'
                    }]
            }
        },

        // Generate RTL .css files
        rtlcss: {
            wallet: {
                expand: true,
                cwd: '<%= dirs.css %>',
                src: [
                    '*.css',
                    '!*-rtl.css'
                ],
                dest: '<%= dirs.css %>/',
                ext: '-rtl.css'
            }
        },

        // Minify all .css files.
        cssmin: {
            minify: {
                expand: true,
                cwd: '<%= dirs.css %>/',
                src: ['*.css'],
                dest: '<%= dirs.css %>/',
                ext: '.css'
            }
        },

        // Watch changes for assets.
        watch: {
            css: {
                files: ['<%= dirs.css %>/*.scss'],
                tasks: ['sass', 'rtlcss', 'cssmin']
            },
            js: {
                files: [
                    '<%= dirs.js %>/admin/*js',
                    '<%= dirs.js %>/frontend/*js',
                    '!<%= dirs.js %>/admin/*.min.js',
                    '!<%= dirs.js %>/frontend/*.min.js'
                ],
                tasks: ['jshint', 'uglify']
            }
        },

        // Autoprefixer.
        postcss: {
            options: {
                processors: [
                    require('autoprefixer')()
                ]
            },
            dist: {
                src: [
                    '<%= dirs.css %>/*.css'
                ]
            }
        }
    });

    // Load NPM tasks to be used here
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-rtlcss');
    grunt.loadNpmTasks('grunt-postcss');
    grunt.loadNpmTasks('grunt-stylelint');
    grunt.loadNpmTasks('grunt-contrib-jshint');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-watch');

    // Register tasks
    grunt.registerTask('default', [
        'js',
        'css'
    ]);

    grunt.registerTask('js', [
        'jshint',
        'uglify:admin',
        'uglify:frontend'
    ]);

    grunt.registerTask('css', [
        'sass',
        'rtlcss',
        'postcss',
        'cssmin'
    ]);

    // Only an alias to 'default' task.
    grunt.registerTask('dev', [
        'default'
    ]);
};