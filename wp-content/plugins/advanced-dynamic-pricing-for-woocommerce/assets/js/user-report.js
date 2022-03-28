/**
 * @var {{ajaxurl:string, i:object, classes:object}} user_report_data
 */

/**
 *
 * @type {{init: rule_tooltip.init, onmouseout: rule_tooltip.onmouseout, showingTooltip: null, onmouseover: rule_tooltip.onmouseover}}
 */
var rule_tooltip = {
    showingTooltip: null,

    onmouseover: function (e) {
        var _this = rule_tooltip;
        var target = e.target;

        var rule_id = target.getAttribute('data-rule-id');
        if (!rule_id && target.parentElement) {
            target = target.parentElement;
            rule_id = target.getAttribute('data-rule-id');
        }

        if (!rule_id) return;

        var tooltip_content = '';
        // tooltip_content += "<div>" + user_report_data.i.rule_id + ':' + rule_id + "</div>";
        tooltip_content += "<div>" + user_report_data.i.rule + ' : ' + '"' + rules_storage.get_title(rule_id) + '"' + "</div>";

        if ( target.classList.contains(user_report_data.classes.replaced_by_coupon) ) {
            tooltip_content += "<div>" + user_report_data.i.replaced_by_coupon + "</div>";
        } else if ( target.classList.contains((user_report_data.classes.replaced_by_fee)) ) {
            tooltip_content += "<div>" + user_report_data.i.replaced_by_fee + "</div>";
        }

        var tooltipElem = document.createElement('div');
        tooltipElem.className = 'tooltip';
        tooltipElem.innerHTML = tooltip_content;
        document.body.appendChild(tooltipElem);

        var coords = target.getBoundingClientRect();

        var left = coords.left + (target.offsetWidth - tooltipElem.offsetWidth) / 2;
        if (left < 0) left = 0;

        var top = coords.top - tooltipElem.offsetHeight - 5;
        if (top < 0) {
            top = coords.top + target.offsetHeight + 5;
        }

        tooltipElem.style.left = left + 'px';
        tooltipElem.style.top = top + 'px';

        _this.showingTooltip = tooltipElem;
    },

    onmouseout: function (e) {
        var _this = rule_tooltip;

        if (_this.showingTooltip) {
            document.body.removeChild(_this.showingTooltip);
            _this.showingTooltip = null;
        }

    },

    init: function () {
        var _this = rule_tooltip;

        document.onmouseover = _this.onmouseover;
        document.onmouseout = _this.onmouseout;
    }

};

var rules_storage = {
    rules: {},

    update: function (rules) {
        this.rules = rules;
    },

    get_title: function (rule_id) {
        return this.is_rule_exists(rule_id) ? this.rules[rule_id].title : false;
    },

    get_edit_url: function (rule_id) {
        return this.is_rule_exists(rule_id) ? this.rules[rule_id].edit_page_url : false;
    },

    is_rule_exists: function (rule_id) {
        return typeof this.rules[rule_id] !== 'undefined';
    }
};


