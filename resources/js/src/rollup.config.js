import terser from '@rollup/plugin-terser';
import copyright from './copyright.js';
import preserveCopyright from '../../../../../../../../../../CI/Copyright-Checker/preserveCopyright.js';

export default {
  external: [
    'document',
    'SafeExamBrowser',
    'ilias',
  ],
  input: './seb.js',
  output: {
    file: '../dist/seb.js',
    format: 'iife',
    banner: copyright,
    globals: {
      document: 'document',
      SafeExamBrowser: 'SafeExamBrowser',
      ilias: 'il',
    },
    plugins: [
      terser({
        format: {
          comments: preserveCopyright,
        },
      }),
    ],
  },
};
