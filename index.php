<?php
/**
 * Plugin Name: TDU Meylan Filters
 * Description: Filters for Meylan
 * Version: 1.0.0
 * Author: TDU
 * Text Domain: tdu-meylan-filters
 * Domain Path: /languages
 */

add_action('plugins_loaded', function () {
    load_plugin_textdomain('tdu-meylan-filters', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Cache durations in seconds
define('CACHE_TIME_CATEGORIES_STRUCTURE', 2 * HOUR_IN_SECONDS); // 5 minutes for empty result
define('CACHE_TIME_CATEGORIES_STRUCTURE_WITH_DATA', 2 * HOUR_IN_SECONDS); // 15 minutes for data
define('CACHE_TIME_CATEGORY_BRANDS', 2 * HOUR_IN_SECONDS); // 1 hour
define('CACHE_TIME_WATCH_TYPES', 2 * HOUR_IN_SECONDS); // 30 minutes
define('CACHE_TIME_JEWELRY_TYPES', 2 * HOUR_IN_SECONDS); // 30 minutes
define('CACHE_TIME_COLLECTIONS', 2 * HOUR_IN_SECONDS); // 30 minutes
define('CACHE_TIME_DIAMETERS', 2 * HOUR_IN_SECONDS); // 2 hours
define('CACHE_TIME_PRICE_RANGES', 2 * HOUR_IN_SECONDS); // 15 minutes

/**
 * Suffix to append to cache keys so results for one WPML language never leak
 * into another (needed because get_transient()/wp_cache_get() are shared
 * across all languages of the site).
 *
 * @return string
 */
function tdu_mf_lang_cache_suffix() {
    if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
        $lang = apply_filters( 'wpml_current_language', null );
        if ( $lang ) {
            return '_' . $lang;
        }
    }
    return '';
}

/**
 * Builds the JOIN/WHERE SQL fragments needed to restrict a raw term_taxonomy
 * query to the terms of the current WPML language. Without this, raw SQL
 * queries on wp_terms/wp_term_taxonomy return every language's terms at once
 * (e.g. "Automatic" and "Automatic-en" both showing in a filter dropdown).
 *
 * @param string $taxonomy Taxonomy of the term being selected.
 * @param string $tt_alias SQL alias used for the wp_term_taxonomy row of that term.
 * @return array{join:string,where:string,param:string}|null
 */
function tdu_mf_get_wpml_lang_filter_sql( $taxonomy, $tt_alias = 'tt' ) {
    if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
        return null;
    }

    $current_lang = apply_filters( 'wpml_current_language', null );

    if ( ! $current_lang ) {
        return null;
    }

    global $wpdb;
    $join_alias = 'tdu_icl_' . $tt_alias;

    return array(
        'join'  => "INNER JOIN {$wpdb->prefix}icl_translations {$join_alias} ON {$join_alias}.element_id = {$tt_alias}.term_taxonomy_id AND {$join_alias}.element_type = 'tax_{$taxonomy}'",
        'where' => "{$join_alias}.language_code = %s",
        'param' => $current_lang,
    );
}

/**
 * Get hierarchical product category structure with caching
 *
 * @since 1.0.0
 * @return array Array of product categories with their children
 */
function tdu_mf_get_categories_structure() {
    $cache_key = 'tdu_categories_structure' . tdu_mf_lang_cache_suffix();
    $categories = wp_cache_get($cache_key);
    
    if (false === $categories) {
        $parent_args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'term_order',
            'order'      => 'ASC',
            'parent'     => 0,
        );

        $parent_categories = get_terms($parent_args);
        $categories = array();

        if (is_wp_error($parent_categories) || empty($parent_categories)) {
            wp_cache_set($cache_key, array(), '', CACHE_TIME_CATEGORIES_STRUCTURE);
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

            // Create separate args for children to avoid modifying parent args
            $child_args = array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'orderby'    => 'term_order',
                'order'      => 'ASC',
                'parent'     => $parent->term_id,
            );
            
            $children = get_terms($child_args);
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
        
        wp_cache_set($cache_key, $categories, '', CACHE_TIME_CATEGORIES_STRUCTURE_WITH_DATA);
    }

    return $categories;
}

/**
 * Get children category from query var
 * 
 * @return string|false The children category slug or false
 * @since 1.0.0
 */
function tdu_mf_get_children_category_from_query_var() {
	return isset($_GET['lm_sc']) ? sanitize_text_field(wp_unslash($_GET['lm_sc'])) : false;
}

/**
 * Get category by slug
 * 
 * @param string $parent_category The parent category slug
 * @param string|null $children_category The children category slug or null
 * @return WP_Term|null The category object or null
 * @since 1.0.0
 */
function tdu_mf_get_category_by_slug($parent_category, $children_category = null)
{
	if($children_category){
		return get_term_by('slug', $children_category, 'product_cat');
	}

	return get_term_by('slug', $parent_category, 'product_cat');
}


function tdu_mf_get_nearest_category() 
{
	$main_category = isset($_GET['lm_mc']) ? $_GET['lm_mc'] : false;
	$sub_category = isset($_GET['lm_sc']) ? $_GET['lm_sc'] : false;

	// if there is a main category and a sub category 
	if ($main_category && $sub_category) {
		return tdu_mf_get_category_by_slug($main_category, $sub_category)->term_id ?? null;
	}

	// if there is no main category but a sub category
	if ($main_category) {
		return tdu_mf_get_category_by_slug($main_category, null)->term_id ?? null;
	}

	if(is_tax('product_cat')){
		return meylan_get_queried_term()->term_id ?? null;
	}

	return null;
}

/**
 * Gets the hierarchical structure of product categories
 *
 * Retrieves all parent product categories and their children, organizing them into
 * a nested array structure. Each category contains its ID, name, slug, permalink and
 * any child categories.
 *
 * @return array
 */
