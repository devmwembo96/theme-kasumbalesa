<?php
/**
 * Fonctions d'affichage réutilisées par les gabarits.
 *
 * @package Kasumbalesa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Les 5 derniers articles publiés (mis en cache pour la requête).
 *
 * @return WP_Post[]
 */
function kb_latest_posts() {
	static $posts = null;
	if ( null === $posts ) {
		$posts = get_posts(
			array(
				'post_type'           => 'post',
				'posts_per_page'      => 5,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => false,
			)
		);
	}
	return $posts;
}

/**
 * La bande défilante doit-elle s'afficher sur cette page ?
 *
 * @return bool
 */
function kb_ticker_is_visible() {
	$mode = kb_mod( 'ticker_display' );
	if ( 'none' === $mode || ( 'front' === $mode && ! is_front_page() ) || kb_is_full_width() ) {
		return false;
	}
	return ! empty( kb_latest_posts() );
}

/**
 * Logo (WordPress) ou nom du site.
 *
 * @param string $context header|footer.
 */
function kb_site_branding( $context = 'header' ) {
	$name = get_bloginfo( 'name' );
	?>
	<a class="kb-brand kb-brand--<?php echo esc_attr( $context ); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
		<?php
		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			echo wp_get_attachment_image(
				$logo_id,
				'medium',
				false,
				array(
					'class'    => 'kb-brand__logo',
					'alt'      => '',
					'loading'  => 'header' === $context ? 'eager' : 'lazy',
					'decoding' => 'async',
				)
			);
		}
		?>
		<span class="kb-brand__text">
			<span class="kb-brand__name"><?php echo esc_html( $name ); ?></span>
			<?php if ( get_bloginfo( 'description' ) ) : ?>
				<span class="kb-brand__tagline"><?php bloginfo( 'description' ); ?></span>
			<?php endif; ?>
		</span>
	</a>
	<?php
}

/**
 * Catégorie principale d'un article.
 *
 * @param int|WP_Post|null $post Article.
 * @return WP_Term|null
 */
function kb_primary_category( $post = null ) {
	$cats = get_the_category( get_post( $post )->ID );
	return $cats ? $cats[0] : null;
}

/**
 * Pastille de catégorie.
 *
 * @param int|WP_Post|null $post Article.
 * @param bool             $link Faire un lien.
 */
function kb_category_badge( $post = null, $link = true ) {
	$cat = kb_primary_category( $post );
	if ( ! $cat ) {
		return;
	}
	if ( $link ) {
		printf( '<a class="kb-badge" href="%s">%s</a>', esc_url( get_category_link( $cat ) ), esc_html( $cat->name ) );
	} else {
		printf( '<span class="kb-badge">%s</span>', esc_html( $cat->name ) );
	}
}

/**
 * Date de publication.
 *
 * @param int|WP_Post|null $post Article.
 */
function kb_post_date( $post = null ) {
	$post = get_post( $post );
	printf(
		'<time class="kb-date" datetime="%s">%s</time>',
		esc_attr( get_the_date( DATE_W3C, $post ) ),
		esc_html( get_the_date( '', $post ) )
	);
}

/**
 * Bouton lien.
 *
 * @param string $label   Texte.
 * @param string $url     Adresse.
 * @param string $variant primary|ghost|outline|link.
 * @param string $icon    Pictogramme final.
 */
function kb_button( $label, $url, $variant = 'primary', $icon = 'arrow-right' ) {
	if ( '' === trim( (string) $label ) || '' === trim( (string) $url ) ) {
		return;
	}
	printf(
		'<a class="kb-btn kb-btn--%1$s" href="%2$s"><span>%3$s</span>%4$s</a>',
		esc_attr( $variant ),
		esc_url( $url ),
		esc_html( $label ),
		$icon ? kb_icon( $icon ) : '' // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne.
	);
}

/**
 * En-tête de section (surtitre + titre).
 *
 * @param string $eyebrow Surtitre.
 * @param string $title   Titre.
 * @param string $id      Identifiant du titre (aria-labelledby).
 * @param string $align   left|center.
 */
function kb_section_heading( $eyebrow, $title, $id = '', $align = 'left' ) {
	if ( '' === $eyebrow && '' === $title ) {
		return;
	}
	?>
	<header class="kb-section-head kb-section-head--<?php echo esc_attr( $align ); ?>" data-reveal>
		<?php if ( $eyebrow ) : ?>
			<p class="kb-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>
		<?php if ( $title ) : ?>
			<h2 class="kb-section-title"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?>><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
	</header>
	<?php
}

/**
 * Sélecteur de langue FR / EN (Polylang, WPML ou adresses du Customizer).
 *
 * @param string $context header|mobile.
 */
