# TDU Meylan Filters - Performance Optimization Guide

This document provides step-by-step instructions to optimize the TDU Meylan Filters plugin using WordPress internal features without external caching.

## Overview of Performance Issues

The current plugin has several performance bottlenecks:

1. **Inefficient Database Queries**: Using `get_posts()` with `numberposts => -1` loads all products into memory
2. **N+1 Query Problems**: Multiple `wp_get_post_terms()` calls in loops
3. **No Caching**: Expensive operations run on every page load
4. **Redundant Data Processing**: Complex operations repeated unnecessarily

## Expected Performance Gains

- **50-80% reduction** in database queries
- **60-90% faster** filter rendering on subsequent loads
- **Reduced memory usage** by avoiding unnecessary post data
- **Better scalability** as product catalog grows

---

## Step 1: Implement WordPress Transients Caching

### 1.1 Cache Category Brands Function

**Location**: Replace `tdu_mf_get_category_brands()` function (lines 130-169)

```php
/**
 * Gets the brands for a given category with caching
 *
 * @param int $category_id The term ID of the category to retrieve brands for.
 * @return array Array of brand data
 */
function tdu_mf_get_category_brands($category_id) {
    // Create unique cache key
    $transient_key = 'tdu_category_brands_' . $category_id;
    $brands = get_transient($transient_key);
    
    if (false === $brands) {
        global $wpdb;
        
        // Optimized SQL query instead of get_posts() + wp_get_post_terms()
        $sql = "
            SELECT DISTINCT t.term_id, t.name, t.slug
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_relationships} tr2 ON p.ID = tr2.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
            WHERE tt.taxonomy = 'product_brand'
            AND tt2.taxonomy = 'product_cat'
            AND tt2.term_id = %d
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            ORDER BY t.name ASC
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $category_id));
        
        // Convert to expected format
        $brands = array();
        foreach ($results as $result) {
            $brands[] = (object) array(
                'term_id' => $result->term_id,
                'name' => $result->name,
                'slug' => $result->slug
            );
        }
        
        // Cache for 1 hour
        set_transient($transient_key, $brands, HOUR_IN_SECONDS);
    }
    
    return $brands;
}
```

### 1.2 Cache Watch Types Function

**Location**: Replace `tdu_mf_get_watch_types()` function (lines 171-216)

```php
function tdu_mf_get_watch_types() {
    $current_parent_category = tdu_mf_get_current_parent_category();
    $current_children_category = tdu_mf_get_current_children_category();
    $current_brand = tdu_get_current_brand_query_var();
    
    // Create cache key based on current filters
    $cache_key = 'tdu_watch_types_' . md5(serialize(array(
        'parent_cat' => $current_parent_category,
        'child_cat' => $current_children_category,
        'brand' => $current_brand
    )));
    
    $types = get_transient($cache_key);
    
    if (false === $types) {
        global $wpdb;
        
        // Build WHERE conditions
        $where_conditions = array("p.post_type = 'product'", "p.post_status = 'publish'");
        $join_conditions = array();
        $params = array();
        
        // Add category filter
        $category_id = $current_children_category ?: $current_parent_category;
        if ($category_id) {
            $join_conditions[] = "INNER JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id";
            $join_conditions[] = "INNER JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id";
            $where_conditions[] = "tt_cat.taxonomy = 'product_cat' AND tt_cat.term_id = %d";
            $params[] = $category_id;
        }
        
        // Add brand filter
        if ($current_brand) {
            $join_conditions[] = "INNER JOIN {$wpdb->term_relationships} tr_brand ON p.ID = tr_brand.object_id";
            $join_conditions[] = "INNER JOIN {$wpdb->term_taxonomy} tt_brand ON tr_brand.term_taxonomy_id = tt_brand.term_taxonomy_id";
            $join_conditions[] = "INNER JOIN {$wpdb->terms} t_brand ON tt_brand.term_id = t_brand.term_id";
            $where_conditions[] = "tt_brand.taxonomy = 'product_brand' AND t_brand.slug = %s";
            $params[] = $current_brand;
        }
        
        $sql = "
            SELECT DISTINCT t.term_id, t.name, t.slug
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            " . implode(' ', $join_conditions) . "
            WHERE tt.taxonomy = 'pa_product_watch_type'
            AND " . implode(' AND ', $where_conditions) . "
            ORDER BY t.name ASC
        ";
        
        if (!empty($params)) {
            $types = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        } else {
            $types = $wpdb->get_results($sql);
        }
        
        // Cache for 30 minutes
        set_transient($cache_key, $types, 30 * MINUTE_IN_SECONDS);
    }
    
    return $types;
}
```

