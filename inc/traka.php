<?php
/* Ugrađena traka: za sajtove bez Bricksa (Elementor, Divi, Gutenberg) ili bez Bricks popupa. Isti data-jwsk atributi kao
   Bricks popup, pa js/jws-kolacici.js radi isto. Tekstovi iz tekstovi.php (bs/en/sl, WPML/Polylang).
   Izgled: diskretna kartica u donjem desnom uglu (ne preko cijele širine), kao kod Complianza. Boje dugmadi prate sajt:
   „Prihvatam sve" = primary, „Samo neophodni" = neutral (ili secondary), iz Advanced Themera, ACSS-a ili Elementora;
   bez njih boje iz postavki. Boja teksta na dugmetu se bira po kontrastu. Font je od sajta; sve ostalo preko --jwsk-*. */

defined( 'ABSPATH' ) || exit;

// CSS lanac za boju sajta: AT, pa ACSS, pa Elementor, pa boja iz postavki
function jwsk_boja_sajta( $uloga, $rezerva ) {
	$lanci = [
		'primary'   => [ '--at-primary', '--primary', '--e-global-color-primary' ],
		'neutral'   => [ '--at-neutral', '--neutral', '--e-global-color-text' ],
		'secondary' => [ '--at-secondary', '--secondary', '--e-global-color-secondary' ],
	];
	$css = esc_attr( $rezerva );
	foreach ( array_reverse( $lanci[ $uloga ] ) as $var ) {
		$css = 'var(' . $var . ',' . $css . ')';
	}
	return $css;
}

