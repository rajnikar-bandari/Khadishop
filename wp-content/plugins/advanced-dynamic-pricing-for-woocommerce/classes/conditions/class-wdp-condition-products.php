<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WDP_Condition_Products extends WDP_Condition_Cart_Items_Abstract {
	protected $filter_type = 'products';
}