<div>
	<label for="lm_price_min">Prix minimum <span style="font-size: 10px;">(0 - <?php echo $max_price; ?>)</span></label>
	<div style="display: flex; gap: 10px;">
		<input type="number" name="lm_price_min" id="lm_price_min" step="100" min="0" max="<?php echo $max_price; ?>" placeholder="Prix minimum" value="<?php echo $current_price_min; ?>">
		<input type="number" name="lm_price_max" id="lm_price_max" step="100" min="0" max="<?php echo $max_price; ?>" placeholder="Prix maximum" value="<?php echo $current_price_max; ?>">
	</div>
</div>