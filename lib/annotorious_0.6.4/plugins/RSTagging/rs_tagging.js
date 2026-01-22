annotorious.plugin.RSTagging = function (opt_config_options)
    {
    console.log('RSTagging: setting config options');

    this._ANNOTATIONS_ENDPOINT = opt_config_options['annotations_endpoint'];
    this._NODES_ENDPOINT       = opt_config_options['nodes_endpoint'];
    this._RESOURCE             = opt_config_options['resource'];
    this._CSRF_IDENTIFIER      = opt_config_options['csrf_identifier'];
    this._CSRF_TOKEN           = '';

    // CSRF check (as it can be disabled, we consider this optional). CSRF identifier will/ should always be set to at least
    // the default value
    if(opt_config_options.hasOwnProperty(this._CSRF_IDENTIFIER))
        {
        this._CSRF_TOKEN = opt_config_options[this._CSRF_IDENTIFIER];
        }

    this._READ_ONLY = opt_config_options['read_only'];
    if(typeof this._READ_ONLY === 'undefined' || typeof this._READ_ONLY !== 'boolean')
        {
        this._READ_ONLY = true;
        }

    this._PAGE = opt_config_options['page'];
    if(typeof this._PAGE === 'undefined'
        || typeof this._PAGE !== 'number'
        || (typeof this._PAGE === 'number' && this._PAGE <= 0)
    )
        {
        this._PAGE = 0;
        }

    // Allow higher level context to provide translations (i.e $lang subset)
    annotorious.plugin.RSTagging.prototype._i18n_get_translated = (name) => {
        let lang = opt_config_options['lang'];
        if (typeof lang === 'undefined' || typeof lang !== 'object') {
            lang = {};
        }

        if (name in lang) {
            return lang[name];
        } else {
            console.warn('Language string %o was not provided to the RSTagging plugin', name);
            return name;
        }
    }

    // Allow higher level context to provide relevant ResourceSpace config options
    annotorious.plugin.RSTagging.prototype._get_rs_config = (option) => {
        let rs_config = opt_config_options['rs_config'];
        if (typeof rs_config === 'undefined' || typeof rs_config !== 'object') {
            rs_config = {};
        }

        if (option in rs_config) {
            return rs_config[option];
        } else {
            console.warn('ResourceSpace config option %o was not provided to the RSTagging plugin.', option);
            return null;
        }
    }

    /*
    Potential feature (implemented on popup only)
    The ability to show users that a tag which was originally attached to an
    annotation is no longer part of that resource metadata and can no longer
    be searched by it. It will show a warning icon before the tag.

    IMPORTANT: if enabling this feature, just uncomment the line below and remove
    the hardcoded value for warn_tags_unsearchable
    */
    // this._WARN_TAGS_UNSEARCHABLE = opt_config_options['warn_tags_unsearchable'];
    this._WARN_TAGS_UNSEARCHABLE = false;

    // If user does not have access to any of the allowed fields to be bound to annotations,
    // put Annotorious in read-only mode (this happens in onInitAnnotator)
    this._access_to_fixed_list_fields = false;

    this._rs_fields = [];
    this.user_can_manage_text_annotation = false;
    }


