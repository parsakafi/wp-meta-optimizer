=== Meta Optimizer ===
Contributors: parselearn
Donate link: https://parsa.ws
Tags: Post Meta, User Meta, Comment Meta, Term Meta, Meta, Optimizer
Requires at least: 5.0
Tested up to: 6.3.1
Stable tag: 1.2.2
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

You can use Meta Optimizer to make your WordPress website load faster if you use meta information. For example, Post / Comment / User / Term metas.

== Description ==

WordPress saves every post / comment / user / term meta in new row. with this plugin, you can save all of them to single row, and each column will be a meta key.

Plugin work with default WordPress functions and support all plugins use WordPress standard functions and hooks.

## Features
- Create database tables for each of WordPress meta tables (Post / Comment / User / Meta).
- Support WordPress Queries
- Faster Queries & Easy Export
- Import old data from default WordPress meta table
- Bypass core meta tables for specific fields
- Export all the data easier by exporting only one table

## Integration
- [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/) and Pro version
- [Meta Box – WordPress Custom Fields Framework](https://wordpress.org/plugins/meta-box/) and Pro version
- [CMB2](https://wordpress.org/plugins/cmb2/)
- And all plugins and themes use WordPress standard functions and hooks.

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
2. Settings tab, Plugin options.
3. Import tab, Import options.
4. Preview of table structures

== Changelog ==

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
