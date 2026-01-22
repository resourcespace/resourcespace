# Automated Metadata Management

[TOC]

## Introduction

If you want to connect an Identity Provider, or a Service Provider to a **federation**, you need to setup metadata for the entries that you trust. In many federations, in particular federations based upon the Shibboleth software, it is normal to setup automated distribution of metadata using the SAML 2.0 Metadata XML Format.

Some central administration or authority, provides a URL with a SAML 2.0 document including metadata for all entities in the federation.

The present document explains how to setup automated downloading and parsing of a metadata document on a specific URL.

## Preparations

You need to enable the following modules:

 1. [cron](https://simplesamlphp.org/docs/stable/cron/cron.html)
 2. metarefresh

The cron module allows you to do tasks regularly, by setting up a cron job that calls a hook in SimpleSAMLphp.
It is shipped with the SimpleSAMLphp core.

The metarefresh module will download and parse the metadata document and store it in metadata files cached locally
and needs to be installed separately:

```bash
composer require simplesamlphp/simplesamlphp-module-metarefresh
```

In `config.php`, search for the `module.enable` key and set `cron` and `metarefresh` to true:

```php
    'module.enable' => [
         'cron' => true,
         'metarefresh' => true,
         …
    ],
```

Then, you will need to copy the `config-templates` files of the two modules above into the global `config/` directory.

```sh
cd /var/simplesamlphp
cp modules/cron/config-templates/*.php config/
cp modules/metarefresh/config-templates/*.php config/
```

## Testing it manually

It is often useful to verify that the metadata sources we want to use can be
parsed and verified by metarefresh, before actually configuring it. We can do
so in the command line, by invoking metarefresh with the URL of the metadata
set we want to check. For instance, if we want to configure the metadata of the
SWITCH AAI Test Federation:

```sh
cd modules/metarefresh/bin
./metarefresh.php -s http://metadata.aai.switch.ch/metadata.aaitest.xml
```

### Note

If you have the SimpleSAMLphp configuration-directory in a non-default place, remember to set the
`SIMPLESAMLPHP_CONFIG_DIR` environment-variable to point to this directory _before_ running the commands above.

```sh
export SIMPLESAMLPHP_CONFIG_DIR='/custom/config/location'
```

The `-s` option sends the output to the console (for testing purposes). If the
output makes sense, continue. If you get a lot of error messages, try to read
them and fix the problems that might be causing them. If you are having
problems and you can't figure out the cause, you can always send an e-mail to
the SimpleSAMLphp mailing list and ask for advice.

## Configuring the metarefresh module

Now we are going to proceed to configure the metarefresh module. First, edit the configuration file `config/module_metarefresh.php`.

Here's an example of a possible configuration for the Kalmar Federation,
UK Access Management Federation, and eduGAIN:

```php
$config = [
    'sets' => [
        'kalmar' => [
            'cron'       => ['hourly'],
            'sources'    => [
                [
                    'src' => 'https://kalmar.feide.no/simplesaml/module.php/aggregator/?id=kalmarcentral&mimetype=text/plain&exclude=norway',
                    'certificates' => [
                        'current.crt',
                        'rollover.crt',
                    ],
                    'template' => [
                        'tags'    => ['kalmar'],
                        'authproc' => [
                            51 => ['class' => 'core:AttributeMap', 'oid2name'],
                        ],
                    ],
                ],
            ],
            'expireAfter'  => 60*60*24*4, // Maximum 4 days cache time.
            'outputDir'    => 'metadata/metarefresh-kalmar/',
            'outputFormat' => 'flatfile',
        ],
        'uk' => [
            'cron'       => ['hourly'],
            'sources'    => [
                [
                    'src' => 'http://metadata.ukfederation.org.uk/ukfederation-metadata.xml',
                ],
            ],
            'expireAfter'  => 60*60*24*4, // Maximum 4 days cache time.
            'outputDir'    => 'metadata/metarefresh-ukaccess/',
            'outputFormat' => 'serialize',
        ],
        'edugain' => [
            'cron'          => ['daily'],
            'sources'       => [
                [
                    'src' => 'https://metadata.surfconext.nl/edugain-downstream.xml',
                    'certificates' => [
                        'SURFconext-metadata-signer.pem',
                    ],
                ],
            ],
            'attributewhitelist' => [
                [
                    '#EntityAttributes#' => [
                        '#urn:oasis:names:tc:SAML:attribute:assurance-certification#'
                         => ['#https://refeds.org/sirtfi#'],
                        '#http://macedir.org/entity-category-support#'
                         => ['#http://refeds.org/category/research-and-scholarship#'],
                    ],
                ],
                [
                    '#RegistrationInfo#' => [
                        '#registrationAuthority#'
                        => '#http://www.surfconext.nl/#',
                    ],
                    '#EntityAttributes#' => [
                        '#urn:oasis:names:tc:SAML:attribute:assurance-certification#'
                        => ['#https://refeds.org/sirtfi#'],
                    ],
                ],
            ],
        ],
    ],
];
```

The configuration consists of one or more metadata sets. Each metadata set has
its own configuration, representing a metadata set of sources.  Some
federations will provide you with detailed instructions on how to configure
metarefresh to fetch their metadata automatically, like, for instance, [the
InCommon federation in the US](https://spaces.at.internet2.edu/x/JggOCg). Whenever
a federation provides you with specific instructions to configure metarefresh,
be sure to use them from the authoritative source.

The metarefresh module supports the following configuration options:

`cron`
:   Which cron tags will refresh this metadata set.

`sources`
:   An array of metadata sources that will be included in this
    metadata set. The contents of this option will be described later in more detail.

`expireAfter`
:   The maximum number of seconds a metadata entry will be valid.

`outputDir`
:   The directory where the generated metadata will be stored. Non-absolute paths
    will be relative to the SimpleSAMLphp base directory.

`outputFormat`
:   The format of the generated metadata files. This must match the
    metadata source added in `config.php`.

`types`
:   The sets of entities to load. An array containing strings identifying the different types of entities that will be
    loaded. Valid types are: `saml20-idp-remote`, `saml20-sp-remote`, `attributeauthority-remote`.
    All entity types will be loaded by default.

Each metadata source has the following options:

`src`
:   The source URL where the metadata will be fetched from.

`certificates`
:   An array of certificate files, the filename is relative to the `cert/`-directory,
    that will be used to verify the signature of the metadata. The public key will
    be extracted from the certificate and everything else will be ignored. So it is
    possible to use a self signed certificate that has expired. Add more than one
    certificate to be able to handle key rollover.

    Note: certificates files must be formatted in PEM format.

`template`
:   This is an array which will be combined with the metadata fetched to
    generate the final metadata array.

`regex-template`
:   This is an array of arrays that allows metadata elements to be added or changed for al entities matching a regular
    expression. The key of each element is a regular expression that will be matched against all entity IDs in metadata.
    If the regular expression matches, the value array will be combined with the metadata fetched to generate the final
    metadata array.

`types`
:   Same as the option with the same name at the metadata set level. This option has precedence when both are specified,
    allowing a more fine grained configuration for every metadata source.

`whitelist`
:   This is an array that allows for selectively refreshing a list of identity providers. Only data from identity
    providers that exist in the whitelist will be processed and written to disk. This is especially useful for hosting
    environments that have strict limits memory and maximum execution time.

`blacklist`
:   This is an array that allows for selectively skipping a list of identity providers.  Only data from identity
    providers that do not appear in the blacklist are processed and written to disk.

`attributewhitelist`
:   This is a multilevel array for selectively refreshing a list of identity providers based on specific attributes
    patterns in their metadata. Only data from identity providers that match at least one element of the top-level array
    will be processed and written to disk.
    Matching of such a top-level element, itself being a (multi-level) array, means that at each level (recursively) the
    key and value match with the identity provider's metadata. Scalar keys and values are matched using PCRE.
    A typical use-case is to accept only identity providers from eduGAIN that match a combination of specific
    EntityAttributes, such as the `https://refeds.org/sirtfi` assurance-certification *and*
    `http://refeds.org/category/research-and-scholarship` entity-category.
    Another example is filtering identity providers from a specific federation, by filtering on specific values of the
    registrationAuthority inside the RegistrationInfo.

After you have configured the metadata sources, you need to give the
web-server write access to the output directories. Following the previous example:

```sh
chown www-data /var/simplesamlphp/metadata/metarefresh-kalmar/
chown www-data /var/simplesamlphp/metadata/metarefresh-ukaccess/
```

Now you can configure SimpleSAMLphp to use the metadata fetched by metarefresh. Edit the main
config.php file, and modify the `metadata.sources` directive accordingly:

```php
'metadata.sources' => [
    ['type' => 'flatfile'],
    ['type' => 'flatfile', 'directory' => 'metadata/metarefresh-kalmar'],
    ['type' => 'serialize', 'directory' => 'metadata/metarefresh-ukaccess'],
],
```

Remember that the `type` parameter here must match the `outputFormat` in the configuration of the module.

## Configuring the cron module

See the [cron module documentation](https://simplesamlphp.org/docs/stable/cron:cron) to configure `cron`

Once you have invoked cron, and if this operation seems to run fine, navigate
to the **SimpleSAMLphp Admin** › **Federation**. Here you will see a list
of all the Identity Providers trusted. They will be listed with information
about the maximum duration of their cached version, such as *(expires in 96.0
hours)*.

You *may* need to adjust the below php.ini settings if the metadata files you consume are quite large.

* `memory_limit`
* `max_execution_time`

## Metadata duration

SAML metadata may supply a `cacheDuration` attribute which indicates the
maximum time to keep metadata cached. Because this module is run from cron, it
cannot decide how often it is run and enforce this duration on its own. Make
sure to run metarefresh from cron at least as often as the shortest
`cacheDuration` in your metadata sources.
