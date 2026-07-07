<div>
	<label for="lm_collection" class="tdu-mf-label"><?php esc_html_e('Collection', 'tdu-meylan-filters'); ?></label>
	<select name="lm_collection" id="lm_collection" class="no-tom">
		<option value=""><?php esc_html_e('Toutes les collections', 'tdu-meylan-filters'); ?></option>
		<?php foreach ($collections as $collection): ?>
			<option value="<?php echo esc_attr($collection->slug); ?>" <?php selected($collection->slug, tdu_get_current_collection_query_var()); ?>>
				<?php echo esc_html($collection->name); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
