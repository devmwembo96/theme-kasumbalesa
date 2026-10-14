<?php
/**
 * Section « Mot d'accueil de Monsieur le Maire » : texte intégral de la page source.
 *
 * @package Kasumbalesa
 */

$kb_page      = kb_get_mayor_page();
$kb_signature = absint( kb_mod( 'mayor_signature' ) );

// Le contenu est rendu avec les filtres habituels (blocs, intégrations…).
$kb_content = apply_filters( 'the_content', $kb_page->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
?>
<section class="kb-section kb-section--white kb-mayor" id="mot-du-maire" aria-labelledby="kb-mayor-title">
	<div class="kb-container kb-mayor__grid">
		<figure class="kb-mayor__portrait kb-mayor__portrait--cutout" data-reveal>
			<div class="kb-mayor__stage">
				<?php kb_mayor_cutout( 'kb-mayor__cutout', '(min-width: 900px) 360px, 80vw' ); ?>
			</div>
			<figcaption class="kb-mayor__caption">
				<span><?php echo esc_html( kb_mod( 'mayor_name' ) ); ?></span>
				<?php echo esc_html( kb_mod( 'mayor_role' ) ); ?>
			</figcaption>
		</figure>

		<div class="kb-mayor__letter">
			<header data-reveal>
				<?php if ( kb_mod( 'mayor_section_eyebrow' ) ) : ?>
					<p class="kb-eyebrow"><?php echo esc_html( kb_mod( 'mayor_section_eyebrow' ) ); ?></p>
				<?php endif; ?>
				<h2 class="kb-mayor__title" id="kb-mayor-title"><?php echo esc_html( get_the_title( $kb_page ) ); ?></h2>
			</header>

			<div class="kb-mayor__body" id="kb-mayor-body" data-reveal>
				<span class="kb-mayor__quote" aria-hidden="true">“</span>
				<div class="kb-entry kb-entry--letter">
					<?php echo $kb_content; // phpcs:ignore WordPress.Security.EscapeOutput -- contenu filtré par the_content. ?>
				</div>
				<div class="kb-signature">
					<?php
					if ( $kb_signature ) {
						echo wp_get_attachment_image( $kb_signature, 'medium', false, array( 'class' => 'kb-signature__img', 'alt' => '', 'loading' => 'lazy' ) );
					}
					?>
					<p class="kb-signature__name"><?php echo esc_html( kb_mod( 'mayor_name' ) ); ?></p>
					<p class="kb-signature__role"><?php echo esc_html( kb_mod( 'mayor_role' ) ); ?></p>
				</div>
			</div>

			<button type="button" class="kb-btn kb-btn--outline kb-mayor__more" data-readmore aria-controls="kb-mayor-body" aria-expanded="false">
				<span><?php echo esc_html( kb_mod( 'mayor_readmore' ) ); ?></span><?php echo kb_icon( 'chevron' ); // phpcs:ignore ?>
			</button>
		</div>
	</div>
</section>