### 1.3 Cache Collections Function

**Location**: Replace `tdu_mf_get_collections()` function (lines 218-262)

```php
function tdu_mf_get_collections() {
    $current_parent_category = tdu_mf_get_current_parent_category();
    $current_children_category = tdu_mf_get_current_children_category();
    $current_brand = tdu_get_current_brand_query_var();
    
    // Create cache key based on current filters
    $cache_key = 'tdu_collections_' . md5(serialize(array(
        'parent_cat' => $current_parent_category,
        'child_cat' => $current_children_category,
        'brand' => $current_brand
    )));
    
    $collections = get_transient($cache_key);
    
    if (false === $collections) {
        global $wpdb;
        
        // Build WHERE conditions (same logic as watch types)
        $where_conditions = array("p.post_type = 'product'", "p.post_status = 'publish'");
        $join_conditions = array();
        $params = array();
        
        $category_id = $current_children_category ?: $current_parent_category;
        if ($category_id) {
            $join_conditions[] = "INNER JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id";
            $join_conditions[] = "INNER JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id";
            $where_conditions[] = "tt_cat.taxonomy = 'product_cat' AND tt_cat.term_id = %d";
            $params[] = $category_id;
        }
        
        if ($current_brand) {
            $join_conditions[] = "INNER JOIN {$wpdb->term_relationships} tr_brand ON p.ID = tr_brand.object_id";
            $join_conditions[] = "INNER JOIN {$wpdb->term_taxonomy} tt_brand ON tr_brand.term_taxonomy_id = tt_brand.term_taxonomy_id";
            $join_conditions[] = "INNER JOIN {$wpdb->terms} t_brand ON tt_brand.term_id = t_brand.term_id";
            $where_conditions[] = "tt_brand.taxonomy = 'product_brand' AND t_brand.slug = %s";
            $params[] = $current_brand;
        }
        
        $sql = "
            SELECT DISTINCT t.term_id, t.name, t.slug
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            " . implode(' ', $join_conditions) . "
            WHERE tt.taxonomy = 'product_collection'
            AND " . implode(' AND ', $where_conditions) . "
            ORDER BY t.name ASC
        ";
        
        if (!empty($params)) {
            $collections = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        } else {
            $collections = $wpdb->get_results($sql);
        }
        
        // Cache for 30 minutes
        set_transient($cache_key, $collections, 30 * MINUTE_IN_SECONDS);
    }
    
    return $collections;
}
```

---

## Step 2: Optimize Category Structure Caching

### 2.1 Cache Categories Structure

**Location**: Replace `tdu_mf_get_categories_structure()` function (lines 14-57)

