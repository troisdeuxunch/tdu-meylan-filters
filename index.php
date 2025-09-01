<?php
/**
 * Plugin Name: TDU Meylan Filters
 * Description: Filters for Meylan
 * Version: 1.0.0
 * Author: TDU
 */

/**
 * Get hierarchical product category structure
 *
 * @return array Array of product categories with their children
 */
function tdu_mf_get_categories_structure() {
	$args = array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'orderby'    => 'term_order',
		'order'      => 'ASC',
		'parent'     => 0,
	);

	$parent_categories = get_terms( $args );
	$categories       = array();

	if ( is_wp_error( $parent_categories ) || empty( $parent_categories ) ) {
		return array();
	}

	foreach ( $parent_categories as $parent ) {
		$category = array(
			'id'        => $parent->term_id,
			'name'      => $parent->name,
			'slug'      => $parent->slug,
			'permalink' => get_term_link( $parent->term_id ),
			'children'  => array(),
		);

		$args['parent'] = $parent->term_id;
		$children       = get_terms( $args );
		if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
			foreach ( $children as $child ) {
				$category['children'][] = array(
					'id'        => $child->term_id,
					'name'      => $child->name,
					'slug'      => $child->slug,
					'permalink' => get_term_link( $child->term_id ),
					'parent_id' => $parent->term_id,
				);
			}
		}

		$categories[] = $category;
	}

	return $categories;
}

/**
 * Gets the hierarchical structure of product categories
 *
 * Retrieves all parent product categories and their children, organizing them into
 * a nested array structure. Each category contains its ID, name, slug, permalink and
 * any child categories.
 *
 * @return array Array of category data with the following structure:
 *               [
 *                 'id' => (int) Term ID,
 *                 'name' => (string) Category name,
 *                 'slug' => (string) Category slug,
 *                 'permalink' => (string) Category URL,
 *                 'children' => [
 *                   [
 *                     'id' => (int) Child term ID,
 *                     'name' => (string) Child category name,
 *                     'slug' => (string) Child category slug,
 *                     'permalink' => (string) Child category URL,
 *                     'parent_id' => (int) Parent term ID
 *                   ],
 *                   ...
 *                 ]
 *               ]
 */
function tdu_mf_get_current_parent_category() {
	$current_term = get_queried_object();

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
	$current_term = get_queried_object();

	if ( 0 !== $current_term->parent ) {
		return $current_term->term_id;
	}

	return null;
}

/**
 * Gets the brands for a given category
 *
 * Retrieves all unique brands associated with products in the specified category.
 *
 * @param int $category_id The term ID of the category to retrieve brands for.
 * @return array Array of brand data with the following structure:
 *               [
 *                 'id' => (int) Brand term ID,
 *                 'name' => (string) Brand name,
 *                 'slug' => (string) Brand slug,
 *                 'permalink' => (string) Brand URL
 *               ]
 */
