<?php

//ini_set("display_errors",1);
//ini_set("error_reporting", E_ALL&~E_NOTICE);

require_once('setting.inc');
require_once('engine_clusters.inc');

init_qlog_env();

// 获取参数
$mul = isset($_REQUEST["mul"]) ? (int)$_REQUEST["mul"] : 0;
$ics = isset($_REQUEST["ics"]) && trim($_REQUEST["ics"])!='' ? $_REQUEST["ics"] : 'gb2312';
$ocs = isset($_REQUEST["ocs"]) && trim($_REQUEST["ocs"])!='' ? $_REQUEST["ocs"] : 'gb2312';
$ofmt = isset($_REQUEST["ofmt"]) && trim($_REQUEST["ofmt"])!='' ? $_REQUEST["ofmt"] : 'dump';
$doflt = isset($_REQUEST["doflt"]) ? (int)$_REQUEST["doflt"] : DEFAULT_DOFLT;
$product = isset($_REQUEST["product"]) ? $_REQUEST["product"] : "";
//$group = 'nbbs';

/* print $mul; exit; */

$searches = array();
if($mul === 1) {
  // 多个请求
  $num = isset($_REQUEST['num']) ? (int)$_REQUEST['num'] : 0;
  for($i=0; $i<$num; $i++) {
    $str = isset($_REQUEST["search$i"]) ? trim($_REQUEST["search$i"]) : '';
    if($str != '') {
      $arr1 = preg_split('/\|{3,4}/', $str);
      foreach($arr1 as $arr) {
        $pos = strpos($arr, '=');
        if($pos) {
          $key = substr($arr, 0, $pos);
          $value = substr($arr, $pos+1);
          if($value !== false)
            $searches[$i][$key] = trim($value);
        }
      }
    }
  }
}

$total_timespan = new TimeSpan();
$request_num = count($searches);
$cache_keys = array();

// 合并消重处理
if( ( $mul == 0 ) &&
    ( 'bbs_content' == trim($_REQUEST['plugin']) ) &&
    ( 'bbs' == trim($_REQUEST['src']) ) &&
    ( 'content' == trim($_REQUEST['stype']) )
){
	require_once('bbs_content.php');
	if(in_array( $_SERVER['SCRIPT_NAME'], array('/search.htm', '/index.htm')) ){
        include('search.htm');
    }
	exit();
}

