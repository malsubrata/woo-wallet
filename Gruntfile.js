/* jshint node:true */
module.exports = function (grunt) {
    'use strict';

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
                    sourceMap: 'none'
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

        // Generate POT files.
        makepot: {
            options: {
                type: 'wp-plugin',
                domainPath: 'languages',
                potHeaders: {
                    'report-msgid-bugs-to': 'https://github.com/malsubrata/woo-wallet/issues',
                    'language-team': 'LANGUAGE <m.subrata1991@gmail.com>',
                    'last-translator': 'Subrata Mal<m.subrata1991@gmail.com>'
                }
            },
            dist: {
                options: {
                    potFilename: 'woo-wallet.pot',
                    exclude: [
                        'tmp/.*'
                    ]
                }
            }
        },

        // Check textdomain errors.
        checktextdomain: {
            options: {
                text_domain: 'woo-wallet',
                keywords: [
                    '__:1,2d',
                    '_e:1,2d',
                    '_x:1,2c,3d',
                    'esc_html__:1,2d',
                    'esc_html_e:1,2d',
                    'esc_html_x:1,2c,3d',
                    'esc_attr__:1,2d',
                    'esc_attr_e:1,2d',
                    'esc_attr_x:1,2c,3d',
                    '_ex:1,2c,3d',
                    '_n:1,2,4d',
                    '_nx:1,2,4c,5d',
                    '_n_noop:1,2,3d',
                    '_nx_noop:1,2,3c,4d'
                ]
            },
            files: {
                src: [
                    '**/*.php', // Include all files
                    '!tmp/**'                 // Exclude tmp/
                ],
                expand: true
            }
        },

        // Autoprefixer.
        postcss: {
            options: {
                processors: [
                    require('autoprefixer')({
                        browsers: [
                            '> 0.1%',
                            'ie 8',
                            'ie 9'
                        ]
                    })
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
    grunt.loadNpmTasks('grunt-wp-i18n');
    grunt.loadNpmTasks('grunt-checktextdomain');
    grunt.loadNpmTasks('grunt-contrib-jshint');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-watch');

    // Register tasks
    grunt.registerTask('default', [
        'js',
        'css',
        'i18n'
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
        'cssmin',
        'concat'
    ]);

    // Only an alias to 'default' task.
    grunt.registerTask('dev', [
        'default'
    ]);

    grunt.registerTask('i18n', [
        'checktextdomain',
        'makepot'
    ]);
};