annotorious.plugin.RSTagging.prototype.initPlugin = function (anno)
    {
    console.log('RSTagging: initializing plugin');

    var self = this;
    var default_post_data = {};

    // empty CSRF token might mean CSRF is not enabled. If it is enabled, the system will error about it anyway.
    if(self._CSRF_TOKEN != '')
        {
        default_post_data[self._CSRF_IDENTIFIER] = self._CSRF_TOKEN;
        }

    // When in public view (external shares) context, the user gets read-only access (see annotationEditable())
    const k = new URL(self._ANNOTATIONS_ENDPOINT).searchParams.get('k');
    if (!(self._get_rs_config('annotate_public_view') && k)) {
        api(
            'checkperm',
            {perm: 'o'},
            response => self.user_can_manage_text_annotation = response,
            default_post_data
        )
    }

    if(self._READ_ONLY)
        {
        console.info('RSTagging: Preparing to run in read-only mode!');
        return;
        }

    // Load fields for wizard
    jQuery.get(
        self._ANNOTATIONS_ENDPOINT,
        {
        action: 'get_allowed_fields',
        resource: self._RESOURCE,
        },
        function (response)
            {
            if(typeof response.error !== 'undefined' && response.error.status == 404)
                {
                console.info('RSTagging: User has no access to bound fields.');
                return;
                }

            if(typeof response.data !== 'undefined' && response.data.length > 0)
                {
                self._access_to_fixed_list_fields = true;
                console.log('RSTagging: User has access to at least one allowed field. Annotorious will stop running in read-only mode (if applicable)!');

                for(var key in response.data)
                    {
                    self._rs_fields[key] = response.data[key];
                    }
                }
            },
        'json'
    );

    /*****************************
    * Handlers beyond this point *
    *****************************/

    // Get annotation information and display it on editor component
    anno.addHandler('onEditorShown', function (annotation)
        {
        var tags_container = document.getElementById('RSTagging-tags');

        // Add a clearer after tags container (for both new and existing annotations)
        var clearer       = document.createElement('div');
        clearer.className = 'clearer';
        tags_container.parentNode.insertBefore(clearer, tags_container.nextSibling);

        // If this is a new annotation, any information/ tags displayed will not be saved
        // at this point so we manage dynamically
        if(!annotation)
            {
            return;
            }

        for(var key in annotation.tags)
            {
            if(!self._WARN_TAGS_UNSEARCHABLE && annotation.tags[key].tag_searchable == '')
                {
                continue;
                }

            tags_container.appendChild(self.renderTag(annotation.tags[key], '', true));
            }
        });

    // Create annotation
    anno.addHandler('onAnnotationCreated', function (annotation)
        {
        console.debug('Handler - onAnnotationCreated(annotation = %o)', annotation);

        // Set ResourceSpace specific properties
        annotation.resource            = self._RESOURCE;
        annotation.resource_type_field = self._resource_type_field;
        annotation.page                = self._PAGE;

        var post_data = Object.assign({}, default_post_data);
        post_data.action = 'create';
        post_data.resource = self._RESOURCE;
        post_data.annotation = annotation;

        jQuery.post(
            self._ANNOTATIONS_ENDPOINT,
            post_data,
            function (response)
                {
                if(typeof response.error !== 'undefined')
                    {
                    styledalert('Error: ' + response.error.title, response.error.detail);

                    console.error('RSTagging: ' + response.error.status + ' ' + response.error.title + ' - ' + response.error.detail);

                    return;
                    }

                if(typeof response.data === 'undefined')
                    {
                    console.error('RSTagging: Something went wrong. Expecting data back but missing from response');
                    return;
                    }

                annotation.ref = response.data;
                console.log('RSTagging: ResourceSpace created annotation with ID ' + response.data);
                },
            'json');
        });

    // Update annotation
    anno.addHandler('onAnnotationUpdated', function (annotation)
        {
        var post_data = Object.assign({}, default_post_data);
        post_data.action = 'update';
        post_data.resource = self._RESOURCE;
        post_data.annotation = annotation;

        jQuery.post(
            self._ANNOTATIONS_ENDPOINT,
            post_data,
            function (response)
                {
                if(typeof response.error !== 'undefined')
                    {
                    styledalert('Error: ' + response.error.title, response.error.detail);

                    console.error('RSTagging: ' + response.error.status + ' ' + response.error.title + ' - ' + response.error.detail);

                    return;
                    }

                console.log('RSTagging: ResourceSpace updated annotation with ID ' + annotation.ref);
                },
            'json');
        });

    // Delete annotation
    anno.addHandler('onAnnotationRemoved', function (annotation)
        {
        var post_data = Object.assign({}, default_post_data);
        post_data.action = 'delete';
        post_data.annotation_id = annotation.ref;

        jQuery.post(
            self._ANNOTATIONS_ENDPOINT,
            post_data,
            function (response)
                {
                if(typeof response.data !== 'undefined' && response.data == true)
                    {
                    console.log('RSTagging: deleted annotation (ID ' + annotation.ref + ')');
                    }
                else if(typeof response.data !== 'undefined' && response.data == false)
                    {
                    styledalert('Error', 'Could not delete annotation!');

                    console.error('RSTagging: could not delete annotation (ID ' + annotation.ref + ')');
                    }
                },
            'json');
        });
    }


