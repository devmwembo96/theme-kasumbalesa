<?php
/**
 * Bandeau institutionnel du pied de page : un cadre (photo de la montagne) qui ne contient que
 * les logos — emblème de la Ville en grand à gauche, armoiries de la RDC et logo du Haut-Katanga
 * empilés à sa droite — puis, à part, l'appartenance, le titre, la devise et les réseaux.
 *
 * @package Kasumbalesa
 */

$kb_img = static function ( $key ) {
	$id = absint( kb_mod( $key ) );
	return wp_attachment_is_image( $id ) ? $id : 0;
};
$kb_rdc      = $kb_img( 'heritage_emblem_1' );
$kb_province = $kb_img( 'heritage_emblem_2' );
$kb_photo    = $kb_img( 'heritage_photo' );
$kb_city     = wp_attachment_is_image( (int) get_theme_mod( 'custom_logo' ) ) ? (int) get_theme_mod( 'custom_logo' ) : 0;
$kb_emblem   = static function ( $id, $label, $main = false ) {
	if ( ! $id ) {
		return;
	}
	printf(
		'<li class="kb-heritage__emblem%1$s">%2$s</li>',
		$main ? ' kb-heritage__emblem--main' : '',
		wp_get_attachment_image( $id, 'medium', false, array( 'alt' => $label, 'loading' => 'lazy', 'sizes' => $main ? '200px' : '100px' ) ) // phpcs:ignore
	);
};
?>
<div class="kb-heritage">
	<div class="kb-heritage__frame<?php echo $kb_photo ? '' : ' kb-heritage__frame--plain'; ?>">
		<?php
		if ( $kb_photo ) {
			echo wp_get_attachment_image( $kb_photo, 'kb-hero', false, array( 'class' => 'kb-heritage__photo', 'alt' => '', 'loading' => 'lazy', 'sizes' => '(min-width: 1100px) 50vw, 100vw' ) );
		}
		?>
		<ul class="kb-heritage__seal">
			<?php $kb_emblem( $kb_city, __( 'Emblème de la Ville de Kasumbalesa', 'kasumbalesa' ), true ); ?>
			<?php if ( $kb_rdc || $kb_province ) : ?>
				<li class="kb-heritage__stack">
					<ul>
						<?php
						$kb_emblem( $kb_rdc, __( 'Armoiries de la République Démocratique du Congo', 'kasumbalesa' ) );
						$kb_emblem( $kb_province, __( 'Logo de la province du Haut-Katanga', 'kasumbalesa' ) );
						?>
					</ul>
				</li>
			<?php endif; ?>
		</ul>
	</div>
	<div class="kb-heritage__body">
		<?php if ( kb_mod( 'heritage_line' ) ) : ?>
			<p class="kb-heritage__line">
				<?php
				// Une information par ligne (séparateur « · » dans le Customizer).
				foreach ( array_filter( array_map( 'trim', explode( '·', kb_mod( 'heritage_line' ) ) ) ) as $kb_part ) {
					echo '<span>' . esc_html( $kb_part ) . '</span>';
				}
				?>
			</p>
		<?php endif; ?>
		<?php if ( kb_mod( 'heritage_title' ) ) : ?>
			<p class="kb-heritage__title"><?php echo kb_rich_title( kb_mod( 'heritage_title' ) ); // phpcs:ignore ?></p>
		<?php endif; ?>
		<?php if ( kb_mod( 'heritage_motto' ) ) : ?>
			<p class="kb-heritage__motto"><?php echo kb_icon( 'mountain' ); // phpcs:ignore ?><span><?php echo esc_html( kb_mod( 'heritage_motto' ) ); ?></span></p>
		<?php endif; ?>
		<?php kb_social_links( 'kb-social--heritage' ); ?>
	</div>
</div>
