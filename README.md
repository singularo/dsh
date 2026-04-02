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

## Domain Configuration

By default, dsh uses [nip.io](https://nip.io) for domain resolution. This service provides
wildcard DNS where any subdomain of `<ip>.nip.io` resolves to that IP address, requiring
no local configuration.

**Default domains:**
- **Linux:** `myproject.172.17.0.1.nip.io:8080` (Docker bridge IP)
- **macOS:** `myproject.127.0.0.1.nip.io:8080`

### Custom Domain

To use your own domain, set the `DOMAIN` environment variable in your `.env` file:

```bash
DOMAIN=mydomain.test
```

This allows you to use domains like `myproject.mydomain.test:8080`.

Use the custom DOMAIN if you want to setup your own local dns server with bind,
dnsmasq or something else.
