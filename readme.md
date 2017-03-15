Simple configuration system
===

[![Build Status](https://travis-ci.org/bzick/confd.svg?branch=master)](https://travis-ci.org/bzick/confd)

```php

$config = new Confd\Config("path/to/custom.config.php", "path/to/defaults/");
```


```php

$config->part->key;
$config->part['key'];
$config['part']['key'];

$config->getItem("part", "key");

```