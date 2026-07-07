<div>
	<label for="lm_sc" class="tdu-mf-label"><?php esc_html_e('Catégorie', 'tdu-meylan-filters'); ?></label>
	<input type="hidden" name="lm_mc" value="neuf">
	<select name="lm_sc" id="lm_sc" onchange="this.form.submit()" class="no-tom">
		<option value=""><?php esc_html_e('Sélectionner une catégorie', 'tdu-meylan-filters'); ?></option>
		<?php foreach($new_categories as $category): ?>
			<option value="<?php echo $category->slug; ?>" <?php selected($category->slug, tdu_mf_get_children_category_from_query_var()); ?>><?php echo $category->name; ?></option>
		<?php endforeach; ?>
	</select>
</div>
