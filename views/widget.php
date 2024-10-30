<ul>
	<?php foreach ( $list as $item ) : ?>
		<li>
			<a href="<?php echo $item['url'];?>" title="<?php echo $item['description'];?>" target="_blank">
				<?php echo $item['description_short'];?>
			</a>
			<br />

			<?php if ( true === $show_comments ) : ?>
				<span><?php echo $item['comments']; ?>.</span>
			<?php endif; ?>

			<?php if ( true === $show_date ) : ?>
                <span><?php echo $item['created']; ?>.</span>
			<?php endif; ?>

            <?php if ( true === $show_icons ) : ?>
                <?php for ($i = 0; $i < count( $item['types'] ); $i++) : ?>
                    <img class="gist-icon" src="<?php echo plugins_url( '/images/' . $item['types'][$i] , dirname(__FILE__) ); ?>.png" />
                <?php endfor; ?>
            <?php endif; ?>
		</li>
	<? endforeach; ?>
</ul>