function tdu_mf_get_current_parent_category() {

	$main_category = isset($_GET['_product_mc']) ? $_GET['_product_mc'] : false;
	$sub_category = isset($_GET['_product_sc']) ? $_GET['_product_sc'] : false;

	// if there is a main category and a sub category 
	if ($main_category && $sub_category) {
		return tdu_mf_get_category_by_slug($main_category, $sub_category)->term_id ?? null;
	}

	// if there is no main category but a sub category
	if ($main_category) {
		return tdu_mf_get_category_by_slug($main_category, null)->term_id ?? null;
	}

	// if the tax is not product_cat, there is no need to filter category
	if(!$main_category && !$sub_category && !is_tax('product_cat')){
		return null;
	}

	// else...
	$current_term = meylan_get_queried_term();
	
	// Safety check - ensure we have a valid term object
	if (!$current_term || !is_object($current_term) || !isset($current_term->term_id)) {
		return null;
	}

	// If current term has a parent, return parent ID.
	if ( 0 !== $current_term->parent ) {
		return $current_term->parent;
	}

	// Otherwise return current term ID (it is a parent category).
	return $current_term->term_id;
}

/**
 * Gets the current child category ID if viewing a child category
 *
 * When viewing a product category page, determines if the current category
 * is a child category and returns its ID. If viewing a parent category,
 * returns null.
 *
 * @return int|null The term ID of the current child category if viewing a child category,
 *                  null if viewing a parent category
 */
function tdu_mf_get_current_children_category() {
	$current_term = meylan_get_queried_term();
	
	// Safety check - ensure we have a valid term object
	if (!$current_term || !is_object($current_term) || !isset($current_term->term_id)) {
		return null;
	}

	if ( 0 !== $current_term->parent ) {
		return $current_term->term_id;
	}

	return null;
}

/**
 * Gets the brands for a given category with caching
 *
 * @param int $category_id The term ID of the category to retrieve brands for.
 * @return array Array of brand data
 */
function tdu_mf_get_category_brands($category_id) {
    // Safety check - ensure we have a valid category ID
    if (!$category_id || !is_numeric($category_id)) {
        return array();
    }
    
    // Create unique cache key (language-scoped, see tdu_mf_lang_cache_suffix())
    $transient_key = 'tdu_category_brands_' . $category_id . tdu_mf_lang_cache_suffix();
    $brands = get_transient($transient_key);
    
    if (false === $brands) {
        global $wpdb;

        $lang_filter = tdu_mf_get_wpml_lang_filter_sql('product_brand', 'tt');
        $params = array($category_id);
        if ($lang_filter) {
            $params[] = $lang_filter['param'];
        }
        
        // Optimized SQL query instead of get_posts() + wp_get_post_terms()
        $sql = "
            SELECT DISTINCT t.term_id, t.name, t.slug
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_relationships} tr2 ON p.ID = tr2.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
            " . ($lang_filter ? $lang_filter['join'] : '') . "
            WHERE tt.taxonomy = 'product_brand'
            AND tt2.taxonomy = 'product_cat'
            AND tt2.term_id = %d
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            " . ($lang_filter ? "AND {$lang_filter['where']}" : '') . "
            ORDER BY t.name ASC
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        
        // Convert to expected format
        $brands = array();
        if ($results && is_array($results)) {
            foreach ($results as $result) {
                $brands[] = (object) array(
                    'term_id' => $result->term_id,
                    'name' => $result->name,
                    'slug' => $result->slug
                );
            }
        }
        
        set_transient($transient_key, $brands, CACHE_TIME_CATEGORY_BRANDS);
    }
    
    return $brands;
}

/**
 * Gets the watch types for a given category with caching
 *
 * @param int $category_id The term ID of the category to retrieve watch types for.
 * @return array Array of watch type data
 */
function tdu_mf_get_watch_types() {
    $current_nearest_category = tdu_mf_get_nearest_category(); // term_id
    $current_brand = tdu_get_current_brand_query_var();
    
    // Create cache key based on current filters (language-scoped, see tdu_mf_lang_cache_suffix())
    $cache_key = 'tdu_watch_types_' . md5(serialize(array(
        'category' => $current_nearest_category,
        'brand' => $current_brand
    ))) . tdu_mf_lang_cache_suffix();
    
    $types = get_transient($cache_key);
    
    if (false === $types) {
        global $wpdb;
        
        // Build WHERE conditions
        $where_conditions = array("p.post_type = 'product'", "p.post_status = 'publish'");
        $join_conditions = array();
        $params = array();

        // Restrict results to terms of the current WPML language
        $lang_filter = tdu_mf_get_wpml_lang_filter_sql('pa_product_watch_type', 'tt');
        if ($lang_filter) {
            $join_conditions[] = $lang_filter['join'];
            $where_conditions[] = $lang_filter['where'];
            $params[] = $lang_filter['param'];
        }
        
        // Add category filter
        if ($current_nearest_category) {
            $join_conditions[] = "INNER JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id";
            $join_conditions[] = "INNER JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id";
            $where_conditions[] = "tt_cat.taxonomy = 'product_cat' AND tt_cat.term_id = %d";
            $params[] = $current_nearest_category;
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
        
        // Ensure we have a valid result
        if (!$types || !is_array($types)) {
            $types = array();
        }
        
        set_transient($cache_key, $types, CACHE_TIME_WATCH_TYPES);
    }
    
    return $types;
}

/**
 * Gets the jewelry types for a given category with caching
 *
 * @return array Array of jewelry type data
 */
function tdu_mf_get_jewelry_types() {
    $current_nearest_category = tdu_mf_get_nearest_category();
    $current_brand = tdu_get_current_brand_query_var();

    $cache_key = 'tdu_jewelry_types_' . md5(serialize(array(
        'category' => $current_nearest_category,
        'brand' => $current_brand
    ))) . tdu_mf_lang_cache_suffix();

    $types = get_transient($cache_key);

    if (false === $types) {
        global $wpdb;

        $where_conditions = array("p.post_type = 'product'", "p.post_status = 'publish'");
        $join_conditions = array();
        $params = array();

        $lang_filter = tdu_mf_get_wpml_lang_filter_sql('pa_product_jewelry_type', 'tt');
        if ($lang_filter) {
            $join_conditions[] = $lang_filter['join'];
            $where_conditions[] = $lang_filter['where'];
            $params[] = $lang_filter['param'];
        }

        if ($current_nearest_category) {
            $join_conditions[] = "INNER JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id";
            $join_conditions[] = "INNER JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id";
            $where_conditions[] = "tt_cat.taxonomy = 'product_cat' AND tt_cat.term_id = %d";
            $params[] = $current_nearest_category;
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
            WHERE tt.taxonomy = 'pa_product_jewelry_type'
            AND " . implode(' AND ', $where_conditions) . "
            ORDER BY t.name ASC
        ";

        if (!empty($params)) {
            $types = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        } else {
            $types = $wpdb->get_results($sql);
        }

        if (!$types || !is_array($types)) {
            $types = array();
        }

        set_transient($cache_key, $types, CACHE_TIME_JEWELRY_TYPES);
    }

    return $types;
}

/**
 * Gets the collections for a given category with caching
 *
 * @param int $category_id The term ID of the category to retrieve collections for.
 * @return array Array of collection data
 */
function tdu_mf_get_collections() {
    $current_nearest_category = tdu_mf_get_nearest_category();
    $current_brand = tdu_get_current_brand_query_var();
    
    // Create cache key based on current filters (language-scoped, see tdu_mf_lang_cache_suffix())
    $cache_key = 'tdu_collections_' . md5(serialize(array(
        'parent_cat' => $current_nearest_category,
        'brand' => $current_brand
    ))) . tdu_mf_lang_cache_suffix();
    
    $collections = get_transient($cache_key);
    
    if (false === $collections) {
        global $wpdb;
        
        // Build WHERE conditions (same logic as watch types)
        $where_conditions = array("p.post_type = 'product'", "p.post_status = 'publish'");
        $join_conditions = array();
        $params = array();

        // Restrict results to terms of the current WPML language
        $lang_filter = tdu_mf_get_wpml_lang_filter_sql('product_collection', 'tt');
        if ($lang_filter) {
            $join_conditions[] = $lang_filter['join'];
            $where_conditions[] = $lang_filter['where'];
            $params[] = $lang_filter['param'];
        }
        
        if ($current_nearest_category) {
            $join_conditions[] = "INNER JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id";
            $join_conditions[] = "INNER JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id";
            $where_conditions[] = "tt_cat.taxonomy = 'product_cat' AND tt_cat.term_id = %d";
            $params[] = $current_nearest_category;
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
        
        // Ensure we have a valid result
        if (!$collections || !is_array($collections)) {
            $collections = array();
        }
        
        set_transient($cache_key, $collections, CACHE_TIME_COLLECTIONS);
    }
    
    return $collections;
}

/**
 * Gets the diameters for a given category with caching
 *
 * @param int $category_id The term ID of the category to retrieve diameters for.
 * @return array Array of diameter data
 */
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
            set_transient($cache_key, [], CACHE_TIME_DIAMETERS);
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
            set_transient($cache_key, [], CACHE_TIME_DIAMETERS);
            return [];
        }

        $nums = array_values(array_unique($nums));
        sort($nums, SORT_NUMERIC);
        
        set_transient($cache_key, $nums, CACHE_TIME_DIAMETERS);
        $diameters = $nums;
    }

    return $diameters;
}