function tdu_mf_get_category_brands( $category_id ) {
	$products = get_posts(
		array(
			'post_type'   => 'product',
			'numberposts' => -1,
			'tax_query'   => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category_id,
				),
			),
		)
	);

	$brand_ids = array();
	foreach ( $products as $product ) {
		$product_brands = wp_get_post_terms( $product->ID, 'product_brand' );
		foreach ( $product_brands as $brand ) {
			$brand_ids[ $brand->term_id ] = $brand->term_id;
		}
	}

	// Get the brand terms.
	if ( ! empty( $brand_ids ) ) {
		$brands = get_terms(
			array(
				'taxonomy'   => 'product_brand',
				'include'    => array_values( $brand_ids ),
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
	} else {
		$brands = array();
	}

	return $brands;
}

function tdu_mf_get_watch_types()
{
	$current_parent_category = tdu_mf_get_current_parent_category();
	$current_children_category = tdu_mf_get_current_children_category();
	$current_brand = tdu_get_current_brand_query_var();
	
	// Get products based on current filters
	$products = get_posts(array(
		'post_type' => 'product',
		'numberposts' => -1,
		'tax_query' => array_filter(array(
			// Category filter
			$current_children_category || $current_parent_category ? array(
				'taxonomy' => 'product_cat',
				'field' => 'term_id',
				'terms' => $current_children_category ? $current_children_category : $current_parent_category
			) : null,
			// Brand filter  
			$current_brand ? array(
				'taxonomy' => 'product_brand',
				'field' => 'slug',
				'terms' => $current_brand
			) : null
		))
	));

	// Get watch type IDs from filtered products
	$type_ids = array();
	foreach ($products as $product) {
		$product_types = wp_get_post_terms($product->ID, 'pa_product_watch_type');
		foreach ($product_types as $type) {
			$type_ids[$type->term_id] = $type->term_id;
		}
	}

	// Get watch type terms
	$types = !empty($type_ids) ? get_terms(array(
		'taxonomy' => 'pa_product_watch_type',
		'include' => array_values($type_ids),
		'hide_empty' => false,
		'orderby' => 'name',
		'order' => 'ASC'
	)) : array();

	return $types;
}

function tdu_mf_get_collections() {
	$current_parent_category = tdu_mf_get_current_parent_category();
	$current_children_category = tdu_mf_get_current_children_category();
	$current_brand = tdu_get_current_brand_query_var();
	
	// Get products based on current filters
	$products = get_posts(array(
		'post_type' => 'product',
		'numberposts' => -1,
		'tax_query' => array_filter(array(
			// Category filter
			$current_children_category || $current_parent_category ? array(
				'taxonomy' => 'product_cat',
				'field' => 'term_id', 
				'terms' => $current_children_category ? $current_children_category : $current_parent_category
			) : null,
			// Brand filter
			$current_brand ? array(
				'taxonomy' => 'product_brand',
				'field' => 'slug',
				'terms' => $current_brand
			) : null
		))
	));

	// Get collection IDs from filtered products
	$collection_ids = array();
	foreach ($products as $product) {
		$product_collections = wp_get_post_terms($product->ID, 'product_collection');
		foreach ($product_collections as $collection) {
			$collection_ids[$collection->term_id] = $collection->term_id;
		}
	}

	// Get collection terms
	$collections = !empty($collection_ids) ? get_terms(array(
		'taxonomy' => 'product_collection',
		'include' => array_values($collection_ids),
		'hide_empty' => false,
		'orderby' => 'name',
		'order' => 'ASC'
	)) : array();

	return $collections;
}

function tdu_mf_get_diameters() {

	global $wpdb;

	// 1) From variations (postmeta)
	$sql_variations = "
		 SELECT DISTINCT pm.meta_value
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} pv ON pv.ID = pm.post_id
		 WHERE pm.meta_key = 'attribute_pa_product_diameter'
			AND pv.post_type = 'product_variation'
			AND pv.post_status = 'publish'
	";
	$var_vals = $wpdb->get_col($sql_variations);

	// 2) From products (taxonomy terms)
	$sql_terms = "
		 SELECT DISTINCT t.slug
		 FROM {$wpdb->terms} t
		 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
		 WHERE tt.taxonomy = 'pa_product_diameter'
	";
	$term_vals = $wpdb->get_col($sql_terms);

	$raw_vals = array_filter(array_merge($var_vals ?: [], $term_vals ?: []));

	if (empty($raw_vals)) {
		 return [];
	}

	// 3) Normalize values into numbers
	$to_number = function($val) {
		 $s = trim(wp_strip_all_tags((string)$val));
		 if ($s === '') return null;

		 $s = str_replace(',', '.', $s);
		 $s = str_replace(['–','—','/'], '-', $s);

		 // Range like "40-45" → return [40, 45]
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
		 return [];
	}

	// 4) Deduplicate + sort
	$nums = array_values(array_unique($nums));
	sort($nums, SORT_NUMERIC);

	return $nums;
}

/**
 * Get the minimum or maximum price based on the current filters
 *
 * @param bool $max Whether to get the maximum price or the minimum price
 * @return float The minimum or maximum price
 */
function tdu_mf_get_price_min_or_max($max = true)
{
    $current_parent_category   = tdu_mf_get_current_parent_category();
    $current_children_category = tdu_mf_get_current_children_category();
	
    $search                    = tdu_get_current_search_query_var();
    $query_filters             = tdu_mf_get_query_filters(
        $current_children_category ? $current_children_category : $current_parent_category
    );

    $args = [
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => 1,
        'orderby'                => 'meta_value_num', // ✅ correct param (not order_by)
        'order'                  => $max ? 'DESC' : 'ASC',
        'meta_key'               => '_price',
        'tax_query'              => $query_filters['tax_query'],
        'meta_query'             => $query_filters['meta_query'] 
                                     ? array_merge(['relation' => 'OR'], $query_filters['meta_query']) 
                                     : [],
        's'                      => $search,
        'tdu_search_title_or_sku'=> true,
    ];

    $products = new WP_Query($args);

    if ($products->have_posts()) {
        $product_id = $products->posts[0]->ID;
        $price      = get_post_meta($product_id, '_price', true);
        return (float) $price;
    }

    return 0;
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
 * Gets the query filters for products
 *
 * Builds and returns an array of WP_Query tax_query and meta_query filters based on the current
 * category, brand and search parameters.
 *
 * @param int|null $category_id Optional category term ID to filter by.
 * @param int|null $brand_id    Optional brand term ID to filter by (currently unused).
 * @return array   Array containing tax_query, meta_query and search query parameters
 *                 [
 *                   'tax_query'  => array Tax query filters for categories and brands
 *                   'meta_query' => array Meta query filters (empty array with OR relation)
 *                   's'          => string|null Search query string if present
 *                 ]
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
		'tax_query'  => $tax_query_filters,
		'meta_query' => $meta_query_filters ? array_merge( array( 'relation' => 'OR' ), $meta_query_filters ) : array(),
		's'          => $search,
	);
}

