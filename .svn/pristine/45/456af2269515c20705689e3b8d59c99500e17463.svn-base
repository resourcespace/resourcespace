<?php
/* -------- Dynamic Keywords List ----------- */
global $baseurl, $pagename, $edit_autosave, $k;

if ($pagename == 'search_advanced') {
    $name .= '_search_adv';
}

if (!isset($selected_nodes)) {
    $selected_nodes = array();

    if (isset($searched_nodes) && is_array($searched_nodes)) {
        $selected_nodes = $searched_nodes;
    }
}

// Decide when the user can add new keywords to a dynamic keywords list
$readonly = false;
if ('search_advanced' == $pagename || checkperm('bdk' . $field['ref'])) {
    $readonly = true;
}

$is_search                        = (isset($is_search) ? $is_search : false);
$hidden_input_elements_name       = ($is_search ? 'nodes_searched' : 'nodes');
$js_keywords_suffix               = "{$hidden_input_elements_name}_{$field['ref']}_" . md5($name);
$add_searched_nodes_function_call = '';
?>

<div class="dynamickeywords ui-front">
    <input
        id="<?php echo $name; ?>_selector"
        type="text"
        <?php if ($pagename == "search_advanced") { ?>
            class="SearchWidth"
        <?php } else { ?>
            class="stdwidth"
        <?php } ?>
        name="<?php echo $name; ?>_selector"
        placeholder="<?php echo escape($lang['starttypingkeyword']); ?>"
        onFocus="
            <?php if ($pagename == "edit") {
                echo "ShowHelp(" . $field["ref"] . ");";
            } ?>"
        onBlur="
            <?php if ($pagename == "edit") {
                echo "HideHelp(" . $field["ref"] . ");";
            } ?>"
    />
    <?php

    $nodes_in_sequence = array_filter($field['node_options'], 'node_is_active');

    if ((bool) $field['automatic_nodes_ordering']) {
        uasort($nodes_in_sequence, "node_translated_name_comparator");
    } else {
        uasort($nodes_in_sequence, "node_orderby_comparator");
    }

    foreach ($nodes_in_sequence as $node) {
        // Deal with previously searched nodes
        if (!in_array($node['ref'], $selected_nodes) && !(isset($user_set_values[$field['ref']]) && in_array($node['ref'], $user_set_values[$field['ref']]))) {
            continue;
        }

        // Replace & " ' < > characters with &amp &quot &apos &lt &gt
        $i18n_node_name = htmlentities(i18n_get_translated($node['name']), ENT_QUOTES);

        // Strip line breaks because there shouldn't be any within dynamic keywords
        $i18n_node_name = preg_replace('/[\r\n]+/', '', $i18n_node_name);

        // Normally, when editing multiple resources, we don't want to preselect any fixed list values; the user must make the necessary selections
        // However, when using copyfrom and editing multiple resources, then preselection does occur
        if (!$multiple || ($multiple && $copyfrom != '')) {
            $add_searched_nodes_function_call .= "addKeyword_{$js_keywords_suffix}('{$node['ref']}', '{$i18n_node_name}');";
        }
    }
    ?>
    <div id="<?php echo $name; ?>_selected" class="keywordsselected"></div>
