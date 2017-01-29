<?php
/*	Seo_Bro plugin for OpenCart 2.x
	made by lakbor:
	
	boris@lakbor.ru
	github.com/lakbor
	
	Thank you for using my SEO module.
	I appreciate that. You can ask for adding
	any functionality you want just by writing me)
	
	version 0.6.5-stable 2017-01-26
*/

class ControllerCommonSeoBro extends Controller {
	private $cache_data = null;
		
	public function __construct($registry){
		parent::__construct($registry);

//DEL cache		
//$this->cache->delete('seo_bro');
		$this->cache_data = $this->cache->get('seo_bro');
		if(!$this->cache_data){
			$this->cache_data['keypaths'][''] = ['route' => 'common/home', 'path' => '', 'id' => 0];
			$this->cache_data['queries']['route=common/home'] = ' ';
			$this->cache->set('seo_bro', $this->cache_data);
		}
	}
	
	public function index() {
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);
		}
		
		if (isset($this->request->get['_route_'])) {
			$keypath = rtrim($this->request->get['_route_'], "/");
			
			if(substr($this->request->get['_route_'], -1) === '/') {
				$redirect_url = HTTP_SERVER . $keypath;
				header($this->request->server['SERVER_PROTOCOL'] . ' 301 Moved Permanently');
				$this->response->redirect($redirect_url, 301);
			}
			
			$to_request = self::get_query($keypath);
			switch($to_request['mode']){
				case 'product':			$this->request->get['product_id'] = $to_request['id'];
										$this->request->get['path'] = $to_request['path'];

										$exp = explode("/", $keypath);
										$prod_kpath = self::get_path($to_request['id'], 'pk')['kpath'] . '/' . array_pop($exp);

										if($keypath !== $prod_kpath){
											$redirect_url = HTTP_SERVER . $prod_kpath;
											header($this->request->server['SERVER_PROTOCOL'] . ' 301 Moved Permanently');
											$this->response->redirect($redirect_url, 301);
										}
										break;
				case 'category':		$this->request->get['path'] = $to_request['path'];
										break;
				case 'manufacturer':	$this->request->get['manufacturer_id'] = $to_request['id'];
										break;
				case 'information':		$this->request->get['information_id'] = $to_request['id'];
										break;
				default:
			}
			if(isset($_GET['page']) && !isset($this->request->get['page'])) $this->request->get['page'] = $_GET['page'];
			$this->request->get['route'] = $to_request['route'];

			return new Action($this->request->get['route']);
		}
	} // index() ENDs

	public function rewrite($link) {
		$page_flag = false;
		
		if(isset($this->request->get['page'])) unset($this->request->get['page']);
		$url_info = parse_url(str_replace('&amp;', '&', $link));
		
		$data = array();
		parse_str($url_info['query'], $data);
		
		$query = 'route=' . $data['route'];
		if(isset($data['path'])) $query .= '&path=' . $data['path'];
		if(isset($data['product_id'])) {
			$query .= '&product_id=' . $data['product_id'];
			$page_flag = true;
		}
		if(isset($data['manufacturer_id'])) $query .= '&manufacturer_id=' . $data['manufacturer_id'];
		if(isset($data['information_id'])) $query .= '&information_id=' . $data['information_id'];
		
		$url = self::get_keypath($query, $data); 	//спорный момент: что быстрее работает, если передать тут дату 
													//или если генерировать дату заново при вызове add_qcache()
													//пока что передедаю тут, а там посмотрим...
													//по сути надо понять сколько держится кэш (вроде час)
													//если сделать больше, то выгоднее генерировать дату в эдд_кукэш,
													//потому что она будет вызываться не так часто...

		if ($url !== '') {
			unset($data['route']);
			if(isset($data['path'])) 			unset($data['path']);
			if(isset($data['product_id'])) 		unset($data['product_id']);
			if(isset($data['manufacturer_id'])) unset($data['manufacturer_id']);
			if(isset($data['information_id'])) 	unset($data['information_id']);
			
			$query = '';

			if ($data && $page_flag == false) {
				foreach ($data as $key => $value) {
					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
				}
				if ($query) {
					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
				}
			}

			return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . '/' . $url . $query;
		} else {
			return $link;
		}
	} // rewrite() ENDs
	
	private function get_query($keypath){ //index #1
		// 1 - checking cache_data
		if(isset($this->cache_data['keypaths'][$keypath]) && !empty($this->cache_data['keypaths'][$keypath]['route'])){
			$ret_array['route'] = $this->cache_data['keypaths'][$keypath]['route'];
			$ret_array['id'] = $this->cache_data['keypaths'][$keypath]['id'];
			$ret_array['path'] = $this->cache_data['keypaths'][$keypath]['path'];
			switch($ret_array['route']){
				case 'product/product': $ret_array['mode'] = 'product'; break;
				case 'product/category': $ret_array['mode'] = 'category'; break;
				case 'product/manufacturer/info': $ret_array['mode'] = 'manufacturer'; break;
				case 'information/information': $ret_array['mode'] = 'information'; break;
				default: $ret_array['mode'] = 'xa';
			}
		} else {
		// 2 - set cache for new queries
			$ret_array = self::set_kcache($keypath);
		}
		return $ret_array;
	}
	
	private function get_keypath($query, $data){ //rewrite #1
		// 1 - checking cache_data
$keypath = '';
		if(isset($this->cache_data['queries'][$query])/* && !empty($this->cache_data['queries'][$query])*/){
			$keypath = $this->cache_data['queries'][$query];
		} else {
			// 2 - add 2 cache a new value
			$keypath = self::set_qcache($query, $data); // ага, вот тут трансфером, жесть какая оО
		}
		
		return $keypath;
	}
	
	private function set_kcache($keypath){ //index #2
		$keypath = $this->db->escape($keypath);
		
		$query = "SELECT * FROM " . DB_PREFIX . "url_alias WHERE keyword='" . $keypath . "'";
		$res = $this->db->query($query);
		if($res->num_rows == 1){
			//если по запорсу найден тлько один результат (супер ваще!)
			$res1 = explode("=", $res->row['query']);
			switch($res1[0]){
				case 'product_id':			$ret_array['id'] = $res1[1];
											$ret_array['path'] = '';
											$ret_array['route'] = 'product/product';
											$ret_array['mode'] = 'product';
											break;
				case 'category_id':			$ret_array['path'] = $res1[1];
											$ret_array['route'] = 'product/category';
											$ret_array['mode'] = 'category';
											break;
				case 'manufacturer_id':		$ret_array['id'] = $res1[1];
											$ret_array['route'] = 'product/manufacturer/info';
											$ret_array['mode'] = 'manufacturer';
											break;
				case 'information_id':		$ret_array['id'] = $res1[1];
											$ret_array['route'] = 'information/information';
											$ret_array['mode'] = 'information';
											break;
				default:					$ret_array['route'] = $res->row['query'];
											$ret_array['mode'] = 'xx';
			}
		} else if($res->num_rows > 1){
			// фиг тебе, так не делается. главные категории должны различаться. :Р
		} else {
			$kp_parts = explode("/", $keypath);
			$kp_right = array_pop($kp_parts); //сначала берём и изучаем крайнюю часть чпу

			$query = "SELECT * FROM " . DB_PREFIX . "url_alias WHERE keyword='" . $kp_right . "'";
			$res2 = $this->db->query($query);
			if($res2->num_rows == 1){
				//определяем что перед нами:
				$res3 = explode("=", $res2->row['query']);
				switch($res3[0]){
					case 'product_id':			$ret_array['id'] = $res3[1];
												$ret_array['route'] = 'product/product';
												$ret_array['path'] = self::get_path($res3[1], 'p');
												$ret_array['mode'] = 'product';
												break;
					case 'category_id':			$ret_array['route'] = 'product/category';
												$ret_array['path'] = self::get_path($res3[1], 'c');
												$ret_array['mode'] = 'category';
												break;
					case 'manufacturer_id':		$ret_array['id'] = $res3[1];
												$ret_array['route'] = 'product/manufacturer/info';
												$ret_array['mode'] = 'manufacturer';
												break;
					case 'information_id':		$ret_array['id'] = $res3[1];
												$ret_array['route'] = 'information/information';
												$ret_array['mode'] = 'information';
												break;
					default:					$ret_array['route'] = $res3[1];
												$ret_array['mode'] = 'xb';
				}
			} else if($res2->num_rows > 1){
				foreach($res2->rows as $row){
					$res3 = explode("=", $row['query']);
					switch($res3[0]){
						case 'product_id':			$paths = self::get_path($res3[1], 'pk');
													$ret1_array[] = [	'id' => $res3[1],
																		'route' => 'product/product',
																		'path' => $paths['path'],
																		'kpath' => $paths['kpath'],
																		'mode' => 'product'
													];
													break;
						case 'category_id':			$paths = self::get_path($res3[1], 'ck');
													$ret1_array[] = [	'route' => 'product/category',
																		'path' => $paths['path'],
																		'kpath' => $paths['kpath'],
																		'mode' => 'category'
													];
													break;
						case 'manufacturer_id':		$ret1_array[] = [	'id' => $res3[1],
																		'route' => 'product/manufacturer/info',
																		'kpath' => 'brands/' . $row['keyword'], //здесь надо изменить брэндс на вычисляемое значение по query = 'promuct/manuf' из урл-алиас
																		'mode' => 'manufacturer'
													];
													break;
						case 'information_id':		$ret1_array[] = [	'id' => $res3[1],
																		'route' => 'information/information',
																		'kpath' => $row['keyword'],
																		'mode' => 'information'
													];
													break;
						default:					$ret1_array[] = [	'route' => $res3[1],
																		'kpath' => '',
																		'mode' => 'xc'
													];
					}
				}
				//here we've got an array $ret_array[] со всеми совпадениями, у каждого есть нужный нам ключ - 'kpath', which we're gonna "compare" with $keypath
				foreach($ret1_array as $test_val){
					if($test_val['kpath'] == $keypath){
						$ret_array['id'] = isset($test_val['id']) ? $test_val['id'] : 0;
						$ret_array['route'] = $test_val['route'];
						$ret_array['path'] = isset($test_val['path']) ? $test_val['path'] : '';
						$ret_array['mode'] = $test_val['mode'];
						break;
					}
				}
			} else { // 404 page call
				$ret_array['route'] = 'error/not_found';
				$ret_array['mode'] = 'x404';
			}
			
	} //CHECK			
		if(!empty($ret_array['route'])){
			$this->cache_data['keypaths'][$keypath] = [
				'route' => $ret_array['route'], 
				'path' => isset($ret_array['path']) ? $ret_array['path'] : '', 
				'id' => isset($ret_array['id']) ? $ret_array['id'] : 0
				];
			$this->cache->set('seo_bro', $this->cache_data);
		}
		return $ret_array;

	}
	
	private function set_qcache($query, $data){ //rewrite #2
		$keypath = '';
		$alias = ''; //define alias cos its sometimes missing...		
		switch($data['route']){
			case 'product/product':
				// 1 - alias of product_id
				$q1 = "SELECT * FROM " . DB_PREFIX . "url_alias WHERE query='product_id=" . $data['product_id'] . "'";
				$res = $this->db->query($q1);
				if($res->num_rows){
					$alias = $res->row['keyword'];
					if(!isset($alias) || empty($alias)) break;
				}
				// 2 - kpath by product_id
				$kpath = self::get_path($data['product_id'], 'pk')['kpath'];
				// 3 => url = kpath + alias
				$keypath = $kpath . '/' . $alias;
				break;
			case 'product/category':
				// берем path -> explode -> array_pop() => ищем path по категории
				$res = explode("_", $data['path']);
				$rcat = array_pop($res);
				$keypath = self::get_path($rcat, 'ck')['kpath'];
				break;
			case 'product/manufacturer/info':
				// manuf id => alias
				$q1 = "SELECT * FROM " . DB_PREFIX . "url_alias WHERE query='manufacturer_id=" . $data['manufacturer_id'] . "'";
				$res = $this->db->query($q1);
				if($res->num_rows){
					$alias = $res->row['keyword'];
					if(!isset($alias) || empty($alias)) break;
				}
				// url = /brands/ alias
				$keypath = 'brands/' . $alias; //oughta make BRANDS changeable from query = 'product/manufacturer'
				break;
			case 'information/information':
				// inform-id -> alias
				// url = alias
				$q1 = "SELECT * FROM " . DB_PREFIX . "url_alias WHERE query='information_id=" . $data['information_id'] . "'";
				$res = $this->db->query($q1);
				if($res->num_rows){
					$keypath = $res->row['keyword'];
				}
				break;
			default:
				//query = $data['route'] => alias =====> url
				$q1 = "SELECT * FROM " . DB_PREFIX . "url_alias WHERE query='" . $data['route'] . "'";
				$res = $this->db->query($q1);
				if($res->num_rows){
					$keypath = $res->row['keyword'];
				}
		} //switch ENDs
		
		//NEEDA create CACHE!
		if(!empty($keypath)){
			$this->cache_data['queries'][$query] = $keypath;
			$this->cache->set('seo_bro', $this->cache_data);
		}
		return $keypath;
	} //set_qcache() ENDs
	
	private function get_path($id, $flag){
		if($flag == 'p'){
			$query = "SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id='" . $id . "' ORDER BY main_category DESC";
			$res = $this->db->query($query);
			$cid = $res->rows[0]['category_id']; //found product's main category (or just any, if main ain't set
			return self::get_path($cid, 'c');
		}
		if($flag == 'c'){
			$path = '';
			$query = "SELECT * FROM " . DB_PREFIX . "category_path WHERE category_id='" . $id . "' ORDER BY level ASC";
			$res = $this->db->query($query);
			$i = 0;
			foreach($res->rows as $row){
				$path .= $row['path_id'];
				if(++$i < $res->num_rows) $path .= '_';
			}
			return $path;
		}
		//flag pk & ck - path with keypath
		if($flag == 'pk'){
			$query = "SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id='" . $id . "' ORDER BY main_category DESC";
			$res = $this->db->query($query);
			$cid = $res->rows[0]['category_id']; //found product's main category (or just any, if main ain't set
			return self::get_path($cid, 'ck');
		}
		if($flag == 'ck'){
			$path['path'] = $path['kpath'] = '';

			$query = "SELECT * FROM " . DB_PREFIX . "category_path WHERE category_id='" . $id . "' ORDER BY level ASC";
			$res = $this->db->query($query);
			$i = 0;
			foreach($res->rows as $row){
				$path['path'] .= $row['path_id'];
				
				$query2 = "SELECT * FROM " . DB_PREFIX . "url_alias WHERE query='category_id=" . $row['path_id'] . "'";
				$res_q2 = $this->db->query($query2);
				if($res_q2->num_rows){
					$path['kpath'] .= $res_q2->row['keyword'];
				}
				
				if(++$i < $res->num_rows){
					$path['path'] .= '_';
					$path['kpath'] .= '/';
				}
			}
			return $path;
		}
	}

	private function make_kpath($path, $arr = true){
		$kpath = '';
		if($arr){
			$parts = $path;
		} else {
			$parts = explode("_", $path);
		}
		
		foreach($parts as $part){
			$query = "SELECT * FROM " . DB_PREFIX . "url_alias WHERE query='category_id=" . $part . "'";
			$res = $this->db->query($query);

			if($res->num_rows){
				$kpath .= $res->row['keyword'] . '/';
			}
		}
		return rtrim($kpath, "/");
	}
}
?>
