module.exports = function(grunt) {
  // Project configuration.
  grunt.initConfig({
    checktextdomain: {
      options:{
        text_domain: 'activitypub',
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
        src:  [
          '**/*.php',         // Include all files
          '!sass/**',         // Exclude sass/
          '!node_modules/**', // Exclude node_modules/
          '!tests/**',        // Exclude tests/
          '!vendor/**',       // Exclude vendor/
          '!build/**',        // Exclude build/
          '!static/**',       // Exclude static resources
        ],
        expand: true
     }
   },

    wp_readme_to_markdown: {
      target: {
        files: {
          'README.md': 'readme.txt'
        },
      },
      options: {
        pre_convert: function( readme ) {
          return readme.replace( /\*\*Note\*\*:/g, "> [!NOTE]\n>" );
        }
      }
    }
  });

  grunt.loadNpmTasks('grunt-wp-readme-to-markdown');
  grunt.loadNpmTasks('grunt-checktextdomain');

  // Default task(s).
  grunt.registerTask('default', ['wp_readme_to_markdown', 'checktextdomain']);
};
