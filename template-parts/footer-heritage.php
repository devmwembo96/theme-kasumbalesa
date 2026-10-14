<?php
/**
 * Bandeau institutionnel du pied de page : armoiries de la RDC, emblème de la Ville et logo
 * de la province réunis en sceau, titre, devise et réseaux, sur la photo de la montagne.
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
		wp_get_attachment_image( $id, 'medium', false, array( 'alt' => $label, 'loading' => 'lazy', 'sizes' => $main ? '120px' : '84px' ) ) // phpcs:ignore
	);
};
?>
<div class="kb-heritage<?php echo $kb_photo ? '' : ' kb-heritage--plain'; ?>">
	<?php
	if ( $kb_photo ) {
		echo wp_get_attachment_image( $kb_photo, 'kb-hero', false, array( 'class' => 'kb-heritage__photo', 'alt' => '', 'loading' => 'lazy', 'sizes' => '(min-width: 1100px) 60vw, 100vw' ) );
	}
	?>
	<div class="kb-heritage__body">
		<ul class="kb-heritage__seal">
			<?php
			$kb_emblem( $kb_rdc, __( 'Armoiries de la République Démocratique du Congo', 'kasumbalesa' ) );
			$kb_emblem( $kb_city, __( 'Emblème de la Ville de Kasumbalesa', 'kasumbalesa' ), true );
			$kb_emblem( $kb_province, __( 'Logo de la province du Haut-Katanga', 'kasumbalesa' ) );
			?>
		</ul>
		<?php if ( kb_mod( 'heritage_line' ) ) : ?>
			<p class="kb-heritage__line"><?php echo esc_html( kb_mod( 'heritage_line' ) ); ?></p>
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
