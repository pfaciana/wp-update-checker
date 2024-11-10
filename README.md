# Project Update Checker

## Overview

This is a WordPress plugin that will manage updates for your custom WordPress plugins or themes that are hosted on external repositories such as GitHub and GitLab. This is an alternative option to having your theme or plugin hosted on WordPress.org. Right now this only supports GitHub and GitLab repositories out of the box, more may come, but you can create your own custom option for any platform by extending the `\PackageUpgrader\V1\Context\AbstractRemote` class from this project in your code.

## How to use

This plugin is designed to be easy to use. Its configuration is done using your existing plugin/theme metadata comments already defined in your plugin or theme's main file, with a couple extra metadata comments for where to find the source repo.

### Step 1

Install via composer packagist

```bash
composer require pfaciana/wp-update-checker
```

NOTE: Alternative option, you can download the package as a standalone plugin alongside your plugin or theme. To do this, go to https://github.com/pfaciana/wp-update-checker/releases and download the `Release Asset` named `wp-update-checker.zip` from the lasted release. Then go to your WordPress admin and choose `Plugin` >  `Add New Plugin` from the side menu and upload the `wp-update-checker.zip` and click `Install Now`.

Both options will work, but using composer and bundling it inside your plugin/theme is recommended.

### Step 2

Init the `PackageUpgrader` Plugin or Theme instance in your code...

```php
# For your plugin
add_action( 'rpuc/init', fn() => new PackageUpgrader\V1\Plugin );

# For your theme
add_action( 'rpuc/init', fn() => new PackageUpgrader\V1\Theme );
```

Place this code in the root of your plugin or theme. It will automatically find your plugin/theme's main file if it's in the same directory. If it's not, then you'll have to pass the location of the main file of your plugin/theme to tell the update checker where to find your plugin/theme.

```php
# For your plugin
add_action( 'rpuc/init', fn() => new PackageUpgrader\V1\Plugin( WP_PLUGIN_DIR . "/your-plugin/index.php") );

# For your theme
add_action( 'rpuc/init', fn() => new PackageUpgrader\V1\Theme( WP_THEME_DIR . "/your-theme/style.css" ) );
```

### Step 3

Add in the necessary WordPress comments into your plugin or theme's main file. For example,

```php
/**
 * Plugin Name: Your Plugin's Name
 * Plugin URI: https://example.com/
 * Version: 1.2.3
 * Description: A WordPress plugin that does something
 * Author: Your Name
 * Author URI: https://example.com/
 * GitHub URI: owner/repo
 * Release Asset: release-asset.zip
 * Requires PHP: 7.4
 * Requires at least: 5.4
 * Compatible up to: 6.4
 * License: GPLv2 only
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
```

Most of these metadata comments you'll already have in your plugin/theme's main file. It supports all the WordPress metadata comments (like `Plugin/Theme Name`, `Plugin/Theme URI`, `Version`, `Description`, `Author`, `Author URI`,  `Requires at least`, `Requires PHP`, `Compatible up to`, `Tags`, etc) and will use them in the plugin/theme dialog overlay. It also supports most of the WordPress plugin/theme api status fields (like `Homepage`, `Donate Link`, `Active Installs`, etc) from the `plugins_api`/`themes_api` filter hooks. You can also have your own code hook into these as well.

In addition to the standard WordPress metadata comments, it also supports a few custom metadata comments to help find the source repo. The only required one is the `GitHub URI` or `GitLab URI` metadata comment. This is your username (or organization name) and repository name, as defined in the repo's url. The rest are optional. `Release Asset` and `Remote File` are only used if you have a special build process that creates your plugin/theme. If your build creates a release asset that should be used instead of the default source code zip, use the `Release Asset` key. If your build process dynamically creates the main plugin/theme file (which means it does not exist in the source code of the repo) use `Remote File` to define which file in your repo has the metadata comments. You can enter a json file here, and it will look for the `extra.wordpress` key and parse that as the metadata comments.

# This plugin's custom metadata comments

| Header Comment    | Description                                                                     | Example Value                                    |
|-------------------|---------------------------------------------------------------------------------|--------------------------------------------------|
| GitHub URI        | GitHub repository identifier                                                    | `owner/repo`                                     |
| GitLab URI        | GitLab repository identifier                                                    | `owner/repo`                                     |
| Remote Visibility | If the plugin or theme is private and needs an api token to access remote data  | `public` (default), `private`                    |
| Primary Branch    | Primary branch name                                                             | `master` (default)                               |
| Release Asset     | Relative URL to the zip file for download                                       | `wp-plugin.zip` (defaults to release source zip) |
| Remote File       | The relative file path to the plugin or theme file that has the header comments | `index.php` (defaults to main file)              |