var wdp_reporter = {
    container: null,

    ajaxurl: user_report_data.ajaxurl,
    import_key: user_report_data.import_key,

    format_price: function ($price) {
        $price = parseFloat($price);

        return Math.round($price * 100) / 100;
    },

    format_difference: function ($price) {
        return $price > 0 ? "+" + this.format_price($price) : this.format_price($price);
    },

    format_decimals: function ($price) {
        $price = parseFloat($price);

        return Math.round($price * 100) / 100;
    },

    template_manager: {
        templates: {
            'tab': '#wdp_reporter_tab_template',
            'tab_link': '#wdp_reporter_tab_link_template',
            'history_chunk': '#wdp_reporter_history_chunk_template',


            'cart_is_empty': '#wdp_reporter_tab_cart_empty_template',
            'cart_items': '#wdp_reporter_tab_cart_items_template',
            'cart_coupons': '#wdp_reporter_tab_cart_coupons_template',
            'cart_fees': '#wdp_reporter_tab_cart_fees_template',
            'single_item': '#wdp_reporter_tab_items_single_item_template',
            'cart_coupon': '#wdp_reporter_tab_items_single_coupon_template',
            'cart_adj_history_chunk': '#wdp_reporter_tab_items_cart_adj_history_chink_template',
            'empty_history': '#wdp_reporter_tab_items_single_item_empty_history_template',
            'gifted_history': '#wdp_reporter_tab_items_single_item_gifted_history_template',
            'cart_fee': '#wdp_reporter_tab_items_single_fee_template',

            'cart_shipping': '#wdp_reporter_tab_cart_shipping_template',
            'cart_shipping_package': '#wdp_reporter_tab_cart_shipping_package_template',
            'shipping_rate': '#wdp_reporter_tab_items_single_shipping_rate_template',
            'free_wdp_shipping_rate': '#wdp_reporter_tab_items_single_free_shipping_rate_template',

            'products': '#wdp_reporter_tab_products_template',
            'product_row': '#wdp_reporter_tab_products_single_product_template',

            'rules': '#wdp_reporter_tab_rules_template',
            'product_rules_table': '#wdp_reporter_tab_rules_products_table_template',
            'cart_rules_table': '#wdp_reporter_tab_rules_cart_table_template',
            'rule_row': '#wdp_reporter_tab_rules_single_rule_template',

            'export_buttons': '#wdp_reporter_tab_reports_buttons_template',
        },

        get_template: function (name, variables) {
            var template_selector = wdp_reporter.template_manager.templates[name] || '';
            if (!template_selector) {
                return '';
            }

            if ( jQuery(template_selector).length === 0 ) {
                console.log("%c Template %s not found", "color:red;",  name);
                return '';
            }

            var template = jQuery(template_selector).html();

            var required_variable_keys = [];
            var regExp = /{(\w+)}/g;
            var match = regExp.exec(template);

            while (match != null) {
                required_variable_keys.push(match[1]);
                match = regExp.exec(template);
            }

            for (var i = 0; i < required_variable_keys.length; i++) {
                var required_key = required_variable_keys[i];

                if (Object.keys(variables).indexOf(required_key) !== -1) {
                    template = template.replace(new RegExp('{' + required_key + '}', 'g'), variables[required_key]);
                } else {
                    console.log("%c Key %s not found in template \"%s\"", "color:red;", required_key, name);
                    template = '';
                }
            }

            return template;
        }

    },

    update: function ($) {
        jQuery.ajax({
            url: wdp_reporter.ajaxurl,
            data: {
                action: 'get_user_report_data',
                import_key: this.import_key,
            },
            dataType: 'json',
            type: 'POST',
            beforeSend: function() {
                jQuery("#progress_div").show();
            },
            success: function (response) {

		if (!response.data.processed_cart) {
            window.location.reload();
		    return;
		}
                rules_storage.update(response.data.rules);
                wdp_reporter.fill_tabs(response.data);
            },
            error: function (response) {
                console.log("%c Update ajax error", "color:red;");
                console.log(response);
            },
            complete: function() {
                jQuery("#progress_div").hide();
            }
        });
    },

    fill_tabs: function (data) {
        jQuery('#wdp-report-tab-window').html('');
        wdp_reporter.tab_cart.fill(data.processed_cart);
        wdp_reporter.tab_products.fill(data.processed_products);
        wdp_reporter.tab_rules.fill(data.rules_timing);
        wdp_reporter.tab_get_report.fill();
    },

    tab_rules: {
        key: 'rules',
        label: user_report_data.i.rules,

        fill: function(rules) {
            var $cart_rules = '';
            var $cart_table_classes_formatted = '';
            var $product_rules = '';
            var $product_table_classes_formatted = '';
            var _this = this;

            if (Object.keys(rules.cart).length > 0) {
                jQuery.each(rules.cart, function (index, rule) {
                    $cart_rules += _this.make_row(rule, index + 1)
                });
            } else {
                $cart_table_classes_formatted = 'hide ';
            }


            if (Object.keys(rules.products).length > 0) {
                jQuery.each(rules.products, function (index, rule) {
                    $product_rules += _this.make_row(rule, index + 1)
                });
            } else {
                $product_table_classes_formatted = 'hide ';
            }

            var $cart_table_html = wdp_reporter.template_manager.get_template('cart_rules_table', {
                'rule_rows': $cart_rules,
            });

            var $products_table_html = wdp_reporter.template_manager.get_template('product_rules_table', {
                'rule_rows': $product_rules,
            });

            var $tab_content_html = wdp_reporter.template_manager.get_template('rules', {
                'cart_table': $cart_table_html,
                'products_table': $products_table_html,
                'cart_table_classes': $cart_table_classes_formatted,
                'products_table_classes': $product_table_classes_formatted,
            });

            var $tab_product_content = wdp_reporter.template_manager.get_template('tab', {
                'tab_key': this.key,

                'active': '',

                'sub_tabs_selector_html': '',
                'sub_tabs_selector_class': '',

                'tab_content_html': $tab_content_html,
            });

            jQuery('#wdp-report-tab-window').append($tab_product_content);
        },

        make_row: function (rule, index) {
            return wdp_reporter.template_manager.get_template('rule_row', {
                'rule_id': rule.id,
                'index': index,
                'title': rules_storage.get_title(rule.id),
                'edit_page_url': rules_storage.get_edit_url(rule.id),
                'timing': rule.timing >= 0.01 ? wdp_reporter.format_decimals(rule.timing) : '< 0.01',
            });
        },
    },

    tab_products: {
        key: 'products',
        label: user_report_data.i.products,

        fill: function(products) {
            var $products = '';
            var _this = this;

            jQuery.each(products, function (index, product) {
                $products += _this.make_row(product, index + 1)
            });

            var $tab_content_html = wdp_reporter.template_manager.get_template('products', {
                'product_rows': $products,
            });

            var $tab_product_content = wdp_reporter.template_manager.get_template('tab', {
                'tab_key': 'products',

                'active': '',

                'sub_tabs_selector_html': '',
                'sub_tabs_selector_class': '',

                'tab_content_html': $tab_content_html,
            });

            jQuery('#wdp-report-tab-window').append($tab_product_content);
        },

        make_row: function (product, index) {
            var $history = '';
            var $original_price = product.data.original_price;

            if (Object.keys(product.history).length) {
                jQuery.each(product.history, function (_, history_chunk) {
                    if ( typeof history_chunk.rule_id === 'undefined' || typeof history_chunk.amount === 'undefined') {
                        return;
                    }

                    var $rule_id = parseInt(history_chunk.rule_id);
                    var $amount = parseFloat(history_chunk.amount);

                    $history += wdp_reporter.template_manager.get_template('history_chunk',
                        {
                            'rule_id': $rule_id,
                            'old_price': wdp_reporter.format_price($original_price),
                            'amount': wdp_reporter.format_difference((-1) * $amount),
                            'new_price': wdp_reporter.format_price($original_price - $amount),
                            'is_replaced': '',
                        }
                    );
                    $original_price -= $amount;
                });
            } else {
                $history += wdp_reporter.template_manager.get_template('empty_history', {});
            }

            return wdp_reporter.template_manager.get_template('product_row', {
                'product_id': product.data.id,
                'parent_product_id': product.data.parent_id,
                'index': index,
                'name': product.data.name,
                'page_url': product.data.page_url,
                'original_price': wdp_reporter.format_price(product.data.original_price),
                'discounted_price': wdp_reporter.format_price(product.data.discounted_price),
                'history': $history,
            });
        },
    },


    tab_cart: {
        key: 'cart',
        label: user_report_data.i.cart,

        tab_items: {
            key: 'items',
            label: user_report_data.i.items,

            is_show: function (data) {
                return Object.keys(data.items).length > 0;
            },

            get_items_html: function (items) {
                var $items_tab_content = '';
                var $index = 1;

                jQuery.each(items, function (hash, data) {
                    var $qty = data.quantity;
                    var $original_price = data.original_price;

                    // var is_on_wdp_sale = data.is_on_wdp_sale;
                    var is_on_wdp_sale = Object.keys(data.history).length > 0;

                    var $history = '';
                    if (is_on_wdp_sale) {
                        if (data.is_wdp_gifted) {
                            var $rule_id = parseInt(Object.keys(data.history)[0]);
                            $history += wdp_reporter.template_manager.get_template('gifted_history', {
                                'rule_id': $rule_id,
                                'is_replaced': data.replacements.indexOf($rule_id) > -1 ? user_report_data.classes.replaced_by_coupon : '',
                            });
                        } else {
                            jQuery.each(data.history, function (_, history_chunk) {
                                if (typeof history_chunk.rule_id === 'undefined' || typeof history_chunk.amount === 'undefined') {
                                    return;
                                }

                                var $rule_id = parseInt(history_chunk.rule_id);
                                var $amount = parseFloat(history_chunk.amount);

                                var replaced_by = '';
                                if (data.replacements.indexOf($rule_id) > -1) {
                                    if ($amount > 0) {
                                        replaced_by = user_report_data.classes.replaced_by_coupon;
                                    } else if ($amount < 0) {
                                        replaced_by = user_report_data.classes.replaced_by_fee;
                                    }
                                }

                                $history += wdp_reporter.template_manager.get_template('history_chunk',
                                    {
                                        'rule_id': $rule_id,
                                        'old_price': wdp_reporter.format_price($original_price),
                                        'amount': wdp_reporter.format_difference((-1) * $amount),
                                        'new_price': wdp_reporter.format_price($original_price - $amount),
                                        'is_replaced': replaced_by,
                                    }
                                );
                                $original_price -= $amount;
                            });
                        }
                    } else {
                        $history += wdp_reporter.template_manager.get_template('empty_history', {});
                    }


                    $items_tab_content += wdp_reporter.template_manager.get_template('single_item', {
                        'hash': hash,
                        'index': $index++,
                        'quantity': $qty,
                        'title': data.title,
                        'original_price': wdp_reporter.format_price(data.original_price),
                        'price': wdp_reporter.format_price(data.price),
                        'history': $history
                    });
                });

                return wdp_reporter.template_manager.get_template('cart_items', {'items': $items_tab_content});
            },

            get_selector_html: function (selected) {
                return wdp_reporter.template_manager.get_template('tab_link', {
                    'selected': selected ? 'selected' : '',
                    'tab_key': this.key,
                    'tab_label': this.label
                });
            },

            get_content_html: function (data) {
                return wdp_reporter.template_manager.get_template('tab', {
                    'tab_key': this.key,

                    'active': 'active',

                    'sub_tabs_selector_html': '',
                    'sub_tabs_selector_class': 'hide',

                    'tab_content_html': this.get_items_html(data.items),
                });
            },
        },

        tab_coupons: {
            key: 'coupons',
            label: user_report_data.i.coupons,

            is_show: function (data) {
                return Object.keys(data.coupons).length > 0;
            },

            get_coupons_html: function (coupons) {
                var $cart_coupons_tab_content = '';
                var $index = 1;

                jQuery.each(coupons, function (hash, data) {
                    var $rules = '';

                    jQuery.each(data.rules, function ($rule_id, $amount) {
                        $rules += wdp_reporter.template_manager.get_template('cart_adj_history_chunk', {
                            'rule_id': $rule_id,
                            'amount': wdp_reporter.format_price($amount)
                        });
                    });

                    $cart_coupons_tab_content += wdp_reporter.template_manager.get_template('cart_coupon', {
                        'index': $index++,
                        'coupon_code': data.code,
                        'coupon_amount': data.amount,
                        'affected_rules': $rules,
                    });
                });

                return wdp_reporter.template_manager.get_template('cart_coupons', {
                    'coupons': $cart_coupons_tab_content
                });
            },

            get_selector_html: function (selected) {
                return wdp_reporter.template_manager.get_template('tab_link', {
                    'selected': selected ? 'selected' : '',
                    'tab_key': this.key,
                    'tab_label': this.label
                });
            },

            get_content_html: function (data) {
                return wdp_reporter.template_manager.get_template('tab', {
                    'tab_key': this.key,

                    'active': '',

                    'sub_tabs_selector_html': '',
                    'sub_tabs_selector_class': 'hide',

                    'tab_content_html': this.get_coupons_html(data.coupons),
                });
            },
        },

        tab_fees: {
            key: 'fees',
            label: user_report_data.i.fees,

            is_show: function (data) {
                return Object.keys(data.fees).length > 0;
            },

            get_fees_html: function (fees) {
                var $cart_fees_tab_content = '';
                var $index = 1;

                jQuery.each(fees, function (hash, data) {
                    var $rules = '';

                    jQuery.each(data.rules, function ($rule_id, $amount) {
                        $rules += wdp_reporter.template_manager.get_template('cart_adj_history_chunk', {
                            'rule_id': $rule_id,
                            'amount': wdp_reporter.format_price($amount)
                        });
                    });

                    $cart_fees_tab_content += wdp_reporter.template_manager.get_template('cart_fee', {
                        'index': $index++,
                        'fee_id': data.id,
                        'fee_name': data.name,
                        'fee_amount': data.amount,
                        'affected_rules': $rules,
                    });
                });

                return wdp_reporter.template_manager.get_template('cart_fees', {
                    'fees': $cart_fees_tab_content
                });
            },

            get_selector_html: function (selected) {
                return wdp_reporter.template_manager.get_template('tab_link', {
                    'selected': selected ? 'selected' : '',
                    'tab_key': this.key,
                    'tab_label': this.label
                });
            },

            get_content_html: function (data) {
                return wdp_reporter.template_manager.get_template('tab', {
                    'tab_key': this.key,

                    'active': '',

                    'sub_tabs_selector_html': '',
                    'sub_tabs_selector_class': 'hide',

                    'tab_content_html': this.get_fees_html(data.fees),
                });
            },
        },

        tab_shipping: {
            key: 'shipping',
            label: user_report_data.i.shipping,

            is_show: function (data) {
                var al_least_one_rate_exists = false;
                for (var package_title in data.shipping_packages) {
                    if ( data.shipping_packages.hasOwnProperty(package_title) ) {
                        al_least_one_rate_exists = Object.keys(data.shipping_packages[package_title]).length > 0;

                        if ( al_least_one_rate_exists ) {
                            break;
                        }
                    }
                }

                return al_least_one_rate_exists;
            },

            get_shipping_rates_html: function (shipping_packages) {
                var $shipping_packages_html = '';
                var _this = this;

                jQuery.each(shipping_packages, function (package_title, shipping_rates) {
                    $shipping_packages_html += _this.get_single_package_html(package_title, shipping_rates);
                });

                return wdp_reporter.template_manager.get_template('cart_shipping', {
                    'shipping_packages': $shipping_packages_html
                });
            },

            get_single_package_html: function(package_title, shipping_rates) {
                var $shipping_rates_html = '';
                var $index = 1;

                jQuery.each(shipping_rates, function (instance_id, data) {
                    var $rules = '';

                    if ( data.is_wdp_free ) {
                        var $rule_id = parseInt(Object.keys(data.rules)[0]);
                        $rules += wdp_reporter.template_manager.get_template('free_wdp_shipping_rate', {
                            'rule_id': $rule_id,
                        });
                    } else {
                        var $original_price = data.original_cost;
                        jQuery.each(data.rules, function ($rule_id, $amount) {
                            $rules += wdp_reporter.template_manager.get_template('history_chunk',
                                {
                                    'rule_id': $rule_id,
                                    'old_price': wdp_reporter.format_price($original_price),
                                    'amount': wdp_reporter.format_difference((-1) * $amount),
                                    'new_price': wdp_reporter.format_price($original_price - $amount),
                                    'is_replaced': '',
                                }
                            );

                            $original_price -= $amount;
                        });
                    }

                    $shipping_rates_html += wdp_reporter.template_manager.get_template('shipping_rate', {
                        'index': $index++,
                        'instance_id': instance_id,
                        'label': data.label,
                        'initial_cost': data.original_cost,
                        'cost': data.cost,
                        'affected_rules': $rules,
                    });
                });

                return wdp_reporter.template_manager.get_template('cart_shipping_package', {
                    'package_title': package_title,
                    'shipping_rates': $shipping_rates_html
                });
            },

            get_selector_html: function (selected) {
                return wdp_reporter.template_manager.get_template('tab_link', {
                    'selected': selected ? 'selected' : '',
                    'tab_key': this.key,
                    'tab_label': this.label
                });
            },

            get_content_html: function (data) {
                return wdp_reporter.template_manager.get_template('tab', {
                    'tab_key': this.key,

                    'active': '',

                    'sub_tabs_selector_html': '',
                    'sub_tabs_selector_class': 'hide',

                    'tab_content_html': this.get_shipping_rates_html(data.shipping_packages),
                });
            },
        },

        fill: function (data) {
            var $sub_tabs_selector_html = '';
            var $tab_content_html = '';
            var $all_tabs_empty = true;

            var $tab_cart_sub_tabs = [this.tab_items, this.tab_coupons, this.tab_fees, this.tab_shipping];
            $tab_cart_sub_tabs.forEach(function (sub_tab) {
                if (sub_tab.is_show(data)) {
                    $all_tabs_empty = false;

                    var selected = !$sub_tabs_selector_html;
                    $sub_tabs_selector_html += sub_tab.get_selector_html(selected);
                    $tab_content_html += sub_tab.get_content_html(data);
                }
            });

            if ($all_tabs_empty) {
                $tab_content_html = wdp_reporter.template_manager.get_template('cart_is_empty', {});
            }

            var $tab_cart_content = wdp_reporter.template_manager.get_template('tab', {
                'tab_key': 'cart',

                'active': 'active',

                'sub_tabs_selector_html': $sub_tabs_selector_html,
                'sub_tabs_selector_class': '',

                'tab_content_html': $tab_content_html,
            });

            jQuery('#wdp-report-tab-window').append($tab_cart_content);

        }

    },

    tab_get_report: {
        key: 'reports',
        label: user_report_data.i.get_system_report,

        fill: function() {
            var $tab_content_html = wdp_reporter.template_manager.get_template('export_buttons', {
                'import_key': wdp_reporter.import_key,
            });

            var $tab_reports_content = wdp_reporter.template_manager.get_template('tab', {
                'tab_key': this.key,

                'active': '',

                'sub_tabs_selector_html': '',
                'sub_tabs_selector_class': '',

                'tab_content_html': $tab_content_html,
            });

            jQuery('#wdp-report-tab-window').append($tab_reports_content);
            this.set_button_handlers();
        },

        set_button_handlers: function () {
            jQuery('#wdp-report-tab-window #export_all').on('click', function (event) {
                var src = wdp_reporter.ajaxurl + (wdp_reporter.ajaxurl.indexOf('?') === -1 ? '?' : '&') + 'action=download_report&import_key=' + wdp_reporter.import_key + '&reports=all';
                jQuery('#wdp_export_new_window_frame').attr("src", src);
            });
        },
    },

    init: function () {
        wdp_reporter.container = jQuery('#wdp-report-window');
 
        /** Resize handle */
        var maxheight = (jQuery(window).height() - wdp_reporter.container.outerHeight());
        var startY, startX, resizerHeight;

        jQuery(document).on('mousedown', '#wdp-report-resizer', function (event) {
            resizerHeight = jQuery(this).outerHeight() - 1;
            startY = wdp_reporter.container.outerHeight() + event.clientY;
            startX = wdp_reporter.container.outerWidth() + event.clientX;

            jQuery(document).on('mousemove', do_resizer_drag);
            jQuery(document).on('mouseup', stop_resizer_drag);
        });

        function do_resizer_drag(event) {
            var h = (startY - event.clientY);
            if (h >= resizerHeight && h <= maxheight) {
                wdp_reporter.container.height(h);
            }
        }

        function stop_resizer_drag(event) {
            jQuery(document).off('mousemove', do_resizer_drag);
            jQuery(document).off('mouseup', stop_resizer_drag);
        }

        /** Close handle */
        wdp_reporter.container.on('click', '#wdp-report-window-close .dashicons', function (event) {
            wdp_reporter.container.hide();
        });

        /** Open handle */
        jQuery('#wp-toolbar').find('.wdp-report-visibility-control').click(function (e) {
            wdp_reporter.container.show();
        });
    },

};