annotorious.plugin.RSTagging.prototype.onInitAnnotator = function (annotator)
    {
    console.log('RSTagging: onInitAnnotator...');

    var self = this;

    // Remove the "Click and drag to annotate" we get on top of the image
    jQuery('.annotorious-hint').remove();
    console.log('RSTagging: removed .annotorious-hint element from DOM');

    // Get annotations
    jQuery.get(
        self._ANNOTATIONS_ENDPOINT,
        {
        action  : 'get_resource_annotations',
        resource: self._RESOURCE,
        page    : self._PAGE
        },
        function (response)
            {
            if(typeof response.data !== 'undefined' && response.data.length > 0)
                {
                for(var key in response.data)
                    {
                    if(!self._access_to_fixed_list_fields)
                        {
                        response.data[key].editable = false;
                        }

                    anno.addAnnotation(response.data[key]);
                    }
                }
            },
        'json'
    );

    // Put Annotorious in read-only mode if needed
    if(self._READ_ONLY || !self._access_to_fixed_list_fields)
        {
        anno.hideSelectionWidget();
        console.warn('RSTagging: Running in read-only mode!');
        }

    self._extendPopup(annotator);
    self._extendEditor(annotator);

    // User - tags interactions/ actions
    jQuery('.annotorious-editor').on('click', 'a > .RSTagging-tag', function (e)
        {
        e.preventDefault();

        /*
        A tag can only have 2 states:
         - selected  <=> tag is part of this annotation and resource metadata (i.e can be searched by it)
         - suggested <=> user has searched for new tags in this field and has new suggested keywords which 
                         have not yet been approved OR selected tags have been "deselected"
        */
        var tag        = jQuery(this);
        var annotation = annotator.editor.getAnnotation();

        if(typeof annotation.tags === 'undefined')
            {
            annotation.tags = [];
            }

        tag.toggleClass('suggested');

        // Going back to suggested? log it and return. We have already removed it from tags
        if(tag.hasClass('suggested'))
            {
            console.log('RSTagging: Removing tag - ' + this.dataset.name);

            for(key in annotation.tags)
                {
                if(this.dataset.ref == annotation.tags[key].ref)
                    {
                    annotation.tags.splice(key, 1);
                    }
                }

            return;
            }

        // Accepted tag? add it to tags array
        console.log('RSTagging: Accepting tag - ' + this.dataset.name);

        // If user accepted this tag, then reset the text field so that user can search for new tags
        jQuery('.annotorious-editor-text')[0].value = '';

        // Make sure values are of expected types (int, string or null).
        // Anything else, will invalidate tags and not add them
        var accepted_tag_ref               = parseInt(this.dataset.ref, 10);
        var accepted_tag_resourceTypeField = parseInt(this.dataset.resourceTypeField, 10);
        var accepted_tag_name              = String(this.dataset.name);
        var accepted_tag_parent            = parseInt(this.dataset.parent, 10);
        var accepted_tag_orderBy           = parseInt(this.dataset.orderBy, 10);

        annotation.tags.push(
            {
            ref                 : (isNaN(accepted_tag_ref) ? null : accepted_tag_ref),
            resource_type_field : (isNaN(accepted_tag_resourceTypeField) ? null : accepted_tag_resourceTypeField),
            name                : accepted_tag_name,
            parent              : (isNaN(accepted_tag_parent) ? null : accepted_tag_parent),
            order_by            : (isNaN(accepted_tag_orderBy) ? null : accepted_tag_orderBy)
            });
        });
    }


