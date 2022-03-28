<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


//TODO: delete most of the methods
class WDP_Rules_Collection {
	/** @var WDP_Rule[] */
	private $rules;

	/**
	 * @param WDP_Rule[] $rules
	 */
	public function __construct( $rules ) {
		$this->rules = $rules;
		$this->order();
	}

	public function to_array() {
		return $this->rules;
	}

	/**
	 * @return WDP_Rules_Collection
	 */
	public function with_type( $types ) {
		if ( ! is_array( $types ) ) // support single value
		{
			$types = array( $types );
		}
		$filtered_rules = array();
		foreach ( $this->rules as $rule ) {
			if ( in_array( $rule->type, $types ) ) {
				$filtered_rules[] = $rule;
			}
		}
		$new_collection = new self( $filtered_rules );

		return $new_collection;
	}

	public function with_exclusive() {
		$filtered_rules = array();
		foreach ( $this->rules as $rule ) {
			if ( $rule->is_exclusive() ) {
				$filtered_rules[] = $rule;
			}
		}
		$new_collection = new self( $filtered_rules );

		return $new_collection;
	}

	public function with_bulk() {
		$filtered_rules = array();
		foreach ( $this->rules as $rule ) {
			if ( $rule->has_bulk() ) {
				$filtered_rules[] = $rule;
			}
		}
		$new_collection = new self( $filtered_rules );

		return $new_collection;
	}

	public function order() {
		usort( $this->rules, array( $this, 'cmp_by_exclusive_and_priority' ) );

		return $this;
	}

	/**
	 * @param WDP_Rule $a
	 * @param WDP_Rule $b
	 *
	 * @return int
	 */
	private function cmp_by_exclusive_and_priority( $a, $b ) {
		if ( $a->is_exclusive() && ! $b->is_exclusive() ) {
			return - 1;
		} elseif ( ! $a->is_exclusive() && $b->is_exclusive() ) {
			return 1;
		} elseif ( $a->get_priority() <= $b->get_priority() ) {
			return - 1;
		} else {
			return 1;
		}
	}

	/**
	 * @return WDP_Rules_Collection
	 */
	public function order_by_priority() {
		usort( $this->rules, array( $this, 'cmp_by_priority' ) );

		return $this;
	}

	/**
	 * @param WDP_Rule $a
	 * @param WDP_Rule $b
	 *
	 * @return int
	 */
	private function cmp_by_priority( $a, $b ) {
		if ( $a->get_priority() <= $b->get_priority() ) {
			return - 1;
		} else {
			return 1;
		}
	}

	/**
	 * @return WDP_Rules_Collection
	 */
	public function order_by_product_dependencies() {
		usort( $this->rules, array( $this, 'cmp_by_product_dependencies' ) );

		return $this;
	}

	/**
	 * @param WDP_Rule $a
	 * @param WDP_Rule $b
	 *
	 * @return int
	 */
	private function cmp_by_product_dependencies( $a, $b ) {
		if ( $a->get_count_of_product_dependencies() <= $b->get_count_of_product_dependencies() ) {
			return - 1;
		} else {
			return 1;
		}
	}

	public function count() {
		return count( $this->rules );
	}

	public function is_empty() {
		return empty( $this->rules );
	}

	/**
	 * @return WDP_Rule|false
	 */
	public function get_first() {
		$rule = reset( $this->rules );

		return $rule;
	}

	protected function get_rule( $pos ) {
		$rule = null;

		if ( isset( $this->rules[ $pos ] ) ) {
			$rule = $this->rules[ $pos ];
		} else {
			throw new Exception( 'Invalid pos number for collection of rules' );
		}

		return $rule;
	}

	public function get_exact( $rule_ids ) {
		$filtered_rules    = array();
		$rule_ids = (array) $rule_ids;

		foreach ( $this->rules as $rule ) {
			/**
			 * @var $rule WDP_Rule
			 */
			if ( in_array( $rule->get_id(), $rule_ids ) ) {
				$filtered_rules[] = $rule;
			}
		}

		$new_collection = new self( $filtered_rules );

		return $new_collection;
	}
}