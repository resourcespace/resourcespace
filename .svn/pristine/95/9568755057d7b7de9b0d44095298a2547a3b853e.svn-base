`sqlauth:SQL`
=============

These are authentication modules for authenticating a user against a
SQL database.

The SQL module performs password verification in the database itself
using database functions such as sha512 and storing a salt in the
database. The PasswordVerify module verifies passwords in php using
the password_verify() function. The PasswordVerify module was created
to ask the least of the database either because there is minimal
support in the database or to allow the same code to work against many
databases without modification. More information on PasswordVerify is
provided at the end of this document.

Options
-------

`dsn`
:   The DSN which should be used to connect to the database server.
    Check the various database drivers in the [PHP documentation](http://php.net/manual/en/pdo.drivers.php) for a description of the various DSN formats.

`username`
:   The username which should be used when connecting to the database server.

`password`
:   The password which should be used when connecting to the database server.

`query`
:   The SQL query or queries which should be used to authenticate the user and retrieve their attributes.

`username_regex`
:   (Optional) A regular expression that the username must match. Useful if the type of the username column in the database isn't a string (eg. an integer), or if the format is well known (eg. email address, single word with no spaces, etc) to avoid going to the database for a query that will never result in successful authentication.

`passwordhashcolumn`
:    (Optional) Only When using the sqlauth:PasswordVerify module. This is the name of the column that contains the hashed password. The default is to look for a column 'passwordhash' in the database.

Writing a Query / Queries
-------------------------

A `query` can be either a single string with an SQL statement, or an array of queries, run in order. That single string (or the first query in the array) is the "authentication query" - the parameters `:username` and `:password` are available and should be evaluated by the query for authenticaion purposes. If the username/password is incorrect, the "authentication query" should return no rows. The rows returned represent attributes to be returned.

Taking this example schema:

```sql
    CREATE TABLE users (
      uid VARCHAR(30) NOT NULL PRIMARY KEY,
      password TEXT NOT NULL,
      salt TEXT NOT NULL,
      givenName TEXT NOT NULL,
      email TEXT NOT NULL,
      eduPersonPrincipalName TEXT NOT NULL
    );
    CREATE TABLE usergroups (
      uid VARCHAR(30) NOT NULL REFERENCES users (uid) ON DELETE CASCADE ON UPDATE CASCADE,
      groupname VARCHAR(30) NOT NULL,
      UNIQUE(uid, groupname)
    );
```

a basic entry with a single SQL string in `authsources.php` might look like this (PostgreSQL, SHA512 of salt + password, base64 encoded with the salt stored in an independent column):

```php
    'example-sql' => [
        'sqlauth:SQL',
        'dsn' => 'pgsql:host=postgresql;port=5432;dbname=simplesaml',
        'username' => 'simplesaml',
        'password' => 'secretpassword',
        'query' => "select uid, givenName as \"givenName\", email from users where uid=:username and password=encode(sha512(concat((select salt from users where uid=1),  :password)::bytea), 'base64')",
        'username_regex' => '/^[a-z]+$/', // Username will only be acceptable if it is a single lower case word
    ],
```

It's worth repeating at this point that if authentication is unsuccessful (ie. the username / password pair don't match), this query **must** return zero rows. Assuming the username / password pair provided was a match, the name of the columns in result set will be used as attribute names. In the above case, PostgreSQL lowercases the names by default, which we correct with the "as" clause. The result might look like this:

| Attribute Name | Attribute Value |
|----------------|-----------------|
| uid            | [ bobsmith ]    |
| givenName      | [ Bob ]         |
| email          | [ bob@example.com ] |

You'll likely need to collect attributes from more than just the table with the username and password hash. There are two supported ways to do this: table joins on your authentication query, or providing an array of queries for the `query` parameter instead of just the single query.

A basic example of the single query with join:

```php
    'example-sql' => [
        'sqlauth:SQL',
        'dsn' => 'pgsql:host=postgresql;port=5432;dbname=simplesaml',
        'username' => 'simplesaml',
        'password' => 'secretpassword',
        'query' => "select u.uid, u.givenName as \"givenName\", ug.groupname as \"groupName\" from users u left join usergroups ug on (u.uid=ug.uid) where u.uid=:username and u.password=encode(sha512(concat((select salt from users where uid=1),  :password)::bytea), 'base64')",
    ],
```

which can also be written as:

```php
    'example-sql' => [
        'sqlauth:SQL',
        'dsn' => 'pgsql:host=postgresql;port=5432;dbname=simplesaml',
        'username' => 'simplesaml',
        'password' => 'secretpassword',
        'query' => [
            "select uid, givenName as \"givenName\", email from users where uid=:username and password=encode(sha512(concat((select salt from users where uid=1),  :password)::bytea), 'base64')",
            "select groupName as \"groupName\" from usergroups where uid=:username",
        ],
        "select u.uid, u.givenName, ug.groupname from users u left join usergroups ug on (u.uid=ug.uid) where u.uid=:username and u.password=encode(sha512(concat((select salt from users where uid=1),  :password)::bytea), 'base64')",
    ],
```

