<?php

defined( 'ABSPATH' ) or exit;

class WDP_Customize_Text_Align_Control extends WP_Customize_Control {

	public $type = 'wdp_text_align';
	private $choises = array();

    public function __construct( WP_Customize_Manager $manager, $id, array $args = array() ) {
        $this->choices = array(
	        'left'   => __( 'Align left', 'woocommerce-pdf-product-vouchers' ),
	        'center' => __( 'Align center', 'woocommerce-pdf-product-vouchers' ),
	        'right'  => __( 'Align right', 'woocommerce-pdf-product-vouchers' ),
        );
	    parent::__construct( $manager, $id, $args );
    }

	public function enqueue() {
		// enqueue control script in WDP_Customizer
	}

	public function render_content() {
		$this_value = $this->value();

		$name = '_customize-radio-' . $this->id;
		?>
        <span class="font-style-radio-container">

				<?php /* hidden radio option to allow unchecking the text align control */ ?>
            <input type="radio" value="" class="font-style-text-align-empty" style="display: none;" name="<?php echo esc_attr( $name ); ?>" <?php $this->link(); checked( ! $this_value, true ); ?> />

			<?php foreach ( $this->choices as $value => $label ) : ?>
                <label class="wdp-control-icon font-style-button <?php if ( $this_value === $value ) : echo 'active'; endif; ?>" data-tip="<?php echo esc_html( $label ); ?>">
						<input type="radio" class="font-style-text-align" value="<?php echo esc_attr( $value ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php $this->link(); checked( $this_value, $value ); ?> />
						<span class="dashicons dashicons-editor-align<?php echo esc_attr( $value ); ?>"></span>
					</label>
			<?php endforeach; ?>
			</span>
		<?php


	}

}
