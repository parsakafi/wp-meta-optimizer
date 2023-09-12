=== Meta Optimizer ===
Contributors: parselearn
Donate link: https://parsa.ws
Tags: Post Meta, User Meta, Comment Meta, Term Meta, Meta, Optimizer
Requires at least: 5.0
Tested up to: 6.3.1
Stable tag: 1.4
Requires PHP: 7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Meta Optimizer is a WordPress plugin that helps you speed up your website by using meta data. It lets you optimize the meta tables for your posts, comments, users, and terms.

== Description ==

This plugin optimizes WordPress meta data storage by saving all meta data for each post, comment, user, or term in a single row with separate columns for each meta key. This reduces the number of rows and improves the query performance and data export. The plugin works seamlessly with WordPress core functions and hooks, and supports any plugins that use them. Some of the features of this plugin are:

- Custom database tables for each type of meta data (post, comment, user, term)
- Compatibility with WordPress queries
- Faster queries and easy data export
- Data migration from default WordPress meta tables
- Option to exclude specific fields from core meta tables
- Support for popular plugins and themes such as Advanced Custom Fields, Meta Box, CMB2, and more.

== Frequently Asked Questions ==

= Where can I read the plugin documentation? =

[Meta Optimizer plugin documentation page](https://parsakafi.github.io/wp-meta-optimizer/)

= What type of meta types supported? =

Meta Optimizer can save default WordPress meta types like Post/User/Comment/Term.

= Can I use this plugin for custom post types? =

Yes, of course. Even though the plugin supports the built-in types of post and page, it is well suited to storing metadata for custom post types.

= Can I rename meta key in DB tables? =

Yes, You can rename meta key in default WP tables and plugin tables.

== Screenshots ==

1. Tables tab, You can manage meta table columns.
2. Reset section in tables tab, You can reset Meta Optimizer DB tables and import again meta data.
3. Settings tab, Plugin options.
4. Import tab, Import options.
5. Tools tab, Optimize WordPress functionality
6. Optimizer tab, Optimize WordPress Database
7. Preview of table structures

== Changelog ==
= 1.4 =
* Add a Tools tab to enhance WordPress functionality
* Add an Optimizer tab to improve WordPress database performance
* Show the size of each plugin table in the tables tab

= 1.3 =
* Add a filter for changing import item numbers ([Documentation](https://parsakafi.github.io/wp-meta-optimizer/#plugin-hooks))
* It is now possible to change the indexes of DB tables
* You will be able to reset the plugin's database tables
* The import tab now includes an estimate of import time

= 1.2.2 =
* Fix save array when insert new meta row

= 1.2.1 =
* NumericVal meta value & change field type when create db table field

= 1.2 =
* Fix bugs effected on save meta array value
* Improve the import process

= 1.1 =
* Fix bugs effected on save meta array value

= 1.0 =
* Release first version of plugin
* Support get/add/update/delete meta functions and WordPress queries
