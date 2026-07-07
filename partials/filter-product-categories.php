<div class="tdu-mf-categories">
	<div class="main-categories">
	<?php foreach($categories as $category) : ?>
		<div class="tdu-mf-category">
			<a href="<?php echo $category['permalink']; ?>" class="tdu-mf-category-link">
				<?php if($category['id'] === $current_parent_category): ?>
					<strong><?php echo $category['name']; ?></strong>
				<?php else: ?>
					<?php echo $category['name']; ?>
				<?php endif; ?>
			</a>
		</div>
	<?php endforeach; ?>
	</div>

	<div class="sub-categories">
		<?php foreach($categories as $category) : ?>
			<?php if($category['id'] === $current_parent_category && !empty($category['children'])) : ?>
				<?php foreach($category['children'] as $child) : ?>
					<div class="tdu-mf-subcategory">
						<a href="<?php echo $child['permalink']; ?>" class="tdu-mf-subcategory-link">
							<?php if($child['id'] === $current_children_category): ?>
								<strong><?php echo $child['name']; ?></strong>
							<?php else: ?>
								<?php echo $child['name']; ?>
							<?php endif; ?>
						</a>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
