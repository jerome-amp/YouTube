# YouTube PHP Class

A simple PHP class which grab YouTube video informations including media URLs.
The frequent changes of the JavaScript ciphering algorithm are managed automatically.
The class behavior can obviously be impacted by new modifications of the security system.

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
