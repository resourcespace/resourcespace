# DiscoPower

DiscoPower is a more advanced replacement for the built-in discovery service. It supports grouping identity providers into tabs and provides mechanisms to sort, filter and search.

## Enable the module

In `config.php`, search for the `module.enable` key and set `discopower` to true:

```php
    'module.enable' => [
         'discopower' => true,
         …
    ],
```

## Configuration

DiscoPower expects to find its configuration in `config/module_discopower.php`. There is a sample configuration in [config-templates](../config-templates/) that can be copied and adapted.

### Config options

`defaulttab`
:   An integer specifying which tab should be displayed first. Tabs number left to right, starting at zero. The default is `0` which is the first tab.

`taborder`
:   List a set of tags (Tabs) that should be listed in a specific order. All other available tabs will be listed after the ones specified.

`tabs`
:   Allows you to limit the tabs to a specific list. Unlisted tags are excluded from display.

`score`
:   Change the way DiscoPower scores results in searches. Valid options are `quicksilver` or `suggest`, with `quicksilver` being the default.

`cdc.domain`
:   The domain to use for common domain cookie support. This must be a parent domain of the domain hosting the discovery service. If this is `null` (the default), common domain cookie support will be disabled.

`cdc.lifetime`
:   The lifetime of the common domain cookie, in seconds. If this is `null` (the default), the common domain cookie will be deleted when the browser closes.

`useunsafereturn`
:   See [Filtering on a protocol bridge](#filtering-on-a-protocol-bridge). Defaults to `false`.

## Enabling DiscoPower for a service

To enable the use of DiscoPower, you need to edit your [service provider configuration](https://simplesamlphp.org/docs/stable/simplesamlphp-sp) in `config/authsources.php` and set the `discoURL` parameter to point at the DiscoPower module:

```php
<?php
$config = [
    'default-sp' => [
        'saml:SP',
        'discoURL' => 'https://sp1.example.org/simplesaml/module.php/discopower/disco.php',
    ],
];
```

This causes SimpleSAMLphp to use DiscoPower in preference to the built-in discovery interface.

## Arranging identity providers onto tabs

DiscoPower determines its list of identity providers by parsing the [IdP remote metadata](https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote).

In order to specify which tab(s) an identity provider should be displayed on, you can set the `tabs` attribute in the entity's metadata. If you're using locally hosted metadata, you can do this by simply editing your metadata as follows:

```php
$metadata['https://idp1.example.net/'] = [
    // ...
    'tags' => ['tag1', 'tag2'],
];
```

The order in which these tags is displayed is controlled by the `taborder` parameter in `config/module_discopower.php`.

## Filtering identity providers

You can filter the tabs or individual entities displayed to particular services by editing their metadata entry in saml20-sp-remote to include a `discopower.filter` stanza.

```php
$metadata['https://sp1.example.org/'] = [
    // ...
    'discopower.filter' => [
        'entities.include' => [ ... ],
        'entities.exclude' => [ ... ],
        'tags.include' => [ ... ],
        'tags.exclude' => [ ... ],
    ],
];
```

The `.include` versions take precedence over the `.exclude` ones. If both are specified, the list of identity providers is first filtered to the `.include` lists, and then any `.exclude`d ones are removed.

### Filtering on a protocol bridge

If you have configured SimpleSAMLphp as a [protocol bridge](https://simplesamlphp.org/docs/stable/simplesamlphp-advancedfeatures#section_2), you may want to filter entities arriving from the other side of the bridge and for which you do not have metadata in saml20-sp-remote. Unfortunately, this makes it difficult to safely filter the list of identity providers, and so this functionality is disabled by default.

In this scenario, the only way to infer the entityId is from the `return` parameter of the disco URL.  As the `return` parameter is information that came from the user's browser, it should not be trusted. A user could manipulate the entityId in the `return` parameter to change what your DiscoPower service sees as the originating service. If you're filtering your entities to prevent people from learning of their existance, this potentially means your filters can be bypassed and people can learn about identity providers you do not wish them to know about.

However, if, as is often the case, you're not worried if users learn all the IdPs you support and are merely filtering to improve the user interface, then you may consider this relatively safe. In this case, you can enable support for filtering over the protocol bridge by setting the `useunsafereturn` option in `config/module_discopower.php` to `true`.

## Changing the display order

By default, DiscoPower sorts identity providers alphabetically by the English name (specified by the `name` parameter in metadata). Where providers do not have names, they're sorted by their `entityId`. However, providers with only an entityId will always appear below those with an English name.

If you wish to manipulate the default sort order, the easiest way to do this is by setting a `discopower.weight` in the identity provider's metadata. Weights are numeric values, and higher weights are sorted above lower ones. Where weights are not specified, they inherit the `defaultweight` from `config/module_discopower.php` (which itself defaults to 100).

Thus to force a particular identity provider to the top of the list, you can set it's weight very high, like this:

```php
$metadata['https://idp2.example.net/'] = [
    // ...
    'name' => ['en' => 'IdP 1'],
    'tags' => ['tag1', 'tag2'],
    'discopower.weight' => 200,
];
```

More complex sorting can be done with a hook. To do this, create a file named `hook_discosort.php` and place it under the `<module_name>/hooks` directory. This file should contain a function named:

```php
<module_name>_hook_discosort(&$entities)
```

where the `$entities` parameter is a reference to an array containing the metadata objects for each entity on a given tab. This is suitable for passing to a function like [`uasort()`](https://www.php.net/manual/en/function.uasort.php), but you're free to sort it using any method you wish.

## Interacting with metarefresh

If you're making use of the [metarefresh](https://github.com/simplesamlphp/simplesamlphp-module-metarefresh) module for automated metadata management, then you need to add any metadata paramters into the appropriate `template` in `config/config-metarefresh.php` so that they get applied each time metadata is refreshed.
