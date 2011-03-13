<?php
defined('_JEXEC') or die('Restricted access.');

class SefExt_com_virtuemart extends SefExt
{
	
	function _vm_sef_get_category_array( &$db, $category_id ){
		static $tree = null;  // V 1.2.4.m  $tree must an array based on current language
		 
		if(empty($tree)){
		  $q  = "SELECT c.category_name, c.category_id, x.category_parent_id,c.category_description FROM #__vm_category AS c" ;
		  $q .= "\n LEFT JOIN #__vm_category_xref AS x ON c.category_id = x.category_child_id;";
		  //$q .= "\n WHERE c.category_publish = 'Y';"; // V x
		  $db->setQuery( $q );
		  $tree = $db->loadObjectList( 'category_id' );
		}
		$title=array();
		if (0)    // only one category
		$title[] = $tree[ $category_id ]->category_name;
		else
		do {               // all categories and subcategories. We don't really need id, as path
		  $title[] = $tree[ $category_id ]->category_name;                           // will always be unique
		  $category_id = $tree[ $category_id ]->category_parent_id;
		  if( empty($this->category_desc) ) {
                $this->category_desc = stripslashes($row->category_description);
           }
		} while( $category_id != 0 );
		$title = array_reverse( $title );
		$this->pagetitle = implode(' '.$this->params->get('title_sep', '/').' ', $title);
		return  $title;
	}
	
	
  function _vmSefGetProductName( $productId) {
    if (empty($productId)) return null;
    $database =& JFactory::getDBO();
    $q = "SELECT product_id, product_sku, product_name FROM #__vm_product";  // then try to add its name as well
    $q .= "\n WHERE product_id = ".$productId;
    $database->setQuery( $q);
	$row = $database->loadObject( );
    $shName = $row->product_name;
    return $shName;
  }
	
	function _createNonSefVars(&$uri)
    {
        if (isset($this->nonSefVars) && isset($this->ignoreVars))
            return;
        $this->nonSefVars = array();
        $this->ignoreVars = array();
        if (!is_null($uri->getVar('limit')))
            $this->nonSefVars['limit'] = $uri->getVar('limit');
        if (!is_null($uri->getVar('limitstart')))
            $this->nonSefVars['limitstart'] = $uri->getVar('limitstart');
        if (!is_null($uri->getVar('keyword')))
            $this->nonSefVars['keyword'] = $uri->getVar('keyword');
        if (!is_null($uri->getVar('orderby')))
            $this->nonSefVars['orderby'] = $uri->getVar('orderby');
    }
	
