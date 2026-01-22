annotorious.plugin.RSFaceRecognition = function(opt_config_options)
    {
    console.log('RSFaceRecognition: setting config options');

    this._ANNOTATIONS_ENDPOINT         = opt_config_options['annotations_endpoint'];
    this._FACIAL_RECOGNITION_ENDPOINT  = opt_config_options['facial_recognition_endpoint'];
    this._RESOURCE                     = opt_config_options['resource'];
    this._CSRF_IDENTIFIER              = opt_config_options['fr_csrf_identifier'];
    this._FACIAL_RECOGNITION_TAG_FIELD = opt_config_options['facial_recognition_tag_field'];
    this._CSRF_TOKEN                   = '';

    // CSRF check (as it can be disabled, we consider this optional). CSRF identifier will/ should always be set to at least
    // the default value
    if(opt_config_options.hasOwnProperty(this._CSRF_IDENTIFIER))
        {
        this._CSRF_TOKEN = opt_config_options[this._CSRF_IDENTIFIER];
        }
    }

annotorious.plugin.RSFaceRecognition.prototype.initPlugin = function(anno)
    {
    console.log('RSFaceRecognition: initializing plugin');

    var self = this;
    self.image_prepared = false;
    var check_fr_ready = false;
    var selectionCompletedEvent_cache;

    jQuery.get(
        self._ANNOTATIONS_ENDPOINT,
        {
        action: 'get_allowed_fields'
        },
        function (response)
            {
            if(typeof response.data !== 'undefined' && response.data.length > 1)
                {
                check_fr_ready = true;
                }
            else if(typeof response.data !== 'undefined' && response.data.length == 1)
                {
                if(response.data[0] != self._FACIAL_RECOGNITION_TAG_FIELD)
                    {
                    check_fr_ready = true;
                    }
                }
            },
        'json');

    // When multiple fields are used for tagging we don't call certain features unless the facial recognition tag field 
    // has been selected
    jQuery('#CentralSpace').on('RSTaggingSelectedField', function(e, resource_type_field)
        {
        self.facialRecognitionProcess(self, resource_type_field, selectionCompletedEvent_cache.shape);
        });

    jQuery('#CentralSpace').on(
        'RSFaceRecognitionSelectionCompleted',
        (e, shape) => {
            console.debug('RSFaceRecognition: onRSFaceRecognitionSelectionCompleted');
            self.facialRecognitionProcess(self, self._FACIAL_RECOGNITION_TAG_FIELD, shape);
        }
    );

    anno.addHandler('onSelectionCompleted', function (selectionCompletedEvent)
        {
        console.debug('RSFaceRecognition: onSelectionCompleted, event = %o', selectionCompletedEvent);
        selectionCompletedEvent_cache = selectionCompletedEvent;

        // Do not run if multiple annotation fields (ie $annotate_fields) and currently selected one is not the same 
        // as the expected FR field (ie $facial_recognition_tag_field)
        if(check_fr_ready && (anno.l[0].bb[0]._resource_type_field !== self._FACIAL_RECOGNITION_TAG_FIELD))
            {
            return;
            }

        jQuery('#CentralSpace').trigger('RSFaceRecognitionSelectionCompleted', [selectionCompletedEvent.shape]);
        });
    }

annotorious.plugin.RSFaceRecognition.prototype.onInitAnnotator = function(annotator) {}


