# leaflet-shades
Leaflet plugin for creating gray overlay in unselected areas.
This plugin adds onto Leaflet.Editable which makes geometries editable in Leaflet (https://github.com/Leaflet/Leaflet.Editable)

Leaflet shades specifically expands on the Rectangle Editor of Leaflet.Editable. 
Originally, Leaflet.Editable's geometries have a blue overlay within the geometry. 
Using Leaflet shades, the area inside the geometry now has a transparent overlay while the unselected regions have a gray overlay. This is so that the selected region can be seen while the unselected regions are slightly hidden. 

# Requirements 
Leaflet, Leaflet.Editable, and Leaflet.Path.Drag are all embedded in the Leaflet Shades plugin. Leaflet is required before adding Leaflet Shades.

Leaflet Shades supports Leaflet v1.2.0, Leaflet.Editable v.1.1.0, and Leaflet.Path.Drag 0.0.6.

Leaflet.Editable syntax is also required to start drawing the rectangle or enable editing on an already existing rectangle as seen in the "Basic Usage" instructions step 4.

# Basic Usage: 
<b> Step 1: </b> Clone the Leaflet Shades repository by doing:

```
git clone git@github.com:mkong0216/leaflet-shades.git
```

<b> Step 2: </b> In HTML, import the required Leaflet Javascript and CSS files along with the Javascript and CSS files for the leaflet-shades plugin. 

```html
<!-- Load Leaflet and Leaflet-Shades stylesheets -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.2.0/dist/leaflet.css" />
<link rel="stylesheet" href="./src/css/leaflet-shades.css" />
 
<!-- Load Javascript files for Leaflet and Leaflet-Shades -->
<script src="https://unpkg.com/leaflet@1.2.0/dist/leaflet.js"></script>
<script src="./dist/leaflet-shades.js"></script>
```

<b> Step 3: </b> In Javascript, initialize your Leaflet Map and enable editable in your initialization

```javascript
var map = L.map('map', {editable: true});
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
map.setView([0,0], 5);
```

<b> Step 4: </b> In Javascript, start drawing your rectangle using Leaflet.Editable's `startRectangle()` or allow an already existing rectangle to be edited by using `enableEdit()`

```javascript
// Start drawing rectangle
map.editTools.startRectangle();

// Enable edit on already existing rectangle
const rect = L.rectangle([[54.559322, -5.767822], [56.1210604, -3.021240]]).addTo(map);
rect.enableEdit();
```

<b> Step 5a: </b> In Javascript, create your shades and add it onto your map 

```javascript
var shades = new L.LeafletShades();
// or you can do 
// var shades = L.leafletShades();
shades.addTo(map); 
```

<b> Step 5b: </b> If you want to add shades to an already existing rectangle on the map, pass the bounds of the rectangle to the Leaflet Shades constructor as an object before adding it to the map

```javascript
// rect was previously created in step 4
var shades = new L.LeafletShades({bounds: rect.getBounds()});
shades.addTo(map);
```

Now you're done! Go to: https://mkong0216.github.io/leaflet-shades/examples to see the finished product. Alternatively, click <a href='https://mkong0216.github.io/leaflet-shades/examples/bounds'> here </a> to see how Leaflet Shades works with an already defined rectangle using step 5b. 

<b> Sidenote: </b> In Javascript, you can remove your shades from the map by doing:

```javascript
map.removeLayer(shades);
```

# Leaflet Shades as Module 
You can also install Leaflet Shades as a module by doing: <br/>

```
npm install leaflet-shades
```

And then import it into your module system. For example, with Browserify:

```javascript
// Require Leaflet first
var L = require('leaflet');

// You can store a reference to the leaflet shades constructor in require
var shades = require('leaflet-shades');

// Now you can do steps 3 to 5 from "Basic Usage" instructions above
```


# API Documentation: 
Leaflet-Shades only has one public method which is the `setDimensions(element, dimensions)` method. 
This method takes an element and an object containing the desired dimensions for this element. 
For example, if you wanted to manually set the dimensions for the left side of the selected region you can do this:

```javascript
// Defining the width and height of the shade along with the top and left position of the shade
var dimensions = {
  width: 500,
  height: 500,
  top: 10,
  left: 10
}

// Element passed into this method can be either 
// shades._leftShade, shades._rightShade, shades._topShade, or shades._bottomShade 
shades.setDimensions(shades._leftShade, dimensions);
```
This will change the left shade to become 500px by 500px at position 10px from the top and 10px to the left.

# Events

Leaflet Shades listens to events fired by Leaflet.Editable (http://leaflet.github.io/Leaflet.Editable/doc/api.html) and Leaflet. 

When the Leaflet.Editable geometry is resized or dragged, firing the events `editable:vertex:dragend` and `editable:dragend`, respectively, Leaflet Shades updates the shades' dimensions. When the Leaflet map is zoomed in/out or panned, firing the event `moveend`, Leaflet Shades updates the shades' dimensions as well. 

Leaflet Shades provides the event: 
`shades:bounds-changed` which fires whenever shades' dimensions are updated and allow users to access the new values for the bounds of the selected region through `event.bounds`

To use the `shades:bounds-changed` event to access the values of the region's bounds, you can do:

```javascript 
shades.on('shades:bounds-changed', function(event) {
  var bounds = event.bounds
});
```

All events, including events from Leaflet and Leaflet Editable that Leaflet Shades listens to, can be seen in this <a href='https://mkong0216.github.io/leaflet-shades/examples/events'> demo </a>
