<?php

class pluginPostsbymonths extends Plugin {

	public function init()
	{
		// Fields and default values for the database of this plugin
		$this->dbFields = array(
			'label'=>'Navigation',
			'homeLink'=>true,
			'numberOfItems'=>5
		);
	}

	// Method called on the settings of the plugin on the admin area
	public function form()
	{
		global $L;

		$html  = '<div class="alert alert-primary" role="alert">';
		$html .= $this->description();
		$html .= '</div>';

		$html .= '<div>';
		$html .= '<label>'.$L->get('Label').'</label>';
		$html .= '<input id="jslabel" name="label" type="text" value="'.$this->getValue('label').'">';
		$html .= '<span class="tip">'.$L->get('This title is almost always used in the sidebar of the site').'</span>';
		$html .= '</div>';

		$html .= '<div>';
		$html .= '<label>'.$L->get('Home link').'</label>';
		$html .= '<select name="homeLink">';
		$html .= '<option value="true" '.($this->getValue('homeLink')===true?'selected':'').'>'.$L->get('Enabled').'</option>';
		$html .= '<option value="false" '.($this->getValue('homeLink')===false?'selected':'').'>'.$L->get('Disabled').'</option>';
		$html .= '</select>';
		$html .= '<span class="tip">'.$L->get('Show the home link on the sidebar').'</span>';
		$html .= '</div>';

		$html .= '<div>';
		$html .= '<label>'.$L->get('Amount of items').'</label>';
		$html .= '<input id="jsnumberOfItems" name="numberOfItems" type="text" value="'.$this->getValue('numberOfItems').'">';
		$html .= '</div>';

		return $html;
	}

	// Method called on the sidebar of the website
	public function siteSidebar()
	{
		global $L;
		global $url;
		global $site;
		global $pages;

		// HTML for sidebar
		$html  = '<div class="plugin plugin-navigation">';

		// Print the label if not empty
		$label = $this->getValue('label');
		if (!empty($label)) {
			$html .= '<h2 class="plugin-label">'.$label.'</h2>';
		}

		$html .= '<div class="plugin-content">';
		$html .= '<ul>';

		// Show Home page link
		if ($this->getValue('homeLink')) {
			$html .= '<li>';
			$html .= '<a href="' . $site->url() . '">' . $L->get('Home page') . '</a>';
			$html .= '</li>';
		}

		// Pages order by date

		// List of published pages
		$onlyPublished = true;
		$pageNumber = 1;
		$numberOfItems = $this->getValue('numberOfItems');
		$publishedPages = $pages->getList($pageNumber, $numberOfItems, $onlyPublished);

		$previousValueY = null;
		$previousValueM = null;

		foreach ($publishedPages as $pageKey) {

			$page = new Page($pageKey);
			$date = $page->date('Y,F');
			$year = substr($date, 0, 4);
			$month = substr($date, 5);

			if ($year !== $previousValueY) {
			    $html .= '<div class="year">'.$year.'</div>';
			}

	   		if ($month !== $previousValueM) {
			    $html .= '<div class="month">'.$month.'</div>';
			}

			try {
				$html .= '<li>';
				$html .= '<a href="' . $page->permalink() . '">' . $page->title() . '</a>';
				$html .= '</li>';
				} catch (Exception $e) {
				// Continue
			}

			$previousValueY = $year;
			$previousValueM = $month;

			}

			$html .= '</ul>';
 			$html .= '</div>';
 			$html .= '</div>';

			return $html;
	}

}
