<div>
	<label for="lm_collection">Collection</label>
	<select name="lm_collection" id="lm_collection">
		<option value="">Toutes les collections</option>
		<?php foreach ($collections as $collection): ?>
			<option value="<?php echo esc_attr($collection->slug); ?>" <?php selected($collection->slug, tdu_get_current_collection_query_var()); ?>>
				<?php echo esc_html($collection->name); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>