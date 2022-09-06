# WP Meta Optimizer

WP Meta Optimizer a WordPress plugin that helps your website load faster if you use more meta like Post/Comment/User/Term metas!

&nbsp;
## How To plguin works
WordPress saves every post/comment/user/term meta in new row. with this plugin you can save all of them to single row, and each column will be a meta key.

![WP Meta Table Vs WPMO Table](https://user-images.githubusercontent.com/7957513/188633375-1fe658fd-ab34-4052-a672-6b3e05980085.png "WP Meta Table Vs WPMO Table")

Plugin work with default WordPress functions and support all of plugins use WordPress standard functions and hooks.

&nbsp;
## Features
- Create database tables for each of WordPress meta tables (Post/Comment/User/Meta).
- Support WordPress Queries
- Faster Queries & Easy Export
- Import old data from default WordPress meta table
- Bypass core meta tables for specific fields
- Export all the data easier by exporting only one table

&nbsp;
## Integration
- [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/) and Pro version
- [Meta Box – WordPress Custom Fields Framework](https://wordpress.org/plugins/meta-box/) and Pro version
- [CMB2](https://wordpress.org/plugins/cmb2/)
- And all of plugins and themes use WordPress standard functions.

&nbsp;
## Attention
If you use reserved column keys such as `post_id` for post meta, the plugin adds a suffix to the meta key. It creates a column based on the renamed key. As an example, if you save meta with key `post_id`, then plugin adds `_wpmork` suffix and creates column `post_id_wpmork`. In response to a query (WP_Query), the plugin automatically changes the meta key if necessary.

&nbsp;

Example [update post meta](https://developer.wordpress.org/reference/functions/update_post_meta/)
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


### Documents
([Documents page](https://parsakafi.github.io/wp-meta-optimizer/))