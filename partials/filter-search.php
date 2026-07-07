<div class="tdu-mf-search">
	<label for="lm_search" class="tdu-mf-label"><?php esc_html_e('Rechercher', 'tdu-meylan-filters'); ?></label>
	<input type="text" name="lm_search" id="lm_search" placeholder="<?php esc_attr_e('Votre recherche..', 'tdu-meylan-filters'); ?>" value="<?= tdu_get_current_search_query_var() ?>" class="tdu-mf-input">
</div>
