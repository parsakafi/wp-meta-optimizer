=== Meta Optimizer ===
Contributors: parselearn
Donate link: https://parsa.ws
Tags: Post Meta, User Meta, Comment Meta, Term Meta, Meta, Optimizer
Requires at least: 5.0
Tested up to: 6.0.2
Stable tag: 1.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

You can use Meta Optimizer to make your WordPress website load faster if you use meta information, for example Post/Comment/User/Term metas.

== Description ==

WordPress saves every post/comment/user/term meta in new row. with this plugin you can save all of them to single row, and each column will be a meta key.

Plugin work with default WordPress functions and support all of plugins use WordPress standard functions and hooks.

## Features
- Create database tables for each of WordPress meta tables (Post/Comment/User/Meta).
- Support WordPress Queries
- Faster Queries & Easy Export
- Import old data from default WordPress meta table
- Bypass core meta tables for specific fields
- Export all the data easier by exporting only one table

## Integration
- [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/) and Pro version
- [Meta Box – WordPress Custom Fields Framework](https://wordpress.org/plugins/meta-box/) and Pro version
- [CMB2](https://wordpress.org/plugins/cmb2/)
- And all of plugins and themes use WordPress standard functions.

## Attention
If you use reserved column keys such as `post_id` for post meta, the plugin adds a suffix to the meta key. It creates a column based on the renamed key. As an example, if you save meta with key `post_id`, then plugin adds `_wpmork` suffix and creates column `post_id_wpmork`. In response to a query (WP_Query), the plugin automatically changes the meta key if necessary.

[Update post meta](https://developer.wordpress.org/reference/functions/update_post_meta/) example 
```
update_post_meta(1, 'post_id', 222);
```
The meta key has been changed to:
```
update_post_meta(1, 'post_id_wpmork', 222);
```

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
Plugin changed query to this:
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

== Frequently Asked Questions ==

= What type of meta types supported? =

Meta Optimizer can save default WordPress meta types like Post/User/Comment/Term.

= Can I use this plugin for custom post types? =

Yes, of course. Even though the plugin supports the built-in types of post and page, it is well suited to storing meta data for custom post types.

= Can I rename meta key in DB tables? =

Yes, You can rename meta key in default WP tables and plugin tables.

== Screenshots ==

1. Tables tab, You can manage meta table columns.
2. Settings tab, Plugin options.
3. Import tab, Import options.

== Changelog ==

= 1.0 =
* Release first version of plugin
* Support get/add/update/delete meta and WordPress queries
