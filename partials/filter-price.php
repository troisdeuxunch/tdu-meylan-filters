<div>
	<label for="lm_price_min" class="tdu-mf-label"><?php esc_html_e('Prix minimum', 'tdu-meylan-filters'); ?></label>
	<div style="display: flex; gap: 10px;">
		<input type="number" name="lm_price_min" id="lm_price_min" step="100" min="0" placeholder="<?php esc_attr_e('Prix minimum', 'tdu-meylan-filters'); ?>" value="<?php echo $current_price_min; ?>" class="tdu-mf-input">
		<input type="number" name="lm_price_max" id="lm_price_max" step="100" min="0" placeholder="<?php esc_attr_e('Prix maximum', 'tdu-meylan-filters'); ?>" value="<?php echo $current_price_max; ?>" class="tdu-mf-input">
	</div>
</div>