annotorious.plugin.RSTagging.prototype._extendPopup = function (annotator) {
    console.log('RSTagging: extending Annotorious popup...');

    var self = this;

    if (!self._get_rs_config('annotate_text_adds_comment')) {
        jQuery('.annotorious-popup-text').hide();
        console.log('RSTagging: Comment annotations disabled (i.e. always hide .annotorious-popup-text element)');
    }

    annotator.popup.addField(function (annotation)
        {
        var tags_container_id = 'RSTagging-popup-tags';

        if (self._get_rs_config('annotate_text_adds_comment')) {
            if (annotation.text === '') {
                jQuery('.annotorious-popup-text').hide();
            } else {
                jQuery('.annotorious-popup-text').show();
            }
        }

        if (annotation.text !== '' && self.user_can_manage_text_annotation) {
            jQuery('.annotorious-popup-button-edit').css('visibility', 'hidden');
        } else {
            jQuery('.annotorious-popup-button-edit').css('visibility', 'visible');
        }

        /*
        Annotorious uses one popup box for all annotations.
        We have to remove all the tags from it that may belong to an 
        old annotation we used before
        */
        jQuery('#' + tags_container_id).remove();

        if(typeof annotation.tags === 'undefined' || annotation.tags.length == 0)
            {
            return false;
            }

        var tags_container = document.createElement('div');
        tags_container.setAttribute('id', tags_container_id);

        for(var key in annotation.tags)
            {
            if(!self._WARN_TAGS_UNSEARCHABLE && annotation.tags[key].tag_searchable == '')
                {
                continue;
                }

            tags_container.appendChild(self.renderTag(annotation.tags[key]));
            }

        var clearer = document.createElement('div');
        clearer.className = 'clearer';
        tags_container.appendChild(clearer);

        return tags_container;
        });

    if (self._get_rs_config('annotate_show_author')) {
        annotator.popup.addField(function (annotation) {
            const author_html_id = 'RSTagging-author';

            // Annotorious uses one popup box for all annotations.
            // Remove the author from it as it may belong to the previous annotation.
            jQuery(`#${author_html_id}`).remove();

            if (annotation.author && annotation.author !== '') {
                let author_el = document.createElement('p');
                author_el.setAttribute('id', author_html_id);
                author_el.textContent = `@${annotation.author}`;
                return author_el;
            }

            return false;
        });
    }
}