/**
 * Get the minimum or maximum price based on the current filters with caching
 *
 * @param bool $max Whether to get the maximum price or the minimum price
 * @return float The minimum or maximum price
 */
function tdu_mf_get_price_min_or_max($max = true) {
	$current_nearest_category = tdu_mf_get_nearest_category();
	 $search = tdu_get_current_search_query_var();
    
    // Create cache key based on filters
    $cache_key = 'tdu_price_' . ($max ? 'max' : 'min') . '_' . md5(serialize(array(
        'category' => $current_nearest_category,
        
        'search' => $search,
        'filters' => $_GET
    )));
    
    $price = get_transient($cache_key);
    
    if (false === $price) {
        $query_filters = tdu_mf_get_query_filters($current_nearest_category);

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
        
        set_transient($cache_key, $price, CACHE_TIME_PRICE_RANGES);
    }

    return $price;
}

/**
 * Get the lower price and higher price based on current filters
 */
function tdu_mf_get_price_options(){
	$max_price = tdu_mf_get_price_min_or_max();

	$options = array();
	for ($i = 0; $i <= $max_price; $i += 100) {
		$options[] = $i;
	}

	return $options;
}

/**
 * Gets the new categories
 *
 * @return array Array of new categories
 */
function tdu_mf_get_new_categories()
{
	$is_brand = is_tax('product_brand');
	
	$parent_category = get_term_by('slug', 'neuf', 'product_cat');

	if ($is_brand) {
		// Get current brand term
		$brand = meylan_get_queried_term();
		
		// Check cache first
		$cache_key = 'brand_categories_' . $brand->term_id;
		$children_categories = wp_cache_get($cache_key);

		if (false === $children_categories) {
			// Get products from this brand
			$products = get_posts(array(
				'post_type' => 'product',
				'numberposts' => -1,
				'tax_query' => array(
					array(
						'taxonomy' => 'product_brand',
						'field' => 'term_id',
						'terms' => $brand->term_id
					)
				)
			));

			// Get categories from these products
			$category_ids = array();
			foreach ($products as $product) {
				$terms = wp_get_post_terms($product->ID, 'product_cat');
				foreach ($terms as $term) {
					if ($term->parent === $parent_category->term_id) {
						$category_ids[$term->term_id] = $term->term_id;
					}
				}
			}

			// Get the category terms
			$children_categories = get_terms(array(
				'taxonomy' => 'product_cat',
				'include' => array_values($category_ids),
				'hide_empty' => false
			));

			// Cache for 1 hour
			wp_cache_set($cache_key, $children_categories, '', CACHE_TIME_CATEGORY_BRANDS);
		}

	} else {
		$children_categories = get_terms(array(
			'taxonomy' => 'product_cat', 
			'hide_empty' => false,
			'parent' => $parent_category->term_id,
		));
	}

	return $children_categories;
}


/**
 * Gets the query filters for products
 *
 * Builds and returns an array of WP_Query tax_query and meta_query filters based on the current
 * category, brand and search parameters.
 *
 * @param int|null $category_id Optional category term ID to filter by.
 * @param int|null $brand_id    Optional brand term ID to filter by (currently unused).
 * @return array 
 */
