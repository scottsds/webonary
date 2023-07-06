<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */
/** @noinspection PhpMissingReturnTypeInspection */
/** @noinspection PhpComposerExtensionStubsInspection */
/** @noinspection HtmlUnknownTarget */
/** @noinspection CssUnusedSymbol */

/**
 * @param array $atts
 * @param $content
 * @param $shortcode_tag
 *
 * @return string
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpUnusedParameterInspection
 */
function categories_func($atts, $content, $shortcode_tag)
{
	global $wp_query;

	Webonary_SemanticDomains::GetRoots();

	$display = '<div id="domRoot"></div>' . PHP_EOL;

	$postsPerPage = Webonary_Utility::getPostsPerPage();

	$qTransLang = Webonary_Cloud::getCurrentLanguage();

	if(!file_exists($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/sil-dictionary-webonary/js/categoryNodes_' . $qTransLang . '.js'))
		$qTransLang = 'en';

	if (IS_CLOUD_BACKEND)
	{
		Webonary_Cloud::registerAndEnqueueMainStyles();
	}
	else
	{
		$upload_dir = wp_upload_dir();
		wp_register_style('configured_stylesheet', $upload_dir['baseurl'] . '/imported-with-xhtml.css?time=' . date("U"));
		wp_enqueue_style( 'configured_stylesheet');
	}

	$blog_url = get_bloginfo('wpurl');

	wp_register_script('webonary_ua_script', $blog_url . '/wp-content/plugins/sil-dictionary-webonary/js/ua.js', [], false, true);
	wp_enqueue_script('webonary_ua_script');

	wp_register_script('webonary_ftiens_script', $blog_url . '/wp-content/plugins/sil-dictionary-webonary/js/ftiens4.js', [], false, true);
	wp_enqueue_script('webonary_ftiens_script');

	$selected_domain_key = get_query_var('semnumber');
	$js = Webonary_SemanticDomains::GetJavaScript($qTransLang, $selected_domain_key);
	wp_add_inline_script('webonary_ftiens_script', $js);

	$pagenr = Webonary_Utility::getPageNumber();

	$semdomain = trim((string)filter_input(INPUT_GET, 'semdomain', FILTER_UNSAFE_RAW));
	$semnumber = trim((string)filter_input(INPUT_GET, 'semnumber', FILTER_UNSAFE_RAW));
	$semnumber_internal = rtrim(str_replace(".", "-",$semnumber), "-");
	$arrPosts = null;

	$display .= '<div id="searchresults" class="semantic-domain">';
	if($semnumber != '')
	{
		if ($semdomain == '')
			$semdomain = Webonary_SemanticDomains::GetDomainName($semnumber, $qTransLang);

		if(IS_CLOUD_BACKEND)
		{
			$apiParams = array(
				'text' => $semdomain,
				'lang' => $qTransLang,
				'semDomAbbrev' => rtrim($semnumber, '.'),
				'searchSemDoms' => '1',
				'pageNumber' => $pagenr,
				'pageLimit' => $postsPerPage
			);
			$totalEntries = $_GET['totalEntries'] ?? Webonary_Cloud::getTotalCount(Webonary_Cloud::$doSearchEntry, $apiParams);
			$arrPosts = Webonary_Cloud::getEntriesAsPosts(Webonary_Cloud::$doSearchEntry, $apiParams);
		}
		else
		{
			$arrPosts = query_posts("semdomain=" . $semdomain . "&semnumber=" . $semnumber_internal . "&posts_per_page=" . $postsPerPage . "&paged=" . $pagenr);
			$totalEntries = $_GET['totalEntries'] ?? $wp_query->found_posts;
		}
	}
	else
	{
		$totalEntries = 0;
	}

	if(!$arrPosts)
	{
		if($semdomain != '')
		{
			$display .= __('No entries exist for', 'sil_dictionary') . ' "' . $semdomain . '"';
		}
	}
	else
	{
		$display .= "<h3>" . $semnumber  . " " . $semdomain . "</h3>";
		foreach($arrPosts as $mypost)
		{
				$display .= "<div class=post>" . $mypost->post_content . "</div>";
		}
	}

	$display .= displayPageNumbers($semnumber, $totalEntries, $postsPerPage,  $semdomain , "semnumber", $pagenr);
	$display .= "</div>";

 	wp_reset_query();
	return $display;

}
add_shortcode( 'categories', 'categories_func' );

/**
 * @param $alphas
 * @param string $languageCode
 * @param boolean $rtl
 *
 * @return string
 */
function displayAlphabet($alphas, $languageCode, $rtl)
{
	global $wpdb;

	$dir_class = $rtl ? 'rtl' : 'ltr';

	if(trim($alphas[0]) == "" && is_front_page())
		return '';

	$permalink = '';
	if(is_front_page())
	{
		/** @noinspection SqlResolve */
		$sql = "SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[vernacularalphabet]%'";
		$post_id = $wpdb->get_var($sql);

		$permalink = get_permalink($post_id);
	}

	/** @noinspection HtmlUnknownTarget */
	$template = <<<HTML
<div class="lpTitleLetterCell">
	<span>
	<a class="%s" href="%s">%s</a>
	</span>
</div>
HTML;

	if (empty($alphas) || trim($alphas[0]) == '') {
		$content = sprintf($template, 'not-configured', get_site_url() . '/wp-admin/admin.php?page=webonary#browse', 'Alphabet not configured');
	}
	else {
		$content = '';

		$permalink = explode('?', $permalink)[0];

		$query_vars = $_GET ?? [];
		$query_vars['key'] = $languageCode;

		// remove page numbers from the new link, we always want to show the first page
		if (isset($query_vars['pagenr']))
			unset($query_vars['pagenr']);

		if (isset($query_vars['totalEntries']))
			unset($query_vars['totalEntries']);

		foreach($alphas as $letter) {

			$query_vars['letter'] = $letter;
			$content .= sprintf($template, 'lpTitleLetter', $permalink . '?' . http_build_query($query_vars), stripslashes($letter));
		}
	}

	$font_family = get_option('vernacularLettersFont') ?: 'inherit';

	return <<<HTML
<style>
	#chosenLetterHead, .lpTitleLetter {font-family: $font_family}
</style>
<br>
<div style="text-align:center;">
	<div style="display:inline-block;" class="$dir_class">
		$content
	</div>
</div>
<div style=clear:both></div>
HTML;
}

function displayPageNumbers($chosenLetter, $totalEntries, $entriesPerPage, $languageCode, $requestName = null, $currentPage = null)
{
	if(!isset($requestName))
		$requestName = 'letter';

	if(!$currentPage)
		$currentPage = Webonary_Utility::getPageNumber();

	$totalPages = ceil($totalEntries / $entriesPerPage);
	if(($totalEntries / $entriesPerPage) > $totalPages)
		$totalPages++;

	if ($totalPages <= 1)
		return '';


	$next_page = '&gt;';
	$prev_page = '&lt;';

	$url = '?' . $requestName . '=' . urlencode($chosenLetter) . '&key=' . $languageCode . '&totalEntries=' . $totalEntries;
	if(isset($_GET['lang']))
		$url .= '&lang=' . $_GET['lang'];


	$items = [];
	$limit_pages = 10;
	$items[] = '<li class="page_info">' . gettext('Page') . ' ' . $currentPage . ' ' . gettext('of') . ' ' . $totalPages . '</li>';



	if($currentPage > 1)
	{
		if($requestName == 'semnumber')
			$items[] = '<li><a href="?semdomain=' . $languageCode . '&semnumber=' . $chosenLetter . '&pagenr=' . ($currentPage - 1) . '">' . $prev_page . '</a></li>';
		else
			$items[] = '<li><a href="' . $url . '&pagenr=' . ($currentPage - 1) . '">' .$prev_page . '</a></li>';
	}

	$start = 1;
	if($currentPage > ($limit_pages - 5))
	{
		if($requestName == 'semnumber')
			$items[] = '<li><a href="?semdomain=' . $languageCode . '&semnumber=' . $chosenLetter . '&pagenr=1">1</a></li>';
		else
			$items[] = '<li><a href="' . $url . '&pagenr=1">1</a></li>';

		$items[] = '<li class="space">...</li>';
		$start = $currentPage - 5;
		if($currentPage == 6)
			$start = 2;
	}

	for($page = $start; $page <= $totalPages; $page++)
	{
		$class = '';
		if($currentPage == $page || ($page == 1 && !isset($currentPage)))
			$class='class="active_page"';

		if($requestName == 'semnumber')
			$items[] = "<li $class ><a href=\"?semdomain=$languageCode&semnumber=$chosenLetter&pagenr=$page\"></a></li>";
		else
			$items[] = "<li $class><a href=\"$url&pagenr=$page\">$page</a></li>";

		$minusPages = 5;
		if($currentPage < 5)
			$minusPages = $currentPage;

		if(($currentPage + $limit_pages - $minusPages) == $page && ($currentPage + $limit_pages) < $totalPages)
		{
			$items[] = '<li class="space">...</li>';
			$items[] = "<li $class><a href=\"$url&pagenr=$totalPages\">$totalPages</a></li>";
			break;
		}
	}

	if( $currentPage != '' && $currentPage < $totalPages)
	{
		$next_page_num = $currentPage + 1;
		if($requestName == 'semnumber')
			$items[] = "<li><a href=\"?semdomain=$languageCode&semnumber=$chosenLetter&pagenr=$next_page_num\"></a></li>";
		else
			$items[] = "<li><a href=\" $url&pagenr=$next_page_num\">$next_page</a></li>";
	}

	$item_str = implode(PHP_EOL, $items);

	return <<<HTML
<div style="text-align:center;">
<div style="display:inline-block;">
	<div id="wp_page_numbers">
		<ul>
$item_str
		</ul>
	</div>
</div>
</div>
HTML;
}

/** @noinspection PhpUnused */
function english_alphabet_func($atts, $content, $tag)
{
	if(strlen(trim(get_option('reversal1_alphabet'))) == 0)
	{
		$languagecode = "en";

		if(isset($_GET['letter']))
		{
			$chosenLetter = filter_input(INPUT_GET, 'letter', FILTER_UNSAFE_RAW, ['options' => ['default' => '']]);
		}
		else {
			$chosenLetter = "a";
		}

		$alphas = range('a', 'z');
		$display = displayAlphabet($alphas, $languagecode, false);

		$display = reversalindex($display, $chosenLetter, $languagecode);
	}
	else
	{
		$display = reversalalphabet_func(null, "", "reversalindex1");
	}

 return $display;
}

add_shortcode('englishalphabet', 'english_alphabet_func');

/**
 * @return bool
 */
function get_has_reversal_browseletters()
{
	global $wpdb;

	$result = $wpdb->get_results("SHOW COLUMNS FROM " . REVERSALTABLE . " LIKE 'browseletter'");

	return count($result) > 0;
}


/**
 * Returns a MySQL LIMIT statement
 *
 * @param int|null $page
 * @param int|null $postsPerPage
 *
 * @return string
 */
function getLimitSql($page=null, $postsPerPage=null)
{
	if (is_null($page))
		$page = Webonary_Utility::getPageNumber();

	if (is_null($postsPerPage))
		$postsPerPage = Webonary_Utility::getPostsPerPage();

	$startFrom = ($page > 1) ? (($page - 1) * $postsPerPage) : 0;
	return " LIMIT $postsPerPage OFFSET $startFrom";
}

/**
 * @param string $letter
 * @param int $page
 * @param string $reversalLangcode
 * @param bool $displayXHTML
 * @param string $reversalnr
 * @param int|null $postsPerPage
 *
 * @return array|object|null
 */
function getReversalEntries($letter, $page, $reversalLangcode, &$displayXHTML, $reversalnr, $postsPerPage = null)
{
	if(strlen($reversalLangcode) === 0 && $reversalnr > 0)
	{
		return null;
	}

	global $wpdb;

	$postsPerPage = $postsPerPage ?? Webonary_Utility::getPostsPerPage();
	$limitSql = getLimitSql($page, $postsPerPage);

	$result = $wpdb->get_var('SHOW COLUMNS FROM '. REVERSALTABLE . ' LIKE \'sortorder\'');
	$sortorderExists = !is_null($result);

	$alphabet = str_replace(',', '', get_option('reversal' . $reversalnr . '_alphabet'));
	$collate = 'COLLATE ' . MYSQL_CHARSET . '_BIN';

	$sql = 'SELECT SQL_CALC_FOUND_ROWS reversal_content ' .
	' FROM ' . REVERSALTABLE  .
	' WHERE 1 = 1 ';
	if($letter != '')
	{
		//new imports use the letter header from FLEx for grouping
		if(get_has_reversal_browseletters())
		{
			$sql .= " AND browseletter =  '" . $letter . "' " . $collate;
		}
		else
		{
			if((!preg_match('/[^a-z]/', $alphabet)))
			{
				$collate = "";
			}

			$sql .= " AND reversal_head LIKE  '" . $letter . "%' " . $collate;
		}
	}
	if(strlen($reversalLangcode) > 0)
	{
		$sql .=	" AND language_code = '" . $reversalLangcode . "' ";
	}
	if($sortorderExists && !Webonary_Configuration::use_pinyin($reversalLangcode))
	{
		$sql .= " ORDER BY sortorder, reversal_head ASC";
	}
	else
	{
		$sql .= " ORDER BY reversal_head ASC";
	}
	$sql .= $limitSql;

	$arrReversals = $wpdb->get_results($sql);

	if(count($arrReversals) == 0)
	{
		//just get headwords (legacy code, as we didn't use to display the xhtml for reversals)
		$sql = "SELECT SQL_CALC_FOUND_ROWS a.post_id, a.search_strings AS English, b.search_strings AS Vernacular " .
		" FROM " . SEARCHTABLE . " a ";
		$sql .= " INNER JOIN " . SEARCHTABLE. " b ON a.post_id = b.post_id "; // AND a.subid = b.subid " .
		//$sql .= " INNER JOIN " . SEARCHTABLE. " b ON a.post_name = b.post_name ";
		if(strlen($reversalLangcode) > 0)
		{
			$sql .= " AND a.language_code =  '" . $reversalLangcode . "' " .
			" AND b.language_code = '" . get_option('languagecode') . "' ";
		}
		$sql .= " AND a.relevance >=95 AND b.relevance >= 95 " .
		" AND a.search_strings LIKE  '" . $letter . "%' " . $collate .
		" GROUP BY a.post_id, a.search_strings " .
		" ORDER BY a.search_strings ";

		$sql .= $limitSql;

		$arrReversals = $wpdb->get_results($sql);
		if(count($arrReversals) > 0)
		{
			$displayXHTML = false;
		}

	}

	return $arrReversals;
}

add_shortcode( 'reversalindex1', 'reversalalphabet_func' );
add_shortcode( 'reversalindex2', 'reversalalphabet_func' );
add_shortcode( 'reversalindex3', 'reversalalphabet_func' );
add_shortcode( 'reversalindex4', 'reversalalphabet_func' );

function reversalalphabet_func($atts, $content, $tag)
{
	if($tag == "reversalindex2")
	{
		$reversalnr = 2;
	}
	else if($tag == "reversalindex3")
	{
		$reversalnr = 3;
	}
	else if($tag == "reversalindex4")
	{
		$reversalnr = 4;
	}
	else
	{
		$reversalnr = 1;
	}

	$alphas = explode(",",  get_option('reversal'. $reversalnr . '_alphabet'));

	if(isset($_GET['letter']))
	{
		$chosenLetter = filter_input(INPUT_GET, 'letter', FILTER_UNSAFE_RAW, ['options' => ['default' => '']]);
	}
	else {
		$chosenLetter = stripslashes($alphas[0]);
	}

	$rtl = get_option('reversal' . $reversalnr . 'RightToLeft') == '1';
	$alphas = explode(",",  get_option('reversal' . $reversalnr . '_alphabet'));
	$display = displayAlphabet($alphas, get_option('reversal' . $reversalnr . '_langcode'), $rtl);

	return reversalindex($display, $chosenLetter, get_option('reversal' . $reversalnr . '_langcode'), $reversalnr);
}

function reversalindex($display, $chosenLetter, $langcode, $reversalnr = "")
{
	global $wpdb;

	$rtl = get_option('reversal' . $reversalnr . 'RightToLeft') == '1';
	$align_class = $rtl ? 'right' : 'left';

	$pagenr = Webonary_Utility::getPageNumber();
	$postsPerPage = Webonary_Utility::getPostsPerPage();
	$displayXHTML = true;

	if(IS_CLOUD_BACKEND)
	{
		Webonary_Cloud::registerAndEnqueueReversalStyles($langcode);

		$apiParams = array(
			'text' => $chosenLetter,
			'lang' => $langcode,
			'entryType' => 'reversalindexentry',
			'pageNumber' => $pagenr,
			'pageLimit' => $postsPerPage
		);

		$totalEntries = $_GET['totalEntries'] ?? Webonary_Cloud::getTotalCount(Webonary_Cloud::$doBrowseByLetter, $apiParams);
		$arrReversals = Webonary_Cloud::getEntriesAsReversals($apiParams);
	}
	else
	{
		$upload_dir = wp_upload_dir();
		//wp_register_style('reversal_stylesheet', '/files/reversal_' . $langcode . '.css?time=' . date("U"));
		$reversalCSSFile = 'reversal_' . $langcode . '.css';
		if(!file_exists($upload_dir['baseurl'] . '/' . $reversalCSSFile))
		{
			$reversalCSSFile = str_replace('-', '_', $reversalCSSFile);
		}

		wp_register_style('reversal_stylesheet', $upload_dir['baseurl'] . '/' . $reversalCSSFile . '?time=' . date("U"));
		wp_enqueue_style( 'reversal_stylesheet');

		$arrReversals = getReversalEntries($chosenLetter, $pagenr, $langcode, $displayXHTML, $reversalnr, $postsPerPage);
		$totalEntries = $_GET['totalEntries'] ?? $wpdb->get_var('SELECT FOUND_ROWS()');
	}

	if($arrReversals == null)
	{
		$display .= "No reversal entries imported.";
		return $display;
	}

	$display .= '<h1 class="center">' . $chosenLetter . '</h1><br>';

	if($displayXHTML)
	{
		$display .=  sprintf('<div id="searchresults" class="%s">', $align_class);
	}
	else
	{
		$display .=  sprintf('<div id="searchresults" class="reversal-results%s">', $reversalnr);
	}

	$background = "even";
	$count = 0;
	$previousEnglishWord = '';
	foreach($arrReversals as $reversal)
	{
		if($displayXHTML)
		{
			$display .= "<div class=post>" . $reversal->reversal_content . "</div>";
		}
		else
		{
			$display .=  "<div id=searchresult class=" . $background . " style=\"clear:both;\">";

			$display .=  "<div id=englishcol>";
			if($reversal->English != $previousEnglishWord)
			{
				$display .=  $reversal->English;
				$previousEnglishWord = $reversal->English;
			}
			$display .=  "</div>";

			$url = "?p=" . trim($reversal->post_id);
			$display .=  "<div id=vernacularcol><a href=\"" . get_bloginfo('wpurl') . "/" . $url  . "\">" . $reversal->Vernacular . "</a></div>";
			$display .=  "</div>";

			if($background == "even")
			{
				$background = "odd";
			}
			else
			{
				$background = "even";
			}
		}
		$count++;
		if($count == $postsPerPage)
		{
			break;
		}
	}

	$display .=  "<div style=clear:both></div>";
	$display .= displayPageNumbers($chosenLetter, $totalEntries, $postsPerPage, $langcode);

	$display .=  "</div><br>";

	if(isset($_GET['p']))
	{
		if($_GET['p'] == 0)
		{
			$display = "<p>" . gettext("Entry not found");
		}
	}

	return $display;
}

function getNoLetters($chosenLetter, $alphas)
{
	//if for example somebody searches for "k", but there is also a letter 'kp' in the alphabet then
	//words starting with kp should not appear
	$noLetters = "";
	foreach($alphas as $alpha)
	{
		$alpha = trim($alpha);

		if($chosenLetter != "?")
		{
			if(preg_match("/" . $chosenLetter . "/i", $alpha) && $chosenLetter != stripslashes($alpha) && strtoupper($chosenLetter) != strtoupper($alpha))
			{
				if(strlen($noLetters) > 0)
				{
					$noLetters .= ",";
				}
				$noLetters .= $alpha;
			}
		}
	}
	return $noLetters;
}

/**
 * @param string $letter
 * @param string $langcode
 * @param int $page
 * @param int $postsPerPage
 *
 * @return array|object|null
 */
function getVernacularEntries(string $letter, string $langcode, int $page, int $postsPerPage)
{
	global $wpdb;

	$limitSql = getLimitSql($page, $postsPerPage);

	$collate = "COLLATE " . MYSQL_CHARSET . "_BIN"; //"COLLATE 'UTF8_BIN'";
	if(get_option('IncludeCharactersWithDiacritics') == 1)
	{
		$collate = "";
	}

	/** @noinspection SqlResolve */
	$sql = <<<SQL
SELECT SQL_CALC_FOUND_ROWS ID, post_content
FROM $wpdb->posts
WHERE IFNULL(post_content_filtered, '') <> '' AND post_content_filtered = %s $collate
ORDER BY menu_order
$limitSql
SQL;

	$arrEntries = $wpdb->get_results($wpdb->prepare($sql, $letter));

	//for legacy browse views, where we didn't use the fields post_content_filtered and menu_order
	if(count($arrEntries) == 0)
	{
		$sql = "SELECT SQL_CALC_FOUND_ROWS p.ID, post_title, post_content, search_strings";
		$sql .= " FROM $wpdb->posts AS p";
		$sql .= " JOIN " . SEARCHTABLE . " AS s ON p.ID = s.post_id";
		$sql .= " WHERE LOWER(search_strings) LIKE %s $collate AND language_code = %s AND relevance >= 95";

		$alphas = Webonary_Cloud::filterLetterList(get_option('vernacular_alphabet'));
		$chosenLetter = get_letter($alphas[0]);

		$noLetters = getNoLetters($chosenLetter, $alphas);
		$arrNoLetters = explode(",",  $noLetters);
		foreach($arrNoLetters as $noLetter)
		{
			if(strlen($noLetter) > 0)
			{
				$sql .= " AND search_strings NOT LIKE '" . $noLetter ."%' $collate" .
				" AND search_strings NOT LIKE '" . strtoupper($noLetter) ."%' $collate";
			}
		}

		$sql .= " ORDER BY sortorder ASC, search_strings ASC";
		$sql .= $limitSql;

		$arrEntries = $wpdb->get_results($wpdb->prepare($sql, strtolower($letter) . '%', $langcode));
	}

	return $arrEntries;
}

function getVernacularHeadword($post_id, $language_code)
{
	global $wpdb;

	$sql = "SELECT search_strings " .
	" FROM " . SEARCHTABLE .
	" WHERE post_id = " . $post_id . " AND relevance = 100 AND language_code = '" . $language_code . "'";

	return $wpdb->get_var($sql);

}

function get_letter($firstLetterOfAlphabet = "") {
	if (!isset($_GET['letter'])) {
		return $firstLetterOfAlphabet;
	}
	$chosenLetter = filter_input(INPUT_GET, 'letter', FILTER_UNSAFE_RAW, ['options' => ['default' => '']]);
	// REVIEW: Do we really want to silently fail if this is not true? CP 2017-02
	if (class_exists('Normalizer', false))
	{
		$normalization = Normalizer::FORM_C;
		if(get_option("normalization") == "FORM_D")
		{
			$normalization = Normalizer::FORM_D;
		}
		$chosenLetter = normalizer_normalize($chosenLetter, $normalization);
	}
	return $chosenLetter;
}

function vernacularalphabet_func( $atts )
{
	global $wpdb;

	$rtl = get_option('vernacularRightToLeft') == '1';
	$align_class = $rtl ? 'right' : 'left';

	if (IS_CLOUD_BACKEND)
	{
		Webonary_Cloud::registerAndEnqueueMainStyles();
	}
	else
	{
		$upload_dir = wp_upload_dir();
		wp_register_style('configured_stylesheet', $upload_dir['baseurl'] . '/imported-with-xhtml.css?time=' . date("U"));
		wp_enqueue_style( 'configured_stylesheet');

		if(file_exists($upload_dir['basedir'] . '/ProjectDictionaryOverrides.css'))
		{
			wp_register_style('overrides_stylesheet', $upload_dir['baseurl'] . '/ProjectDictionaryOverrides.css?time=' . date("U"));
			wp_enqueue_style( 'overrides_stylesheet');
		}
	}

	$language_code = get_option('languagecode');

	$alphas = Webonary_Cloud::filterLetterList(get_option('vernacular_alphabet'));

	$chosenLetter = get_letter($alphas[0]);

	$display = displayAlphabet($alphas, $language_code, $rtl);

	//just displaying letters, not entries (for homepage)
	if($atts == 'frontpage')
		return $display;

	$display .= '<div class="center"><h1 id=chosenLetterHead>' . $chosenLetter . '</h1></div><br>';

	if(empty($language_code))
	{
		$display .=  'No language code provided. Please import your dictionary.';
		return $display;
	}

	$displaySubentriesAsMinorEntries = get_option('DisplaySubentriesAsMainEntries') != 'no';

	$pagenr = Webonary_Utility::getPageNumber();
	$postsPerPage = Webonary_Utility::getPostsPerPage();

	if(IS_CLOUD_BACKEND)
	{
		$apiParams = array(
			'text' => $chosenLetter,
			'mainLang' => $language_code,
			'pageNumber' => $pagenr,
			'pageLimit' => $postsPerPage);

		$arrPosts = Webonary_Cloud::getEntriesAsPosts(Webonary_Cloud::$doBrowseByLetter, $apiParams);
		$totalEntries = $_GET['totalEntries'] ?? Webonary_Cloud::getTotalCount(Webonary_Cloud::$doBrowseByLetter, $apiParams);
	}
	else
	{
		$arrPosts = getVernacularEntries($chosenLetter, $language_code, $pagenr, $postsPerPage);
		$totalEntries = $_GET['totalEntries'] ?? $wpdb->get_var('SELECT FOUND_ROWS()');
	}

	if(empty($arrPosts)) {
		$content = __('No entries exist starting with this letter.', 'sil_dictionary');
	}
	else {

		$template = <<<HTML
<div class="entry">
	<span class="headword">%s</span>
	<span class="lpMiniHeading">See main entry:</span> <a href="/?s=%s&partialsearch=1">%s</a>
</div>
HTML;

		$content = '';

		foreach($arrPosts as $my_post)
		{
			//legacy
			if($displaySubentriesAsMinorEntries)
			{
				if(trim($my_post->post_title ?? '') != trim($my_post->search_strings ?? ''))
				{
					$headword = getVernacularHeadword($my_post->ID, $language_code);
					$content .= sprintf($template, $my_post->search_strings, $headword, $headword);
				}
				else
				{
					$the_content = addLangQuery($my_post->post_content);
					$the_content = normalizer_normalize($the_content, Normalizer::NFC );
					$content .= '<div class="post">' . $the_content . '</div>' . PHP_EOL;
				}
			}
			else
			{
				$the_content = addLangQuery($my_post->post_content);
				$the_content = normalizer_normalize($the_content, Normalizer::NFC );
				$content .= '<div class="post">' . $the_content . '</div>' . PHP_EOL;
			}
		}
	}

	$page_nums = displayPageNumbers($chosenLetter, $totalEntries, $postsPerPage, $language_code);

	$html = <<<HTML
$display
<div id="searchresults" class="vernacular-results $align_class">
	$content
</div>
<div class="center">
	<br>
	$page_nums
</div>
<br>
HTML;

 	wp_reset_query();
	return $html;
}

add_shortcode( 'vernacularalphabet', 'vernacularalphabet_func' );