annotorious.plugin.RSTagging.prototype._extendEditor = function (annotator)
    {
    console.log('RSTagging: extending Annotorious editor...');

    var self = this;

    // Set the default editor text input placeholder (two modes: comments OR tag field; see dropdown_element.onchange)
    annotator.editor.Ia.F.setAttribute(
        'placeholder',
        this._get_rs_config('annotate_text_adds_comment')
            ? this._i18n_get_translated('annotorious_add_a_comment')
            : this._i18n_get_translated('annotorious_type_to_search_field')
    );

    // Add wizard
    // Note: if there is only one field allowed to be used for annotations, don't bother showing the dropdown,
    // make it the only field to search and just display the text box and tags
    annotator.editor.addField((annotation) => self._renderWizard(annotation, annotator));

    // Add the annotation tags container
    annotator.editor.addField(function (annotation)
        {
        var tags_container = document.createElement('div');
        tags_container.setAttribute('id', 'RSTagging-tags');

        return tags_container;
        });

    // Listen for keystroke events and run the search for that node name
    var search_timeout = null;
    annotator.editor.element.onkeyup = function (e)
        {
        // Comment (text) annotations mode
        if (self._resource_type_field === 0) {
            return false;
        }

        // Tagging resource (bound field) mode
        clearTimeout(search_timeout);
        search_timeout = setTimeout(function ()
            {
            var annotation = annotator.editor.getAnnotation();

            jQuery.get(
                self._NODES_ENDPOINT,
                {
                resource_type_field: self._resource_type_field,
                name               : annotation.text
                },
                function (response)
                    {
                    if(typeof response.error !== 'undefined' && response.error.status == 400)
                        {
                        return false;
                        }

                    jQuery('.RSTagging-tag.suggested').each(function (index, element)
                        {
                        element.remove();
                        });

                    var tags_container = document.getElementById('RSTagging-tags');

                    if(typeof response.data !== 'undefined' && response.data.length > 0)
                        {
                        // Build a list of tags and add them to the tags container
                        // Note: tags can only exist once in tag container based on their node ref
                        for(key in response.data)
                            {
                            var already_rendered_tags = tags_container.getElementsByClassName('RSTagging-tag');
                            var add_suggested_tag     = true;

                            for(var i = 0; i < already_rendered_tags.length; i++)
                                {
                                if(already_rendered_tags[i].dataset.ref == response.data[key].ref)
                                    {
                                    add_suggested_tag = false;

                                    break;
                                    }
                                }

                            if(add_suggested_tag)
                                {
                                tags_container.appendChild(self.renderTag(response.data[key], 'suggested', true));
                                }
                            }
                        }

                    // Search did not match any existing nodes.
                    // Check if we are allowed to add new options for this field and do it if we get green light
                    if(typeof response.data !== 'undefined' && response.data.length == 0)
                        {
                        var new_tag_name = annotation.text;

                        jQuery.get(
                            self._ANNOTATIONS_ENDPOINT,
                            {
                            action             : 'check_allow_new_tags', 
                            resource_type_field: self._resource_type_field
                            },
                            function (response)
                                {
                                if(typeof response.error !== 'undefined')
                                    {
                                    styledalert('Error: ' + response.error.title, response.error.detail);

                                    console.error('RSTagging: ' + response.error.status + ' ' + response.error.title + ' - ' + response.error.detail);

                                    return;
                                    }

                                if(typeof response.data !== 'undefined' && response.data === false)
                                    {
                                    return false;
                                    }

                                tags_container.appendChild(self.renderTag(
                                    {
                                    ref                 : null,
                                    resource_type_field : self._resource_type_field,
                                    name                : new_tag_name,
                                    parent              : null,
                                    order_by            : null
                                    },
                                    'suggested NewTag',
                                    true));
                                },
                                'json');
                        }

                    // Clear out searched text
                    annotation.text = '';
                    },
                'json');
            }, 500);
        }
    }


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
annotorious.plugin.RSTagging.prototype.renderTag = function (tag, extra_classes, actionable)
    {
    var tag_element = document.createElement('span');

    // Add CSS classes
    tag_element.className = 'RSTagging-tag';
    if(typeof extra_classes !== 'undefined' && extra_classes != '')
        {
        tag_element.className += ' ' + extra_classes;
        }

    // Mark tags that are not attached to a resource (ie. resource_node does not have a 
    // record for this node anymore) with a warning sign
    if(typeof tag.tag_searchable !== 'undefined' && tag.tag_searchable == '')
        {
        tag_element.innerHTML = '<i class="icon-triangle-alert" aria-hidden="true"></i>';
        }

    // NewTags (suggested tags as new entries for a field)
    if(typeof extra_classes !== 'undefined' && extra_classes != '' && extra_classes.indexOf('NewTag') > 0)
        {
        tag_element.innerHTML = '<i class="icon-plus" aria-hidden="true"></i>';
        }

    tag_element.innerHTML += tag.name;

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


/**
* Render wizard for selecting a field to bind the annotation to
* 
* @param {Annotation} annotation
* @param {Annotator} annotator
* 
* @returns {boolean|Element}
*/
annotorious.plugin.RSTagging.prototype._renderWizard = function (annotation, annotator)
    {
    var self = this;

    // User has access to only one field? Let Annotorious know there is only one field to use when saving annotations
    if(self._rs_fields.length == 1)
        {
        self._resource_type_field = parseInt(self._rs_fields[0].ref, 10);
        jQuery('.annotorious-editor-text').show();

        return false;
        }

    // Multiple fields are displayed as a dropdown
    if(self._rs_fields.length > 1)
        {
        var dropdown_element = document.createElement('select');
        dropdown_element.setAttribute('id', 'RSTagging-field-selector');
        dropdown_element.onchange = function () {
            self._resource_type_field = this.options[this.selectedIndex].value;

            if (self._resource_type_field > 0) {
                annotator.editor.Ia.F.setAttribute(
                    'placeholder',
                    self._i18n_get_translated('annotorious_type_to_search_field')
                );
                jQuery('#CentralSpace').trigger('RSTaggingSelectedField', [self._resource_type_field]);
            } else if (self._get_rs_config('annotate_text_adds_comment')) {
                annotator.editor.Ia.F.setAttribute(
                    'placeholder',
                    self._i18n_get_translated('annotorious_add_a_comment')
                );
            }
        }

        for(key in self._rs_fields)
            {
            let option = document.createElement('option');
            option.textContent = self._rs_fields[key].title;
            option.value = parseInt(self._rs_fields[key].ref, 10);

            // Looking at an existing annotation? Check if this is the field selected origianlly for it.
            if(annotation && option.value == annotation.resource_type_field)
                {
                option.selected = true;

                self._resource_type_field = option.value;

                jQuery('.annotorious-editor-text').show();
                }

            dropdown_element.appendChild(option);
            }
        }

    return dropdown_element;
    }
