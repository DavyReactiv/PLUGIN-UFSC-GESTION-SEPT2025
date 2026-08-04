# Tests UFSC

Les tests autonomes s’exécutent avec `php tests/test-….php`.

Les deux tests PHPUnit utilisent `phpunit.xml`. Sur un poste autorisé à joindre
Packagist :

```sh
composer require --dev phpunit/phpunit:^9.6
vendor/bin/phpunit -c phpunit.xml
```

Cette dépendance de développement n’est pas inscrite dans le manifeste de
production afin de ne pas modifier le graphe livré par le plugin.
