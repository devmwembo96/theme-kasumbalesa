<?php
/**
 * Pop-up d'accueil du Maire (une fois par session, après l'introduction) :
 * emblème, « Bienvenue », mot du Maire, bouton, puis portrait détouré posé sur un demi-cercle.
 *
 * @package Kasumbalesa
 */

$kb_page = kb_get_mayor_page();
$kb_logo = (int) get_theme_mod( 'custom_logo' );
?>
<dialog class="kb-modal kb-modal--wh" id="kb-mayor-modal" aria-labelledby="kb-modal-title">
	<div class="kb-modal__inner">
		<button type="button" class="kb-modal__close" data-modal-close>
			<?php echo kb_icon( 'close' ); // phpcs:ignore ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Fermer', 'kasumbalesa' ); ?></span>
		</button>

		<div class="kb-modal__content">
			<?php
			if ( $kb_logo ) {
				echo wp_get_attachment_image( $kb_logo, 'medium', false, array( 'class' => 'kb-modal__logo', 'alt' => '', 'loading' => 'lazy' ) );
			}
			?>
			<h2 class="kb-modal__title" id="kb-modal-title"><?php echo nl2br( esc_html( kb_mod( 'popup_title' ) ) ); // phpcs:ignore ?></h2>
			<div class="kb-modal__excerpt">
				<?php echo wpautop( esc_html( kb_mayor_excerpt( $kb_page, 40 ) ) ); // phpcs:ignore ?>
			</div>
			<?php
			// Le bouton mène à la section « Mot du Maire » de l'accueil (la page seule si la section est masquée).
			$kb_target = kb_mod( 'mayor_section_enable' ) ? home_url( '/#mot-du-maire' ) : get_permalink( $kb_page );
			kb_button( kb_mod( 'popup_button' ), $kb_target, 'gold' );
			?>
		</div>

		<figure class="kb-modal__portrait">
			<?php kb_mayor_cutout( 'kb-modal__photo', '(min-width: 600px) 340px, 80vw' ); ?>
			<figcaption class="kb-modal__caption">
				<span class="kb-modal__name"><?php echo esc_html( kb_mod( 'mayor_name' ) ); ?></span>
				<span class="kb-modal__role"><?php echo esc_html( kb_mod( 'mayor_role' ) ); ?></span>
			</figcaption>
		</figure>
	</div>
</dialog>
