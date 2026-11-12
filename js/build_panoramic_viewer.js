#!/usr/bin/env node

// Bundle npm modules into deployment assets; no package manager or network is needed at runtime.
const { buildSync } = require('esbuild');
const { copyFileSync, mkdirSync } = require('fs');

buildSync({
    entryPoints: ['plugins/panoramic_viewer/js/panoramic_viewer.js'],
    outfile: 'lib/panoramic-viewer/viewer.js',
    bundle: true,
    minify: true,
    format: 'iife',
    target: ['es2020'],
    legalComments: 'eof',
});
mkdirSync('plugins/panoramic_viewer/licenses', { recursive: true });
copyFileSync('node_modules/@photo-sphere-viewer/core/LICENSE', 'plugins/panoramic_viewer/licenses/photo-sphere-viewer.txt');
copyFileSync('node_modules/three/LICENSE', 'plugins/panoramic_viewer/licenses/three.txt');
