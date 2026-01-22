# The SimpleSAMLphp statistics module

## Configure your logs

It's recommended to use syslog for logging, then a separate log level is
dedicated to statistics. You need to get all statistics log entries
in one log file. Here is how I do it in syslog.conf:

```bash
# SimpleSAMLphp logging
local5.*                        /var/log/simplesamlphp.log
# Notice level is reserved for statistics only...
local5.=notice                  /var/log/simplesamlphp.stat
```

Then make sure you have configured this correctly such that you
have one log file like this:

```bash
# ls -la /var/log/simplesamlphp.stat
-rw-r--r-- 1 root root 76740 Nov 15 13:37 /var/log/simplesamlphp.stat
```

With content that looks like this:

```bash
# tail /var/log/simplesamlphp.stat

Nov 15 12:01:49 www1 simplesamlphp-foodle[31960]: 5 STAT [77013b4b6e] saml20-sp-SSO urn:mace:feide.no:services:no.feide.foodle sam.feide.no andreas@uninett.no

Nov 15 13:01:14 www1 simplesamlphp-openwiki[2247]: 5 STAT [50292b9d04] saml20-sp-SSO urn:mace:feide.no:services:no.feide.openwikicore sam.feide.no NA

Nov 15 13:16:39 www1 simplesamlphp-openwiki[2125]: 5 STAT [3493d5d87f] saml20-sp-SSO urn:mace:feide.no:services:no.feide.openwikicore sam.feide.no NA

Nov 15 13:37:27 www1 simplesamlphp-foodle[3146]: 5 STAT [77013b4b6e] AUTH-login-admin OK
```

Here you can see that I collect statistics in one file for several
installations. You could easily separate each instance of SimpleSAMLphp
into separate files (your preference).

## Configure the statistics module

First enable the statistics module, as you enable any other module: in
`config.php`, search for the `module.enable` key and set `statistics` to true:

```php
'module.enable' => [
    'statistics' => true,
    …
],
```

Then take the configuration template:

```bash
cp modules/statistics/config-templates/*.php config/
```

Make sure the `simplesamlphp.stat` file is readable. SimpleSAMLphp will read data from it:

```php
'inputfile' => '/var/log/simplesamlphp.stat',
```

Make sure the `stats` dir is writable. SimpleSAMLphp will write data here:

```php
'statdir' => '/var/lib/simplesamlphp/stats/',
```

## Configuring the syntax of the logfile

Syslog uses different date formats on different environments, so you need to do
some manual tweaking to make sure that SimpleSAMLphp knows how to interpret the
logs.

There are three parameter values you need to make sure are correct.

```php
'datestart'  => 1,
'datelength' => 15,
'offsetspan' => 21,
```

The first `datestart` is 1 when the date starts from the beginning of the line.
The `datelength` parameter tells how many characters long the date is.

The `offsetspan` parameter shows on which character the first column starts,
such that the STAT keyword becomes column number 3.

Use the `loganalyzer` script with the `--debug` parameter to debug whether your
configuration is correct. If not, then it easy to see what is wrong, for
example if the STAT keyword is not in column 3.

NOTE: when using `--debug`, no output is being written to disk!

Here is some example output:

```bash
bash $ cd modules/statistics/bin

$ ./loganalyzer.php --debug

Statistics directory   : /var/lib/simplesamlphp/stats/

Input file             : /Users/andreas/Desktop/simplesamlphp.log

Offset                 : 4237200

----------------------------------------

Log line: Feb 11 11:32:57 moria-app1 syslog_moria-app1[6630]: 5 STAT [2d41ee3f1e] AUTH-login-admin Failed

Date parse [Feb 11 11:32:57] to [Wed, 11 Feb 09 11:32:57 +0100]
```

```php
Array
(
    [0] => moria-app1
    [1] => syslog_moria-app1[6630]:
    [2] => 5
    [3] => STAT
    [4] => [2d41ee3f1e]
    [5] => AUTH-login-admin
    [6] => Failed
)
```

In the debug output, please verify four things:

 1. That the first field in the date parse line contains all the characters
    that are part of the timestamp, compared with the log line on the line
    above.
 2. Verify that the second field in the date parse line is correct:
    corresponding to the input timestamp.
 3. That the first `[0]` field contains all the characters from the
    first column.
 4. That column `[3]` is STAT.

### Setup cron

You also should setup the cron module:

```php
'module.enable' => [
    'cron' => true,
    …
],
```

Then take the configuration template:

```bash
cp modules/cron/config-templates/*.php config/
```

Then configure the `cron` module.

Replace, at least, the value `secret` with your secret value into the `module_cron.php` config file:

```php
$config = [
    'key' => 'secret',
    'allowed_tags' => ['daily', 'hourly', 'frequent'],
    'debug_message' => true,
    'sendemail' => true,
];
```

### Alternative to using the cron module

As an alternative to using the cron module you can run the
script `statistics/bin/loganalyzer.php` manually.

## Presentation of the statistics

At the Installation page there will be a link "show statistics", go there and
if SimpleSAMLphp finds the statistics files in the `statdir` generated from
cron or the script you will see statistics. Enjoy.

## Security

This module relies on displaying images from Google's APIs. Make sure to add
`chart.apis.google.com` to the `img-src` Content-Security-Policy header.

## Error Handling

If the execution of the script `loganalyzer.php` generates the error:

```bash
# php loganalyzer.php --debug
PHP Fatal error:  Uncaught SimpleSAML\Error\CriticalConfigurationError: The configuration (config/config.php) is invalid: Missing configuration file
```

It means that the script doesn't find the `config.php` under the main SimpleSAMLphp directory.

(Probably you've installed SimpleSAMLphp as a composer dependency)

The problem can be solved easily by setting the following environment variable to the right path of `config/` dir:

```bash
export SIMPLESAMLPHP_CONFIG_DIR=/var/simplesamlphp/config/
```

### Support

If you need help to make this work, or want to discuss SimpleSAMLphp with other
users of the software, you are fortunate: Around SimpleSAMLphp there is a great
Open source community, and you are welcome to join! The forums are open for you
to ask questions, contribute answers other further questions, request
improvements or contribute with code or plugins of your own.

- [SimpleSAMLphp homepage](http://simplesamlphp.org)
- [List of all available SimpleSAMLphp documentation](http://simplesamlphp.org/docs/stable/)
- [Join the SimpleSAMLphp user's mailing list](http://simplesamlphp.org/lists)