both of which will return attributes like:

| Attribute Name | Attribute Value |
|----------------|-----------------|
| uid            | [ bobsmith ]    |
| givenName      | [ Bob ]         |
| email          | [ bob@example.com ] |
| groupName      | [ users, staff ]  |

For simple cases, the single query will suffice. As the number of tables you are joining to collate your attributes gets higher, then using the query list will make your configuration more maintainable.

In summary:

- If the single string query (or the first query if it's an array of queries) returns no rows, that indicates authentication failed.
- The single string query (or the first query if it's an array of queries) should use the passed `:username` and `:password` query parameters to do authentication.
- If more than one query is desirable or required to get all of the attributes, you can specify an array of queries. In this case, the result set of the second and subsequent queries in that array provide attributes only - only the first query is used to determine if the username/password is correct or not, and as such :password is only passed to the first query in the list.
- If `query` is an array of queries, because the second and subsequent queries have no role in authentication, these queries may return no rows, simply indicating that query should have no effect on the final returned attribute set.
- If any query returns multiple rows, they will be merged into the attributes.
- If multiple queries return the same column names, they will also be merged into the same attributes.
- Duplicate values and NULL values will be removed.

Further Examples
----------------

```sql
Example query - SHA256 of salt + password, with the salt stored in an independent column, MySQL server:

    SELECT uid, givenName, email, eduPersonPrincipalName
    FROM users
    WHERE uid = :username
    AND PASSWORD = SHA2(
        CONCAT(
            (SELECT salt FROM users WHERE uid = :username),
            :password
        ),
        256
    )
```

Example query - SHA256 of salt + password, with the salt stored in an independent column. Multiple groups, MySQL server:

```sql
    SELECT users.uid, givenName, email, eduPersonPrincipalName, groupname AS groups
    FROM users LEFT JOIN usergroups ON users.uid = usergroups.username
    WHERE users.uid = :username
    AND PASSWORD = SHA2(
        CONCAT(
            (SELECT salt FROM users WHERE uid = :username),
            :password
        ),
        256
    )
```

Example query - SHA512 of salt + password, stored as salt (32 bytes) + sha256(salt + password) in password-field, PostgreSQL server:

```sql
    SELECT uid, givenName, email, eduPersonPrincipalName
    FROM users
    WHERE username = :username
    AND SUBSTRING(
        password FROM LENGTH(password) - 31
    ) = SHA2(
        CONCAT(
            SUBSTRING(password FROM 1 FOR LENGTH(password) - 32),
            :password
        ),
        512
    )
```

Security considerations
-----------------------

Please never store passwords in plaintext in a database. You should always hash your passwords with a secure one-way
function like the ones in the SHA2 family. Use randomly generated salts with a length at least equal to the hash of the
password itself. Salts should be per-password, that meaning every time a password changes, the salt must change, and
therefore salts must be stored in the database alongside the passwords they were used for. Application-wide salts can
be used (by just concatenating them to the input of the hash function), but should never replace per-password salts,
used instead as an additional security measure.

One way hashing algorithms like MD5 or SHA1 are considered insecure and should therefore be avoided.

The PasswordVerify module
-------------------------

Users and passwords have to be set in the database by other means than the PasswordVerify module.

For example:

```sql
    CREATE TABLE users (
      uid VARCHAR(30) NOT NULL PRIMARY KEY,
      passwordhash TEXT NOT NULL,
      givenName TEXT NOT NULL,
      email TEXT NOT NULL,
      eduPersonPrincipalName TEXT NOT NULL
    );
```

A user can be added with a known password "FIXMEPASSWORD" as shown below.

```php
$dsn = "pgsql:host=...";
$username = "fixme";
$password = "";
$options = array();

$query = "insert into users values ('test@example.com',:passwordhash, 'test', 'test@example.com', 'test@example.com' )";
    
$db = new PDO($dsn, $username, $password, $options);
$db->exec("SET NAMES 'UTF8'");

$params = ["passwordhash" => password_hash("FIXMEPASSWORD", PASSWORD_ARGON2ID ) ];
$sth = $db->prepare($query);
$sth->execute($params);
```

Since the above is using the default passwordhash column name this can
then be used with the following addition to authsources.php.

```php
'smalldb-dbauth' => [
    'sqlauth:PasswordVerify',
    'dsn' => 'pgsql:host=...',
    'username' => 'dbuser',
    'password' => 'dbpassword',
    'passwodhashcolumn' =>  'passwordhash',
    'query' => 'select uid, email, passwordhash, eduPersonPrincipalName from users where uid = :username ',
],

```
