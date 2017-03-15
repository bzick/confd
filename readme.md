Simple configuration system
===

```php

$config = new Confd\Config("path/to/custom.config.php", "path/to/defaults/");
```


```php

$config->part->key;
$config->part['key'];
$config['part']['key'];

$config->getItem("part", "key");

```