# Upgrade notes for SimpleSAMLphp LDAP-module 2.0

SimpleSAMLphp LDAP-module 2.0 is a major new release which has cleaned up
support for a lot of things that have been marked deprecated in previous
SimpleSAMLphp releases.

The following changes are relevant for installers and/or developers.

## Software requirements

- The minimum PHP version required is now PHP 7.4.
- The module now depends on symfony/ldap.

## Configuration

Some settings for the authsources and authproc-filters have been renamed
to follow the Symfony naming convention:

- 'hostname' becomes 'connection_string' and can no longer contain simple
  hostnames or IP-addresses, but must be given one or more ldap(s):// URIs.
- 'enableTLS' becomes 'encryption' and can be set to 'ssl', 'tls' or 'none',
  following symfony naming convention.
- 'search.base' is not always an array of OUs.
- 'search.scope' can now be set to 'base', 'one' or 'sub'.

The authsources themselves have been renamed:

- 'ldap:LDAP' becomes 'ldap:Ldap'
- 'ldap:LDAPMulti' becomes 'ldap:LdapMulti'

Some new settings have been added for the authsources:

- 'version' can be used to set the LDAP-version to be used. Defaults to v3.
- 'options' can be set to deal with LDAP connection options like referrals
  or connection timeouts. See the example config-template.
  See [Symfony documentation][1] for the available options.

[1]: https://github.com/symfony/symfony/blob/5.4/src/Symfony/Component/Ldap/Adapter/ExtLdap/ConnectionOptions.php
