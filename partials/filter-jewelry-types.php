<?php if(count($jewelry_types) > 0): ?>
<div>
	<label for="lm_jewelry_type" class="tdu-mf-label"><?php esc_html_e('Type de bijou', 'tdu-meylan-filters'); ?></label>
	<select name="lm_jewelry_type" id="lm_jewelry_type" class="no-tom">
		<option value=""><?php esc_html_e('Tous les types', 'tdu-meylan-filters'); ?></option>
		<?php foreach ($jewelry_types as $jewelry_type): ?>
			<option value="<?php echo esc_attr($jewelry_type->slug); ?>" <?php selected($jewelry_type->slug, tdu_get_current_jewelry_type_query_var()); ?>>
				<?php echo esc_html($jewelry_type->name); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
<?php endif; ?>
