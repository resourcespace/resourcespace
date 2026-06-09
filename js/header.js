ResourceSpace.Modules.Header = (() => {
    const header = document.getElementById('header-container');
    const uiCenter = document.getElementById('UICenter');
    const primaryNavOverflowThreshold = 6;
    let stickyHeaderLastScrollTop = 0;
    const updateCssHeaderVarsObserver = new ResizeObserver(updateCssHeaderVars);
    let updateCssHeaderVarsInProgress = false;

    function init() {
        if (!header) return;

        bindEvents();
    }

    function bindEvents() {
        // For mobile devices, make only the header search be "sticky"
        const mq_mobile = ResourceSpace.media.max('tablet');
        mq_mobile.addEventListener('change', makeStickyHeader);
        makeStickyHeader(mq_mobile);

        updateCssHeaderVars();

        header.addEventListener('click', (e) => {
            // The search container is being replaced in the DOM as part of the reloadSearchBar()
            const headerSearch = e.target.closest('.header-search-field');
            if (!headerSearch) return;

            if (
                // The open/close buttons for the search (filter) panel
                e.target.matches('.input-wrapper > button:last-of-type')
                || e.target.matches('section > button')
                
                // Clicking on the search (filter) panel links (except the "Clear filters" one), or on the search button
                // will close the panel
                || e.target.closest('#simplesearchbuttons .search-actions-secondary')
                || (
                    e.target.matches('#simplesearchbuttons .search-actions-primary > input')
                    // Using e.detail to avoid trigger being called when the form is submitted on Enter (which seems to
                    // call click on the submit input virtually)
                    && e.detail > 0
                )
            ) {
                toggleSearchPanel(e);
            } else if (e.target.matches('.input-wrapper > button[type="submit"]')) {
                closeSearchPanel();
            }
        });

        jQuery('#CentralSpace')?.on('CentralSpaceLoaded', closeSearchPanel);
        jQuery('#modal')?.on('CentralSpaceLoaded', closeSearchPanel);

        // Overflow primary navigation links on smaller desktop sizes (e.g. 1366 - 1500px) since there won't be enough
        // space for maximum six links (see render_header_links()).
        window.addEventListener('resize', handlePrimaryNavigationOverflow);
        handlePrimaryNavigationOverflow();

        handleResourceTypesFilter();
    }

    function makeStickyHeader(e) {
        if (e.matches) {
            uiCenter.addEventListener('scroll', handleStickyHeaderScroll);
            updateCssHeaderVarsObserver.observe(header);
        } else {
            uiCenter.removeEventListener('scroll', handleStickyHeaderScroll);
            resetStickyHeader();
            updateCssHeaderVarsObserver.disconnect();
        }
    }

    function handleStickyHeaderScroll() {
        const currentScroll = uiCenter.scrollTop;
        const scrollThreshold = 10; // avoid jittering
        const logo = header.querySelector('.logo');
        const actions = header.querySelector('.actions');

        if (updateCssHeaderVarsInProgress) return;

        if (currentScroll > stickyHeaderLastScrollTop + scrollThreshold) {
            logo.classList.add('is-collapsed');
            actions.classList.add('is-collapsed');
            header.classList.add('is-compact');
        } else if (currentScroll < stickyHeaderLastScrollTop - scrollThreshold) {
            resetStickyHeader();
        }

        stickyHeaderLastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
    }

    function resetStickyHeader() {
        const logo = header.querySelector('.logo');
        const actions = header.querySelector('.actions');

        logo.classList.remove('is-collapsed');
        actions.classList.remove('is-collapsed');
        header.classList.remove('is-compact');
    }

    function updateCssHeaderVars() {
        updateCssHeaderVarsInProgress = true;
        requestAnimationFrame(() => {
            // reset before the next repaint (rendering cycle)
            updateCssHeaderVarsInProgress = false;
        });

        const headerBB = header.getBoundingClientRect();

        document.documentElement.style.setProperty('--js-header-container-bottom', `${headerBB.bottom}px`);
    }

    function toggleSearchPanel(e) {
        const isEvent = e?.target;

        if (isEvent) {
            e.stopPropagation();
        }

        const is_small_screen = ResourceSpace.media.max('desktop').matches;
        const target = isEvent ? e.target : e;
        const form = target.closest('form');
        const panel = document.getElementById('search-filters-panel');
        const isOpen = form.classList.toggle('filters-open');
        form.querySelector('.input-wrapper > button:last-of-type')
            .setAttribute('aria-expanded', String(isOpen));

        if (isOpen) {
            panel.hidden = false;

            if (is_small_screen) {
                target.classList.replace('icon-sliders-horizontal', 'icon-circle-x');
            }
        } else {
            panel.addEventListener(
                'transitionend',
                (event) => {
                    if (event.propertyName !== 'opacity') return;

                    if (!form.classList.contains('filters-open')) {
                        panel.hidden = true;

                        if (is_small_screen) {
                            target.classList.replace('icon-circle-x', 'icon-sliders-horizontal');
                        }
                    }
                },
                { once: true }
            );
        }
    }

    function closeSearchPanel() {
        const openPanel = header.querySelector('.header-search-field .input-wrapper > button[aria-expanded="true"]');

        if (openPanel) {
            toggleSearchPanel(openPanel);
        }
    }

    function reloadSearchBar() {
        console.debug('Reloading the search (filter) panel container...');
        const searchBar_jq = jQuery('search#SearchBox');
        const url = `${baseurl_short}pages/ajax/reload_searchbar.php?ajax=true&pagename=${encodeURIComponent(pagename)}`;

        jQuery.get(url, function(response) {
            const response_jq = jQuery(response);
            const newSearchBar_jq = response_jq.is('search#SearchBox')
                ? response_jq
                : response_jq.find('search#SearchBox').first();

            if (!newSearchBar_jq.length) {
                console.warn('Invalid response content received (no search#SearchBox)! Unable to reload the search (filter) panel container.');
                return;
            }

            searchBar_jq.replaceWith(newSearchBar_jq);

            if (typeof chosen_config !== 'undefined' && typeof chosen_config['#SearchBox select'] !== 'undefined') {
                jQuery('#SearchBox select').each(function() {
                    ChosenDropdownInit(this, '#SearchBox select');
                });
            }

            handleResourceTypesFilter();
        });

        return;
    }

    const nav = header?.querySelector('nav.primary-navigation');
    const list = nav?.querySelector(':scope > ul');
    const panel = nav?.querySelector('ul.menu-panel');

    function handlePrimaryNavigationOverflow() {
        if (!list || !panel || ResourceSpace.media.max('desktop').matches) return;

        restorePrimaryNavOverflowItems();

        const items = getPrimaryNavigationLinkItems();
        const maxNavWidth = getAvailableNavWidth();

        while (
            items.length
            && (
                items.length > primaryNavOverflowThreshold
                || nav.getBoundingClientRect().width > maxNavWidth
            )
        ) {
            const lastItem = items.pop();
            moveToPanel(lastItem);
        }
    }

    function getAvailableNavWidth() {
        const headerBB = header.getBoundingClientRect();
        const headerStyle = getComputedStyle(header);
        const gap = parseFloat(headerStyle.columnGap || headerStyle.gap || 0);
        const totalGapWidth = gap * (header.children.length - 1);

        const siblingWidths = [...header.children]
            .filter((el) => el !== nav)
            .reduce((total, el) => {
                let elWidth = el.getBoundingClientRect().width; 

                if (el.classList.contains('logo')) {
                    elWidth = parseInt(getComputedStyle(el).maxWidth, 10);
                } else if(el.classList.contains('header-search-field')) {
                    // Same as CSS clamp rule for `#header-container .header-search-field`
                    elWidth = Math.min(Math.max(320, headerBB.width * 0.38), 400);
                }

                return total + elWidth;
            }, 0);

        return headerBB.width - totalGapWidth - siblingWidths;
    }

    function getPrimaryNavigationLinkItems() {
        return [...list.children].filter(
            (el) => el.matches('li') && !el.classList.contains('menu-overflow') && !el.closest('.menu-panel')
        );
    }

    function moveToPanel(item) {
        item.classList.add('menu-item');
        item.querySelector('a')?.setAttribute('role', 'menuitem');
        panel.prepend(item);
    }

    function restorePrimaryNavOverflowItems() {
        const items = [...panel.children];
        const listItemMenuOverflow = panel.closest('li.menu-overflow');

        items.forEach((item) => {
            const listItems = getPrimaryNavigationLinkItems();
            if (listItems.length && listItems.length < primaryNavOverflowThreshold) {
                item.classList.remove('menu-item');
                item.querySelector('a')?.removeAttribute('role');
                list.insertBefore(item, listItemMenuOverflow);
            }
        });
    }

    function handleResourceTypesFilter() {
        const resource_type_selector = jQuery('#restypes\\[\\]');

        resource_type_selector.select2();
        bindResourceTypeSelectorEvents(resource_type_selector);
    }

    /**
     * @see https://select2.org/programmatic-control/events
     */
    function bindResourceTypeSelectorEvents(resource_type_selector) {
        // When clearing the search filters on the panel, select only the "All resource types" (default behaviour). 
        resource_type_selector.on('select2:clear', function (e) {
            const all_resource_types_option = jQuery(this).find('option').first().val();
            jQuery(this).val(all_resource_types_option).trigger('change');
        });

        resource_type_selector.on('select2:select', function (e) {
            const all_resource_types_option = jQuery(this).find('option').first().val();

            // If the user selects the "All resource types" option then any manually selected resource types are cleared 
            if (e.params.data.id === all_resource_types_option) {
                // This event clears all selections (default) and our bind (see above) will reselect the all RTs option
                jQuery(this).trigger('select2:clear');
                return;
            }

            // Selecting a resource type will remove the "All resource types" option to only show your manually selected
            // resource types
            jQuery(this)
                .val(jQuery(this).val().filter(v => v !== all_resource_types_option))
                .trigger('change');
        });

        // Triggered whenever an option is selected or removed. Note that clearing can end up running this twice
        // depending on the selectors' state (once for the selected event and once for the removal one).
        resource_type_selector.on('change', function (e) {
            SimpleSearchFieldsHideOrShow(true);
        });
    }

    return {
        init: init,
        reloadSearchBar: reloadSearchBar,
    };
})();