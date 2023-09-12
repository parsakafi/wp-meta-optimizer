# Meta Optimizer

This plugin optimizes WordPress meta data storage by saving all meta data for each post, comment, user, or term in a
single row with separate columns for each meta key. This reduces the number of rows and improves the query performance
and data export. The plugin works seamlessly with WordPress core functions and hooks, and supports any plugins that use
them. Some of the features of this plugin are:

- Custom database tables for each type of meta data (post, comment, user, term)
- Compatibility with WordPress queries
- Faster queries and easy data export
- Data migration from default WordPress meta tables
- Option to exclude specific fields from core meta tables
- Support for popular plugins and themes such as Advanced Custom Fields, Meta Box, CMB2, and more.

# Download

[Download stable version from WordPress.org](https://wordpress.org/plugins/meta-optimizer/)
&nbsp;

## How to plugin works

Instead of storing each post, comment, user, term meta in a separate row,
this plugin lets you store them all in one row, with each meta key as a column.

![WordPress Meta Table Vs Meta Optimizer Table](screenshots/table-structure.png "WordPress Meta Table Vs Meta Optimizer Table")

&nbsp;

## Screenshots

### Tables tab, You can manage meta table columns

![Tables tab, You can manage meta table columns](screenshots/screenshot-1.png)

### Reset section in tables tab, You can reset Meta Optimizer DB tables and import again meta data.

![Tables tab, You can manage meta table columns](screenshots/screenshot-2.png)

### Settings tab, Plugin options

![Settings tab, Plugin options](screenshots/screenshot-3.png)

### Import tab, Import options

![Import tab, Import options](screenshots/screenshot-4.png)

### Tools tab, Optimize WordPress functionality

![Import tab, Import options](screenshots/screenshot-5.png)

### Optimizer tab, Optimize WordPress Database

![Import tab, Import options](screenshots/screenshot-6.png)

&nbsp;

## Plugin Hooks

### Change import items number

```
add_filter( 'wpmetaoptimizer/import_items_number', function ( $importItemsNumber ) {
    // return numeric value
    return $importItemsNumber + 20;
} );
```

&nbsp;

## Attention

If you use reserved column keys such as `post_id` for post meta, the plugin adds a suffix to the meta key. It creates a
column based on the renamed key. As an example, if you save meta with key `post_id`, then plugin adds `_wpmork` suffix
and creates column `post_id_wpmork`. In response to a query (WP_Query), the plugin automatically changes the meta key if
necessary.

&nbsp;

[Update post meta](https://developer.wordpress.org/reference/functions/update_post_meta/) example

```
update_post_meta(1, 'post_id', 222);
```

The meta key has been changed to:

```
update_post_meta(1, 'post_id_wpmork', 222);
```

&nbsp;

Example [Query](https://developer.wordpress.org/reference/classes/wp_query/#custom-field-post-meta-parameters):

```
$query = new WP_Query(array(
    'orderby' => array(
        'post_id' => 'DESC'
    ),
    'meta_query' => array(
        'post_id' => array(
            'key' => 'post_id',
            'compare' => 'EXISTS',
            'type' => 'NUMERIC'
        )
    )
));
```

Plugin changed a query to this:

```
$query = new WP_Query(array(
    'orderby' => array(
        'post_id_wpmork' => 'DESC'
    ),
    'meta_query' => array(
        'post_id_wpmork' => array(
            'key' => 'post_id_wpmork',
            'compare' => 'EXISTS',
            'type' => 'NUMERIC'
        )
    )
));
```

&nbsp;

## Frequently Asked Questions

**What type of meta types supported?**

*Meta Optimizer can save default WordPress meta types like Post / User / Comment / Term.*

**Can I use this plugin for custom post types?**

*Yes, of course. Even though the plugin supports the built-in types of post and page, it is well suited to storing meta
data for custom post types.*

**Can I rename meta key in DB tables?**

*Yes, You can rename meta key in default WP tables and plugin tables.*

&nbsp;

## Changelog

### 1.4

* Add a Tools tab to enhance WordPress functionality
* Add an Optimizer tab to improve WordPress database performance
* Show the size of each plugin table in the tables tab

### 1.3

* Add a filter for changing import item numbers
  ([Documentation](https://parsakafi.github.io/wp-meta-optimizer/#plugin-hooks))
* It is now possible to change the indexes of DB tables
* You will be able to reset the plugin's database tables
* The import tab now includes an estimate of import time

### 1.2.2

* Fix save array when insert new meta row

### 1.2.1

* NumericVal meta value & change field type when create db table field

### 1.2

* Fix bugs effected on save meta array value
* Improve the import process

### 1.1

* Fix bugs effected on save meta array value

### 1.0

* Release a first version of plugin
* Support get/add/update/delete meta functions and WordPress queries

&nbsp;

## Unit Test

Currently, unit tests are limited to checking database tables and can't check plugin functionality! If you are able to
help write tests for the plugin, please submit a pull request. Thank you :)

1. [Initialize the testing environment locally](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/#3-initialize-the-testing-environment-locally)
2. Install Dependency: `composer install`
3. Run Test: Windows `./vendor/bin/phpunit` / Unix: `vendor/bin/phpunit` or `phpunit`

&nbsp;

## Production plugin

For create production plugin, run this command in plugin directory:

Unix: `bin/create-plugin.sh`
Windows: `.\bin\create-plugin.sh`

&nbsp;

### Documents

[Documents page](https://parsakafi.github.io/wp-meta-optimizer/)
