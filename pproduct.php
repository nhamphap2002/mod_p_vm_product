<?php

/*
  Created on : Feb 28, 2014, 2:11:55 PM
  Author: Tran Trong Thang
  Email: trantrongthang1207@gmai.com
 */

class VirtueMartModelPproduct extends VirtueMartModelProduct {

    /**
     * Loads different kind of product lists.
     * you can load them with calculation or only published onces, very intersting is the loading of groups
     * valid values are latest, topten, featured, recent.
     *
     * The function checks itself by the config if the user is allowed to see the price or published products
     *
     * @author Max Milbers
     */
    public function getProductListing($group = FALSE, $nbrReturnProducts = FALSE, $withCalc = TRUE, $onlyPublished = TRUE, $single = FALSE, $filterCategory = TRUE, $category_id = 0, $filterProductId = '') {

        $app = JFactory::getApplication();
        if ($app->isSite()) {
            $front = TRUE;
            if (!class_exists('Permissions')) {
                require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'permissions.php');
            }
            if (!Permissions::getInstance()->check('admin', 'storeadmin')) {
                $onlyPublished = TRUE;
                if ($show_prices = VmConfig::get('show_prices', 1) == '0') {
                    $withCalc = FALSE;
                }
            }
        } else {
            $front = FALSE;
        }

        $this->setFilter();
        if ($filterCategory === TRUE) {
            if ($category_id) {
                $this->virtuemart_category_id = $category_id;
            }
        } else {
            $this->virtuemart_category_id = FALSE;
        }
        $ids = $this->sortSearchListQuery($onlyPublished, $this->virtuemart_category_id, $group, $nbrReturnProducts, $filterProductId);

