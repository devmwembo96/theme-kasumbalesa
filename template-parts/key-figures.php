<?php
/**
 * Chiffres clés animés. N'affiche que les chiffres réellement renseignés dans le Customizer.
 *
 * @package Kasumbalesa
 */

$kb_figures = kb_key_figures();
if ( ! $kb_figures ) {
	return;
}
$kb_bg = absint( kb_mod( 'figures_image' ) );
?>
<section class="kb-section kb-figures<?php echo $kb_bg ? ' kb-figures--image' : ''; ?>" aria-labelledby="kb-figures-title">
	<?php
	if ( $kb_bg ) {
		echo wp_get_attachment_image(
			$kb_bg,
			'kb-hero',
			false,
			array(
				'class'    => 'kb-figures__bg',
				'alt'      => '',
				'sizes'    => '100vw',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
	}
	?>
	<div class="kb-container kb-container--wide">
		<?php kb_section_heading( kb_mod( 'figures_eyebrow' ), kb_mod( 'figures_title' ), 'kb-figures-title', 'center' ); ?>
		<dl class="kb-figures__grid kb-figures__grid--<?php echo count( $kb_figures ); ?>">
			<?php foreach ( $kb_figures as $kb_figure ) : ?>
				<div class="kb-figures__item" data-reveal>
					<dt class="kb-figures__label"><?php echo esc_html( $kb_figure['label'] ); ?></dt>
					<dd class="kb-figures__value">
						<span data-count="<?php echo esc_attr( $kb_figure['number'] ); ?>" data-decimals="<?php echo esc_attr( $kb_figure['decimals'] ); ?>"><?php
						// Une année (ex. 2018) s'affiche sans séparateur de milliers.
						$kb_is_year = ! $kb_figure['decimals'] && $kb_figure['number'] > 1900 && $kb_figure['number'] < 2100;
						echo esc_html( $kb_is_year ? (string) (int) $kb_figure['number'] : number_format_i18n( $kb_figure['number'], $kb_figure['decimals'] ) );
						?></span><?php if ( $kb_figure['suffix'] ) : ?><span class="kb-figures__suffix"><?php echo esc_html( $kb_figure['suffix'] ); ?></span><?php endif; ?>
					</dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</div>
</section>
