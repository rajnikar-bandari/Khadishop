<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Product_Taxonomy extends WDP_Condition_Cart_Items_Abstract {
	public function __construct( $data ) {
		parent::__construct( $data );
		$this->filter_type = $data['type'];
	}
}