<?php
// plugin needs to work on Nucleus versions <=2.0 as well
if (!function_exists('sql_table')){
	function sql_table($name) {
		return 'nucleus_' . $name;
	}
}

class NP_Headlines extends NucleusPlugin {

	function getName() { return 'Headlines'; }
	function getAuthor()  { return 'Taka'; }
	function getURL() { return 'http://reverb.jp/vivian/'; }
	function getVersion() { return '0.11'; }
	function getDescription() { 
		return 'Headlines';
	}
	function supportsFeature($what) {
		switch ($what) {
			case 'SqlTablePrefix':
				return 1;
			default:
				return 0;
		}
	}

	function doSkinVar($skinType, $template='', $amount=10, $cmode='', $bmode='', $infotype="date", $nocatparam=0){
		global $blog, $manager, $CONF, $catid, $itemid;

// ======
//	all blogs mode
		$catformat = '<%blogname%> : <%category%>';
// ======

		if (preg_match("{^[0-9\(\)@/]+$}",$template)) {
			$amount = $template;
			$template = '';
			$params = func_get_args();
			if (isset($params[2])) $cmode = $params[2];
			if (isset($params[3])) $bmode = $params[3];
			if (isset($params[4])) {
				$infotype = $params[4];
			} else {
				$infotype = "date";
			}
			if (isset($params[5])) $nocatparam = $params[5];
		}

		$catformat = preg_replace(array('/<%category%>/','/<%blogname%>/'),array('",c.cname,"','",b.bname,"'),$catformat);
		$catformat = '"'.$catformat.'"';

		if ($blog) {
			$b =& $blog; 
		} else {
			$b =& $manager->getBlog($CONF['DefaultBlog']);
		}

		$mycatid = 0;
		$catdown = 0;
		if ($catid) $mycatid = $catid;
		
		$w = array();
		$catblogname = 1;
		if (preg_match("/^(<>)?([1-9][0-9\/]*)$/",$bmode,$m)) {
			$bnums = preg_split ("{/}",$m[2],-1,PREG_SPLIT_NO_EMPTY);
			if ($m[1] == '<>') {
				$w[] = 'i.iblog not in ('.implode(",",$bnums).')';
			} else {
				$w[] = 'i.iblog in ('.implode(",",$bnums).')';
			}
			if (count($w) <= 1) $catblogname = 0;
		} elseif ($bmode != 'all') {
			$w[] = 'i.iblog='.$b->getID();
			$catblogname = 0;
		}
		
		if (preg_match("/^(<>)?([1-9][0-9\/]*)$/",$cmode,$m)) {
			$cnums = preg_split ("{/}",$m[2],-1,PREG_SPLIT_NO_EMPTY);
			if ($m[1] == '<>') {
				$w[] = 'i.icat not in ('.implode(",",$cnums).')';
			} else {
				$w[] = 'i.icat in ('.implode(",",$cnums).')';
			}
		} elseif ($cmode == 'itemcat' && $skinType == 'item' && !$catid) {
			$mycatid = $this->getCategoryIDFromItemID($itemid);
			$catdown = 1;
		} elseif ($cmode != 'all') {
			$catdown = 1;
		}
		
		$amount = intval($amount);
		$mycatid = intval($mycatid);

		if ($catdown && $mycatid) {
			$w[] = 'i.icat='.$mycatid;
		}
		$where = implode(" and ",$w);
		if (count($w) >=1) $where .= ' and ';
		
		$query = 'SELECT i.inumber as itemid, i.ititle as title, i.itime,';
		if ($catblogname) {
			$query .= ' concat('.$catformat.') as category,' ;
		} else {
			$query .= ' c.cname as category,' ;
		}
		$query .= ' i.icat as catid' ;
		$query .= ' FROM '.sql_table('item').' as i, '.sql_table('category').' as c';
		if ($catblogname) $query .= ', '.sql_table('blog').' as b';
		$query .= ' WHERE '.$where.' i.icat=c.catid and i.idraft=0';
		if ($catblogname) $query .= ' and b.bnumber = c.cblog';
		$query .= ' and i.itime<='. mysqldate($b->getCorrectTime());
		$query .= ' ORDER BY i.itime DESC LIMIT 0,'.$amount;
		
		if ($template) {
			$b->showUsingQuery($template, $query, 0, 1, 1); 
		} else {
			$res = sql_query($query);
			$linkparams = array();
			if ($catdown && $mycatid && !$nocatparam) $linkparams['catid'] = $mycatid;
			switch ($infotype) {
				case "date":
					$istr = "echo ' <span class=\"iteminfo\">['.substr(\$o->itime,0,10).']</span>';";
					break;
				case "category":
					$istr = "echo ' <span class=\"iteminfo\">['.\$o->category.']</span>';";
					break;
				default:
					$istr = "";
			}
			echo "<ul>\n";
			while ($o = mysql_fetch_object($res)) {
				echo ' <li>';
				if ($infotype == 'both') {
					echo '<span class="iteminfo">'.substr($o->itime,0,10) . ' ['.$o->category.']</span><br />';
				}
				echo '<a href="'.createItemLink($o->itemid,$linkparams).'">';
				echo htmlspecialchars($o->title).'</a>';
				eval($istr);
				echo "</li>\n";
			}
			echo "</ul>\n";
		}
	}

	function getCategoryIDFromItemID($itemid) {
		return quickQuery("SELECT icat as result FROM ".sql_table('item')." WHERE inumber=".intval($itemid));
	}

}
?>