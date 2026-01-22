<?php

$config = [
    /*
     * Global blacklist: entityIDs that should be excluded from ALL sets.
     */
    #'blacklist' = array(
    #    'http://my.own.uni/idp'
    #),

    /*
     * Conditional GET requests
     * Efficient downloading so polling can be done more frequently.
     * Works for sources that send 'Last-Modified' or 'Etag' headers.
     * Note that the 'data' directory needs to be writable for this to work.
     */
    #'conditionalGET' => true,

    'sets' => [

        'kalmar' => [
            'cron' => ['hourly'],
            'sources' => [
                [
                    /*
                     * entityIDs that should be excluded from this src.
                     */
                    #'blacklist' => array(
                    #    'http://some.other.uni/idp',
                    #),

                    /*
                     * Whitelist: only keep these EntityIDs.
                     */
                    #'whitelist' => array(
                    #    'http://some.uni/idp',
                    #    'http://some.other.uni/idp',
                    #),

                    #'conditionalGET' => true,
                    'src' => 'https://kalmar2.org/simplesaml/module.php/aggregator/?id=kalmarcentral&set=saml2&exclude=norway',
                    'certificates' => [
                        'current.crt',
                        'rollover.crt',
                    ],
                    'template' => [
                        'tags' => ['kalmar'],
                        'authproc' => [
                            51 => ['class' => 'core:AttributeMap', 'oid2name'],
                        ],
                    ],

                    'regex-template' => [
                        "#^https://www\.example\.com/sp$#" => [
                            'assertion.encryption' => false,
                        ],
                    ],

                    /*
                     * The sets of entities to load, any combination of:
                     *  - 'saml20-idp-remote'
                     *  - 'saml20-sp-remote'
                     *  - 'attributeauthority-remote'
                     *
                     * All of them will be used by default.
                     *
                     * This option takes precedence over the same option per metadata set.
                     */
                    //'types' => [],
                ],
            ],

            'expireAfter' => 34560060, // Maximum 4 days cache time (3600*24*4)
            'outputDir' => 'metadata/metadata-kalmar-consuming/',

            /*
             * Which output format the metadata should be saved as.
             * Can be 'flatfile' or 'serialize'. 'flatfile' is the default.
             */
            'outputFormat' => 'flatfile',

            /*
             * The sets of entities to load, any combination of:
             *  - 'saml20-idp-remote'
             *  - 'saml20-sp-remote'
             *  - 'attributeauthority-remote'
             *
             * All of them will be used by default.
             */
            //'types' => [],
        ],
    ],
];