jQuery(document).ready(function ($) {
    jQuery('#wdp-report-main-window .tab-content:first').addClass('active');

    rule_tooltip.init();
    wdp_reporter.init();
    wdp_reporter.update();

    var params = window.location.search;
    if(params !== "" && params.indexOf('wdp_debug_kill=1') > 0) {
        //jQuery('#export_all').trigger('click');
        var src = wdp_reporter.ajaxurl + (wdp_reporter.ajaxurl.indexOf('?') === -1 ? '?' : '&') + 'action=download_report&import_key=' + wdp_reporter.import_key + '&reports=all';
        jQuery('#wdp_export_new_window_frame').attr("src", src);
        
        setTimeout('window.close()',5000);
    }

    jQuery(document).on('click', '#wdp-report-window .tab-links-list .tab-link', function (e) {
        var $tab_key = jQuery(this).data('tab-id');

        jQuery(this).siblings('.selected').removeClass('selected');
        jQuery(this).addClass('selected');

        jQuery('#wdp-report-' + $tab_key + '-tab').siblings('.active').removeClass('active');
        jQuery('#wdp-report-' + $tab_key + '-tab').addClass('active');
    });

    jQuery(document).on('click', '#wdp-report-window-refresh button', function (e) {
         wdp_reporter.update();
         $('#wdp-report-main-tab-selector').children().removeClass('selected');
         $('#wdp-report-main-tab-selector').find('[data-tab-id=cart]').addClass('selected');
    });

});