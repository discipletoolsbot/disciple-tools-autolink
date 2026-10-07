<div class="container">
    <strong class="greeting">
		<?php echo esc_html( $greeting ); ?>
    </strong>
    <h1 class="user_name"><?php echo esc_html( $user_name ); ?></h1>

	<?php if ( $coach_name ): ?>
        <strong class="coached_by">
			<?php echo esc_html( $coached_by_label ); ?> <?php echo esc_html( $coach_name ); ?>
        </strong>
	<?php endif; ?>

	<?php if ( ! empty( $can_upgrade ) && ( $action ?? '' ) !== 'upgrade' ): ?>
        <dt-tile class="app__upgrade">
            <div class="section__inner">
                <strong class="upgrade__cta-heading"><?php echo esc_html( $upgrade_label ); ?></strong>
                <span class="help-text cloak"><?php echo esc_html( $upgrade_help_text ); ?></span>
                <dt-button href="<?php echo esc_url( $upgrade_url ); ?>" context="success">
                    <?php echo esc_html( $upgrade_label ); ?>
                </dt-button>
            </div>
        </dt-tile>
	<?php endif; ?>

	<?php if ( $share_link ): ?>
        <dt-tile title="<?php echo esc_attr( $link_heading ); ?>"
                 class="app__link">
            <div class="section__inner">
                <dt-copy-text value="<?php echo esc_attr( $share_link ); ?>" <?php language_attributes(); ?>></dt-copy-text>
                <span class="help-text cloak">
                    <?php echo esc_html( $share_link_help_text ) ?>
                </span>
            </div>
        </dt-tile>
	<?php endif; ?>

</div>