function kb_lang_switcher( $context = 'header' ) {
	if ( ! kb_mod( 'lang_enable' ) ) {
		return;
	}

	$items = array();

	if ( function_exists( 'pll_the_languages' ) ) {
		$langs = pll_the_languages(
			array(
				'raw'           => 1,
				'hide_if_empty' => 0,
			)
		);
		foreach ( (array) $langs as $lang ) {
			$items[] = array(
				'code'    => $lang['slug'],
				'name'    => $lang['name'],
				'url'     => $lang['url'],
				'current' => ! empty( $lang['current_lang'] ),
			);
		}
	} elseif ( has_filter( 'wpml_active_languages' ) ) {
		$langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		foreach ( (array) $langs as $lang ) {
			$items[] = array(
				'code'    => $lang['language_code'],
				'name'    => $lang['native_name'],
				'url'     => $lang['url'],
				'current' => ! empty( $lang['active'] ),
			);
		}
	} else {
		$fr      = kb_mod( 'lang_fr_url' );
		$en      = kb_mod( 'lang_en_url' );
		$is_en   = 0 === strpos( get_locale(), 'en' );
		$items[] = array(
			'code'    => 'fr',
			'name'    => 'Français',
			'url'     => $fr ? $fr : home_url( '/' ),
			'current' => ! $is_en,
		);
		$items[] = array(
			'code'    => 'en',
			'name'    => 'English',
			'url'     => $en,
			'current' => $is_en,
		);
	}

	if ( count( $items ) < 2 ) {
		return;
	}
	?>
	<nav class="kb-lang kb-lang--<?php echo esc_attr( $context ); ?>" aria-label="<?php esc_attr_e( 'Choix de la langue', 'kasumbalesa' ); ?>">
		<ul>
			<?php foreach ( $items as $item ) : ?>
				<li>
					<?php if ( $item['current'] ) : ?>
						<span class="is-current" lang="<?php echo esc_attr( $item['code'] ); ?>" aria-current="true" title="<?php echo esc_attr( $item['name'] ); ?>"><?php echo esc_html( strtoupper( $item['code'] ) ); ?></span>
					<?php elseif ( $item['url'] ) : ?>
						<a href="<?php echo esc_url( $item['url'] ); ?>" lang="<?php echo esc_attr( $item['code'] ); ?>" hreflang="<?php echo esc_attr( $item['code'] ); ?>" title="<?php echo esc_attr( $item['name'] ); ?>"><?php echo esc_html( strtoupper( $item['code'] ) ); ?></a>
					<?php else : ?>
						<span class="is-disabled" lang="<?php echo esc_attr( $item['code'] ); ?>" title="<?php esc_attr_e( 'Version anglaise bientôt disponible', 'kasumbalesa' ); ?>"><?php echo esc_html( strtoupper( $item['code'] ) ); ?><span class="screen-reader-text"> – <?php esc_html_e( 'Version anglaise bientôt disponible', 'kasumbalesa' ); ?></span></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}

/**
 * Liens vers les réseaux sociaux renseignés.
 *
 * @param string $class Classe CSS.
 */
