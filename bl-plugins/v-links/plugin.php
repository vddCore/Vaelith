<?php

class vLinksPlugin extends Plugin {
	
	public function init()
	{
		// JSON database
		$jsondb = json_encode(array(
			'DuckDuckGo'=>'https://duckduckgo.com'
		));
		
		$this->dbFields = array(
			'jsondb' => $jsondb
		);
		
		$this->formButtons = false;
	}
	
	public function post()
	{
		$jsondb = $this->db['jsondb'];
		$jsondb = Sanitize::htmlDecode($jsondb);
		
		$links = json_decode($jsondb, true);
		
		if( isset($_POST['deleteLink']) ) {
			$name = $_POST['deleteLink'];
			unset($links[$name]);
		}
		elseif( isset($_POST['addLink']) ) {
			$name = $_POST['linkName'];
			$url = $_POST['linkURL'];
			
			if(empty($name)) { return false; }
			$links[$name] = $url;
		}
		
		$this->db['label'] = Sanitize::html($_POST['label']);
		$this->db['jsondb'] = Sanitize::html(json_encode($links));
		
		return $this->save();
	}
	
	public function form()
	{
		global $L;
		
		$html  = '<div class="alert alert-primary" role="alert">';
		$html .= $this->description();
		$html .= '</div>';
		
		$html .= '<div>';
		$html .= '<button name="save" class="btn btn-primary my-2" type="submit">'.$L->get('Save').'</button>';
		$html .= '</div>';
		
		$html .= '<h4 class="mt-3">'.$L->get('Add a new link').'</h4>';
		
		$html .= '<div>';
		$html .= '<label>'.$L->get('Name').'</label>';
		$html .= '<input name="linkName" type="text" class="form-control" value="" placeholder="Bludit">';
		$html .= '</div>';
		
		$html .= '<div>';
		$html .= '<label>'.$L->get('Url').'</label>';
		$html .= '<input name="linkURL" type="text" class="form-control" value="" placeholder="https://www.bludit.com/">';
			$html .= '</div>';
			
			$html .= '<div>';
			$html .= '<button name="addLink" class="btn btn-primary my-2" type="submit">'.$L->get('Add').'</button>';
			$html .= '</div>';
			
			$jsondb = $this->getValue('jsondb', $unsanitized=false);
			$links = json_decode($jsondb, true);
			
			$html .= !empty($links) ? '<h4 class="mt-3">'.$L->get('Links').'</h4>' : '';
			
			foreach($links as $name=>$url) {
				$html .= '<div class="my-2">';
				$html .= '<label>'.$L->get('Name').'</label>';
				$html .= '<input type="text" class="form-control" value="'.$name.'" disabled>';
				$html .= '</div>';
				
				$html .= '<div>';
				$html .= '<label>'.$L->get('Url').'</label>';
				$html .= '<input type="text" class="form-control" value="'.$url.'" disabled>';
				$html .= '</div>';
				
				$html .= '<div>';
				$html .= '<button name="deleteLink" class="btn btn-secondary my-2" type="submit" value="'.$name.'">'.$L->get('Delete').'</button>';
				$html .= '</div>';
			}
			
			return $html;
		}
		
		public function getLinks() {
			$jsondb = $this->db['jsondb'];
			$jsondb = Sanitize::htmlDecode($jsondb);
			
			return json_decode($jsondb, true);
		}
	}
	