/**
 * Gets the current brand query variable
 *
 * Returns the brand query variable from the GET parameters.
 *
 * @return string|null The brand query variable if present, otherwise null
 */
function tdu_get_current_brand_query_var() {
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

function tdu_get_current_collection_query_var() {
	return isset( $_GET['lm_collection'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_collection'] ) ) : null;
}

function tdu_get_current_type_query_var() {
	return isset( $_GET['lm_type'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_type'] ) ) : null;
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

	$max_price                = tdu_mf_get_price_min_or_max();

	$categories = tdu_mf_get_categories_structure();
	$brands     = tdu_mf_get_category_brands( $current_children_category ? $current_children_category : $current_parent_category );
	$diameters = tdu_mf_get_diameters();
	$collections = tdu_mf_get_collections();
	$watch_types = tdu_mf_get_watch_types();
	?>
	<div class="tdu-filters-wrapper-form">
		<!-- categories -->
		<div class="tdu-filters-wrapper-form-categories">
			<?php include plugin_dir_path( __FILE__ ) . 'partials/filter-product-categories.php'; ?>
		</div>

		<!-- custom filters -->
		<div class="tdu-filters-wrapper-form">
			<form action="" method="GET" class="tdu-filters-wrapper-form-search" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px;">
				<?php include plugin_dir_path( __FILE__ ) . 'partials/filter-search.php'; ?>
				<?php include plugin_dir_path( __FILE__ ) . 'partials/filter-product-brands.php'; ?>
				<?php include plugin_dir_path( __FILE__ ) . 'partials/filter-watch-types.php'; ?>
				<?php include plugin_dir_path( __FILE__ ) . 'partials/filter-collections.php'; ?>
				<?php include plugin_dir_path( __FILE__ ) . 'partials/filter-price.php'; ?>
				<?php include plugin_dir_path( __FILE__ ) . 'partials/filter-diameter.php'; ?>
				<button type="submit" class="tdu-filters-wrapper-form-search-button">Filtrer</button>
			</form>
			<?php include plugin_dir_path( __FILE__ ) . 'partials/reset-filters.php'; ?>
		</div>
	</div>
	<?php
}

/**
 * Gets the products from the query vars
 *
 * Returns the products from the query vars.
 *
 * @param int $per_page Number of products per page.
 * @return array Array of product posts.
 */
function tdu_get_products_from_query_vars( $per_page = 8 ) {
	$current_parent_category   = tdu_mf_get_current_parent_category();
	$current_children_category = tdu_mf_get_current_children_category();
	$search                    = tdu_get_current_search_query_var();

	$query_filters = tdu_mf_get_query_filters( $current_children_category ? $current_children_category : $current_parent_category );

	$args = array(
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'posts_per_page'         => $per_page,
		'tax_query'              => $query_filters['tax_query'],
		'meta_query'             => $query_filters['meta_query'] ? array_merge( array( 'relation' => 'OR' ), $query_filters['meta_query'] ) : array(),
		's'                      => tdu_get_current_search_query_var(),
		'tdu_search_title_or_sku' => true,
	);

	$products = new WP_Query( $args );

	return $products->posts;
}

/**
 * Filters the products by title or SKU
 *
 * @param string   $where The WHERE clause of the query.
 * @param WP_Query $query The WP_Query object.
 * @return string The modified WHERE clause
 */
function tdu_search_products_title_or_sku( $where, $query ) {
	global $wpdb;

	if ( $query->get( 'tdu_search_title_or_sku' ) && $query->get( 's' ) ) {
		$search = '%' . $wpdb->esc_like( $query->get( 's' ) ) . '%';

		// Add AND condition for SKU search instead of OR
		$where .= $wpdb->prepare(
			" AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.ID IN (
				SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_sku' AND meta_value LIKE %s
			)) ",
			$search,
			$search
		);
		
		// Remove the default search clause since we're handling it manually
		$where = preg_replace('/AND \(\(\(\({.*?post_title.*?\)\)\)/', '', $where);
	}

	return $where;
}
add_filter( 'posts_where', 'tdu_search_products_title_or_sku', 10, 2 );