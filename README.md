# WP Meta Optimizer

WP Meta Optimizer a plugin that helps your website load faster if you use more meta like Post/Comment/User/Term metas!

## How To plguin works
WordPress saves every post/comment/user/term meta in new row. with this plugin you can save all of them to single row, and each column will be a meta key.

![Alt text](https://user-images.githubusercontent.com/7957513/187703875-90395dd2-c088-4481-b8a8-c56e269e0da3.png "WP Meta Table Vs WPMO Table")

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
If you use reserved column keys such as post_id for post meta, the plugin adds a suffix to the meta key. It creates a column based on the renamed key. As an example, if you save meta with key "post_id", then plugin adds "_wpmork" suffix and creates column "post_id_wpmork". In response to a query (WP_Query), the plugin automatically changes the meta key if necessary.

Example query:
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
Plugin translated to this query:
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