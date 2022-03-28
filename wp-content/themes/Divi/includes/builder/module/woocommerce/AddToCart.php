<?php
/**
 * WooCommerce Modules: ET_Builder_Module_Woocommerce_Add_To_Cart class
 *
 * The ET_Builder_Module_Woocommerce_Add_To_Cart Class is responsible for rendering the
 * Add To Cart markup using the WooCommerce template.
 *
 * @package Divi\Builder
 *
 * @since   3.29
 */

/**
 * Class representing WooCommerce Add to cart component.
 */
class ET_Builder_Module_Woocommerce_Add_To_Cart extends ET_Builder_Module {
	/**
	 * Initialize.
	 */
	public function init() {
		$this->name       = esc_html__( 'Woo Add To Cart', 'et_builder' );
		$this->plural     = esc_html__( 'Woo Add To Cart', 'et_builder' );
		$this->slug       = 'et_pb_wc_add_to_cart';
		$this->vb_support = 'on';

		$this->settings_modal_toggles = array(
			'general'  => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Content', 'et_builder' ),
					'elements'     => esc_html__( 'Elements', 'et_builder' ),
				),
			),
			'advanced' => array(
				'toggles' => array(
					'text'   => array(
						'title'    => esc_html__( 'Text', 'et_builder' ),
						'priority' => 45,
					),
					'header' => array(
						'title'             => esc_html__( 'Heading Text', 'et_builder' ),
						'priority'          => 49,
						'tabbed_subtoggles' => true,
						'sub_toggles'       => array(
							'h1' => array(
								'name' => 'H1',
								'icon' => 'text-h1',
							),
							'h2' => array(
								'name' => 'H2',
								'icon' => 'text-h2',
							),
							'h3' => array(
								'name' => 'H3',
								'icon' => 'text-h3',
							),
							'h4' => array(
								'name' => 'H4',
								'icon' => 'text-h4',
							),
							'h5' => array(
								'name' => 'H5',
								'icon' => 'text-h5',
							),
							'h6' => array(
								'name' => 'H6',
								'icon' => 'text-h6',
							),
						),
					),
					'width'  => array(
						'title'    => esc_html__( 'Sizing', 'et_builder' ),
						'priority' => 80,
					),
				),
			),
		);

		$this->advanced_fields = array(
			'fonts'          => array(
				'body' => array(
					'label'           => esc_html__( 'Text', 'et_builder' ),
					'css'             => array(
						'main'      => '%%order_class%%, %%order_class%% a, %%order_class%% label, %%order_class%%.et_pb_module .et_pb_module_inner .stock',
						'important' => 'all',
					),
					'font_size'       => array(
						'default' => '14px',
					),
					'line_height'     => array(
						'default' => '1.3em',
					),
					'hide_text_align' => true,
					'toggle_slug'     => 'text',
					'font'            => array(
						'default' => '|700|||||||',
					),
				),
			),
			'background'     => array(
				'settings' => array(
					'color' => 'alpha',
				),
			),
			'margin_padding' => array(
				'css' => array(
					'important' => 'all',
				),
			),
			'text'           => array(
				'use_background_layout' => true,
				'options'               => array(
					'text_orientation'  => array(
						'default' => 'left',
					),
					'background_layout' => array(
						'default' => 'light',
						'hover'   => 'tabs',
					),
				),
			),
			'text_shadow'    => array(
				// Don't add text-shadow fields since they already are via font-options.
				'default' => false,
			),
			'button'         => array(
				'button' => array(
					'label'          => esc_html__( 'Button', 'et_builder' ),
					'css'            => array(
						'main'         => '%%order_class%% .button',
						'limited_main' => '%%order_class%% .button',
						'alignment'    => '%%order_class%% .et_pb_module_inner > form',
					),

					/*
					 * Button inside add to cart module is rendered from WooCommerce's default
					 * template which makes its positioning isn't flexible. Thus button alignment
					 * is removed.
					 */
					'use_alignment'  => false,
					'box_shadow'     => array(
						'css' => array(
							'main' => '%%order_class%% .button',
						),
					),
					'use_icon'       => false,
					'margin_padding' => array(
						'css' => array(
							'important' => 'all',
						),
					),
				),
			),
			'form_field'     => array(
				'fields'         => array(
					'label'           => esc_html__( 'Fields', 'et_builder' ),
					'toggle_priority' => 67,
					'css'             => array(
						'main'                   => '%%order_class%% input, %%order_class%% .quantity input.qty',
						'background_color'       => '%%order_class%% input, %%order_class%% .quantity input.qty',
						'background_color_hover' => '%%order_class%% input:hover, %%order_class%% .quantity input.qty:hover',
						'focus_background_color' => '%%order_class%% input:focus, %%order_class%% select:focus, %%order_class%% .quantity input.qty:focus',
						'form_text_color'        => '%%order_class%% input, %%order_class%% select, %%order_class%% .quantity input.qty',
						'form_text_color_hover'  => '%%order_class%% input[type="text"]:hover, %%order_class%% select:hover, %%order_class%% .quantity input.qty:hover',
						'focus_text_color'       => '%%order_class%% input:focus, %%order_class%% .quantity input.qty:focus',
						'placeholder_focus'      => '%%order_class%% input:focus::-webkit-input-placeholder, %%order_class%% input:focus::-moz-placeholder, %%order_class%% input:focus:-ms-input-placeholder, %%order_class%% textarea:focus::-webkit-input-placeholder, %%order_class%% textarea:focus::-moz-placeholder, %%order_class%% textarea:focus:-ms-input-placeholder',
						'padding'                => '%%order_class%% input',
						'margin'                 => '%%order_class%%',
						'important'              => array(
							'background_color',
							'background_color_hover',
							'focus_background_color',
							'form_text_color',
							'form_text_color_hover',
							'text_color',
							'focus_text_color',
							'padding',
							'margin',
						),
					),
					'box_shadow'      => array(
						'name'              => 'fields',
						'css'               => array(
							'main' => '%%order_class%% input',
						),
						'default_on_fronts' => array(
							'color'    => '',
							'position' => '',
						),
					),
					'border_styles'   => array(
						'fields'       => array(
							'name'         => 'fields',
							'css'          => array(
								'main'      => array(
									'border_radii'  => '%%order_class%% input, %%order_class%% .quantity input.qty',
									'border_styles' => '%%order_class%% input, %%order_class%% .quantity input.qty',
									'defaults'      => array(
										'border_radii'  => 'on|3px|3px|3px|3px',
										'border_styles' => array(
											'width' => '0px',
											'style' => 'none',
										),
									),
								),
								'important' => 'all',
							),
							'label_prefix' => esc_html__( 'Fields', 'et_builder' ),
						),
						'fields_focus' => array(
							'name'         => 'fields_focus',
							'css'          => array(
								'main'      => array(
									'border_radii'  => '%%order_class%% input:focus, %%order_class%% .quantity input.qty:focus',
									'border_styles' => '%%order_class%% input:focus, %%order_class%% .quantity input.qty:focus',
								),
								'important' => 'all',
							),
							'label_prefix' => esc_html__( 'Fields Focus', 'et_builder' ),
						),
					),
					'font_field'      => array(
						'css'         => array(
							'main'      => array(
								'%%order_class%% input, %%order_class%% .quantity input.qty',
							),
							'hover'     => array(
								'%%order_class%% input:hover',
								'%%order_class%% input:hover::-webkit-input-placeholder',
								'%%order_class%% input:hover::-moz-placeholder',
								'%%order_class%% input:hover:-ms-input-placeholder',
							),
							'important' => 'all',
						),
						'font_size'   => array(
							'default' => '20px',
						),
						'line_height' => array(
							'default' => '1em',
						),
					),
					'margin_padding'  => array(
						'css' => array(
							'main'      => '%%order_class%% input',
							'padding'   => '%%order_class%% input, %%order_class%% select',
							'important' => array( 'custom_padding' ),
						),
					),
				),
				'dropdown_menus' => array(
					'label'           => esc_html__( 'Dropdown Menus', 'et_builder' ),
					'toggle_priority' => 67,
					'css'             => array(
						'main'                   => '%%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select',
						'background_color'       => '%%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select',
						'background_color_hover' => '%%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select:hover',
						'focus_background_color' => '%%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select:focus',
						'form_text_color'        => '%%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select',
						'form_text_color_hover'  => '%%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select + label:hover, %%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select:hover',
						'focus_text_color'       => '%%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select option:focus, %%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select + label',
						'placeholder_focus'      => '%%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select:focus, %%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select + label:focus',
						'margin_padding'         => array(
							'css' => array(
								'main'      => '%%order_class%% select',
								'important' => array( 'all' ),
							),
						),
						'important'              => array(
							'text_color',
							'form_text_color',
							'margin_padding',
						),
					),
					'margin_padding'  => array(
						'use_padding' => false,
					),
					'box_shadow'      => array(
						'name' => 'dropdown_menus',
						'css'  => array(
							'main' => '%%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select',
						),
					),
					'border_styles'   => array(
						'dropdown_menus' => array(
							'name'         => 'dropdown_menus',
							'css'          => array(
								'main'      => array(
									'border_styles' => '%%order_class%%.et_pb_module .et_pb_module_inner form.cart .variations td select',
								),
								'important' => 'all',
							),
							'label_prefix' => esc_html__( 'Dropdown Menus', 'et_builder' ),
							'use_radius'   => false,
						),
					),
					'font_field'      => array(
						'css'              => array(
							'main'      => array(
								'%%order_class%% select',
							),
							'hover'     => array(
								'%%order_class%% select:hover',
							),
							'important' => 'all',
						),
						'font_size'        => array(
							'default' => '12px',
						),
						'hide_line_height' => true,
						'hide_text_align'  => true,
					),
				),
			),
		);

		$this->custom_css_fields = array(
			'fields'         => array(
				'label'    => esc_html__( 'Fields', 'et_builder' ),
				'selector' => 'input',
			),
			'dropdown_menus' => array(
				'label'    => esc_html__( 'Dropdown Menus', 'et_builder' ),
				'selector' => 'select',
			),
			'buttons'        => array(
				'label'    => esc_html__( 'Buttons', 'et_builder' ),
				'selector' => '.button',
			),
		);

		$this->help_videos = array(
			array(
				'id'   => esc_html( '7X03vBPYJ1o' ),
				'name' => esc_html__( 'Divi WooCommerce Modules', 'et_builder' ),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields() {
		$fields = array(
			'product'        => ET_Builder_Module_Helper_Woocommerce_Modules::get_field(
				'product',
				array(
					'default'          => ET_Builder_Module_Helper_Woocommerce_Modules::get_product_default(),
					'computed_affects' => array(
						'__add_to_cart',
					),
				)
			),
			'product_filter' => ET_Builder_Module_Helper_Woocommerce_Modules::get_field(
				'product_filter',
				array(
					'computed_affects' => array(
						'__add_to_cart',
					),
				)
			),
			'show_quantity'  => array(
				'label'           => esc_html__( 'Show Quantity Field', 'et_builder' ),
				'type'            => 'yes_no_button',
				'option_category' => 'configuration',
				'options'         => array(
					'on'  => esc_html__( 'On', 'et_builder' ),
					'off' => esc_html__( 'Off', 'et_builder' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'elements',
				'description'     => esc_html__( 'Here you can choose whether the quantity field should be added before the Add to Cart button.', 'et_builder' ),
				'mobile_options'  => true,
				'hover'           => 'tabs',
			),
			'show_stock'     => array(
				'label'           => esc_html__( 'Show Stock', 'et_builder' ),
				'type'            => 'yes_no_button',
				'option_category' => 'configuration',
				'options'         => array(
					'on'  => esc_html__( 'On', 'et_builder' ),
					'off' => esc_html__( 'Off', 'et_builder' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'elements',
				'description'     => esc_html__( 'Here you can choose whether the stock (displayed when product inventory is managed) should be visible or not', 'et_builder' ),
				'mobile_options'  => true,
				'hover'           => 'tabs',
			),
			'__add_to_cart'  => array(
				'type'                => 'computed',
				'computed_callback'   => array(
					'ET_Builder_Module_Woocommerce_Add_To_Cart',
					'get_add_to_cart',
				),
				'computed_depends_on' => array(
					'product',
					'product_filter',
				),
				'computed_minimum'    => array(
					'product',
				),
			),
		);

		return $fields;
	}

	/**
	 * Get add to cart markup as string
	 *
	 * @param array $args Additional arguments.
	 *
	 * @return string
	 */
	public static function get_add_to_cart( $args = array() ) {
		return et_builder_wc_render_module_template( 'woocommerce_template_single_add_to_cart', $args );
	}

	/**
	 * Gets the Button classname.
	 *
	 * @used-by ET_Builder_Module_Helper_Woocommerce_Modules::add_custom_button_icons()
	 *
	 * @return string
	 */
	public function get_button_classname() {
		return 'single_add_to_cart_button';
	}

	/**
	 * Adds Multi view attributes to the Outer wrapper.
	 *
	 * Since we do not have control over the WooCommerce Breadcrumb markup, we inject Multi view
	 * attributes on to the Outer wrapper.
	 *
	 * @param array $outer_wrapper_attrs
	 *
	 * @return array
	 */
	public function add_multi_view_attrs( $outer_wrapper_attrs ) {
		$multi_view = et_pb_multi_view_options( $this );

		$multi_view_attrs = $multi_view->render_attrs( array(
			'classes' => array(
				'et_pb_hide_input_quantity' => array(
					'show_quantity' => 'off',
				),
				'et_pb_hide_stock'          => array(
					'show_stock' => 'off',
				),
			),
		), false, null, true );

		if ( $multi_view_attrs && is_array( $multi_view_attrs ) ) {
			$outer_wrapper_attrs = array_merge( $outer_wrapper_attrs, $multi_view_attrs );
		}

		return $outer_wrapper_attrs;
	}

	/**
	 * Renders the module output.
	 *
	 * @param  array  $attrs       List of attributes.
	 * @param  string $content     Content being processed.
	 * @param  string $render_slug Slug of module that is used for rendering output.
	 *
	 * @return string
	 */
	public function render( $attrs, $content = null, $render_slug ) {
		$multi_view = et_pb_multi_view_options( $this );
		$use_focus_border_color = $this->props['use_focus_border_color'];

		// Module classnames.
		if ( 'on' !== $multi_view->get_value( 'show_quantity' ) ) {
			$this->add_classname( 'et_pb_hide_input_quantity' );
		}

		if ( 'on' !== $multi_view->get_value( 'show_stock' ) ) {
			$this->add_classname( 'et_pb_hide_stock' );
		}

		if ( 'on' === $use_focus_border_color ) {
			$this->add_classname( 'et_pb_with_focus_border' );
		}

		ET_Builder_Module_Helper_Woocommerce_Modules::process_background_layout_data( $render_slug, $this );
		ET_Builder_Module_Helper_Woocommerce_Modules::process_custom_button_icons( $render_slug, $this );

		$this->add_classname( $this->get_text_orientation_classname() );

		add_filter( "et_builder_module_{$render_slug}_outer_wrapper_attrs", array(
			$this,
			'add_multi_view_attrs',
		) );

		$output = self::get_add_to_cart( $this->props );

		// Render empty string if no output is generated to avoid unwanted vertical space.
		if ( '' === $output ) {
			return '';
		}

		return $this->_render_module_wrapper( $output, $render_slug );
	}
}

new ET_Builder_Module_Woocommerce_Add_To_Cart();