function tdu_mf_get_query_filters( $category_id = null, $brand_id = null ) {
	$tax_query_filters  = array();
	$meta_query_filters = array();
	$search             = tdu_get_current_search_query_var() ?: null;
	$brand              = tdu_get_current_brand_query_var() ?: null;
	$current_price_min        = tdu_get_current_price_min_query_var();
	$current_price_max        = tdu_get_current_price_max_query_var();
	$current_diameter_min     = tdu_get_current_diameter_min_query_var();
	$current_diameter_max     = tdu_get_current_diameter_max_query_var();
	$current_collection       = tdu_get_current_collection_query_var();
	$current_watch_type       = tdu_get_current_type_query_var();
	$current_jewelry_type     = tdu_get_current_jewelry_type_query_var();

	if($brand_id !== null){
		$brand = get_term_by('id', $brand_id, 'product_brand')->slug;
	}

	if ( $category_id ) {
		$tax_query_filters[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => $category_id,
		);
	}

	if ( $brand ) {
		$tax_query_filters[] = array(
			'taxonomy' => 'product_brand',
			'field'    => 'slug',
			'terms'    => $brand,
		);
	}
	
	if ($current_price_min) {
		$meta_query_filters[] = array(
			'key'     => '_price',
			'value'   => $current_price_min,
			'compare' => '>=',
			'type'    => 'NUMERIC'
		);
	}

	if ($current_price_max) {
		$meta_query_filters[] = array(
			'key'     => '_price',
			'value'   => $current_price_max,
			'compare' => '<=',
			'type'    => 'NUMERIC'
		);
	}

	if($current_collection) {
		$tax_query_filters[] = array(
			'taxonomy' => 'product_collection',
			'field'    => 'slug',
			'terms'    => $current_collection,
		);
	}
	
	// filter attributes : pa_product_watch_type
	if($current_watch_type) {
		$tax_query_filters[] = array(
			'taxonomy' => 'pa_product_watch_type',
			'field'    => 'slug',
			'terms'    => $current_watch_type,
			'operator' => 'IN'
		);
	}

	// filter attributes : pa_product_jewelry_type
	if($current_jewelry_type) {
		$tax_query_filters[] = array(
			'taxonomy' => 'pa_product_jewelry_type',
			'field'    => 'slug',
			'terms'    => $current_jewelry_type,
			'operator' => 'IN'
		);
	}
	
	$meta_query_filters['stock'] = [
      'key'     => '_stock_status',
      'compare' => 'EXISTS',
	];

	if ($current_diameter_min || $current_diameter_max) {
		// Get all diameter terms
		$diameter_terms = get_terms(array(
			'taxonomy' => 'pa_product_diameter',
			'hide_empty' => false
		));

		// Filter terms based on min/max values
		$valid_terms = array();
		foreach ($diameter_terms as $term) {
			$diameter_value = (float)$term->slug;
			
			if ($current_diameter_min && $diameter_value < $current_diameter_min) {
				continue;
			}
			if ($current_diameter_max && $diameter_value > $current_diameter_max) {
				continue;
			}
			
			$valid_terms[] = $term->slug;
		}

		if (!empty($valid_terms)) {
			$tax_query_filters[] = array(
				'taxonomy' => 'pa_product_diameter',
				'field'    => 'slug',
				'terms'    => $valid_terms,
				'operator' => 'IN'
			);
		}
	}

	

	return array(
		'tax_query'  => $tax_query_filters ? array_merge( array( 'relation' => 'AND' ), $tax_query_filters ) : array(),
		'meta_query' => $meta_query_filters ? array_merge( array( 'relation' => 'AND' ), $meta_query_filters ) : array(),
		's'          => $search,
	);
}

/**
 * Returns the brand query variable from the GET parameters or the brand of the current term if the tax is product_brand.
 *
 * @return string|null Brand slug
 * @since 1.0.0
 */
