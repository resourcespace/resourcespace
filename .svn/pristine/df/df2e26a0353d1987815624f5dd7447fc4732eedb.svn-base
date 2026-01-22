<?php

/**
 * Configuration for the DiscoPower module.
 */

$config = [
    // Which tab should be set as default. 0 is the first tab
    'defaulttab' => 0,

    /*
     * List a set of tags (Tabs) that should be listed in a specific order.
     * All other available tabs will be listed after the ones specified below.
     */
    'taborder' => ['norway'],

    /*
     * the 'tab' parameter allows you to limit the tabs to a specific list. (excluding unlisted tags)
     *
     * 'tabs' => ['norway', 'finland'],
     */

    /*
     * The 'defaultweight' parameter is used to determine the sort weight when
     * 'discopower.weight' is not explicitly set for the entity, and allows you
     * to influence the sorting of the otherwise alphabetical display. Larger
     * values appear higher up than smaller ones. The default defaultweight is 100.
     *
     * 'defaultweight' => 100,
     */

    /*
     * If you want to change the scoring algorithm to a more google suggest like one
     * (filters by start of words) uncomment this ...
     *
     * 'score' => 'suggest',
     */

    /*
     * The domain to use for common domain cookie support.
     * This must be a parent domain of the domain hosting the discovery service.
     *
     * If this is NULL (the default), common domain cookie support will be disabled.
     */
    'cdc.domain' => null,

    /*
     * The lifetime of the common domain cookie, in seconds.
     *
     * If this is NULL (the default), the common domain cookie will be deleted when the browser closes.
     *
     * Example: 'cdc.lifetime' => 180*24*60*60, // 180 days
     */
    'cdc.lifetime' => null,

    /*
     * If you are configuring a protocol bridge, setting this to `true` will
     * parse the URL return parameter and use it to find 'discopower.filter'
     * configuration in SP metadata on the other side of the bridge.
     * Because it introduces a small risk, the default is `false`.
     *
     * 'useunsafereturn' => false,
     */
];
