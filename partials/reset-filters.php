<?php
$current_parent_category = tdu_mf_get_current_parent_category();
$current_children_category = tdu_mf_get_current_children_category();
$current_brand = tdu_get_current_brand_query_var();
$current_type = tdu_get_current_type_query_var();
$current_collection = tdu_get_current_collection_query_var();
$current_price_min = tdu_get_current_price_min_query_var();
$current_price_max = tdu_get_current_price_max_query_var();
$current_diameter_min = tdu_get_current_diameter_min_query_var();
$current_diameter_max = tdu_get_current_diameter_max_query_var();
$current_search = tdu_get_current_search_query_var();

// Only show reset button if there are active filters
if ($current_brand || $current_type || $current_collection || 
    $current_price_min || $current_price_max ||
    $current_diameter_min || $current_diameter_max ||
    $current_search): ?>

    <div class="tdu-filters-reset">
        <a href="<?php echo esc_url(strtok($_SERVER["REQUEST_URI"], '?')); ?>" class="tdu-filters-reset-button">
            Réinitialiser les filtres
        </a>
    </div>

<?php endif; ?>