/**
* Render an Annotation tag
* 
* @param {Object} {Tag} tag
* @param {String}       extra_classes - Add extra CSS classes to a tag
* @param {Boolean}      actionable    - Set to true if tags should be wrapped in links to
*                                     allow triggering actions
* 
* @returns {Element}
*/
annotorious.plugin.RSFaceRecognition.prototype.renderTag = function(tag, extra_classes, actionable)
    {
    var tag_element = document.createElement('span');

    // Add CSS classes
    tag_element.className = 'RSTagging-tag';
    if(typeof extra_classes !== 'undefined' && extra_classes != '')
        {
        tag_element.className += ' ' + extra_classes;
        }

    tag_element.innerHTML = '<i class="icon-database" aria-hidden="true"></i>' + tag.name;

    // Wrap tags in links in order to trigger certain actions (e.g: accept/denied (suggested)/ remove tags)
    if(typeof actionable !== 'undefined' && typeof actionable === 'boolean' && actionable == true)
        {
        var a_element = document.createElement('a');
        a_element.setAttribute('href', '#');
        a_element.setAttribute('onclick', 'return false;');

        // Actionable tags also have extra information which we'll need later on for 
        // saving an annotation (either new/ existing one)
        tag_element.dataset.ref               = tag.ref;
        tag_element.dataset.resourceTypeField = tag.resource_type_field;
        tag_element.dataset.name              = tag.name;
        tag_element.dataset.parent            = tag.parent;
        tag_element.dataset.orderBy           = tag.order_by;

        a_element.appendChild(tag_element);

        return a_element;
        }

    return tag_element;
    }


annotorious.plugin.RSFaceRecognition.prototype.prepareSelectedArea = function(self, shape)
    {
    self.image_prepared = false;
    var post_data = {
        action    : 'prepare_selected_area',
        resource  : self._RESOURCE,
        shape     : shape,
    };

    // empty CSRF token might mean CSRF is not enabled. If it is enabled, the system will error about it anyway.
    if(self._CSRF_TOKEN != '')
        {
        post_data[self._CSRF_IDENTIFIER] = self._CSRF_TOKEN;
        }

    jQuery.post(
        self._FACIAL_RECOGNITION_ENDPOINT,
        post_data,
        function (response)
            {
            if(typeof response.error !== 'undefined')
                {
                styledalert('Error: ' + response.error.title, response.error.detail);

                console.error('RSFaceRecognition: '
                    + response.error.status + ' '
                    + response.error.title + ' - '
                    + response.error.detail);

                return;
                }

            self.image_prepared = response.data;

            console.info('RSFaceRecognition: image_prepared = ' + self.image_prepared);
            },
        'json');
    }

annotorious.plugin.RSFaceRecognition.prototype.predictLabel = function(self)
    {
    var tags_container = document.getElementById('RSTagging-tags');

    // IMPORTANT: because onSelectionCompleted event gets triggered later, we need to wait in order to
    // get the shape information
    setTimeout(function ()
        {
        if(!self.image_prepared)
            {
            return;
            }

        // With big model states (tens of thousands of faces), it can take a bit to get a prediction
        CentralSpaceShowProcessing();

        jQuery.get(
            self._FACIAL_RECOGNITION_ENDPOINT,
            {
            action    : 'predict_label',
            resource  : self._RESOURCE
            },
            function (response)
                {
                if(typeof response.error !== 'undefined')
                    {
                    styledalert('Error: ' + response.error.title, response.error.detail);

                    CentralSpaceHideProcessing();

                    console.error('RSFaceRecognition: '
                    + response.error.status + ' '
                    + response.error.title + ' - '
                    + response.error.detail);

                    return;
                    }

                // Add a clearer after tags container (for both new and existing annotations)
                var clearer       = document.createElement('div');
                clearer.className = 'clearer';
                tags_container.parentNode.insertBefore(clearer, tags_container.nextSibling);

                // Add the returned tag
                tags_container.appendChild(self.renderTag(response.data, 'suggested', true));

                CentralSpaceHideProcessing();
                },
            'json');
        }, 
        200);
    }

annotorious.plugin.RSFaceRecognition.prototype.facialRecognitionProcess = function(self, resource_type_field, shape)
    {
    console.debug('RSFaceRecognition: facialRecognitionProcess(%o, %o)', resource_type_field, shape);
    if(resource_type_field == self._FACIAL_RECOGNITION_TAG_FIELD)
        {
        console.debug('RSFaceRecognition: facialRecognitionProcess - preparing the selection & predicting the label');
        self.prepareSelectedArea(self, shape);
        self.predictLabel(self);
        }
    };