```php
/**
 * Get hierarchical product category structure with caching
 *
 * @return array Array of product categories with their children
 */
function tdu_mf_get_categories_structure() {
    $cache_key = 'tdu_categories_structure';
    $categories = wp_cache_get($cache_key);
    
    if (false === $categories) {
        $args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'term_order',
            'order'      => 'ASC',
            'parent'     => 0,
        );

        $parent_categories = get_terms($args);
        $categories = array();

        if (is_wp_error($parent_categories) || empty($parent_categories)) {
            wp_cache_set($cache_key, array(), '', 300); // Cache empty result for 5 minutes
            return array();
        }

        foreach ($parent_categories as $parent) {
            $category = array(
                'id'        => $parent->term_id,
                'name'      => $parent->name,
                'slug'      => $parent->slug,
                'permalink' => get_term_link($parent->term_id),
                'children'  => array(),
            );

            $args['parent'] = $parent->term_id;
            $children = get_terms($args);
            if (!is_wp_error($children) && !empty($children)) {
                foreach ($children as $child) {
                    $category['children'][] = array(
                        'id'        => $child->term_id,
                        'name'      => $child->name,
                        'slug'      => $child->slug,
                        'permalink' => get_term_link($child->term_id),
                        'parent_id' => $parent->term_id,
                    );
                }
            }

            $categories[] = $category;
        }
        
        // Cache for 15 minutes
        wp_cache_set($cache_key, $categories, '', 15 * MINUTE_IN_SECONDS);
    }

    return $categories;
}
```

---

## Step 3: Optimize Diameter Function

### 3.1 Cache Diameter Values

**Location**: Replace `tdu_mf_get_diameters()` function (lines 264-329)

```php
function tdu_mf_get_diameters() {
    $cache_key = 'tdu_diameters_processed';
    $diameters = get_transient($cache_key);
    
    if (false === $diameters) {
        global $wpdb;

        // Optimized SQL queries
        $sql_variations = "
            SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} pv ON pv.ID = pm.post_id
            WHERE pm.meta_key = 'attribute_pa_product_diameter'
                AND pv.post_type = 'product_variation'
                AND pv.post_status = 'publish'
                AND pm.meta_value != ''
        ";
        
        $sql_terms = "
            SELECT DISTINCT t.slug
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'pa_product_diameter'
                AND t.slug != ''
        ";
        
        $var_vals = $wpdb->get_col($sql_variations);
        $term_vals = $wpdb->get_col($sql_terms);
        
        $raw_vals = array_filter(array_merge($var_vals ?: [], $term_vals ?: []));

        if (empty($raw_vals)) {
            set_transient($cache_key, [], 2 * HOUR_IN_SECONDS);
            return [];
        }

        // Process diameter values (existing logic)
        $to_number = function($val) {
            $s = trim(wp_strip_all_tags((string)$val));
            if ($s === '') return null;

            $s = str_replace(',', '.', $s);
            $s = str_replace(['–','—','/'], '-', $s);

            preg_match_all('/-?\d+(?:\.\d+)?/', $s, $m);
            if (!empty($m[0])) {
                return array_map('floatval', $m[0]);
            }
            return null;
        };

        $nums = [];
        foreach ($raw_vals as $v) {
            $parts = $to_number($v);
            if (is_array($parts)) {
                foreach ($parts as $n) {
                    $nums[] = $n;
                }
            }
        }

        if (empty($nums)) {
            set_transient($cache_key, [], 2 * HOUR_IN_SECONDS);
            return [];
        }

        $nums = array_values(array_unique($nums));
        sort($nums, SORT_NUMERIC);
        
        // Cache for 2 hours
        set_transient($cache_key, $nums, 2 * HOUR_IN_SECONDS);
        $diameters = $nums;
    }

    return $diameters;
}
```

---

## Step 4: Optimize Query Functions

### 4.1 Optimize Product Query

**Location**: Replace `tdu_get_products_from_query_vars()` function (lines 613-633)

```php
/**
 * Gets the products from the query vars with optimized query
 *
 * @param int $per_page Number of products per page.
 * @return array Array of product posts.
 */
function tdu_get_products_from_query_vars($per_page = 8) {
    $current_parent_category = tdu_mf_get_current_parent_category();
    $current_children_category = tdu_mf_get_current_children_category();
    $search = tdu_get_current_search_query_var();

    $query_filters = tdu_mf_get_query_filters($current_children_category ? $current_children_category : $current_parent_category);

    $args = array(
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => $per_page,
        'no_found_rows'          => false, // Keep pagination
        'update_post_meta_cache' => true,  // We need meta for products
        'update_post_term_cache' => true,  // We need terms for products
        'tax_query'              => $query_filters['tax_query'],
        'meta_query'             => $query_filters['meta_query'] ? array_merge(array('relation' => 'AND'), $query_filters['meta_query']) : array(),
        's'                      => $search,
        'tdu_search_title_or_sku' => true,
    );

    $products = new WP_Query($args);

    return $products->posts;
}
```