function kb_social_links( $class = '' ) {
	$links = array();
	foreach ( kb_social_networks() as $key => $label ) {
		$url = kb_mod( 'social_' . $key );
		if ( $url ) {
			$links[ $key ] = array( $label, $url );
		}
	}
	if ( ! $links ) {
		return;
	}
	?>
	<ul class="kb-social <?php echo esc_attr( $class ); ?>">
		<?php foreach ( $links as $key => $link ) : ?>
			<li>
				<a href="<?php echo esc_url( $link[1] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo kb_icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="screen-reader-text">
						<?php
						/* translators: %s: nom du réseau social. */
						printf( esc_html__( '%s (nouvel onglet)', 'kasumbalesa' ), esc_html( $link[0] ) );
						?>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Coordonnées : adresse, téléphones (tel:), e-mail (mailto:), horaires.
 */
function kb_contact_list() {
	$address = kb_mod( 'contact_address' );
	$phones  = array_filter( array( kb_mod( 'contact_phone' ), kb_mod( 'contact_phone_2' ) ) );
	$email   = kb_mod( 'contact_email' );
	$hours   = kb_mod( 'contact_hours' );

	if ( ! $address && ! $phones && ! $email && ! $hours ) {
		return;
	}
	?>
	<ul class="kb-contact">
		<?php if ( $address ) : ?>
			<li><?php echo kb_icon( 'map-pin' ); // phpcs:ignore ?><address><?php echo nl2br( esc_html( $address ) ); ?></address></li>
		<?php endif; ?>
		<?php foreach ( $phones as $phone ) : ?>
			<li><?php echo kb_icon( 'phone' ); // phpcs:ignore ?><a href="<?php echo esc_attr( kb_tel_href( $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li>
		<?php endforeach; ?>
		<?php if ( $email && is_email( $email ) ) : ?>
			<li><?php echo kb_icon( 'mail' ); // phpcs:ignore ?><a href="<?php echo esc_attr( 'mailto:' . antispambot( $email, 1 ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></li>
		<?php endif; ?>
		<?php if ( $hours ) : ?>
			<li><?php echo kb_icon( 'clock' ); // phpcs:ignore ?><span><?php echo nl2br( esc_html( $hours ) ); ?></span></li>
		<?php endif; ?>
	</ul>
	<?php
}

/**
 * Boutons de partage d'un article (sans script tiers).
 */
function kb_share_links() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( wp_strip_all_tags( get_the_title() ) );
	$nets  = array(
		'facebook' => array( 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $url ),
		'x'        => array( 'X', 'https://x.com/intent/post?url=' . $url . '&text=' . $title ),
		'whatsapp' => array( 'WhatsApp', 'https://wa.me/?text=' . $title . '%20' . $url ),
		'linkedin' => array( 'LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url ),
		'mail'     => array( __( 'E-mail', 'kasumbalesa' ), 'mailto:?subject=' . $title . '&body=' . $url ),
	);
	?>
	<div class="kb-share" data-reveal>
		<p class="kb-share__label"><?php esc_html_e( 'Partager', 'kasumbalesa' ); ?></p>
		<ul class="kb-share__list">
			<?php foreach ( $nets as $key => $net ) : ?>
				<li>
					<a href="<?php echo esc_attr( $net[1] ); ?>"<?php echo 'mail' === $key ? '' : ' target="_blank" rel="noopener noreferrer"'; ?>>
						<?php echo kb_icon( $key ); // phpcs:ignore ?>
						<span class="screen-reader-text">
							<?php
							/* translators: %s: nom du service. */
							printf( esc_html__( 'Partager sur %s', 'kasumbalesa' ), esc_html( $net[0] ) );
							?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
			<li>
				<button type="button" class="kb-share__copy" data-copy="<?php echo esc_url( get_permalink() ); ?>" data-share-title="<?php echo esc_attr( wp_strip_all_tags( get_the_title() ) ); ?>">
					<?php echo kb_icon( 'link' ); // phpcs:ignore ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Copier le lien', 'kasumbalesa' ); ?></span>
				</button>
			</li>
		</ul>
		<span class="kb-share__status" role="status" aria-live="polite"></span>
	</div>
	<?php
}

/**
 * Pagination des listes.
 */
function kb_pagination() {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => kb_icon( 'arrow-left' ) . '<span class="screen-reader-text">' . esc_html__( 'Page précédente', 'kasumbalesa' ) . '</span>',
			'next_text'          => '<span class="screen-reader-text">' . esc_html__( 'Page suivante', 'kasumbalesa' ) . '</span>' . kb_icon( 'arrow-right' ),
			'before_page_number' => '<span class="screen-reader-text">' . esc_html__( 'Page', 'kasumbalesa' ) . ' </span>',
			'class'              => 'kb-pagination',
		)
	);
}

/**
 * Image d'un article avec srcset et lazy-load.
 *
 * @param string $size  Taille.
 * @param array  $attr  Attributs.
 * @param mixed  $post  Article.
 */
function kb_thumbnail( $size = 'kb-card', $attr = array(), $post = null ) {
	$post = get_post( $post );
	if ( ! has_post_thumbnail( $post ) ) {
		echo '<span class="kb-thumb-fallback" aria-hidden="true">' . kb_icon( 'mountain' ) . '</span>'; // phpcs:ignore
		return;
	}
	echo get_the_post_thumbnail(
		$post,
		$size,
		array_merge(
			array(
				'loading'  => 'lazy',
				'decoding' => 'async',
			),
			$attr
		)
	);
}

/**
 * Filtre par catégorie (pastilles) affiché en tête des listes d'actualités.
 */
function kb_category_filter() {
	$cats = get_categories(
		array(
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 8,
			'hide_empty' => true,
		)
	);
	if ( count( $cats ) < 2 ) {
		return;
	}
	$current = is_category() ? get_queried_object_id() : 0;
	?>
	<nav class="kb-filter" aria-label="<?php esc_attr_e( 'Filtrer par catégorie', 'kasumbalesa' ); ?>">
		<ul>
			<li><a href="<?php echo esc_url( kb_news_url() ); ?>"<?php echo $current ? '' : ' aria-current="page"'; ?>><?php esc_html_e( 'Tout', 'kasumbalesa' ); ?></a></li>
			<?php foreach ( $cats as $cat ) : ?>
				<li><a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"<?php echo $current === $cat->term_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $cat->name ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}
