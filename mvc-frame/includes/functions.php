<?php
/**
 * Global helper functions used throughout the application.
 *
 * @package    mvc-frame
 * @license    http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author     Jordan R McLean <jordmclean@icloud.com>
 */


// all global helper functions
//
// generate_pagination(total, current, url) - creates HTML for pagination.
// url(url, query_params) - parse the query params into the URL, replacing if already existing.
// build_ordering(template, url, ordering fields) - on a page where rows are displayed, get all necessary ordering info
// create_dropdown_options(current, assoc array) - create html for options in  a dropdown




// generate nice pagination HTML.
// $total = total pages, $curpage = current page
// $url = the url in which to integrate page number into and use for the links
function generate_pagination($total, $curpage, $url) {
	$last_page = $total;
	$first_page = 1;
	$next_page = $curpage + 1;
	$prev_page = $curpage - 1;

	//only 1 page so return nothing.
	if($total < 2) {
		return '';
	}

	//sets the points of which number to show. For example if on page 23 we want to display 22 & 24.
	$low_point = $prev_page - 1 > 2 ? $prev_page - 1 : 0;
	$high_point = $next_page < ($total - 2) ? $next_page + 1 : 0;

	//The pages that will not be displayed, above and below the current page.
	$hidden_area1 = $low_point ? range(3, $prev_page - 1 ) : array();
	$hidden_area2 = $high_point ? range($high_point, $total - 2) : array();
	$hidden_area = array_merge($hidden_area1, $hidden_area2);

	//start to build the html.
	$html = '';

	// foreach page we decide wether we add it or whether it is hidden.
	for($i = 1; $i < $total + 1; $i++) {
		$selected = $i === $curpage ? ' current' : '';
		$html .= in_array($i, $hidden_area) ?
					'{HIDDEN}'
					: '<span class="page' . $selected . '"><a href="' . url($url, ['p' => $i]) . '">' . $i . '</a></span>';
	}

	//replace all the hidden pages
	$html = preg_replace('#(\{HIDDEN\})+#', '<span class="hidden_pages">...</span>', $html);

	//add first and last page?
	if($curpage != 1) {
		$html = '<span class="page"><a href="'. sprintf($url, 1) .'">First</a></span>' . $html;
	}

	if($curpage != $total) {
		$html .= '<span class="page"><a href="' . sprintf($url, $total) . '">Last</a></span>';
	}

	return '<div class="pagination">' . $html . '</div>';
}

//create a URL with a query string.
//easy url management. Adds the query params or overwrites them if they already exist.
//very handy when dealing with "order direction/asc/order by/page etc" type query strings.
function url($url, $params = false)
{
	if(!$params) {
		return $url;
	}

	//turn params into an array if not already.
	if( is_string($params) ) {
		$new_params = array();
		parse_str($params, $new_params);
		$params = $new_params;
	}

	if(empty($params)) {
		return $url;
	}

	$current_query = parse_url($url, PHP_URL_QUERY); //the current query string.
	$query = array();
	parse_str($current_query, $query); //parse the current query into an array.

	foreach($params as $param => $new_value)
	{
		$query[$param] = $new_value;
	}

	$new_query = http_build_query($query, '', '&');

	return empty($current_query) ? $url . '?' . $new_query : str_replace($current_query, $new_query, $url);
}

//build the ordering data of a page with order-able rows displayed.
//Whenever displaying orderable rows the same logic is followed every time.
//So this will tackle all the ordering, search query, and page number and URLs and template.
//$url = the url in which to incorporate the ordering query params.
//$ordering = an assoc array (query name => db field) of which fields to accept for ordering rows.
function build_ordering(&$template, $url, $ordering, $default_dir = 'asc') {

	//set our expectations
	//default_dir is set at function call because can be very variable depending on what dealing with.
	$order_by_values = array_keys($ordering);
	$directions = [
		'a' => 'ASC', 'A' => 'ASC', 'asc' => 'ASC', 'ASC' => 'ASC',
		'd'	=> 'DESC', 'D' => 'DESC', 'desc' => 'DESC', 'DESC' => 'DESC'
	];
	$order_by_default = $order_by_values[0]; //use the first as the default
	$order_dir_default = $directions[$default_dir];

	//now get all the query params that affect our view; page, order field, order direction, search value.
	$page = get_var('p', 1); //page number
	$order_by = get_var('ob', $order_by_default); //order by sort.
	$order_dir = get_var('dir', $order_dir_default); //ordering direction
	$search = get_var('search', '');
	$url_array = []; //array to build our urls off of.

	//ensure order_by and order_dir are valid.
	$order_by = in_array($order_by, $order_by_values) ? $order_by : $order_by_default;
	$order_dir = in_array($order_dir, array_keys($directions)) ? $directions[$order_dir] : $default_dir;

	//now go through each and add them to the url array if they have been specifiedd.
	//if the default then no need to add them to the url.
	if( $page !== 1 ) {
		$url_array['p'] = $page;
	}

	if( !empty($search) ) {
		$url_array['search'] = $search;
	}

	if( $order_by !== $order_by_default ) {
		$url_array['ob'] = $order_by;
	}

	if( $order_dir !== $order_dir_default ) {
		$url_array['dir'] = $order_dir;
	}

	//create the current URL with the sorted parameters.
	$this_url = url($url, $url_array);

	//makes the URLs for the other sorting options.
	foreach($ordering as $order_val => $order_field)
	{
		$col_url = array('ob' => $order_val);

		//if this is the current field we're ordering by then switch the dir.
		if($order_val === $order_by) {
			//if this is the current ordering, then add the opposite direction.
			$col_url['dir'] = $order_dir === 'ASC' ? 'desc' : 'asc';

			//add an icon to show we are ordering by this col.
			$template->set(strtoupper($order_val).'_COL_DIR', $order_dir === 'ASC' ? 'sort-amount-down' : 'sort-amount-up');
		}

		//add the URL to the template to order by the column
		$template->set(strtoupper($order_val).'_COL_URL', url($this_url, $col_url));
	}

	return array(
		'by'		=> $order_by,
		'field'		=> $ordering[$order_by],
		'dir'		=> $order_dir,
		'url'		=> $this_url,
		'search'	=> empty($search) ? false : $search,
		'page'		=> $page
	);
}


function create_dropdown_options($current_val, $options, $value_key = null, $content_key = null) {
	$html = '';

	//options can either be an assoc array value => content
	//or a numerical array (ie from db) and specify the keys to use

	foreach($options as $value => $content) {

		if($value_key && $content_key) {
			$value = $content[$value_key];
			$content = $content[$content_key];
		}

		$html .= '<option value="' . $value . ($value == $current_val ? '" selected="selected' : '') . '">' . $content . '</option>';
	}

	return $html;
}