### 4.2 Optimize Price Min/Max Function

**Location**: Replace `tdu_mf_get_price_min_or_max()` function (lines 337-371)

```php
/**
 * Get the minimum or maximum price based on the current filters with caching
 *
 * @param bool $max Whether to get the maximum price or the minimum price
 * @return float The minimum or maximum price
 */
function tdu_mf_get_price_min_or_max($max = true) {
    $current_parent_category = tdu_mf_get_current_parent_category();
    $current_children_category = tdu_mf_get_current_children_category();
    $search = tdu_get_current_search_query_var();
    
    // Create cache key based on filters
    $cache_key = 'tdu_price_' . ($max ? 'max' : 'min') . '_' . md5(serialize(array(
        'parent_cat' => $current_parent_category,
        'child_cat' => $current_children_category,
        'search' => $search,
        'filters' => $_GET
    )));
    
    $price = get_transient($cache_key);
    
    if (false === $price) {
        $query_filters = tdu_mf_get_query_filters(
            $current_children_category ? $current_children_category : $current_parent_category
        );

        $args = [
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'fields'                 => 'ids', // Only get IDs
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'orderby'                => 'meta_value_num',
            'order'                  => $max ? 'DESC' : 'ASC',
            'meta_key'               => '_price',
            'tax_query'              => $query_filters['tax_query'],
            'meta_query'             => $query_filters['meta_query'] 
                                         ? array_merge(['relation' => 'AND'], $query_filters['meta_query']) 
                                         : [],
            's'                      => $search,
            'tdu_search_title_or_sku'=> true,
        ];

        $products = new WP_Query($args);

        if ($products->have_posts()) {
            $product_id = $products->posts[0];
            $price = (float) get_post_meta($product_id, '_price', true);
        } else {
            $price = 0;
        }
        
        // Cache for 15 minutes
        set_transient($cache_key, $price, 15 * MINUTE_IN_SECONDS);
    }

    return $price;
}
```

---

## Step 5: Implement Cache Invalidation

### 5.1 Add Cache Invalidation Hooks

**Location**: Add at the end of the plugin file (after line 664)