	function create($uri) {
		$database =& JFactory::getDBO();
		$vars = $uri->getQuery(true);
		$query = $uri->getQuery();
		extract($vars);
		if (!empty($Itemid)){
			$database->setQuery('SELECT params FROM #__menu WHERE id='.intval($Itemid));
			$params = $database->loadResult();
			$menu_params = new stdClass();
			$menu_params = new JParameter($params);
			if(empty($product_id) && $menu_params->get("product_id"))
				$product_id=$menu_params->get("product_id");
			if((strpos($query,'category') === FALSE) && $menu_params->get("category_id"))
				$category_id=$menu_params->get("category_id");
			if(empty($flypage) && $menu_params->get("flypage"))
				$flypage=$menu_params->get("flypage");
			if(empty($page) && $menu_params->get("page"))
				$page=$menu_params->get("page");
			else 
				$page=$page?$page:"shop.browse";
		}
		$shVmCChk = false;
		$title = array();
		if (strpos( $uri->toString(), 'vmcchk') !== false) {// if VM is doing a cookie check
		  $shVmCChk = true;
		  // this is a trick to counter a 'bug' in VM 1.0.10 when using SEF URL
		  setcookie( 'VMCHECK', 'OK', time()+60*60, '/' );
		}
		$title[] = JoomSEF::_getMenuTitle(@$option, @$task, @$Itemid);
		if($shVmCChk)
		$title[] = 'vmchk';
		switch ($page){
		case "shop.feed":
			$title[] = 'feed';
		case "shop.browse":
			if (!empty($manufacturer_id)){
				$sql = "SELECT mf_name FROM #__vm_manufacturer WHERE manufacturer_id=".$manufacturer_id." LIMIT 1";
				$database->setQuery($sql);
				$manufacturer_name = $database->loadResult();
				$title[] = $manufacturer_name;
			}
			//show category only
			if (!empty($category_id))
			{
				$title = array_merge( $title, $this->_vm_sef_get_category_array( $database, $category_id));
			} else {
				$title[] = 'allproducts';
			}
			$this->metadesc = $this->category_desc;
		break;
		case "shop.ask":
			$title[] = 'shop.ask';
		case "shop.product_details":
			if (!empty($category_id))
			{
				$title = array_merge( $title, $this->_vm_sef_get_category_array( $database, $category_id));
			} else {
				$sql = "SELECT category_id FROM #__vm_product_category_xref WHERE product_id=".$product_id." LIMIT 1";
				$database->setQuery($sql);
				$data = $database->loadObject();
				$category_id = $data->category_id;
				$title = array_merge( $title, $this->_vm_sef_get_category_array( $database, $category_id));
			}
			if (!empty($manufacturer_id)&& $this->params->get('manufacturer', '0') != '0'){
				$sql = "SELECT mf_name FROM #__vm_manufacturer WHERE manufacturer_id=".$manufacturer_id." LIMIT 1";
				$database->setQuery($sql);
				$manufacturer_name = $database->loadResult();
				$title[] = $manufacturer_name;
			}
			$sql = "SELECT product_name,product_s_desc FROM #__vm_product WHERE product_id=".$product_id." LIMIT 1";
			$database->setQuery($sql);
			$data = $database->loadObject();
			$product_name = $data->product_name;
			$this->metadesc = $data->product_s_desc;
			if ($this->pagetitle)
				$this->pagetitle .= ' ' . $this->params->get('title_sep', '/') . ' ';
			$this->pagetitle .= $product_name;
			$title[] = $product_name;
			if ($this->params->get('flypage', '0')){
				$title[] = $flypage;
			}
		break;

		case "shop.manufacturer_page":
			$sql = "SELECT mf_name FROM #__vm_manufacturer WHERE manufacturer_id=".$manufacturer_id." LIMIT 1";
			$database->setQuery($sql);
			$manufacturer_name = $database->loadResult();
			$title[] = $manufacturer_name;
		break;

		case "shop.cart":
			if (!empty($func))
				switch ($func){
				  case 'cartAdd':
					$title[] = 'cartadd';
					if (!empty($product_id)) {  // if a product_id is set (it should!)
					  $title[] = $this->_vmSefGetProductName( $product_id);
					}
					break;
				  case 'cartUpdate':
					$title[] = 'cartupdate';
					if (!empty($product_id)) {  // if a product_id is set (it should!)
					  $title[] = $this->_vmSefGetProductName( $product_id);
					}
					break;
				  case 'cartDelete':
					$title[] = 'cartdelete';
					if (!empty($product_id)) {  // if a product_id is set (it should!)
					  $title[] = $this->_vmSefGetProductName( $product_id);
					}
					break;
				} 
			else {  // only show cart, no function
				  $title[] = 'cart';
				}
		break;
		case 'shop.downloads':
			$title[] = 'download';
			$title[] = '/';
		break;

		case "shop.registration":
			$title[] = "vmregistration";
		break;

		case 'shop.view_images':
			$title[] =  'vmimages';

			if (!empty($category_id))
			{
				$title = array_merge( $title, $this->_vm_sef_get_category_array( $database, $category_id));
			}
			if (!empty($product_id)) {
			  $title[] = $this->_vmSefGetProductName( $product_id, $option, $shLangName, $shLangIso);// V 1.2.4.s
			}

			if (!empty($image_id))
			if ($image_id == 'product') {
			  $title[] = 'productimage';
			}
			else {
			  $q = "SELECT file_id, file_title FROM #__vm_product_files";  // then try to add its name as well
			  $q .= "\n WHERE file_id = %s";
			  $database->setQuery( sprintf( $q, $image_id ) );
			  $row = $database->loadObject( );
			  if (!empty($row)) {
				$title[] = $row->file_id.'_'.$row->file_title;
			  }
			}
		break;
		case 'shop.parameter_search' :
			$title[] =  'paramvmsearch';
		break;
		
		case 'shop.parameter_search_form' :
			$title[] =  'paramvmsearch_form';
		break;
		
		case 'shop.search' :
			$title[] =  'vmsearch';
		break;
		
		case 'account.index':
			$title[] =  'account';
		break;	
		
		case 'account.billing':
			$title[] =  'accinfo';
			if (!empty($next_page)) {
			  $title[] = $next_page;
			}
		break;
		case 'account.shipto':
			$title[] =  'shipto';
			if (!empty($next_page)) {
			  $title[] = $next_page;
			}
		break;
		
		case  'account.shipping':
			$title[] =  'shipinfo';
		break;
		
		case 'account.order_details':
			$order_id = isset($order_id) ? @$order_id : null;
			$title[] =  'odrderdetails'.($order_id ? '_id'.strval($order_id):'');
		break;
		
		case 'checkout.confirm':
		case 'checkout.customer_info':
		case 'checkout.dandomain_cc_form':
		case 'checkout.dandomain_result':
		case 'checkout.danhost_cc_form':
		case 'checkout.danhost_result':
		case 'checkout.freepay_cc_form':
		case 'checkout.freepay_result':
		case 'checkout.login_form':
		case 'checkout.paymentradio':
		case 'checkout.result':
		case 'checkout.thankyou':
		case 'checkout.wannafind_cc_form':
		case 'checkout.wannafind_result':
		case 'checkout_bar':
		case 'checkout_register_form':
		case 'checkout.index':
			$title = array();
	}
	if ($pshop_mode == 'admin')$title = array();
		$newUri = $uri;
		if (count($title) > 0) {
			$meta = $this->getMetaTags();
			if (! empty($this->pagetitle)) {
                $meta['metatitle'] = str_replace('"', '&quot;', $this->pagetitle);
            }
			$this->_createNonSefVars($uri);
			$newUri = JoomSEF::_sefGetLocation($uri, $title, @$task, @$limit, @$limitstart, @$lang, $this->nonSefVars,null, $meta);
		}
		return $newUri;
	}
}
?>
