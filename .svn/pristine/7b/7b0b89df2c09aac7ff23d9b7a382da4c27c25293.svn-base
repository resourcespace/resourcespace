<?php

use function ImageBanks\getProviders;
use function ImageBanks\providersCheckedAndActive;

function HookImage_banksAllExtra_checks()
    {
    $errors = [];
    [$providers] = getProviders($GLOBALS['image_banks_loaded_providers']);
    foreach($providers as $provider)
        {
        $provider_name = $provider->getName();
        $dependency_check = $provider->checkDependencies();
        if ($dependency_check !== [])
            {
            $errors[$provider_name] = $dependency_check;
            }
        }

    if ($errors !== [])
        {
        $message['image_banks'] = [
            'status' => 'FAIL',
            'severity' => SEVERITY_WARNING,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
            'info' => $GLOBALS['lang']['image_banks_system_unmet_dependencies'],
            'details' => $errors,
        ];
        return $message;
        }
    }

function HookImage_banksAllSearchfiltertop()
    {
    global $lang, $image_banks_loaded_providers, $clear_function;

    [$providers, $errors] = getProviders($image_banks_loaded_providers);

    if ($errors !== [])
        {
        return;
        }

    $providers_select_list = providersCheckedAndActive($providers);
    if($providers_select_list === [])
        {
        return;
        }

    $image_bank_provider_id = (int) getval("image_bank_provider_id", 0, true);
    ?>
    <div id="SearchImageBanksItem" class="SearchItem" title="">
        <label for="SearchImageBanks"><?php echo escape($lang['image_banks_search_image_banks_label']); ?></label>
        <select id="SearchImageBanks" class="SearchWidth" name="image_bank_provider_id" onchange="toggleUnwantedElementsFromSimpleSearch(jQuery(this));SimpleSearchFieldsHideOrShow(true);">
            <option value=""></option>
            <?php
            foreach($providers_select_list as $provider_id => $provider)
                {
                $selected = ($image_bank_provider_id === $provider_id ? "selected" : "");
                ?>
                <option value="<?php echo (int) $provider_id; ?>" <?php echo $selected; ?>><?php echo escape($provider); ?></option>
                <?php
                }
                ?>
        </select>
        <script>
        function toggleUnwantedElementsFromSimpleSearch(selector)
            {
            var selected_option = selector.val();
            SetCookie("image_bank_provider_id",selected_option);
            
            var siblings = jQuery("#SearchImageBanksItem")
                .siblings()
                .not("#ssearchbox")
                .not(".tag-editor")
                .not("input[type=hidden]")
                .not("script")
                .not(".ui-widget")
                .not("#simplesearchbuttons");

            if(selected_option == "")
                {
                // Image bank is not selected, so show the siblings
                // When showing the siblings, we need to honour the display conditions
                if(typeof search_show == 'undefined' || search_show)
                    {
                    siblings.each(function()
                        {
                        searchfield_id = this.id.substring(13);
                        // If the field is not in the fieldsToHideOnClear array, then show it 
                        if( (typeof fieldsToHideOnClear == "undefined") || (typeof fieldsToHideOnClear == "object" && !fieldsToHideOnClear.includes(searchfield_id)) )
                            {
                            jQuery(this).show();
                            }
                        });
                    }
                return;
                }

            // Image bank is selected, so hide the siblings
            siblings.hide();

            return;
            }

        </script>
        <div class="clearerleft"></div>
    </div>
    <?php
    $clear_function .= 'jQuery("#SearchImageBanks").val([\'\']); search_show=true; toggleUnwantedElementsFromSimpleSearch(jQuery("#SearchImageBanks"));';
    }

function HookImage_banksAllAdd_folders_to_delete_from_temp(array $folders_scan_list)
    {
    [$providers] = getProviders($GLOBALS['image_banks_loaded_providers']);
    if(count($providers) === 0)
        {
        return false;
        }

    foreach($providers as $provider)
        {
        $tmp_dir = $provider->getTempDirPath();

        if($tmp_dir == "")
            {
            continue;
            }

        $folders_scan_list[] = $tmp_dir;
        }

    return $folders_scan_list;
    }

function HookImage_banksAllClearsearchcookies()
    {
    global $clear_function;
    $clear_function .= "SetCookie('image_bank_provider_id','');";
    return true;
    }

function HookImage_banksAllSimplesearchfieldsarehidden()
    {
    return getval('image_bank_provider_id', 0, true) > 0;
    }  