for($i=0; $i<$request_num; $i++) {
  $search = &$searches[$i];

	if($doflt)
	{
		// 输入过滤
		if($search['kw'] && isset($filter_level[$search['src']]['kwfilter']) && $filter_level[$search['src']]['kwfilter']===true)
		{
	        $timespan = new TimeSpan();
	        $kw_filter_res  = filter_kw($search['kw'], $filter_level[$search['src']]['product']);
	        $kw_filter_consume = $timespan->getTimeSpan();

	        //	分析敏感结果，根据不同的推荐行为不同处理
	        if( $kw_filter_res )
	        {
		        $SE_FORBIDEN_LEVEL = '3';	// 搜索禁查
		        preg_match( '/v_process=(\d+)/', @$kw_filter_res, $match ); $v_process = $match[ 1 ];
		        $hit_filter_kw = strchr( $v_process, $SE_FORBIDEN_LEVEL ) ? 1 : 0;
	        }
	        else
	        {
	        	$hit_filter_kw = 0;
	        }
		}
		else
		{
	        $hit_filter_kw = 0;
		}
	}

  if($hit_filter_kw) {
    $searches[$i]['filter_kw'] = $hit_filter_kw;
    $searches[$i]['consume_filter_kw'] = $kw_filter_consume;
    $searches[$i]['param_str'] = '';
    $searches[$i]['cache_key'] = '';
    continue;
  }

  if(isset($search_engine_clusters[$search['src']][$search['stype']]['uri']))
    $uri = $search_engine_clusters[$search['src']][$search['stype']]['uri'];
  else
    $uri = DEFAULT_ENGINE_URI;

  if($search['src'] != 'service') {
    // 搜索查询
    if($search['src'] == 'blog')
      $param_str = "{$uri}?kw=".urlencode($search['kw']." tag:sblog");
    else
      $param_str = "{$uri}?kw=".urlencode($search['kw']);
    if(($search['src']=='image') && empty($search['column']))
      $search['column'] = 'compose0';
    $param_str .= "&ofmt=php";
    $param_str .= "&doflt=".$doflt;
    $param_str .= "&product=".$product;
    $param_str .= "&group=".$group;
    if($ics !== 'gb2312')
      $param_str .= "&ics=".$ics;
    if($ocs !== 'gb2312')
      $param_str .= "&ocs=".$ocs;
    if(isset($search['column']) && $search['column']!=='')
      $param_str .= "&column=".$search['column'];
    if(isset($search['param']) && $search['param']!=='') {
      $str = &$search['param'];
      if(substr($str, strlen($str)-1) == '|')
        $param_str .= "&param=".urlencode($search['param']);
      else
        $param_str .= "&param=".urlencode($search['param'])."|";
    }
    if(isset($search['start']) && $search['start']!=='')
      $param_str .= "&start=".$search['start'];
    if(isset($search['count']) && $search['count']!=='')
    {
    	// 如果是标题搜索，在统一接口做内容消重，需要预取 $MULTIPLE 倍的结果再消重（丑陋的短线补丁，长线应该放在引擎处理）
    	if( ( 'bbs' == $search['src'] ) && ( 'title' == $search['stype'] ) && ( 0 < $dedup ) )
    	{
    		$MULTIPLE = 3;	//	预取的倍数
			$param_str .= "&count=" . ( $search['count'] * $MULTIPLE );
    	}
    	else
    	{
			$param_str .= "&count=".$search['count'];
    	}
    }
    if(isset($search['sort']) && $search['sort']!='')
      $param_str .= "&sort=".$search['sort'];
    if($search['stype']=='se_proxy') {
          $param_str = "{$uri}?url=".urlencode($_REQUEST['kw'])."&timeout=".urlencode($_REQUEST['timeout']/1000)."&gz=".urlencode($_REQUEST['gz']);
    }
  } // end 搜索查询
  else {
    // 服务查询
    if($search['stype'] == 'keyterm') {
      $param_str = "{$uri}?t=".urlencode($search['kw']);
      if(isset($search['body']))
        $param_str .= "&b=".urlencode($search['body']);
      else
        $param_str .= "&b=";
      if(isset($search['type']))
        $param_str .= "&f=".$search['type'];
    }
    else if($search['stype'] == 'fword') {
      $filter = '';
      if(empty($search['type']))
        $filter .= 'v_type=0';
      else
        $filter .= 'v_type='.$search['type'];
      if(!empty($search['level']))
        $filter .= ';v_levels=>('.$search['level'].')';
      if(isset($search['fpr']) && $search['fpr']!='')
        $filter .= ';v_levels=>('.$search['fpr'].')';

      $param_str = "{$uri}?name=".urlencode($search['kw'])."&filter={$filter}";
    }
    else if($search['stype']=='snap') {
      $param_str = "{$uri}?ofmt=xml&".$search['query_string'];
    }
    else if($search['stype']=='qclass') {
      $param_str = "{$uri}?ofmt=xml&".$search['query_string'];
      // 分类Q的缓存key需要去掉用户ip
	  $cache_str = preg_replace( "/&client=[\d\.]*/", "", $param_str );
    }
    else if($search['stype']=='xiaoq') {
      $param_str = "{$uri}?".$search['query_string'];
    }
    else if($search['stype']=='sedoc') {
      $param_str = "{$uri}?".$search['query_string'];
    }
    else if($search['stype']=='newsdoc') {
      $param_str = "{$uri}?".$search['query_string'];
    }
    else if($search['stype']==‘rxiaoq’) {
      $param_str = "{$uri}?".$search['query_string'];
    }
    else if($search['stype']==‘rsedoc') {
      $param_str = "{$uri}?".$search['query_string'];
    }
    else if($search['stype']==‘rnewsdoc') {
      $param_str = "{$uri}?".$search['query_string'];
    }
    else if($search['stype']==‘txiaoq') {
      $param_str = "{$uri}?".$search['query_string'];
    }
    else if($search['stype']==‘tsedoc') {
      $param_str = "{$uri}?".$search['query_string'];
    }
    else if($search['stype']==‘tnewsdoc') {
      $param_str = "{$uri}?".$search['query_string'];
    }
    else if($search['stype']==‘class') {
      $param_str = "{$uri}?".$search['query_string'];
    }
  } // end 服务查询

  $searches[$i]['param_str'] = $param_str;
  // 若存在$cache_str变量则使用此变量生成缓存key，否则使用$param_str，如分类Q
  $searches[$i]['cache_key'] = md5(($cache_str ? $cache_str : $param_str)."&src=".$search['src']."&stype=".$search['stype']);

  if(isset($search_engine_clusters[$search['src']][$search['stype']]['cache']) &&
    $search_engine_clusters[$search['src']][$search['stype']]['cache'] == true &&
    $cache ) {
    // cache的key
    $cache_keys[] = $searches[$i]['cache_key'];
  }
}
