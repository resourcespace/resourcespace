/*
 * leaflet-shades
 * Leaflet plugin to add transparent overlay within selected region 
 * of Leaflet.Editable and a gray overlay outside selected region
 *
 * License: MIT
 * (c) Mandy Kong
 */

(function (root, factory) { // eslint-disable-line no-extra-semi
  var L;
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define(['leaflet'], factory);
  } else if (typeof module === 'object' && module.exports) {
    // Node. Does not work with strict CommonJS, but
    // only CommonJS-like environments that support module.exports,
    // like Node.
    L = require('leaflet');
    module.exports = factory(L);
  } else {
    // Browser globals (root is window)
    if (typeof root.L === 'undefined') {
      throw new Error('Leaflet must be loaded first');
    }
    root.LeafletShades = factory(root.L);
  }
}(this, function (L) {
'use strict';
// var L = require('leaflet');
var LeafletShades = require('./leaflet-shades');

// Automatically attach to Leaflet's `L` namespace.
L.LeafletShades = LeafletShades;

L.leafletShades = function(opts) {
  return new LeafletShades(opts);
}

  // Return value defines this module's export value.
  return LeafletShades;
}));