        //quickndirty hack for the BE list, we can do that, because in vm2.1 this is anyway fixed correctly
        $this->listing = TRUE;
        $products = $this->getProducts($ids, $front, $withCalc, $onlyPublished, $single);
        $this->listing = FALSE;
        return $products;
    }

    /**
     * New function for sorting, searching, filtering and pagination for product ids.
     *
     * @author Max Milbers
     */
    function sortSearchListQuery($onlyPublished = TRUE, $virtuemart_category_id = FALSE, $group = FALSE, $nbrReturnProducts = FALSE, $filterProductId) {

        $app = JFactory::getApplication();

        //User Q.Stanley said that removing group by is increasing the speed of product listing in a bigger shop (10k products) by factor 60
        //So what was the reason for that we have it? TODO experiemental, find conditions for the need of group by
        $groupBy = ' group by p.`virtuemart_product_id` ';

        //administrative variables to organize the joining of tables
        $joinCategory = FALSE;
        $joinMf = FALSE;
        $joinPrice = FALSE;
        $joinCustom = FALSE;
        $joinShopper = FALSE;
        $joinChildren = FALSE;
        $joinLang = TRUE;
        $orderBy = ' ';

        $where = array();
        $useCore = TRUE;
        if ($this->searchplugin !== 0) {
            //reset generic filters ! Why? the plugin can do it, if it wishes it.
            // 			if ($this->keyword ==='') $where=array();
            JPluginHelper::importPlugin('vmcustom');
            $dispatcher = JDispatcher::getInstance();
            $PluginJoinTables = array();
            $ret = $dispatcher->trigger('plgVmAddToSearch', array(&$where, &$PluginJoinTables, $this->searchplugin));
            foreach ($ret as $r) {
                if (!$r) {
                    $useCore = FALSE;
                }
            }
        }

        if ($useCore) {
            $isSite = $app->isSite();
// 		if ( $this->keyword !== "0" and $group ===false) {
            if (!empty($this->keyword) and $this->keyword !== '' and $group === FALSE) {

                $keyword = '"%' . str_replace(array(' ', '-'), '%', $this->_db->getEscaped($this->keyword, true)) . '%"';
                //$keyword = '"%' . $this->_db->getEscaped ($this->keyword, TRUE) . '%"';

                foreach ($this->valid_search_fields as $searchField) {
                    if ($searchField == 'category_name' || $searchField == 'category_description') {
                        $joinCategory = TRUE;
                    } else {
                        if ($searchField == 'mf_name') {
                            $joinMf = TRUE;
                        } else {
                            if ($searchField == 'product_price') {
                                $joinPrice = TRUE;
                            } else {
                                //vmdebug('sortSearchListQuery $searchField',$searchField);
                                /* 	if (strpos ($searchField, 'p.') == 1) {
                                  $searchField = 'p`.`' . substr ($searchField, 2, (strlen ($searchField)));
                                  //vmdebug('sortSearchListQuery $searchField recreated',$searchField);
                                  } */
                            }
                        }
                    }
                    if (strpos($searchField, '`') !== FALSE) {
                        $keywords_plural = preg_replace('/\s+/', '%" AND ' . $searchField . ' LIKE "%', $keyword);
                        $filter_search[] = $searchField . ' LIKE ' . $keywords_plural;
                    } else {
                        $keywords_plural = preg_replace('/\s+/', '%" AND `' . $searchField . '` LIKE "%', $keyword);
                        $filter_search[] = '`' . $searchField . '` LIKE ' . $keywords_plural;
                        //$filter_search[] = '`' . $searchField . '` LIKE ' . $keyword;
                    }
                }
                if (!empty($filter_search)) {
                    $where[] = '(' . implode(' OR ', $filter_search) . ')';
                } else {
                    $where[] = '`product_name` LIKE ' . $keyword;

                    //If they have no check boxes selected it will default to product name at least.
                }
                $joinLang = TRUE;
            }

// 		vmdebug('my $this->searchcustoms ',$this->searchcustoms);
            if (!empty($this->searchcustoms)) {
                $joinCustom = TRUE;
                foreach ($this->searchcustoms as $key => $searchcustom) {
                    $custom_search[] = '(pf.`virtuemart_custom_id`="' . (int) $key . '" and pf.`custom_value` like "%' . $this->_db->getEscaped($searchcustom, TRUE) . '%")';
                }
                $where[] = " ( " . implode(' OR ', $custom_search) . " ) ";
            }

            if ($onlyPublished) {
                $where[] = ' p.`published`="1" ';
            }

            if ($isSite and !VmConfig::get('use_as_catalog', 0)) {
                if (VmConfig::get('stockhandle', 'none') == 'disableit_children') {
                    $where[] = ' (p.`product_in_stock` - p.`product_ordered` >"0" OR children.`product_in_stock` - children.`product_ordered` > "0") ';
                    $joinChildren = TRUE;
                } else if (VmConfig::get('stockhandle', 'none') == 'disableit') {
                    $where[] = ' p.`product_in_stock` - p.`product_ordered` >"0" ';
                }
            }

            if ($virtuemart_category_id > 0) {
                $joinCategory = TRUE;
                $where[] = ' `pc`.`virtuemart_category_id` = ' . $virtuemart_category_id;
            }

            if ($isSite and !VmConfig::get('show_uncat_child_products', TRUE)) {
                $joinCategory = TRUE;
                $where[] = ' `pc`.`virtuemart_category_id` > 0 ';
            }

            if ($this->product_parent_id) {
                $where[] = ' p.`product_parent_id` = ' . $this->product_parent_id;
            }

            if ($isSite) {
                $usermodel = VmModel::getModel('user');
                $currentVMuser = $usermodel->getUser();
                $virtuemart_shoppergroup_ids = (array) $currentVMuser->shopper_groups;

                if (is_array($virtuemart_shoppergroup_ids)) {
                    $sgrgroups = array();
                    foreach ($virtuemart_shoppergroup_ids as $key => $virtuemart_shoppergroup_id) {
                        $sgrgroups[] = 's.`virtuemart_shoppergroup_id`= "' . (int) $virtuemart_shoppergroup_id . '" ';
                    }
                    $sgrgroups[] = 's.`virtuemart_shoppergroup_id` IS NULL ';
                    $where[] = " ( " . implode(' OR ', $sgrgroups) . " ) ";

                    $joinShopper = TRUE;
                }
            }

            if ($this->virtuemart_manufacturer_id) {
                $joinMf = TRUE;
                $where[] = ' `#__virtuemart_product_manufacturers`.`virtuemart_manufacturer_id` = ' . $this->virtuemart_manufacturer_id;
            }

            // Time filter
            if ($this->search_type != '') {
                $search_order = $this->_db->getEscaped(JRequest::getWord('search_order') == 'bf' ? '<' : '>');
                switch ($this->search_type) {
                    case 'parent':
                        $where[] = 'p.`product_parent_id` = "0"';
                        break;
                    case 'product':
                        $where[] = 'p.`modified_on` ' . $search_order . ' "' . $this->_db->getEscaped(JRequest::getVar('search_date')) . '"';
                        break;
                    case 'price':
                        $joinPrice = TRUE;
                        $where[] = 'pp.`modified_on` ' . $search_order . ' "' . $this->_db->getEscaped(JRequest::getVar('search_date')) . '"';
                        break;
                    case 'withoutprice':
                        $joinPrice = TRUE;
                        $where[] = 'pp.`product_price` IS NULL';
                        break;
                    case 'stockout':
                        $where[] = ' p.`product_in_stock`- p.`product_ordered` < 1';
                        break;
                    case 'stocklow':
                        $where[] = 'p.`product_in_stock`- p.`product_ordered` < p.`low_stock_notification`';
                        break;
                }
            }

            // special  orders case
            //vmdebug('my filter ordering ',$this->filter_order);
            switch ($this->filter_order) {
                case 'product_special':
                    if ($isSite) {
                        $where[] = ' p.`product_special`="1" '; // TODO Change  to  a  individual button
                        $orderBy = 'ORDER BY RAND()';
                    } else {
                        $orderBy = 'ORDER BY `product_special`';
                    }

                    break;
                case 'category_name':
                    $orderBy = ' ORDER BY `category_name` ';
                    $joinCategory = TRUE;
                    break;
                case 'category_description':
                    $orderBy = ' ORDER BY `category_description` ';
                    $joinCategory = TRUE;
                    break;
                case 'mf_name':
                    $orderBy = ' ORDER BY `mf_name` ';
                    $joinMf = TRUE;
                    break;
                case 'pc.ordering':
                    $orderBy = ' ORDER BY `pc`.`ordering` ';
                    $joinCategory = TRUE;
                    break;
                case 'product_price':
                    //$filters[] = 'p.`virtuemart_product_id` = p.`virtuemart_product_id`';
                    $orderBy = ' ORDER BY `product_price` ';
                    $joinPrice = TRUE;
                    break;
                case 'created_on':
                    $orderBy = ' ORDER BY p.`created_on` ';
                    break;
                default;
                    if (!empty($this->filter_order)) {
                        $orderBy = ' ORDER BY ' . $this->filter_order . ' ';
                    } else {
                        $this->filter_order_Dir = '';
                    }
                    break;
            }

            //Group case from the modules
            if ($group) {

                $latest_products_days = VmConfig::get('latest_products_days', 7);
                $latest_products_orderBy = VmConfig::get('latest_products_orderBy', 'created_on');
                $groupBy = 'group by p.`virtuemart_product_id` ';
                switch ($group) {
                    case 'featured':
                        $where[] = 'p.`product_special`="1" ';
                        $orderBy = 'ORDER BY RAND()';
                        break;
                    case 'latest':
                        $date = JFactory::getDate(time() - (60 * 60 * 24 * $latest_products_days));
                        $dateSql = $date->toMySQL();
                        $where[] = 'p.`' . $latest_products_orderBy . '` > "' . $dateSql . '" ';
                        $orderBy = 'ORDER BY p.`' . $latest_products_orderBy . '`';
                        $this->filter_order_Dir = 'DESC';
                        break;
                    case 'random':
                        $orderBy = ' ORDER BY RAND() '; //LIMIT 0, '.(int)$nbrReturnProducts ; //TODO set limit LIMIT 0, '.(int)$nbrReturnProducts;
                        break;
                    case 'topten':
                        $orderBy = ' ORDER BY p.`product_sales` '; //LIMIT 0, '.(int)$nbrReturnProducts;  //TODO set limitLIMIT 0, '.(int)$nbrReturnProducts;
                        $where[] = 'pp.`product_price`>"0.0" ';
                        $this->filter_order_Dir = 'DESC';
                        break;
                    case 'recent':
                        $rSession = JFactory::getSession();
                        $rIds = $rSession->get('vmlastvisitedproductids', array(), 'vm'); // get recent viewed from browser session
                        return $rIds;
                    //Trong Thang
                    case 'product_id':
                        $where[] = 'p.`virtuemart_product_id` in (' . $filterProductId . ')';
                        $orderBy = 'ORDER BY p.`virtuemart_product_id`';
                        $this->filter_order_Dir = 'ASC';
                        break;
                    case 'category_id':
                        $where[] = 'pc.`virtuemart_category_id` in (' . $filterProductId . ')';
                        $orderBy = ' ORDER BY RAND() ';
                        break;
                }
                // 			$joinCategory 	= false ; //creates error
                // 			$joinMf 		= false ;	//creates error
                $joinPrice = TRUE;
                $this->searchplugin = FALSE;
// 			$joinLang = false;
            }
        }

        //write the query, incldue the tables
        //$selectFindRows = 'SELECT SQL_CALC_FOUND_ROWS * FROM `#__virtuemart_products` ';
        //$selectFindRows = 'SELECT COUNT(*) FROM `#__virtuemart_products` ';
        if ($joinLang) {
            $select = ' l.`virtuemart_product_id` FROM `#__virtuemart_products_' . VMLANG . '` as l';
            $joinedTables[] = ' JOIN `#__virtuemart_products` AS p using (`virtuemart_product_id`)';
        } else {
            $select = ' p.`virtuemart_product_id` FROM `#__virtuemart_products` as p';
            $joinedTables[] = '';
        }

        if ($joinCategory == TRUE) {
            $joinedTables[] = ' LEFT JOIN `#__virtuemart_product_categories` as pc ON p.`virtuemart_product_id` = `pc`.`virtuemart_product_id`
			 LEFT JOIN `#__virtuemart_categories_' . VMLANG . '` as c ON c.`virtuemart_category_id` = `pc`.`virtuemart_category_id`';
        }
        if ($joinMf == TRUE) {
            $joinedTables[] = ' LEFT JOIN `#__virtuemart_product_manufacturers` ON p.`virtuemart_product_id` = `#__virtuemart_product_manufacturers`.`virtuemart_product_id`
			 LEFT JOIN `#__virtuemart_manufacturers_' . VMLANG . '` as m ON m.`virtuemart_manufacturer_id` = `#__virtuemart_product_manufacturers`.`virtuemart_manufacturer_id` ';
        }

        if ($joinPrice == TRUE) {
            $joinedTables[] = ' LEFT JOIN `#__virtuemart_product_prices` as pp ON p.`virtuemart_product_id` = pp.`virtuemart_product_id` ';
        }
        if ($this->searchcustoms) {
            $joinedTables[] = ' LEFT JOIN `#__virtuemart_product_customfields` as pf ON p.`virtuemart_product_id` = pf.`virtuemart_product_id` ';
        }
        if ($this->searchplugin !== 0) {
            if (!empty($PluginJoinTables)) {
                $plgName = $PluginJoinTables[0];
                $joinedTables[] = ' LEFT JOIN `#__virtuemart_product_custom_plg_' . $plgName . '` as ' . $plgName . ' ON ' . $plgName . '.`virtuemart_product_id` = p.`virtuemart_product_id` ';
            }
        }
        if ($joinShopper == TRUE) {
            $joinedTables[] = ' LEFT JOIN `#__virtuemart_product_shoppergroups` ON p.`virtuemart_product_id` = `#__virtuemart_product_shoppergroups`.`virtuemart_product_id`
			 LEFT  OUTER JOIN `#__virtuemart_shoppergroups` as s ON s.`virtuemart_shoppergroup_id` = `#__virtuemart_product_shoppergroups`.`virtuemart_shoppergroup_id`';
        }

        if ($joinChildren) {
            $joinedTables[] = ' LEFT OUTER JOIN `#__virtuemart_products` children ON p.`virtuemart_product_id` = children.`product_parent_id` ';
        }

        if (count($where) > 0) {
            $whereString = ' WHERE (' . implode(' AND ', $where) . ') ';
        } else {
            $whereString = '';
        }
        //vmdebug ( $joinedTables.' joined ? ',$select, $joinedTables, $whereString, $groupBy, $orderBy, $this->filter_order_Dir );		/* jexit();  */
        $this->orderByString = $orderBy;
        if ($this->_onlyQuery) {
            return (array($select, $joinedTables, $where, $orderBy));
        }
        $joinedTables = implode('', $joinedTables);
        $product_ids = $this->exeSortSearchListQuery(2, $select, $joinedTables, $whereString, $groupBy, $orderBy, $this->filter_order_Dir, $nbrReturnProducts);

        return $product_ids;
    }

}

?>