add_action( 'wp_footer', function() {
	if ( 'ugradjena' !== jwsk_prikaz() || jwsk_u_builderu() ) {
		return;
	}
	$o        = jwsk_opcije();
	$t        = function( $k ) {
		return esc_html( jwsk_tekst( $k ) );
	};
	$politika = get_privacy_policy_url();
	$grupe    = array_merge( [ 'neophodni' ], jwsk_grupe() );
	$sajt     = 'sajt' === $o['boja_izvor'];
	$b1       = $sajt ? jwsk_boja_sajta( 'primary', $o['boja'] ) : esc_attr( $o['boja'] );
	$b2       = $sajt ? jwsk_boja_sajta( $o['boja_2_uloga'], $o['boja_2'] ) : esc_attr( $o['boja_2'] );
	?>
<style id="jws-kolacici-traka-css">
.jwsk-traka{--jwsk-pozadina:#fff;--jwsk-tekst:#1f2933;--jwsk-tiho:#5f6b76;--jwsk-linija:#e3e7ec;--jwsk-b1:<?php echo $b1; ?>;--jwsk-b2:<?php echo $b2; ?>;--jwsk-veza:var(--jwsk-b1);--jwsk-radijus:12px;--jwsk-radijus-dugme:6px;
	position:fixed;right:24px;bottom:24px;z-index:99999;width:min(500px,calc(100vw - 48px));max-height:calc(100vh - 48px);overflow:auto;margin:0;
	background:var(--jwsk-pozadina);color:var(--jwsk-tekst);border-radius:var(--jwsk-radijus);box-shadow:0 12px 40px rgba(16,24,40,.16),0 2px 8px rgba(16,24,40,.06);
	padding:18px 20px 16px;font-family:inherit;font-size:14px;font-weight:400;line-height:1.55;letter-spacing:normal;text-align:left;text-transform:none;box-sizing:border-box}
.jwsk-traka[hidden]{display:none}
.jwsk-traka *{box-sizing:border-box;font-family:inherit}
.jwsk-traka p{margin:0;font-size:14px;line-height:1.55;color:inherit;letter-spacing:normal}
.jwsk-traka .jwsk-traka__naslov{display:block;margin:0 0 10px;font-size:16px;font-weight:500;line-height:1.3;text-align:center}
.jwsk-traka a{color:var(--jwsk-veza);text-decoration:underline;text-underline-offset:3px}
.jwsk-traka__dugmad{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:16px}
/* Dugmad su resetovana jer teme (Elementor, Divi, Astra) stilizuju svaki <button>: velika slova, sjena, margine */
.jwsk-traka .jwsk-traka__dugme{appearance:none;display:flex;align-items:center;justify-content:center;width:100%;min-height:44px;margin:0;padding:8px 10px;
	border:1px solid transparent;border-radius:var(--jwsk-radijus-dugme);box-shadow:none;background-image:none;
	font-family:inherit;font-size:14px;font-weight:600;line-height:1.2;letter-spacing:normal;text-transform:none;text-decoration:none;text-align:center;cursor:pointer;transition:filter .15s}
.jwsk-traka .jwsk-traka__dugme:hover{filter:brightness(1.1)}
.jwsk-traka .jwsk-traka__dugme--1{background-color:var(--jwsk-b1);border-color:var(--jwsk-b1);color:#fff}
.jwsk-traka .jwsk-traka__dugme--2{background-color:var(--jwsk-b2);border-color:var(--jwsk-b2);color:#fff}
.jwsk-traka .jwsk-traka__dugme--3{background-color:var(--jwsk-pozadina);border-color:var(--jwsk-linija);color:var(--jwsk-tekst);font-weight:500}
.jwsk-traka .jwsk-traka__dugme--3:hover{filter:none;border-color:var(--jwsk-tiho)}
.jwsk-traka .jwsk-traka__sacuvaj{display:none;grid-column:1 / -1}
.jwsk-traka.is-postavke .jwsk-traka__sacuvaj{display:flex}
.jwsk-traka .jwsk-traka__politika{margin-top:12px;text-align:center;font-size:13px}
.jwsk-traka.is-postavke .jwsk-traka__politika{margin-top:4px}
.jwsk-traka .jwsk-traka__dugme:focus-visible,.jwsk-traka__switch:focus-visible,.jwsk-traka a:focus-visible{outline:2px solid var(--jwsk-tekst);outline-offset:2px}
.jwsk-traka__postavke{display:none;margin-top:12px;border-top:1px solid var(--jwsk-linija)}
.jwsk-traka.is-postavke .jwsk-traka__postavke{display:block}
.jwsk-traka__grupa{padding:10px 0;border-bottom:1px solid var(--jwsk-linija)}
.jwsk-traka__grupa:last-child{border-bottom:0}
.jwsk-traka__glava{display:flex;justify-content:space-between;align-items:center;gap:16px}
.jwsk-traka .jwsk-traka__ime{font-weight:600}
.jwsk-traka .jwsk-traka__opis{margin-top:2px;color:var(--jwsk-tiho);font-size:13px;line-height:1.5}
.jwsk-traka .jwsk-traka__switch{appearance:none;position:relative;flex-shrink:0;width:44px;height:24px;min-height:0;margin:0;padding:0;border:0;border-radius:12px;box-shadow:none;background:var(--jwsk-linija);cursor:pointer}
.jwsk-traka__switch::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.3);transition:transform .15s}
.jwsk-traka .jwsk-traka__switch[aria-checked="true"]{background:var(--jwsk-b1)}
.jwsk-traka__switch[aria-checked="true"]::after{transform:translateX(20px)}
.jwsk-traka .jwsk-traka__switch:disabled{opacity:.5;cursor:default}
.jwsk-traka details{margin-top:4px;font-size:13px}
.jwsk-traka summary{cursor:pointer;color:var(--jwsk-tiho)}
.jwsk-traka .jwsk-spisak{width:100%;margin:6px 0 0;border-collapse:collapse;font-size:12px;line-height:1.45}
.jwsk-traka .jwsk-spisak th,.jwsk-traka .jwsk-spisak td{padding:6px 8px;border:0;border-bottom:1px solid var(--jwsk-linija);background:none;text-align:left;vertical-align:top}
/* U postavkama je kartica visoka i skroluje se; dugmad ostaju vidljiva na dnu */
.jwsk-traka.is-postavke .jwsk-traka__dugmad{position:sticky;bottom:-16px;margin:12px -20px 0;padding:12px 20px 14px;background:var(--jwsk-pozadina);border-top:1px solid var(--jwsk-linija)}
@media (max-width:600px){
	.jwsk-traka{right:12px;bottom:12px;width:calc(100vw - 24px);max-height:calc(100vh - 24px);padding:16px 16px 14px}
	.jwsk-traka p{font-size:14px;line-height:1.5}
	.jwsk-traka__dugmad{grid-template-columns:1fr 1fr;gap:8px;margin-top:14px}
	.jwsk-traka .jwsk-traka__dugme{min-height:44px;font-size:14px}
	.jwsk-traka .jwsk-traka__dugme--3{grid-column:1 / -1}
	.jwsk-traka .jwsk-traka__politika{margin-top:10px;font-size:13px}
	.jwsk-traka.is-postavke .jwsk-traka__dugmad{bottom:-14px;margin:12px -16px 0;padding:10px 16px 12px}
	.jwsk-traka .jwsk-spisak th:nth-child(3),.jwsk-traka .jwsk-spisak td:nth-child(3){display:none}
}
@media (prefers-reduced-motion:reduce){.jwsk-traka__switch::after,.jwsk-traka .jwsk-traka__dugme{transition:none}}
</style>
<section class="jwsk-traka" data-jwsk-traka data-jwsk-boje="<?php echo $sajt ? 'sajt' : 'rucno'; ?>" role="dialog" aria-modal="false" aria-labelledby="jwsk-naslov" hidden>
	<strong class="jwsk-traka__naslov" id="jwsk-naslov"><?php echo $t( 'naslov' ); ?></strong>
	<p class="jwsk-traka__uvod"><?php echo $t( 'uvod' ); ?></p>
	<div class="jwsk-traka__postavke" id="jwsk-postavke">
		<?php foreach ( $grupe as $g ) : ?>
		<div class="jwsk-traka__grupa" data-jwsk-grupa="<?php echo esc_attr( $g ); ?>">
			<div class="jwsk-traka__glava">
				<p class="jwsk-traka__ime" id="jwsk-g-<?php echo esc_attr( $g ); ?>"><?php echo $t( 'grupa_' . $g ); ?></p>
				<?php if ( 'neophodni' === $g ) : ?>
				<button type="button" class="jwsk-traka__switch" role="switch" aria-checked="true" aria-labelledby="jwsk-g-neophodni" disabled></button>
				<?php else : ?>
				<button type="button" class="jwsk-traka__switch" role="switch" aria-checked="false" aria-labelledby="jwsk-g-<?php echo esc_attr( $g ); ?>" data-jwsk-switch="<?php echo esc_attr( $g ); ?>"></button>
				<?php endif; ?>
			</div>
			<p class="jwsk-traka__opis"><?php echo $t( 'opis_' . $g ); ?></p>
			<details><summary><?php echo $t( 'spisak' ); ?></summary><?php echo do_shortcode( '[jws_kolacici_spisak grupa="' . esc_attr( $g ) . '"]' ); // phpcs:ignore ?></details>
		</div>
		<?php endforeach; ?>
	</div>
	<div class="jwsk-traka__dugmad">
		<button type="button" class="jwsk-traka__dugme jwsk-traka__dugme--1" data-jwsk="sve"><?php echo $t( 'prihvati' ); ?></button>
		<button type="button" class="jwsk-traka__dugme jwsk-traka__dugme--2" data-jwsk="nuzni"><?php echo $t( 'odbij' ); ?></button>
		<button type="button" class="jwsk-traka__dugme jwsk-traka__dugme--3" data-jwsk="postavke" aria-controls="jwsk-postavke" aria-expanded="false"><?php echo $t( 'postavke' ); ?></button>
		<button type="button" class="jwsk-traka__dugme jwsk-traka__dugme--3 jwsk-traka__sacuvaj" data-jwsk="sacuvaj"><?php echo $t( 'sacuvaj' ); ?></button>
	</div>
	<?php if ( $politika ) : ?><p class="jwsk-traka__politika"><a href="<?php echo esc_url( $politika ); ?>"><?php echo $t( 'politika' ); ?></a></p><?php endif; ?>
</section>
<script id="jws-kolacici-traka-kontrast">
/* Tekst na dugmetu: bijel ili taman, prema svjetlini pozadine. Boje dugmadi dolaze samo iz CSS varijabli sajta ili iz postavki,
   nikad sa dugmadi na stranici: to bi davalo drugu boju na svakoj stranici. */
(function () {
	document.querySelectorAll('.jwsk-traka__dugme--1,.jwsk-traka__dugme--2').forEach(function (b) {
		var m = getComputedStyle(b).backgroundColor.match(/\d+(\.\d+)?/g); if (!m) return;
		var l = function (c) { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
		var y = 0.2126 * l(+m[0]) + 0.7152 * l(+m[1]) + 0.0722 * l(+m[2]);
		b.style.color = y > 0.179 ? '#111' : '#fff'; // prag gdje su kontrast sa bijelim i crnim jednaki
	});
	// Link na politiku je u boji primary; ako je primary svijetla (npr. žuta), ide boja teksta
	var t = document.querySelector('.jwsk-traka'), d = t && t.querySelector('.jwsk-traka__dugme--1');
	if (d && d.style.color === 'rgb(17, 17, 17)') t.style.setProperty('--jwsk-veza', 'var(--jwsk-tekst)');
})();
</script>
	<?php
}, 5 );
