<?php include 'parts/app-header.php'; ?>
<?php include 'parts/app-greeting.php'; ?>
<?php include 'parts/church-view-tabs.php'; ?>

<div class="container">
    <dt-tile class="disciples">
        <div class="section__inner">
            <div class="disciples__heading">
                <div class="disciples__heading-row">
                    <h3><?php echo esc_html( $translations['heading'] ); ?></h3>
                    <dt-button class="disciples__add"
                               context="success"
                               href="<?php echo esc_url( $add_disciple_link ); ?>"
                               round>
                        <dt-icon icon="ic:baseline-plus"></dt-icon>
                    </dt-button>
                </div>
                <span class="help-text cloak"><?php echo esc_html( $translations['help_text'] ); ?></span>
            </div>

			<?php if ( empty( $disciples ) ): ?>
                <p class="disciples__empty"><?php echo esc_html( $translations['empty'] ); ?></p>
			<?php else : ?>
                <ul class="disciples__list">
					<?php foreach ( $disciples as $disciple ): ?>
                        <li class="disciple">
                            <strong class="disciple__name"><?php echo esc_html( $disciple['name'] ); ?></strong>

                            <span class="disciple__relationships">
                                <?php echo esc_html( implode( ' · ', $disciple['relationships'] ) ); ?>
                            </span>

							<?php if ( $disciple['link'] ): ?>
                                <span class="disciple__link-label"><?php echo esc_html( $translations['link_label'] ); ?></span>
                                <dt-copy-text value="<?php echo esc_attr( $disciple['link'] ); ?>" <?php language_attributes(); ?>></dt-copy-text>
							<?php elseif ( $disciple['has_login'] ): ?>
                                <span class="disciple__has-login"><?php echo esc_html( $translations['has_login'] ); ?></span>
							<?php endif; ?>
                        </li>
					<?php endforeach; ?>
                </ul>
                <span class="help-text cloak"><?php echo esc_html( $translations['link_help'] ); ?></span>
			<?php endif; ?>
        </div>
    </dt-tile>
</div>

<?php include 'parts/app-footer.php'; ?>
