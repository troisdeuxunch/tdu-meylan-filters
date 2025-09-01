<div>
	<label for="lm_type">Type de montre</label>
	<select name="lm_type" id="lm_type">
		<option value="">Tous les types</option>
		<?php foreach ($watch_types as $watch_type): ?>
			<option value="<?php echo esc_attr($watch_type->slug); ?>" <?php selected($watch_type->slug, tdu_get_current_type_query_var()); ?>>
				<?php echo esc_html($watch_type->name); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>