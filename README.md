# dsh

Composer plugin and scripts for getting up developing quickly with Drupal.

Designed to be used with just a few steps, requires only php and composer to be installed:

### Create a bare Drupal project.
```
composer create drupal/recommended-project:^10 drupal-test
cd ./drupal-test
```

### Allow the scaffold to run.
```
composer config --append allow-plugins.singularo/dsh true
composer config --append --json extra.drupal-scaffold.allowed-packages '["singularo/dsh"]'
```

### Require the scaffold/setup the files.
```
composer require singularo/dsh:^1.0
```

### Start the containers and work with dsh.
```
./dsh
```

### Build a standard Drupal install with standard profile.
```
robo build
```
