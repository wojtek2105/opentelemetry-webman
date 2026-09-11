# Publishing the package

## Create the repository

Copy the contents of this package directory into an empty GitHub repository:

```bash
git init
git add .
git commit -m "Initial OpenTelemetry integration for Webman"
git branch -M main
git remote add origin git@github.com:wojtek2105/opentelemetry-webman.git
git push -u origin main
```

Do not commit `vendor/` or `composer.lock` for this library.

## Install before Packagist

An application can install directly from GitHub:

```bash
composer config repositories.opentelemetry-webman vcs \
  https://github.com/wojtek2105/opentelemetry-webman
composer require wojtek2105/opentelemetry-webman:dev-main
```

## Release

After CI passes, create a semantic version tag:

```bash
git tag -a v0.1.0 -m "First development release"
git push origin v0.1.0
```

Use `0.x` until the request lifecycle and dependency correlation tests have run
under realistic concurrent Webman traffic. Register the GitHub URL on Packagist
when direct Composer installation is desired.
