<?php include 'parts/app-header.php'; ?>
<?php include 'parts/app-greeting.php'; ?>
<?php include 'parts/church-view-tabs.php'; ?>

<div class="container">
    <dt-tile class="disciples">
        <div class="section__inner">
            <div class="disciples__heading">
                <h3><?php echo esc_html( $translations['heading'] ); ?></h3>
                <span class="help-text cloak"><?php echo esc_html( $translations['help_text'] ); ?></span>
            </div>

			<?php if ( $error ) : ?>
                <dt-alert context="alert" dismissable><?php echo esc_html( $error ); ?></dt-alert>
			<?php endif; ?>

            <form method="post" action="<?php echo esc_url( $form_action ); ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( $nonce ) ); ?>"/>

                <dt-text name="name"
                         label="<?php echo esc_attr( $translations['name_label'] ); ?>"
                         value="<?php echo esc_attr( $name ); ?>"
                         required></dt-text>

                <div class="disciple-form__actions">
                    <dt-button type="submit" context="success"><?php echo esc_html( $translations['submit_label'] ); ?></dt-button>
                    <dt-button href="<?php echo esc_url( $cancel_url ); ?>" context="inactive"><?php echo esc_html( $translations['cancel_label'] ); ?></dt-button>
                </div>
            </form>
        </div>
    </dt-tile>
</div>

<?php include 'parts/app-footer.php'; ?>
