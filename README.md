# YouTube PHP Class

A simple PHP class which allows you to retrieve information relating to the different formats of a YouTube video and their download URLs.
The numerous and frequent changes to the ciphering algorithm of the YouTube videos signatures are also managed automatically.
The proper functioning of the class can obviously be impacted by significant new modifications of the YouTube security system

## Getting Started

### Prerequisites

```
PHP 7.4.4 or higher
```

### Use of class

```

require 'YouTube.php';

$youtube = new YouTube('dQw4w9WgXcQ');

var_dump($youtube);

```

## Author

* **Jérôme Taillandier**

## License

This project is licensed under the WTFPL License - see the [LICENSE.md](LICENSE.md) file for details
