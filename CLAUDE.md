# PeopleDisplay — Development Rules

## Version sync rule
Whenever the `version` field in `version.json` is changed, `includes/version.php` **must be updated in the same commit** with the matching version number.

Both constants must reflect the new version:
```php
define('PD_CURRENT_VERSION',   'x.y.z');
define('PEOPLEDISPLAY_VERSION', 'x.y.z');
```

`version.json` is the single source of truth. `includes/version.php` is the runtime constant used by PHP. They must always be identical.