</div>
<div class="clearerleft"></div>
<script>
    console.debug('<?php printf('(%s) Init global state for Keywords_%s', str_replace(dirname(__DIR__), '', __FILE__), $js_keywords_suffix); ?>');

    // Associative array with key being node name and index being the node ID
    // Example: Keywords_nodes_3 = ['United Kingdom']=232, ['United States']=233
    // or Keywords_nodes_searched_3 = ['United Kingdom']=232, ['United States']=233
    var Keywords_<?php echo $js_keywords_suffix; ?> = [];

    function updateSelectedKeywords_<?php echo $js_keywords_suffix; ?>(user_action) {
        let fct_name = 'updateSelectedKeywords_<?php echo $js_keywords_suffix; ?>';
        console.debug('%s(user_action = %o)', fct_name, user_action);
        var html                  = '';
        var hidden_input_elements = '';
        var keyword_count         = 0;

        console.debug('[%s] Keywords_<?php echo $js_keywords_suffix; ?> = %o', fct_name, Keywords_<?php echo $js_keywords_suffix; ?>);
        for (var keyword_value in Keywords_<?php echo $js_keywords_suffix; ?>) {
            var keyword_index = Keywords_<?php echo $js_keywords_suffix; ?>[keyword_value];
            
            hidden_input_elements += '<input id="<?php echo $hidden_input_elements_name; ?>_' + keyword_index + '" type="hidden" name="<?php echo $hidden_input_elements_name; ?>[<?php echo $field["ref"]; ?>][]" value="' + keyword_index + '">';

            html += '<div class="keywordselected" id="nodeselected' + keyword_index + '">' + keyword_value;
            html += '<a href="#" class="RemoveKeyword"';
            html += ' onClick="removeKeyword_<?php echo $js_keywords_suffix; ?>(\'' + keyword_index + '\', true); return false;"';
            html += '>x</a></div>';
            
            keyword_count ++;
        };

        // Update DOM with all our recent changes
        var existing_hiddent_input_elements = document.getElementsByName('<?php echo $hidden_input_elements_name; ?>[<?php echo $field["ref"]; ?>][]');
        while (existing_hiddent_input_elements[0]) {
            existing_hiddent_input_elements[0].parentNode.removeChild(existing_hiddent_input_elements[0]);
        }

        document.getElementById('<?php echo $name; ?>_selected').insertAdjacentHTML('beforeBegin', hidden_input_elements);
        document.getElementById('<?php echo $name; ?>_selected').innerHTML = html;
        
        if ("<?php echo $field['field_constraint']; ?>"==1 && keyword_count>=1 && (pagename!='search_advanced')) {
            document.getElementById('<?php echo $name; ?>_selector').disabled = true;
        } else {
            document.getElementById('<?php echo $name; ?>_selector').disabled = false;
        }

        <?php
        if ($pagename != 'edit') {
            ?>
            // Update the result counter, if the function is available (e.g. on Advanced Search).
            if (typeof(UpdateResultCount) == 'function' && user_action) {
                UpdateResultCount();
            }
            <?php
        }

        if ($edit_autosave) {
            ?>
            if (user_action) {
                AutoSave('<?php echo $field["ref"]; ?>');
            }
            <?php
        }
        ?>

        // Trigger an event so we can chain actions once we've changed a dynamic keyword
        jQuery('[name="<?php echo $hidden_input_elements_name; ?>[<?php echo $field["ref"]; ?>][]"]').each(function(index, element) {
            jQuery('#CentralSpace').trigger('dynamicKeywordChanged',[{node: element.value}]);
        });
    }

    function removeKeyword_<?php echo $js_keywords_suffix; ?>(node_id, user_action) {
        console.debug('removeKeyword_<?php echo $js_keywords_suffix; ?>(node_id = %o, user_action = %o)', node_id, user_action);
        <?php if ((int) $field["required"] === 1) { ?>        
            // Prevent removal of final keyword if field is required
            keycount = jQuery("[name^=nodes\\[<?php echo (int) $field["ref"]; ?>\\]]").length;
            if (keycount == 1 && user_action) {
                styledalert('<?php echo escape($lang['requiredfield']); ?>','<?php echo escape(i18n_get_translated($field['title'])); ?>');
                return false;
            }
        <?php } ?>

        // Save existing keywords array    
        var saved_Keywords = Keywords_<?php echo $js_keywords_suffix; ?>;

        // Rebuild keywords array 
        Keywords_<?php echo $js_keywords_suffix; ?> = [];
        for (var keyword_value in saved_Keywords) {
            var keyword_index = saved_Keywords[keyword_value];
            
            if (keyword_index != node_id) {
                Keywords_<?php echo $js_keywords_suffix; ?>[keyword_value] = keyword_index;
            }
        };

        updateSelectedKeywords_<?php echo $js_keywords_suffix; ?>(user_action);

        // Trigger an event so we can chain actions once we've changed a dynamic keyword
        jQuery('#CentralSpace').trigger('dynamicKeywordChanged',[{node: node_id}]);
    }

    function addKeyword_<?php echo $js_keywords_suffix; ?>(node_id, keyword) {
        console.debug('addKeyword_<?php echo $js_keywords_suffix; ?>(node_id = %o, keyword = %o)', node_id, keyword);
        removeKeyword_<?php echo $js_keywords_suffix; ?>(node_id, false);

        Keywords_<?php echo $js_keywords_suffix; ?>[keyword] = node_id;
    }

    function selectKeyword_<?php echo $js_keywords_suffix; ?>(event, ui) {
        var found_suggested = true;
        var keyword         = ui.item.label;
        var node_id         = ui.item.value;
        let no_entry_exists = keyword.substring(0, <?php echo mb_strlen(escape($lang['noentryexists']), 'UTF-8') ?>) == '<?php echo escape($lang["noentryexists"]); ?>';
        let inactive_entry = keyword.substring(0, <?php echo mb_strlen(escape($lang['inactive_entry_matched']), 'UTF-8'); ?>) === '<?php echo escape($lang['inactive_entry_matched']); ?>';

        if (keyword.substring(0, <?php echo mb_strlen($lang['createnewentryfor'], 'UTF-8'); ?>) == '<?php echo escape($lang["createnewentryfor"]); ?>') {
            keyword = keyword.substring(<?php echo mb_strlen($lang['createnewentryfor'], 'UTF-8') + 1; ?>);

            // Add the word.
            args = {
                field: '<?php echo $field["ref"]; ?>',
                ajax: true,
                keyword: keyword,
                k: '<?php echo escape($k) ?>',
                <?php echo generateAjaxToken("selectKeyword_{$js_keywords_suffix}"); ?>
            };

            jQuery.ajax({
                type    : 'POST',
                url     : '<?php echo $baseurl?>/pages/edit_fields/9_ajax/add_keyword.php',
                data    : args,
                dataType: 'json',
                async: false,
                success : function(result, status, xhr) {
                    if (xhr.status == 302) {
                        location.href = xhr.getResponseHeader("Location");
                    }

                    if (typeof result.new_node_id === 'undefined') {
                        styledalert('Error', 'Could not determine new node ID!');
                        return false;
                    }

                    node_id = result.new_node_id;
                }
            });        
        } else if (inactive_entry || no_entry_exists) {
            document.getElementById('<?php echo $name; ?>_selector').value = '';
            found_suggested = false;
        }

        if (found_suggested) {
            addKeyword_<?php echo $js_keywords_suffix; ?>(node_id, keyword);

            updateSelectedKeywords_<?php echo $js_keywords_suffix; ?>(true);

            document.getElementById('<?php echo $name; ?>_selector').value = '';
        }

        return false;
    }

    jQuery('#<?php echo $name; ?>_selector').autocomplete({
        source : "<?php echo $baseurl; ?>/pages/edit_fields/9_ajax/suggest_keywords.php?field=<?php echo $field['ref']; ?>&k=<?php echo escape($k) ?>&readonly=<?php echo $readonly; ?>",
        select : selectKeyword_<?php echo $js_keywords_suffix; ?>
    });

    // prevent return in autocomplete field from submitting entire form
    // we want the user to explicitly choose what they want to do
    jQuery('#<?php echo $name; ?>_selector').keydown(function(event) {
        var keyCode = event.keyCode ? event.keyCode : event.which;
        if (keyCode == 13) {
            event.stopPropagation();
            event.preventDefault();

            return false;
        }
    });

    jQuery(document).ready(function() {
        // Run this after the page has loaded as otherwise existing invalid JS functions may still be in place from an earlier edit page
        <?php
        debug("\$add_searched_nodes_function_call = {$add_searched_nodes_function_call}");
        echo $add_searched_nodes_function_call;
        ?>
        console.debug('<?php
            printf(
                '[document.ready](%s) Going to call updateselectedkeywords_%s(false)',
                str_replace(dirname(__DIR__), '', __FILE__),
                $js_keywords_suffix
            );
            ?>');
        updateSelectedKeywords_<?php echo $js_keywords_suffix; ?>(false);
    });
</script>
