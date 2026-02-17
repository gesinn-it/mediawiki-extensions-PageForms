/**
 * Defines the applyJStree() function, which turns an HTML "tree" of
 * checkboxes or radiobuttons into a dynamic and collapsible tree of options
 * using the jsTree JS library.
 *
 * @author Mathias Lidal
 * @author Yaron Koren
 * @author Priyanshu Varshney
 * @author Amr El-Absy
 * @author thomas-topway-it (search-input)
 */

 ( function ($, mw, pf) {
	pf.TreeInput = function (elem) {
		this.element = elem;
		// ==== GESINN PATCH BEGIN ====
		// check if element is defined to avoid errors when instantiating the object
		// and check for disabled state of the field
		this.id = this.element ? $(this.element).attr('id') : null;

		this.class = this.element ? $(this.element).attr('class') : '';
		this.isDisabled = this.class.includes('pfTreeInputDisabled');
		// ==== GESINN PATCH END ====
	};

	var TreeInput_proto = new pf.TreeInput();

	TreeInput_proto.setOptions = function () {
		var data = $(this.element).attr('data');
		this.data = JSON.parse(data);
		var params = $(this.element).attr('params');
		this.params = JSON.parse(params);
		this.delimiter = this.params.delimiter;
		this.multiple = this.params.multiple;
		this.values = [];
		this.cur_value = this.params.cur_value;

		var options = {
			'plugins' :  [ 'checkbox' ],
			'core' : {
				'data' : this.data,
				'multiple': this.multiple,
				'themes' : {
					"icons": false
				}
			},
			'checkbox': {
				'three_state': false,
				'cascade': "none"
			}
		};

		if ( 'search-input' in this.params && this.params['search-input'] ) {
			options.plugins.push( 'search' );
		}

		return options;
	};

	TreeInput_proto.handleSearch = function ( tree, jsTree ) {
		var escapedId = tree.id.replace('[','\\[')
			.replace(']','\\]');

		$(`#${escapedId}_searchinput`).keyup(function () {
			var searchString = $(this).val();
			var skip_async = true;
			var show_only_matches = true;
			var inside = null;
			var append = false;
			var show_only_matches_children = false;

			jsTree.jstree('search',
				searchString,
				skip_async,
				show_only_matches,
				inside,
				append,
				show_only_matches_children
			);
		});
	};

	TreeInput_proto.check = function( data ) {
		var $input = $(this.element).next('input.PFTree_data');

		if ( this.multiple ) {
			this.values.push( data );
			var data_string = this.values.join( this.delimiter );
			$input.attr( 'value', data_string );
		} else {
			this.values.push( data );
			$input.attr('value', data);
		}
	};

	TreeInput_proto.uncheck = function( data ) {
		var $input = $( this.element ).next( 'input.PFTree_data' );

		this.values.splice( this.values.indexOf( data ), 1 );
		var data_string = this.values.join( this.delimiter );
		$input.attr( 'value', data_string );
	};

	TreeInput_proto.setCurValue = function () {
		if ( this.cur_value !== null && this.cur_value !== undefined && this.cur_value !== "" ) {
			var $input = $( this.element ).next( 'input.PFTree_data' );

			$input.attr( 'value', this.cur_value );
			this.values = this.cur_value.split( this.delimiter );
		}
	};

	pf.TreeInput.prototype = TreeInput_proto;

} (jQuery, mediaWiki, pf) );

$.fn.extend({
	applyJSTree: function () {
		var tree = new pf.TreeInput(this);
		var options = tree.setOptions();

		var jsTree = $(this).jstree(options);

		tree.handleSearch( tree, jsTree );
		// ==== GESINN PATCH BEGIN ====
		// handle disabled state when the field is disabled in the form definition
		// we need to prevent clicks on checkboxes
        if (tree.isDisabled) {
			$(this).on('before_open.jstree', function(e) {
				e.stopImmediatePropagation();
				return false;
			});
            $(this).on('click.jstree-disabled', '.jstree-anchor, .jstree-checkbox, .jstree-ocl', function(e) {
				e.stopImmediatePropagation();
				e.preventDefault();
				return false;
			});

            $(this).on('select_node.jstree deselect_node.jstree', function(e) {
				e.stopImmediatePropagation();
				$(this).jstree('deselect_all', true);
				return false;
			});
        } else {
            $(this).bind('select_node.jstree', function (evt, data) {
                tree.check(data.node.text);
            });
            $(this).bind('deselect_node.jstree', function (evt, data) {
                tree.uncheck(data.node.text);
            });
        }
		// ==== GESINN PATCH END ====

		tree.setCurValue();
	}
});