```php
/**
 * Cache invalidation hooks
 */

// Clear category-related caches when categories are modified
add_action('created_product_cat', 'tdu_mf_clear_category_cache');
add_action('edited_product_cat', 'tdu_mf_clear_category_cache');
add_action('delete_product_cat', 'tdu_mf_clear_category_cache');

// Clear brand-related caches when brands are modified
add_action('created_product_brand', 'tdu_mf_clear_brand_cache');
add_action('edited_product_brand', 'tdu_mf_clear_brand_cache');
add_action('delete_product_brand', 'tdu_mf_clear_brand_cache');

// Clear product-related caches when products are modified
add_action('save_post', 'tdu_mf_clear_product_cache', 10, 2);
add_action('delete_post', 'tdu_mf_clear_product_cache', 10, 2);

/**
 * Clear category-related caches
 */
function tdu_mf_clear_category_cache($term_id = null) {
    // Clear categories structure cache
    wp_cache_delete('tdu_categories_structure');
    
    // Clear all category brands caches
    tdu_mf_clear_transients_by_prefix('tdu_category_brands_');
    
    // Clear filter caches
    tdu_mf_clear_transients_by_prefix('tdu_watch_types_');
    tdu_mf_clear_transients_by_prefix('tdu_collections_');
    tdu_mf_clear_transients_by_prefix('tdu_price_');
}

/**
 * Clear brand-related caches
 */
function tdu_mf_clear_brand_cache($term_id = null) {
    // Clear all brand-related caches
    tdu_mf_clear_transients_by_prefix('tdu_category_brands_');
    tdu_mf_clear_transients_by_prefix('tdu_watch_types_');
    tdu_mf_clear_transients_by_prefix('tdu_collections_');
    tdu_mf_clear_transients_by_prefix('tdu_price_');
}

/**
 * Clear product-related caches
 */
function tdu_mf_clear_product_cache($post_id, $post = null) {
    if (is_object($post) && $post->post_type === 'product') {
        // Clear all product-related caches
        tdu_mf_clear_transients_by_prefix('tdu_category_brands_');
        tdu_mf_clear_transients_by_prefix('tdu_watch_types_');
        tdu_mf_clear_transients_by_prefix('tdu_collections_');
        tdu_mf_clear_transients_by_prefix('tdu_price_');
        
        // Clear diameter cache
        delete_transient('tdu_diameters_processed');
    }
}

/**
 * Helper function to clear transients by prefix
 */
function tdu_mf_clear_transients_by_prefix($prefix) {
    global $wpdb;
    
    $sql = "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_{$prefix}%' OR option_name LIKE '_transient_timeout_{$prefix}%'";
    $wpdb->query($sql);
}

/**
 * Admin function to manually clear all plugin caches
 */
function tdu_mf_clear_all_caches() {
    // Clear WordPress object cache
    wp_cache_delete('tdu_categories_structure');
    
    // Clear all transients
    tdu_mf_clear_transients_by_prefix('tdu_category_brands_');
    tdu_mf_clear_transients_by_prefix('tdu_watch_types_');
    tdu_mf_clear_transients_by_prefix('tdu_collections_');
    tdu_mf_clear_transients_by_prefix('tdu_price_');
    delete_transient('tdu_diameters_processed');
    
    return true;
}

// Add admin action to clear caches
add_action('wp_ajax_tdu_clear_filter_caches', function() {
    if (current_user_can('manage_options')) {
        tdu_mf_clear_all_caches();
        wp_send_json_success('All filter caches cleared successfully.');
    } else {
        wp_send_json_error('Unauthorized.');
    }
});
```

---

## Step 6: Optional Performance Enhancements

### 6.1 Add Database Indexes (Optional)

If you have database access, add these indexes for better performance:

```sql
-- Add indexes for better query performance
CREATE INDEX idx_postmeta_diameter ON wp_postmeta(meta_key, meta_value) WHERE meta_key = 'attribute_pa_product_diameter';
CREATE INDEX idx_postmeta_price ON wp_postmeta(meta_key, meta_value) WHERE meta_key = '_price';
CREATE INDEX idx_posts_type_status ON wp_posts(post_type, post_status);
```

### 6.2 Add Debug Information (Optional)

**Location**: Add at the end of the plugin file

```php
/**
 * Debug function to show cache statistics (for development only)
 */
function tdu_mf_debug_cache_stats() {
    if (!WP_DEBUG || !current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    
    $transient_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_tdu_%'");
    
    echo "<!-- TDU Filter Cache Stats: {$transient_count} transients -->";
}
add_action('wp_footer', 'tdu_mf_debug_cache_stats');
```

---

## Implementation Checklist

- [ ] **Step 1**: Implement transients caching for brand, watch types, and collections functions
- [ ] **Step 2**: Add caching to category structure function
- [ ] **Step 3**: Optimize and cache diameter function
- [ ] **Step 4**: Optimize query functions with better WP_Query parameters
- [ ] **Step 5**: Add cache invalidation hooks
- [ ] **Step 6**: (Optional) Add database indexes and debug information

## Testing the Optimization

1. **Before implementing**: Use a plugin like Query Monitor to measure current performance
2. **After implementing**: Compare query counts and execution times
3. **Test cache invalidation**: Modify products/categories and verify caches clear properly
4. **Load testing**: Test with multiple concurrent users to verify performance gains

## Maintenance

- Monitor transient storage usage in `wp_options` table
- Consider implementing a cleanup routine for old transients
- Adjust cache expiration times based on your update frequency
- Monitor query performance and adjust SQL queries if needed

---

**Note**: Always backup your database before implementing these changes and test in a staging environment first.