function tdu_get_current_brand_query_var() {
	if(is_tax('product_brand')){
		return meylan_get_queried_term()->slug ?? null;
	}

	return isset( $_GET['lm_brand'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_brand'] ) ) : null;
}

/**
 * Gets the current search query variable
 *
 * Returns the search query variable from the GET parameters.
 *
 * @return string|null The search query variable if present, otherwise null
 */
function tdu_get_current_search_query_var() {
	return isset( $_GET['lm_search'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_search'] ) ) : null;
}

/**
 * Gets the current price min query variable
 *
 * Returns the price min query variable from the GET parameters.
 *
 * @return string|null The price min query variable if present, otherwise null
 */
function tdu_get_current_price_min_query_var() {

	return isset( $_GET['lm_price_min'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_price_min'] ) ) : null;
}

/**
 * Gets the current diameter min query variable
 *
 * Returns the diameter min query variable from the GET parameters.
 *
 * @return string|null The diameter min query variable if present, otherwise null
 */
function tdu_get_current_diameter_min_query_var() {
	return isset( $_GET['lm_diameter_min'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_diameter_min'] ) ) : null;
}

/**
 * Gets the current diameter max query variable
 *
 * Returns the diameter max query variable from the GET parameters.
 *
 * @return string|null The diameter max query variable if present, otherwise null
 */
function tdu_get_current_diameter_max_query_var() {
	return isset( $_GET['lm_diameter_max'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_diameter_max'] ) ) : null;
}

/**
 * Gets the current price max query variable
 *
 * Returns the price max query variable from the GET parameters.
 *
 * @return string|null The price max query variable if present, otherwise null
 */
function tdu_get_current_price_max_query_var() {
	return isset( $_GET['lm_price_max'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_price_max'] ) ) : null;
}

/**
 * Gets the current collection query variable
 *
 * Returns the collection query variable from the GET parameters.
 *
 * @return string|null The collection query variable if present, otherwise null
 */
function tdu_get_current_collection_query_var() {
	return isset( $_GET['lm_collection'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_collection'] ) ) : null;
}

/**
 * Gets the current type query variable
 *
 * Returns the type query variable from the GET parameters.
 *
 * @return string|null The type query variable if present, otherwise null
 */function tdu_get_current_type_query_var() {
	return isset( $_GET['lm_type'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_type'] ) ) : null;
}

/**
 * Gets the current jewelry type query variable
 *
 * Returns the jewelry type query variable from the GET parameters.
 *
 * @return string|null The jewelry type query variable if present, otherwise null
 */
function tdu_get_current_jewelry_type_query_var() {
	return isset( $_GET['lm_jewelry_type'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_jewelry_type'] ) ) : null;
}


/**
 * Renders the filters
 *
 * Renders the filters for the current category.
 */
function tdu_mf_render_filters() {
	$current_parent_category   = tdu_mf_get_current_parent_category();
	$current_children_category = tdu_mf_get_current_children_category();
	$current_brand            = tdu_get_current_brand_query_var();
	$current_price_min        = tdu_get_current_price_min_query_var();
	$current_price_max        = tdu_get_current_price_max_query_var();

	$is_tax_brand = is_tax('product_brand');

	$categories = tdu_mf_get_categories_structure();
	$new_categories = tdu_mf_get_new_categories();
	$brands     = tdu_mf_get_category_brands( $current_children_category ? $current_children_category : $current_parent_category );
	$diameters = tdu_mf_get_diameters();
	$collections = tdu_mf_get_collections();
	$is_jewerly = tdu_mf_is_jewerly();
	$watch_types = $is_jewerly ? array() : tdu_mf_get_watch_types();
	$jewelry_types = $is_jewerly ? tdu_mf_get_jewelry_types() : array();
	?>
	<div class="tdu-filters-wrapper" id="tdu-mf-results">
		<button class="tdu-filters-mobile-toggle" aria-expanded="false" aria-controls="tdu-filters-content">
			<span class="label"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" style="opacity: 0.8; margin-right: 8px;" viewBox="0 0 16 16"><path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/></svg> <?php esc_html_e('Filtres', 'tdu-meylan-filters'); ?></span>
			<span class="tdu-filters-count"></span>
			<span class="visually-hidden"><?php esc_html_e('Ouvrir/fermer les filtres', 'tdu-meylan-filters'); ?></span>
		</button>

		<div id="tdu-filters-content" class="tdu-filters-content">
			<?php if(!$is_tax_brand): ?>
			<!-- categories -->
			<div class="tdu-filters-wrapper-form-categories">
				<?php include plugin_dir_path( __FILE__ ) . 'partials/filter-product-categories.php'; ?>
			</div>
			<?php endif; ?>

			<!-- custom filters -->
			<div class="tdu-filters-wrapper-form">
				<form action="#tdu-mf-results" method="GET" class="tdu-filters-wrapper-form-search">
					<?php if (!$is_tax_brand): ?>
						<?php include plugin_dir_path(__FILE__) . 'partials/filter-product-brands.php'; ?>
					<?php endif; ?>
					
					<?php if ($is_tax_brand): ?>
						<div class="tdu-filters-wrapper-form-categories">
							<?php include plugin_dir_path(__FILE__) . 'partials/filter-product-categories-dropdown.php'; ?>
						</div>
					<?php endif; ?>

					<?php if($is_tax_brand || (isset($_GET['lm_brand']) && $_GET['lm_brand'] !== '')): ?>
					<?php include plugin_dir_path(__FILE__) . 'partials/filter-collections.php'; ?>
					<?php endif; ?>

					<?php if ($is_jewerly): ?>
					<?php include plugin_dir_path(__FILE__) . 'partials/filter-jewelry-types.php'; ?>
					<?php else: ?>
					<?php include plugin_dir_path(__FILE__) . 'partials/filter-watch-types.php'; ?>
					<?php endif; ?>
					
					<?php if (!tdu_mf_is_jewerly() && !tdu_mf_is_accessories()): ?>
						<?php include plugin_dir_path(__FILE__) . 'partials/filter-diameter.php'; ?>
					<?php endif; ?>
					
					<?php include plugin_dir_path(__FILE__) . 'partials/filter-price.php'; ?>
					<?php include plugin_dir_path(__FILE__) . 'partials/filter-search.php'; ?>

					<button type="submit" class="tdu-filters-wrapper-form-search-button btn-primary reverse">
						<span class="label"><?php esc_html_e('Filtrer', 'tdu-meylan-filters'); ?></span>
						<span class="visually-hidden loading-text"><?php esc_html_e('Chargement…', 'tdu-meylan-filters'); ?></span>
					</button>
				</form>
				<?php include plugin_dir_path(__FILE__) . 'partials/reset-filters.php'; ?>
			</div>
		</div>
	</div>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const toggleButton = document.querySelector('.tdu-filters-mobile-toggle');
			const filtersContent = document.querySelector('#tdu-filters-content');
			const filtersCount = document.querySelector('.tdu-filters-count');

			if (toggleButton && filtersContent) {
				toggleButton.addEventListener('click', function() {
					const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';
					toggleButton.setAttribute('aria-expanded', !isExpanded);
					filtersContent.classList.toggle('is-open');
				});
			}

			// Count active filters
			function updateFiltersCount() {
				const urlParams = new URLSearchParams(window.location.search);
				let count = 0;

				// List of filter parameters to check
				const filterParams = ['lm_brand', 'lm_collection', 'lm_type', 'lm_jewelry_type', 'lm_diameter', 'lm_price_min', 'lm_price_max', 'lm_search'];

				filterParams.forEach(param => {
					if (urlParams.has(param) && urlParams.get(param) !== '') {
						count++;
					}
				});

				// Update the count badge
				if (count > 0) {
					filtersCount.textContent = count;
				} else {
					filtersCount.textContent = '';
				}
			}

			// Initial count update
			updateFiltersCount();
		});
	</script>
	<?php
}


/**
 * Gets the products from the query vars with optimized query
 *
 * @param int $per_page Number of products per page.
 * @return array Array of product posts.
 */
function tdu_get_products_from_query_vars($per_page = 12, $brand = null) {
	$current_nearest_category  = tdu_mf_get_nearest_category();
	$current_parent_category   = tdu_mf_get_current_parent_category();
	$current_children_category = tdu_mf_get_current_children_category();

	$search        = tdu_get_current_search_query_var();
	$query_filters = tdu_mf_get_query_filters($current_nearest_category, $brand);
	$paged         = tdu_mf_get_current_page();

	$meta_query = $query_filters['meta_query'] ? array_merge(
		 array('relation' => 'AND'),
		 $query_filters['meta_query']
	) : array('relation' => 'AND');

	

	$args = array(
		 'post_type'              => 'product',
		 'post_status'            => 'publish',
		 'posts_per_page'         => $per_page,
		 'paged'                  => $paged,
		 'no_found_rows'          => false,
		 'update_post_meta_cache' => true,
		 'update_post_term_cache' => true,
		 'tax_query'              => $query_filters['tax_query'],
		 'meta_query'             => $meta_query,
		 's'                      => $search,
		 'tdu_search_title_or_sku'=> true,
		 'orderby'                => array(
			  // sera surchargé par le filtre ci-dessous
			  'date' => 'DESC',
		 ),
		 'suppress_filters'       => false,
		 'tdu_custom_ordering'    => true, // flag pour limiter le filtre
	);

	return tdu_query_products( $args );
}

function tdu_join_is_new( $clauses, $query ) {
	if ( ! $query->get('tdu_custom_ordering') || $query->get('post_type') !== 'product' ) {
		 return $clauses;
	}
	global $wpdb;

	$clauses['join'] .= $wpdb->prepare(
		 " LEFT JOIN {$wpdb->postmeta} AS pm_is_new
				ON pm_is_new.post_id = {$wpdb->posts}.ID
			  AND pm_is_new.meta_key = %s",
		 'product_is_new'
	);

	return $clauses;
}

/**
* 2) Filtre: ajoute le JOIN lookup Woo et impose l’ORDER BY final
*    Ordre: Nouveauté DESC → Stock instock,onbackorder,outofstock → Date DESC
*/
function tdu_order_products( $clauses, $query ) {
	if ( ! $query->get('tdu_custom_ordering') || $query->get('post_type') !== 'product' ) {
		 return $clauses;
	}
	global $wpdb;

	// Table lookup WooCommerce
	$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
	$clauses['join'] .= " LEFT JOIN {$lookup} AS wpl ON wpl.product_id = {$wpdb->posts}.ID";

	// Construit l'ORDER BY complet
	$orderby_parts = array(
		 "CASE WHEN pm_is_new.meta_value IN ('1','true','yes') THEN 1 ELSE 0 END DESC",
		 "CASE wpl.stock_status
			  WHEN 'instock' THEN 1
			  WHEN 'onbackorder' THEN 2
			  ELSE 3
		  END ASC",
		 "{$wpdb->posts}.post_date DESC",
	);

	// Remplace/préfixe l'orderby existant sans perdre d'autres tris éventuels
	$clauses['orderby'] = implode(', ', $orderby_parts) . ( $clauses['orderby'] ? ', ' . $clauses['orderby'] : '' );

	// Ensure we use DISTINCT to avoid duplicate counting due to JOINs
	$clauses['fields'] = "DISTINCT {$wpdb->posts}.ID";

	return $clauses;
}

/**
* 3) Exécute WP_Query avec activation/désactivation temporaires des filtres
*/
function tdu_query_products( $args ) {
	add_filter('posts_clauses', 'tdu_join_is_new', 9, 2);
	add_filter('posts_clauses', 'tdu_order_products', 10, 2);

	$enable_search = ! empty($args['tdu_search_title_or_sku']) && ! empty($args['s']);
    if ( $enable_search ) {
        add_filter('posts_join',     'tdu_join_sku', 10, 2);
        add_filter('posts_search',   'tdu_search_title_or_sku_where', 10, 2);
        add_filter('posts_distinct', 'tdu_distinct_if_join', 10, 2);
    }

    $q = new WP_Query( $args );

    remove_filter('posts_clauses',  'tdu_join_is_new', 9);
    remove_filter('posts_clauses',  'tdu_order_products', 10);
    if ( $enable_search ) {
        remove_filter('posts_join',     'tdu_join_sku', 10);
        remove_filter('posts_search',   'tdu_search_title_or_sku_where', 10);
        remove_filter('posts_distinct', 'tdu_distinct_if_join', 10);
    }

    return $q;
}

function tdu_search_title_or_sku_where( $search, $query ) {
	if ( $query->get('post_type') !== 'product' ) return $search;
	if ( ! $query->get('tdu_search_title_or_sku') ) return $search;

	$term = $query->get('s');
	if ( ! $term && $term !== '0' ) return $search;

	global $wpdb;
	$like = '%' . $wpdb->esc_like( $term ) . '%';

	// Remplace le WHERE de WP pour ne garder que titre OU SKU
	return $wpdb->prepare(
		 " AND ( {$wpdb->posts}.post_title LIKE %s OR pm_sku.meta_value LIKE %s ) ",
		 $like, $like
	);
}

function tdu_distinct_if_join( $distinct, $query ) {
	if ( $query->get('post_type') === 'product'
	  && $query->get('tdu_search_title_or_sku')
	  && $query->get('s') ) {
		 return 'DISTINCT';
	}
	return $distinct;
}

// JOIN le SKU uniquement si on cherche
function tdu_join_sku( $join, $query ) {
	if ( $query->get('post_type') !== 'product' ) return $join;
	if ( ! $query->get('tdu_search_title_or_sku') || ! $query->get('s') ) return $join;

	global $wpdb;
	if ( strpos($join, 'pm_sku') !== false ) return $join;

	$join .= $wpdb->prepare(
		 " LEFT JOIN {$wpdb->postmeta} AS pm_sku
				  ON pm_sku.post_id = {$wpdb->posts}.ID
				 AND pm_sku.meta_key = %s",
		 '_sku'
	);
	return $join;
}

/**
 * Search products by title OR SKU (products and variations).
 */
function tdu_search_products_title_or_sku_search( $search_sql, $query ) {
	if ( ! $query->get( 'tdu_search_title_or_sku' ) ) {
		 return $search_sql; // not our custom search
	}

	$raw = $query->get( 's' );
	if ( ! $raw ) {
		 return $search_sql; // nothing to search
	}

	global $wpdb;
	$like = '%' . $wpdb->esc_like( $raw ) . '%';

	// Replace the default search in order: 
	// - post_title LIKE
	// - product _sku LIKE
	// - variation _sku LIKE (match parent product)
	$search_sql = $wpdb->prepare(
		 " AND (
			  {$wpdb->posts}.post_title LIKE %s
			  OR EXISTS (
					SELECT 1
					FROM {$wpdb->postmeta} pm
					WHERE pm.post_id = {$wpdb->posts}.ID
					  AND pm.meta_key = '_sku'
					  AND pm.meta_value LIKE %s
			  )
			  OR EXISTS (
					SELECT 1
					FROM {$wpdb->posts} v
					INNER JOIN {$wpdb->postmeta} pmv
							  ON pmv.post_id = v.ID
							 AND pmv.meta_key = '_sku'
							 AND pmv.meta_value LIKE %s
					WHERE v.post_type = 'product_variation'
					  AND v.post_parent = {$wpdb->posts}.ID
			  )
		 )",
		 $like, $like, $like
	);

	return $search_sql;
}
add_filter( 'posts_search', 'tdu_search_products_title_or_sku_search', 20, 2 );

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
 * Deletes the 'tdu_categories_structure' object cache entry for every WPML
 * language (not just the language of the request that triggered the clear).
 */
function tdu_mf_clear_categories_structure_cache() {
    wp_cache_delete('tdu_categories_structure');

    if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
        $languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
        if ( is_array( $languages ) ) {
            foreach ( array_keys( $languages ) as $lang_code ) {
                wp_cache_delete( 'tdu_categories_structure_' . $lang_code );
            }
        }
    }
}

/**
 * Clear category-related caches
 */
function tdu_mf_clear_category_cache($term_id = null) {
    // Clear categories structure cache
    tdu_mf_clear_categories_structure_cache();
    
    // Clear all category brands caches
    tdu_mf_clear_transients_by_prefix('tdu_category_brands_');
    
    // Clear filter caches
    tdu_mf_clear_transients_by_prefix('tdu_watch_types_');
    tdu_mf_clear_transients_by_prefix('tdu_jewelry_types_');
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
    tdu_mf_clear_transients_by_prefix('tdu_jewelry_types_');
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
        tdu_mf_clear_transients_by_prefix('tdu_jewelry_types_');
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
    tdu_mf_clear_categories_structure_cache();
    
    // Clear all transients
    tdu_mf_clear_transients_by_prefix('tdu_category_brands_');
    tdu_mf_clear_transients_by_prefix('tdu_watch_types_');
    tdu_mf_clear_transients_by_prefix('tdu_jewelry_types_');
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

/**
 * Add admin menu for TDU Meylan Filters
 */
function tdu_mf_admin_menu() {
    add_options_page(
        'TDU Meylan Filters', 
        'TDU Filters', 
        'manage_options', 
        'tdu-meylan-filters', 
        'tdu_mf_admin_page'
    );
}
add_action('admin_menu', 'tdu_mf_admin_menu');

/**
 * Add admin bar menu for quick cache clearing
 */
function tdu_mf_admin_bar_menu($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_menu(array(
        'id'    => 'tdu-filters-cache',
        'title' => 'TDU Filters Cache',
        'href'  => admin_url('options-general.php?page=tdu-meylan-filters'),
    ));
    
    $wp_admin_bar->add_menu(array(
        'parent' => 'tdu-filters-cache',
        'id'     => 'tdu-clear-cache',
        'title'  => 'Clear All Caches',
        'href'   => wp_nonce_url(admin_url('options-general.php?page=tdu-meylan-filters&action=clear_cache'), 'tdu_clear_cache', 'tdu_cache_nonce'),
    ));
}
add_action('admin_bar_menu', 'tdu_mf_admin_bar_menu', 100);

/**
 * Admin page content
 */
function tdu_mf_admin_page() {
    // Handle cache clearing
    if (isset($_POST['clear_cache']) && current_user_can('manage_options')) {
        tdu_mf_clear_all_caches();
        echo '<div class="notice notice-success"><p>All filter caches cleared successfully!</p></div>';
    }
    
    // Get cache statistics
    global $wpdb;
    $transient_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_tdu_%'");
    $cache_size = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE '_transient_tdu_%'");
    $cache_size_mb = round(($cache_size ?: 0) / 1024 / 1024, 2);
    
    ?>
    <div class="wrap">
        <h1>TDU Meylan Filters - Cache Management</h1>
        
        <div class="card">
            <h2>Cache Statistics</h2>
            <p><strong>Active Transients:</strong> <?php echo $transient_count; ?></p>
            <p><strong>Cache Size:</strong> <?php echo $cache_size_mb; ?> MB</p>
        </div>
        
        <div class="card">
            <h2>Clear All Caches</h2>
            <p>This will clear all cached filter data. The cache will be rebuilt automatically on the next page load.</p>
            <form method="post" style="margin-top: 15px;">
                <?php wp_nonce_field('tdu_clear_cache', 'tdu_cache_nonce'); ?>
                <input type="submit" name="clear_cache" class="button button-primary" value="Clear All Caches" 
                       onclick="return confirm('Are you sure you want to clear all filter caches?');">
            </form>
        </div>
        
        <div class="card">
            <h2>Cache Information</h2>
            <p><strong>Categories Structure:</strong> Cached for 15 minutes</p>
            <p><strong>Category Brands:</strong> Cached for 1 hour</p>
            <p><strong>Watch Types:</strong> Cached for 30 minutes</p>
            <p><strong>Jewelry Types:</strong> Cached for 30 minutes</p>
            <p><strong>Collections:</strong> Cached for 30 minutes</p>
            <p><strong>Diameters:</strong> Cached for 2 hours</p>
            <p><strong>Price Ranges:</strong> Cached for 15 minutes</p>
        </div>
        
        <div class="card">
            <h2>Automatic Cache Clearing</h2>
            <p>The following actions automatically clear related caches:</p>
            <ul>
                <li>Creating, editing, or deleting product categories</li>
                <li>Creating, editing, or deleting product brands</li>
                <li>Creating, editing, or deleting products</li>
            </ul>
        </div>
    </div>
    
    <style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin: 20px 0;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .card h2 {
        margin-top: 0;
        color: #23282d;
    }
    </style>
    <?php
}





/**
 * Debug function to help troubleshoot performance issues
 * Only active when WP_DEBUG is enabled
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    function tdu_mf_debug_info() {
        if (current_user_can('manage_options')) {
            echo "<!-- TDU Filters Debug Info:\n";
            echo "Current Parent Category: " . (tdu_mf_get_current_parent_category() ?: 'null') . "\n";
            echo "Current Children Category: " . (tdu_mf_get_current_children_category() ?: 'null') . "\n";
            echo "Current Brand: " . (tdu_get_current_brand_query_var() ?: 'null') . "\n";
            echo "Cache Status: " . (wp_cache_get('tdu_categories_structure') ? 'enabled' : 'disabled') . "\n";
            echo "-->\n";
        }
    }
    add_action('wp_footer', 'tdu_mf_debug_info');
}

/**
 * Get current page from $_GET['page'] parameter
 * 
 * This function replaces WordPress's default paged parameter with a custom 'page' parameter.
 * Use this instead of get_query_var('paged') for custom pagination.
 * 
 * @return int Current page number (defaults to 1)
 * @since 1.0.0
 */
function tdu_mf_get_current_page() {
	return isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
}

/**
 * Generate custom pagination URL with $_GET['page'] parameter
 * 
 * This function creates pagination URLs using the custom 'page' parameter instead of WordPress's default 'paged'.
 * It preserves all existing query parameters while adding or updating the page parameter.
 * 
 * @param int $page_number Page number to generate URL for
 * @return string URL with page parameter (or base URL for page 1)
 * @since 1.0.0
 */
function tdu_mf_get_pagination_url($page_number) {
	$current_url = remove_query_arg('page');
	$page_number = max(1, (int) $page_number);
	
	if ($page_number > 1) {
		return add_query_arg('page', $page_number, $current_url);
	}
	
	return $current_url;
}

/**
 * Renders the custom pagination for the products query using $_GET['page']
 * 
 * This function generates pagination links using the custom 'page' parameter instead of WordPress's default 'paged'.
 * It includes previous/next links, numbered pages, and a dropdown for large page counts.
 * 
 * @param WP_Query $products_query The WP_Query object containing the products
 * @since 1.0.0
 */
function tdu_mf_pagination( WP_Query $products_query ) {
	if ( ! $products_query || $products_query->max_num_pages < 2 ) {
		return;
	}

	// Get current page from $_GET['page'] instead of paged
	$current_page = tdu_mf_get_current_page();
	$total_pages = (int) $products_query->max_num_pages;
	
	echo '<div class="tdu-mf-pagination">';

	// Prev
	if ( $current_page > 1 ) {
		$prev_url = tdu_mf_get_pagination_url($current_page - 1);
		echo '<a href="' . esc_url( $prev_url . '#tdu-mf-results' ) . '" class="tdu-mf-prev tdu-mf-page-numbers" rel="prev">&laquo;</a>';
	}

	// Desktop only - First page
	echo '<div class="tdu-mf-desktop-only">';
	if ( $current_page > 3 ) {
		$page_url = tdu_mf_get_pagination_url(1);
		echo '<a href="' . esc_url( $page_url . '#tdu-mf-results' ) . '" class="tdu-mf-page-numbers">1</a>';
		
		if ( $current_page > 4 ) {
			echo '<select class="tdu-mf-pagination-dropdown no-tom" onchange="if(this.value)window.location.href=this.value+\'#tdu-mf-results\'">';
			echo '<option value="">...</option>';
			for ( $i = 2; $i < $current_page - 1; $i++ ) {
				$url = tdu_mf_get_pagination_url($i);
				echo '<option value="' . esc_url( $url ) . '">' . (int) $i . '</option>';
			}
			echo '</select>';
		}
	}

	// Desktop only - Pages around current
	for ( $i = max(1, $current_page - 1); $i <= min($total_pages, $current_page + 1); $i++ ) {
		if ( $i === $current_page ) {
			echo '<span class="tdu-mf-page-numbers tdu-mf-current">' . (int) $i . '</span>';
		} else {
			$page_url = tdu_mf_get_pagination_url($i);
			echo '<a href="' . esc_url( $page_url . '#tdu-mf-results' ) . '" class="tdu-mf-page-numbers">' . (int) $i . '</a>';
		}
	}

	// Desktop only - Last page
	if ( $current_page < $total_pages - 2 ) {
		if ( $current_page < $total_pages - 3 ) {
			echo '<select class="tdu-mf-pagination-dropdown no-tom" onchange="if(this.value)window.location.href=this.value+\'#tdu-mf-results\'">';
			echo '<option value="">...</option>';
			for ( $i = $current_page + 2; $i < $total_pages; $i++ ) {
				$url = tdu_mf_get_pagination_url($i);
				echo '<option value="' . esc_url( $url ) . '">' . (int) $i . '</option>';
			}
			echo '</select>';
		}
		
		$page_url = tdu_mf_get_pagination_url($total_pages);
		echo '<a href="' . esc_url( $page_url . '#tdu-mf-results' ) . '" class="tdu-mf-page-numbers">' . (int) $total_pages . '</a>';
	}
	echo '</div>';

	// Mobile only - Page selector dropdown
	echo '<select class="tdu-mf-mobile-only tdu-mf-pagination-dropdown no-tom" onchange="if(this.value)window.location.href=this.value+\'#tdu-mf-results\'">';
	for ( $i = 1; $i <= $total_pages; $i++ ) {
		$url = tdu_mf_get_pagination_url($i);
		echo '<option value="' . esc_url( $url ) . '"' . ($i === $current_page ? ' selected' : '') . '>' . 
			sprintf('Page %d', $i) . 
		'</option>';
	}
	echo '</select>';

	// Next
	if ( $current_page < $total_pages ) {
		$next_url = tdu_mf_get_pagination_url($current_page + 1);
		echo '<a href="' . esc_url( $next_url . '#tdu-mf-results' ) . '" class="tdu-mf-next tdu-mf-page-numbers" rel="next">&raquo;</a>';
	}

	echo '</div>';
}

function tdu_mf_is_jewerly()
{
	$current_cat = meylan_get_queried_term();
	$is_jewerly_cat = $current_cat instanceof WP_Term && $current_cat->taxonomy === 'product_cat' && strpos($current_cat->slug, 'joaillerie') !== false;
	$is_jewerly_param = isset($_GET['lm_sc']) && strpos($_GET['lm_sc'], 'joaillerie') !== false;

	return $is_jewerly_cat || $is_jewerly_param;
}

function tdu_mf_is_accessories()
{
	$current_cat = meylan_get_queried_term();
	$is_jewerly_cat = $current_cat instanceof WP_Term && $current_cat->taxonomy === 'product_cat' && strpos($current_cat->slug, 'accessoires') !== false;
	$is_jewerly_param = isset($_GET['lm_sc']) && strpos($_GET['lm_sc'], 'accessoires') !== false;

	return $is_jewerly_cat || $is_jewerly_param;
}