/*
* Polyfills used to provide modern JS features on older browsers that do not natively support them
*/


/**
* Copy all enumerable own properties from one or more source objects to a target object
* 
* As per https://developer.mozilla.org/en-US/docs/MDN/About#Copyrights_and_licenses this code is in the public domain:
* "Any copyright is dedicated to the Public Domain. http://creativecommons.org/publicdomain/zero/1.0/"
* 
* @param  {object}  target   Target object
* @param  {object}  varArgs  One or more source objects
* 
* @return {object} Returns the target object
*/
if (typeof Object.assign !== 'function') {
  // Must be writable: true, enumerable: false, configurable: true
  Object.defineProperty(Object, "assign", {
    value: function assign(target, varArgs) { // .length of function is 2
      'use strict';
      if (target === null || target === undefined) {
        throw new TypeError('Cannot convert undefined or null to object');
      }

      var to = Object(target);

      for (var index = 1; index < arguments.length; index++) {
        var nextSource = arguments[index];

        if (nextSource !== null && nextSource !== undefined) { 
          for (var nextKey in nextSource) {
            // Avoid bugs when hasOwnProperty is shadowed
            if (Object.prototype.hasOwnProperty.call(nextSource, nextKey)) {
              to[nextKey] = nextSource[nextKey];
            }
          }
        }
      }
      return to;
    },
    writable: true,
    configurable: true
  });
}